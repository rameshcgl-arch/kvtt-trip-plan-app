package com.example.tourplanner.ui.tour

import android.content.Context
import android.content.SharedPreferences
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.example.tourplanner.data.models.SightseeingPoint
import com.example.tourplanner.data.models.TeamMember
import com.example.tourplanner.data.models.Tour
import com.example.tourplanner.data.repository.TourRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class TourViewModel @Inject constructor(
    private val repository: TourRepository,
    @ApplicationContext private val context: Context
) : ViewModel() {

    private val prefs: SharedPreferences = context.getSharedPreferences("app_prefs", Context.MODE_PRIVATE)

    var tour by mutableStateOf<Tour?>(null)
    var sightseeingPoints by mutableStateOf<List<SightseeingPoint>>(emptyList())
    var teamMembers by mutableStateOf<List<TeamMember>>(emptyList())
    var isLoading by mutableStateOf(false)
    var errorMessage by mutableStateOf<String?>(null)
    var userRoleId by mutableStateOf(-1)

    fun loadTourPlan() {
        isLoading = true
        errorMessage = null

        val userId = prefs.getInt("user_id", -1)
        val roleId = prefs.getInt("role_id", -1)
        userRoleId = roleId

        if (userId == -1 || roleId == -1) {
            errorMessage = "User not logged in."
            isLoading = false
            return
        }

        viewModelScope.launch {
            try {
                val response = repository.getTourPlan(userId, roleId)
                if (response.isSuccessful && response.body()?.status == "success") {
                    tour = response.body()?.tour
                    sightseeingPoints = response.body()?.sightseeing ?: emptyList()
                    teamMembers = response.body()?.team ?: emptyList()
                } else {
                    errorMessage = response.body()?.message ?: "No active tour found."
                }
            } catch (e: Exception) {
                errorMessage = "Network error: ${e.message}"
            } finally {
                isLoading = false
            }
        }
    }
}
