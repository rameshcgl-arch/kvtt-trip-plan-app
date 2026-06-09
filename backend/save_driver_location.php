<?php
// save_driver_location.php - Updated to link with tour_id
require_once 'db_connection.php';
header('Content-Type: application/json');

$conn = connect_db();

$driver_id = $_POST['driver_id'] ?? null;
$lat = $_POST['latitude'] ?? null;
$lng = $_POST['longitude'] ?? null;
$battery = $_POST['battery_percentage'] ?? null;

if (!$driver_id || !$lat || !$lng) {
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
    exit;
}

// 1. Find the currently active (In-Progress) tour for this driver
$stmt_tour = $conn->prepare("SELECT id FROM tours WHERE driver_id = ? AND status = 'In-Progress' LIMIT 1");
$stmt_tour->bind_param("i", $driver_id);
$stmt_tour->execute();
$tour_res = $stmt_tour->get_result()->fetch_assoc();
$tour_id = $tour_res ? $tour_res['id'] : null;

// 2. Log location with tour_id
$stmt = $conn->prepare("INSERT INTO driver_location_logs (driver_id, tour_id, latitude, longitude, battery_percentage) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiddi", $driver_id, $tour_id, $lat, $lng, $battery);
$stmt->execute();

// 3. Proximity Check (500 meters)
if ($tour_id) {
    $query = "SELECT id, latitude, longitude FROM tour_sightseeing WHERE tour_id = ? AND visit_status = 'Pending'";
    $stmt_s = $conn->prepare($query);
    $stmt_s->bind_param("i", $tour_id);
    $stmt_s->execute();
    $result = $stmt_s->get_result();

    while ($row = $result->fetch_assoc()) {
        $dist = getDistance($lat, $lng, $row['latitude'], $row['longitude']);
        if ($dist <= 500) {
            $up = $conn->prepare("UPDATE tour_sightseeing SET visit_status = 'Visited', actual_visit_time = NOW() WHERE id = ?");
            $up->bind_param("i", $row['id']);
            $up->execute();
        }
    }
}

function getDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

echo json_encode(['status' => 'success']);
?>
