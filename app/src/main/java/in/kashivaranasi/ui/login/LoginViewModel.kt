package `in`.kashivaranasi.ui.login

import android.content.Context
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.core.content.edit
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.annotation.Keep
import android.util.Log
import `in`.kashivaranasi.data.repository.TourRepository
import com.google.firebase.messaging.FirebaseMessaging
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await
import javax.inject.Inject

@HiltViewModel
class LoginViewModel @Inject constructor(
    private val repository: TourRepository,
    @ApplicationContext private val context: Context
) : ViewModel() {

    var username by mutableStateOf("")
    var password by mutableStateOf("")
    var isLoading by mutableStateOf(false)
    var errorMessage by mutableStateOf<String?>(null)

    private val _loginSuccess = MutableSharedFlow<Unit>()
    val loginSuccess = _loginSuccess.asSharedFlow()

    private val prefs = context.getSharedPreferences("app_prefs", Context.MODE_PRIVATE)

    fun onLoginClick() {
        if (username.isBlank() || password.isBlank()) {
            errorMessage = "Please enter both username and password"
            return
        }

        viewModelScope.launch {
            isLoading = true
            errorMessage = null
            try {
                val response = repository.login(username, password)
                if (response.isSuccessful && response.body()?.status == "success") {
                    val user = response.body()?.user
                    if (user != null) {
                        // 1. Save user data to SharedPreferences
                        prefs.edit {
                            putBoolean("is_logged_in", true)
                            putInt("user_id", user.id)
                            putInt("role_id", user.role_id)
                            putString("full_name", user.full_name)
                        }

                        // 2. Fetch and sync FCM token immediately
                        syncFcmToken(user.id)

                        _loginSuccess.emit(Unit)
                    }
                } else {
                    errorMessage = response.body()?.message ?: "Login failed"
                }
            } catch (e: Exception) {
                Log.e("LoginError", "Detailed error", e)
                errorMessage = "Error: ${e::class.simpleName}: ${e.message}\nCause: ${e.cause?.message ?: "Unknown"}"
            } finally {
                isLoading = false
            }
        }
    }

    private suspend fun syncFcmToken(userId: Int) {
        try {
            val token = FirebaseMessaging.getInstance().token.await()
            repository.updateFcmToken(userId, token)
            prefs.edit { putString("fcm_token", token) }
        } catch (e: Throwable) {
            e.printStackTrace()
            // Ignore token failure, can retry later
        }
    }
}
