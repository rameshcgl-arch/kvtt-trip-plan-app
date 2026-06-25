package `in`.kashivaranasi.data.models

import androidx.annotation.Keep

@Keep
data class User(
    val id: Int,
    val full_name: String,
    val role_id: Int
)

@Keep
data class LoginResponse(
    val status: String,
    val message: String?,
    val user: User?
)
