package com.example.tourplanner.ui.tour

import android.content.Context
import android.graphics.PorterDuff
import android.graphics.PorterDuffColorFilter
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import com.example.tourplanner.data.models.SightseeingPoint
import org.osmdroid.config.Configuration
import org.osmdroid.tileprovider.tilesource.TileSourceFactory
import org.osmdroid.util.GeoPoint
import org.osmdroid.views.MapView
import org.osmdroid.views.overlay.Marker

@Composable
fun MapViewContainer(
    modifier: Modifier = Modifier,
    points: List<SightseeingPoint>,
    currentLat: Double?,
    currentLng: Double?
) {
    AndroidView(
        modifier = modifier,
        factory = { context ->
            Configuration.getInstance().load(context, context.getSharedPreferences("osm", Context.MODE_PRIVATE))
            
            MapView(context).apply {
                setTileSource(TileSourceFactory.MAPNIK)
                setMultiTouchControls(true)
                controller.setZoom(15.0)
                
                points.forEach { point ->
                    val marker = Marker(this)
                    marker.position = GeoPoint(point.latitude, point.longitude)
                    marker.title = point.sight_name
                    marker.snippet = "Status: ${point.visit_status}"
                    
                    // Set marker color based on status
                    val icon = ContextCompat.getDrawable(context, org.osmdroid.library.R.drawable.marker_default)?.mutate()
                    if (icon != null) {
                        val color = if (point.visit_status == "Visited") {
                            android.graphics.Color.GREEN
                        } else {
                            android.graphics.Color.RED
                        }
                        icon.colorFilter = PorterDuffColorFilter(color, PorterDuff.Mode.SRC_IN)
                        marker.icon = icon
                    }
                    
                    this.overlays.add(marker)
                }

                if (currentLat != null && currentLng != null) {
                    controller.setCenter(GeoPoint(currentLat, currentLng))
                } else if (points.isNotEmpty()) {
                    controller.setCenter(GeoPoint(points[0].latitude, points[0].longitude))
                }
            }
        },
        update = { view ->
            if (currentLat != null && currentLng != null) {
                view.controller.animateTo(GeoPoint(currentLat, currentLng))
            }
        }
    )
}
