<?php
// get_all_drivers.php - Robust Latest Location Query
require_once 'db_connection.php';
header('Content-Type: application/json');

$conn = connect_db();

// This query joins the latest log entry for every driver with their user profile
$query = "SELECT
            d.driver_id,
            CAST(d.latitude AS FLOAT) as latitude,
            CAST(d.longitude AS FLOAT) as longitude,
            d.battery_percentage,
            d.timestamp,
            u.full_name,
            TIMESTAMPDIFF(MINUTE, d.timestamp, NOW()) as idle_minutes
          FROM (
              SELECT driver_id, MAX(id) as max_id
              FROM driver_location_logs
              GROUP BY driver_id
          ) as latest
          JOIN driver_location_logs d ON latest.max_id = d.id
          JOIN drivers dr ON d.driver_id = dr.id
          JOIN users u ON dr.user_id = u.id
          WHERE u.status = 'Active'";

$result = $conn->query($query);

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Query Failed: ' . $conn->error]);
    exit;
}

$drivers = [];
while ($row = $result->fetch_assoc()) {
    // Ensure numeric values for Leaflet
    $row['latitude'] = (float)$row['latitude'];
    $row['longitude'] = (float)$row['longitude'];
    $drivers[] = $row;
}

echo json_encode($drivers);
?>
