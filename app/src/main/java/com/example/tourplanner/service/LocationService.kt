package com.example.tourplanner.service

import android.app.*
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.os.BatteryManager
import android.os.Build
import android.os.IBinder
import android.os.Looper
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import com.example.tourplanner.R
import com.example.tourplanner.data.repository.TourRepository
import com.google.android.gms.location.*
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.*
import java.util.concurrent.TimeUnit
import javax.inject.Inject

@AndroidEntryPoint
class LocationService : Service() {

    @Inject
    lateinit var repository: TourRepository

    private lateinit var fusedLocationClient: FusedLocationProviderClient
    private lateinit var locationCallback: LocationCallback
    private val serviceScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    companion object {
        private const val CHANNEL_ID = "location_channel"
        private const val NOTIFICATION_ID = 123
        const val ACTION_START = "ACTION_START"
        const val ACTION_STOP = "ACTION_STOP"
    }

    override fun onCreate() {
        super.onCreate()
        fusedLocationClient = LocationServices.getFusedLocationProviderClient(this)
        createNotificationChannel()
        
        locationCallback = object : LocationCallback() {
            override fun onLocationResult(locationResult: LocationResult) {
                locationResult.lastLocation?.let { location ->
                    sendLocationToServer(location.latitude, location.longitude)
                }
            }
        }
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == ACTION_STOP) {
            stopForeground(STOP_FOREGROUND_REMOVE)
            stopSelf()
            return START_NOT_STICKY
        }

        if (hasLocationPermissions()) {
            startMyForegroundService()
        } else {
            Log.e("LocationService", "Missing permissions to start service")
            stopSelf()
        }
        
        return START_STICKY
    }

    private fun hasLocationPermissions(): Boolean {
        val fine = ContextCompat.checkSelfPermission(this, android.Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
        val coarse = ContextCompat.checkSelfPermission(this, android.Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED
        return fine || coarse
    }

    private fun startMyForegroundService() {
        val title = getString(R.string.tracking_service_title)
        val message = getString(R.string.tracking_service_desc)

        val notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle(title)
            .setContentText(message)
            .setSmallIcon(android.R.drawable.ic_menu_mylocation)
            .setOngoing(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .build()

        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                startForeground(NOTIFICATION_ID, notification, ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION)
            } else {
                startForeground(NOTIFICATION_ID, notification)
            }
            requestLocationUpdates()
        } catch (e: Exception) {
            Log.e("LocationService", "Failed to start foreground service", e)
            // If startForeground fails on Android 12+, we must still ensure the service is handled
            // to avoid "Context.startForegroundService() did not then call Service.startForeground()"
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                 try {
                     // Try one more time without type as fallback or just stop
                     stopSelf()
                 } catch (inner: Exception) {}
            }
        }
    }

    private fun requestLocationUpdates() {
        val request = LocationRequest.Builder(Priority.PRIORITY_HIGH_ACCURACY, TimeUnit.MINUTES.toMillis(1))
            .setMinUpdateIntervalMillis(TimeUnit.SECONDS.toMillis(30))
            .build()

        try {
            if (ContextCompat.checkSelfPermission(this, android.Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED) {
                fusedLocationClient.requestLocationUpdates(request, locationCallback, Looper.getMainLooper())
            }
        } catch (unlikely: SecurityException) {
            Log.e("LocationService", "Lost location permission during update request")
        }
    }

    private fun sendLocationToServer(lat: Double, lng: Double) {
        val batteryPct = getBatteryPercentage()
        serviceScope.launch {
            val prefs = getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
            val userId = prefs.getInt("user_id", -1)
            if (userId != -1) {
                try {
                    repository.updateLocation(userId, lat, lng, batteryPct, "Online")
                } catch (e: Exception) {
                    Log.e("LocationService", "Failed to send location", e)
                }
            }
        }
    }

    private fun getBatteryPercentage(): Int {
        val bm = getSystemService(Context.BATTERY_SERVICE) as BatteryManager
        return bm.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY)
    }

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val serviceChannel = NotificationChannel(
                CHANNEL_ID, 
                getString(R.string.tracking_service_title),
                NotificationManager.IMPORTANCE_LOW
            )
            val manager = getSystemService(NotificationManager::class.java)
            manager?.createNotificationChannel(serviceChannel)
        }
    }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onDestroy() {
        super.onDestroy()
        fusedLocationClient.removeLocationUpdates(locationCallback)
        serviceScope.cancel()
    }
}
