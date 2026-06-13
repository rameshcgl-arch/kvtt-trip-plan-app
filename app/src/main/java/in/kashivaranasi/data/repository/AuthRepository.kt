package `in`.kashivaranasi.data.repository

import `in`.kashivaranasi.data.models.LoginResponse
import `in`.kashivaranasi.data.remote.RetrofitClient
import retrofit2.Response

class AuthRepository {

    private val apiService = RetrofitClient.apiService

    suspend fun login(username: String, password: String): Response<LoginResponse> {
        return apiService.login(username, password)
    }
}
