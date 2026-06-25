package `in`.kashivaranasi.data.models

import androidx.annotation.Keep

@Keep
data class TourPlanResponse(
    val status: String,
    val tour: Tour?,
    val sightseeing: List<SightseeingPoint>?,
    val team: List<TeamMember>?,
    val message: String?
)

@Keep
data class Tour(
    val tour_id: Int,
    val tour_name: String,
    val tour_date: String,
    val status: String
)

@Keep
data class SightseeingPoint(
    val id: Int,
    val sight_name: String,
    val latitude: Double,
    val longitude: Double,
    val expected_time: String,
    val sequence_order: Int,
    val visit_status: String,
    val actual_visit_time: String?
)

@Keep
data class TeamMember(
    val full_name: String,
    val phone: String,
    val role_label: String,
    val car_number: String?
)
