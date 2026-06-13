package `in`.kashivaranasi.data.remote

import `in`.kashivaranasi.data.models.LoginResponse
import `in`.kashivaranasi.data.models.TourPlanResponse
import retrofit2.Response
import retrofit2.http.Field
import retrofit2.http.FormUrlEncoded
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Query

interface ApiService {

    @FormUrlEncoded
    @POST("auth.php")
    suspend fun login(
        @Field("username") username: String,
        @Field("password") password: String
    ): Response<LoginResponse>

    @FormUrlEncoded
    @POST("save_driver_location.php")
    suspend fun updateLocation(
        @Field("driver_id") driverId: Int,
        @Field("latitude") latitude: Double,
        @Field("longitude") longitude: Double,
        @Field("battery_percentage") battery: Int,
        @Field("network_status") network: String
    ): Response<Unit>

    @GET("get_tour_plan.php")
    suspend fun getTourPlan(
        @Query("user_id") userId: Int,
        @Query("role_id") roleId: Int
    ): Response<TourPlanResponse>

    @FormUrlEncoded
    @POST("update_fcm_token.php")
    suspend fun updateFcmToken(
        @Field("user_id") userId: Int,
        @Field("fcm_token") token: String
    ): Response<Unit>

    @FormUrlEncoded
    @POST("acknowledge_alert.php")
    suspend fun acknowledgeAlert(
        @Field("user_id") userId: Int,
        @Field("alert_id") alertId: String
    ): Response<Unit>

    @GET("get_all_drivers_location.php")
    suspend fun getAllDriversLocation(): Response<List<DriverLocation>>
}

data class DriverLocation(
    val user_id: Int,
    val full_name: String,
    val latitude: Double,
    val longitude: Double,
    val battery_percentage: Int,
    val network_status: String,
    val last_updated: String
)
