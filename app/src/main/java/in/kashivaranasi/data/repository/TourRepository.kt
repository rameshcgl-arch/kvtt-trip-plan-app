package `in`.kashivaranasi.data.repository

import `in`.kashivaranasi.data.local.LocationDao
import `in`.kashivaranasi.data.local.LocationEntity
import `in`.kashivaranasi.data.remote.ApiService
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class TourRepository @Inject constructor(
    private val apiService: ApiService,
    private val locationDao: LocationDao
) {
    suspend fun login(username: String, password: String) = apiService.login(username, password)

    suspend fun getTourPlan(userId: Int, roleId: Int) = apiService.getTourPlan(userId, roleId)

    suspend fun updateFcmToken(userId: Int, token: String) = apiService.updateFcmToken(userId, token)

    suspend fun acknowledgeAlert(userId: Int, alertId: String) = apiService.acknowledgeAlert(userId, alertId)

    suspend fun getAllDriversLocation() = apiService.getAllDriversLocation()

    suspend fun updateLocation(
        driverId: Int,
        latitude: Double,
        longitude: Double,
        battery: Int,
        network: String
    ): Result<Unit> {
        return try {
            val response = apiService.updateLocation(driverId, latitude, longitude, battery, network)
            if (response.isSuccessful) {
                syncCachedLocations()
                Result.success(Unit)
            } else {
                cacheLocation(driverId, latitude, longitude, battery, network)
                Result.failure(Exception("Server error: ${response.code()}"))
            }
        } catch (e: Exception) {
            cacheLocation(driverId, latitude, longitude, battery, network)
            Result.failure(e)
        }
    }

    private suspend fun cacheLocation(driverId: Int, lat: Double, lon: Double, bat: Int, net: String) {
        locationDao.insertLocation(
            LocationEntity(
                driverId = driverId,
                latitude = lat,
                longitude = lon,
                batteryPercentage = bat,
                networkStatus = net
            )
        )
    }

    private suspend fun syncCachedLocations() {
        val cached = locationDao.getAllCachedLocations()
        if (cached.isEmpty()) return

        for (loc in cached) {
            try {
                val response = apiService.updateLocation(
                    loc.driverId, loc.latitude, loc.longitude, loc.batteryPercentage, loc.networkStatus
                )
                if (response.isSuccessful) {
                    locationDao.deleteLocation(loc.id)
                }
            } catch (e: Exception) {
                break
            }
        }
    }
}
