package `in`.kashivaranasi.data.local

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "location_cache")
data class LocationEntity(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    val driverId: Int,
    val latitude: Double,
    val longitude: Double,
    val batteryPercentage: Int,
    val networkStatus: String,
    val timestamp: Long = System.currentTimeMillis()
)
