package com.example.tourplanner.data.models

data class User(
    val id: Int,
    val full_name: String,
    val role_id: Int
)

data class LoginResponse(
    val status: String,
    val message: String?,
    val user: User?
)
