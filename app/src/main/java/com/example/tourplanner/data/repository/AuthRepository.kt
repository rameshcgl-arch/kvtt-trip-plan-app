package com.example.tourplanner.data.repository

import com.example.tourplanner.data.models.LoginResponse
import com.example.tourplanner.data.remote.RetrofitClient
import retrofit2.Response

class AuthRepository {

    private val apiService = RetrofitClient.apiService

    suspend fun login(username: String, password: String): Response<LoginResponse> {
        return apiService.login(username, password)
    }
}
