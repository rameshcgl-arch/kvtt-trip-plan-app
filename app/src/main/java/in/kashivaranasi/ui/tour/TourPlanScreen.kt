package `in`.kashivaranasi.ui.tour

import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.List
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.material3.TabRowDefaults.tabIndicatorOffset
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import `in`.kashivaranasi.MainActivity
import `in`.kashivaranasi.R
import `in`.kashivaranasi.ui.tour.TourViewModel
import `in`.kashivaranasi.ui.tour.MapViewContainer
import `in`.kashivaranasi.data.models.SightseeingPoint
import `in`.kashivaranasi.data.models.TeamMember

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TourPlanScreen(
    viewModel: TourViewModel
) {
    val context = LocalContext.current
    val tour = viewModel.tour
    val points = viewModel.sightseeingPoints
    val team = viewModel.teamMembers
    val isLoading = viewModel.isLoading
    val errorMessage = viewModel.errorMessage
    
    var selectedTabIndex by remember { mutableIntStateOf(0) }
    var isMapView by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) {
        viewModel.loadTourPlan()
    }

    Scaffold(
        topBar = {
            Column {
                TopAppBar(
                    title = { 
                        Text(
                            text = tour?.tour_name ?: stringResource(R.string.tour_details_title),
                            fontWeight = FontWeight.ExtraBold,
                            fontSize = 22.sp,
                            color = Color.Black
                        ) 
                    },
                    actions = {
                        IconButton(onClick = { isMapView = !isMapView }) {
                            Icon(
                                imageVector = if (isMapView) Icons.AutoMirrored.Filled.List else Icons.Default.Map, 
                                contentDescription = null, 
                                tint = Color.Black
                            )
                        }
                        IconButton(onClick = { logout(context) }) {
                            Icon(
                                imageVector = Icons.AutoMirrored.Filled.Logout, 
                                contentDescription = null, 
                                tint = Color.Red
                            )
                        }
                    },
                    colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White)
                )

                // Uber-Style Status Bar
                val canTrack = viewModel.userRoleId == 5 || viewModel.userRoleId == 3 || viewModel.userRoleId == 4
                if (canTrack && !isLoading) {
                    Surface(
                        modifier = Modifier.fillMaxWidth(),
                        color = Color(0xFF276EF1) // Uber Blue
                    ) {
                        Row(
                            modifier = Modifier.padding(vertical = 10.dp),
                            horizontalArrangement = Arrangement.Center,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(Icons.Default.WifiTethering, null, tint = Color.White, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(8.dp))
                            Text(
                                text = stringResource(R.string.tracking_active_status),
                                color = Color.White,
                                fontWeight = FontWeight.Bold,
                                fontSize = 14.sp
                            )
                        }
                    }
                }

                TabRow(
                    selectedTabIndex = selectedTabIndex,
                    containerColor = Color.White,
                    contentColor = Color.Black,
                    indicator = { tabPositions ->
                        TabRowDefaults.SecondaryIndicator(
                            Modifier.tabIndicatorOffset(tabPositions[selectedTabIndex]),
                            color = Color.Black,
                            height = 4.dp
                        )
                    }
                ) {
                    Tab(
                        selected = selectedTabIndex == 0,
                        onClick = { selectedTabIndex = 0 },
                        text = { 
                            Text(
                                text = stringResource(R.string.tab_plan), 
                                color = Color.Black,
                                fontWeight = if (selectedTabIndex == 0) FontWeight.Bold else FontWeight.Normal
                            ) 
                        }
                    )
                    Tab(
                        selected = selectedTabIndex == 1,
                        onClick = { selectedTabIndex = 1 },
                        text = { 
                            Text(
                                text = stringResource(R.string.tab_contacts), 
                                color = Color.Black,
                                fontWeight = if (selectedTabIndex == 1) FontWeight.Bold else FontWeight.Normal
                            ) 
                        }
                    )
                }
            }
        },
        containerColor = Color(0xFFF1F1F1) // Modern light background
    ) { padding ->
        Box(modifier = Modifier.padding(padding).fillMaxSize()) {
            if (isLoading) {
                CircularProgressIndicator(modifier = Modifier.align(Alignment.Center), color = Color.Black)
            } else if (errorMessage != null) {
                Column(modifier = Modifier.align(Alignment.Center), horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(stringResource(R.string.no_active_tour), color = Color.Gray)
                    Spacer(Modifier.height(16.dp))
                    Button(
                        onClick = { viewModel.loadTourPlan() },
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Color.Black,
                            contentColor = Color.White
                        ),
                        shape = RoundedCornerShape(12.dp)
                    ) { 
                        Text(
                            text = stringResource(R.string.retry_button),
                            color = Color.White
                        ) 
                    }
                }
            } else {
                if (selectedTabIndex == 0) {
                    if (isMapView) {
                        MapViewContainer(modifier = Modifier.fillMaxSize(), points = points, currentLat = null, currentLng = null)
                    } else {
                        LazyColumn(
                            contentPadding = PaddingValues(16.dp),
                            verticalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            items(points) { point -> SightseeingItem(point) }
                        }
                    }
                } else {
                    LazyColumn(
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        if (team.isEmpty()) {
                            item { 
                                Text(
                                    text = stringResource(R.string.no_contacts), 
                                    modifier = Modifier.padding(16.dp), 
                                    color = Color.Gray
                                ) 
                            }
                        } else {
                            items(team) { member -> TeamMemberItem(member) }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun SightseeingItem(point: SightseeingPoint) {
    val context = LocalContext.current
    ElevatedCard(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.elevatedCardColors(containerColor = Color.White),
        elevation = CardDefaults.elevatedCardElevation(defaultElevation = 2.dp)
    ) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Icon Background
            Box(
                modifier = Modifier
                    .size(44.dp)
                    .background(Color(0xFFF6F6F6), CircleShape),
                contentAlignment = Alignment.Center
            ) {
                Icon(Icons.Default.Place, null, tint = Color.Black, modifier = Modifier.size(24.dp))
            }
            
            Spacer(Modifier.width(16.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = point.sight_name, 
                    fontWeight = FontWeight.Bold, 
                    fontSize = 16.sp, 
                    color = Color.Black
                )
                Text(
                    text = "Scheduled: ${point.expected_time}",
                    style = MaterialTheme.typography.bodySmall,
                    color = Color.Gray
                )
            }

            // Navigation Button
            IconButton(
                onClick = {
                    val gmmIntentUri = Uri.parse("google.navigation:q=${point.latitude},${point.longitude}")
                    val mapIntent = Intent(Intent.ACTION_VIEW, gmmIntentUri)
                    mapIntent.setPackage("com.google.android.apps.maps")
                    context.startActivity(mapIntent)
                }
            ) {
                Icon(Icons.Default.Navigation, "Navigate", tint = Color(0xFF276EF1))
            }

            if (point.visit_status == "Visited") {
                Icon(Icons.Default.CheckCircle, "Visited", tint = Color(0xFF2E7D32), modifier = Modifier.size(24.dp))
            }
        }
    }
}

@Composable
fun TeamMemberItem(member: TeamMember) {
    val context = LocalContext.current
    ElevatedCard(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.elevatedCardColors(containerColor = Color.White),
        elevation = CardDefaults.elevatedCardElevation(defaultElevation = 2.dp)
    ) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = member.full_name, 
                    fontWeight = FontWeight.Bold, 
                    style = MaterialTheme.typography.titleMedium,
                    color = Color.Black
                )
                Text(
                    text = "${member.role_label}", 
                    style = MaterialTheme.typography.bodySmall, 
                    color = Color.Gray
                )
                if (!member.car_number.isNullOrBlank()) {
                    Text(
                        text = "Car: ${member.car_number}", 
                        fontWeight = FontWeight.SemiBold, 
                        color = Color.Black
                    )
                }
            }
            
            Button(
                onClick = {
                    val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:${member.phone}"))
                    context.startActivity(intent)
                },
                colors = ButtonDefaults.buttonColors(
                    containerColor = Color.Black,
                    contentColor = Color.White
                ),
                shape = RoundedCornerShape(24.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Phone, 
                    contentDescription = null,
                    modifier = Modifier.size(16.dp),
                    tint = Color.White
                )
                Spacer(Modifier.width(8.dp))
                Text(
                    text = "Call",
                    color = Color.White
                )
            }
        }
    }
}

private fun logout(context: Context) {
    val prefs = context.getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
    prefs.edit().clear().apply()
    val intent = Intent(context, MainActivity::class.java).apply {
        addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK)
    }
    context.startActivity(intent)
}
