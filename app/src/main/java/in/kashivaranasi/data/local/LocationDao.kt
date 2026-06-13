package `in`.kashivaranasi.data.local

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.Query

@Dao
interface LocationDao {
    @Insert
    suspend fun insertLocation(location: LocationEntity)

    @Query("SELECT * FROM location_cache ORDER BY timestamp ASC")
    suspend fun getAllCachedLocations(): List<LocationEntity>

    @Query("DELETE FROM location_cache WHERE id = :id")
    suspend fun deleteLocation(id: Int)
}
