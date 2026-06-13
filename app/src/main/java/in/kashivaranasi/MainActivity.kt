package `in`.kashivaranasi

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.PowerManager
import android.provider.Settings
import android.util.Log
import android.widget.Toast
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.BatteryChargingFull
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.NotificationsActive
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.core.content.edit
import androidx.core.net.toUri
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import `in`.kashivaranasi.R
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.lifecycleScope
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import `in`.kashivaranasi.data.repository.TourRepository
import `in`.kashivaranasi.service.LocationService
import `in`.kashivaranasi.ui.login.LoginScreen
import `in`.kashivaranasi.ui.login.LoginViewModel
import `in`.kashivaranasi.ui.theme.TourPlannerTheme
import `in`.kashivaranasi.ui.tour.TourPlanScreen
import `in`.kashivaranasi.ui.tour.TourViewModel
import com.google.firebase.messaging.FirebaseMessaging
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.launch
import javax.inject.Inject

@AndroidEntryPoint
class MainActivity : AppCompatActivity() {

    @Inject
    lateinit var repository: TourRepository

    private var showNotificationRationale by mutableStateOf(false)
    private var showBatteryRationale by mutableStateOf(false)
    private var showLocationRationale by mutableStateOf(false)

    private val requestPermissionsLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { results ->
        if (results.values.any { it }) {
            checkBatteryOptimizationStep()
        }
    }

    private val backgroundLocationLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { isGranted ->
        if (isGranted) {
            startLocationServiceSafely()
        } else {
            Toast.makeText(this, getString(R.string.bg_tracking_disabled), Toast.LENGTH_LONG).show()
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        installSplashScreen()
        super.onCreate(savedInstanceState)
        
        fetchAndSyncFcmToken()

        val prefs = getSharedPreferences("app_prefs", MODE_PRIVATE)
        val isLoggedIn = prefs.getBoolean("is_logged_in", false)
        val startDestination = if (isLoggedIn) "tour" else "login"

        if (isLoggedIn) {
            checkInitialPermissionsFlow()
        }

        setContent {
            TourPlannerTheme {
                PermissionFlowManager()
                
                AppNavigation(
                    startDestination = startDestination,
                    onLoginSuccess = {
                        checkInitialPermissionsFlow()
                    }
                )
            }
        }
    }

    @Composable
    private fun PermissionFlowManager() {
        if (showNotificationRationale) {
            RationaleDialog(
                title = stringResource(R.string.perm_notif_title),
                text = stringResource(R.string.perm_notif_desc),
                icon = Icons.Default.NotificationsActive,
                onConfirm = {
                    showNotificationRationale = false
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                        requestPermissionsLauncher.launch(arrayOf(Manifest.permission.POST_NOTIFICATIONS))
                    } else {
                        checkBatteryOptimizationStep()
                    }
                }
            )
        }

        if (showBatteryRationale) {
            RationaleDialog(
                title = stringResource(R.string.perm_battery_title),
                text = stringResource(R.string.perm_battery_desc),
                icon = Icons.Default.BatteryChargingFull,
                onConfirm = {
                    showBatteryRationale = false
                    requestBatteryOptimizationIgnore()
                }
            )
        }

        if (showLocationRationale) {
            RationaleDialog(
                title = stringResource(R.string.perm_location_title),
                text = stringResource(R.string.perm_location_desc),
                icon = Icons.Default.LocationOn,
                onConfirm = {
                    showLocationRationale = false
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                        backgroundLocationLauncher.launch(Manifest.permission.ACCESS_BACKGROUND_LOCATION)
                    }
                }
            )
        }
    }

    @Composable
    private fun RationaleDialog(
        title: String, 
        text: String, 
        icon: androidx.compose.ui.graphics.vector.ImageVector, 
        onConfirm: () -> Unit
    ) {
        Dialog(
            onDismissRequest = { },
            properties = DialogProperties(usePlatformDefaultWidth = false)
        ) {
            Surface(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(24.dp),
                shape = RoundedCornerShape(28.dp),
                color = Color.White,
                tonalElevation = 8.dp
            ) {
                Column(
                    modifier = Modifier.padding(24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Box(
                        modifier = Modifier
                            .size(64.dp)
                            .background(Color(0xFFF6F6F6), CircleShape),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(icon, contentDescription = null, tint = Color.Black, modifier = Modifier.size(32.dp))
                    }
                    
                    Spacer(Modifier.height(24.dp))
                    
                    Text(
                        text = title,
                        style = MaterialTheme.typography.headlineSmall,
                        fontWeight = FontWeight.Bold,
                        textAlign = TextAlign.Center,
                        color = Color.Black
                    )
                    
                    Spacer(Modifier.height(12.dp))
                    
                    Text(
                        text = text,
                        style = MaterialTheme.typography.bodyLarge,
                        textAlign = TextAlign.Center,
                        color = Color.DarkGray,
                        lineHeight = 22.sp
                    )
                    
                    Spacer(Modifier.height(32.dp))
                    
                    Button(
                        onClick = onConfirm,
                        modifier = Modifier.fillMaxWidth().height(56.dp),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Color.Black)
                    ) {
                        Text(
                            text = stringResource(R.string.ok_button),
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }
        }
    }

    private fun checkInitialPermissionsFlow() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                showNotificationRationale = true
                return
            }
        }
        checkBatteryOptimizationStep()
    }

    private fun checkBatteryOptimizationStep() {
        val pm = getSystemService(POWER_SERVICE) as PowerManager
        if (!pm.isIgnoringBatteryOptimizations(packageName)) {
            showBatteryRationale = true
        } else {
            val prefs = getSharedPreferences("app_prefs", MODE_PRIVATE)
            if (prefs.getBoolean("is_logged_in", false)) {
                checkPermissionsAndStartService()
            }
        }
    }

    private fun requestBatteryOptimizationIgnore() {
        try {
            val intent = Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS).apply {
                data = "package:$packageName".toUri()
            }
            startActivity(intent)
        } catch (e: Exception) {
            Log.e("Battery", "Error", e)
        }
    }

    private fun fetchAndSyncFcmToken() {
        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (!task.isSuccessful) return@addOnCompleteListener
            val token = task.result
            val prefs = getSharedPreferences("app_prefs", MODE_PRIVATE)
            prefs.edit { putString("fcm_token", token) }
            
            val userId = prefs.getInt("user_id", -1)
            if (userId != -1) {
                lifecycleScope.launch {
                    try { repository.updateFcmToken(userId, token) } catch (e: Exception) {}
                }
            }
        }
    }

    private fun checkPermissionsAndStartService() {
        val hasFine = ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
        val hasCoarse = ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED

        if (!hasFine && !hasCoarse) {
            requestPermissionsLauncher.launch(arrayOf(
                Manifest.permission.ACCESS_FINE_LOCATION,
                Manifest.permission.ACCESS_COARSE_LOCATION
            ))
        } else {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q && 
                ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_BACKGROUND_LOCATION) != PackageManager.PERMISSION_GRANTED) {
                showLocationRationale = true
            } else {
                startLocationServiceSafely()
            }
        }
    }

    private fun startLocationServiceSafely() {
        // Ensure we are in a foreground-eligible state before starting the service
        // On Android 15, we must be careful with FGS start from background
        val intent = Intent(applicationContext, LocationService::class.java).apply {
            action = LocationService.ACTION_START
        }
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                ContextCompat.startForegroundService(this, intent)
            } else {
                startService(intent)
            }
        } catch (e: Exception) {
            Log.e("MainActivity", "Failed to start service", e)
        }
    }
}

@Composable
fun AppNavigation(startDestination: String, onLoginSuccess: () -> Unit) {
    val navController = rememberNavController()
    NavHost(navController = navController, startDestination = startDestination) {
        composable("login") {
            val viewModel: LoginViewModel = hiltViewModel()
            LoginScreen(viewModel = viewModel, onLoginSuccess = {
                onLoginSuccess()
                navController.navigate("tour") {
                    popUpTo("login") { inclusive = true }
                }
            })
        }
        composable("tour") {
            val viewModel: TourViewModel = hiltViewModel()
            TourPlanScreen(viewModel = viewModel)
        }
    }
}
