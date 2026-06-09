<?php
// get_tour_details.php
require_once 'db_connection.php';
header('Content-Type: application/json');

$conn = connect_db();
$tour_id = $_GET['tour_id'] ?? null;

if (!$tour_id) {
    echo json_encode(['status' => 'error', 'message' => 'Tour ID is required']);
    exit;
}

// 1. Fetch Tour & Driver Info
$query_tour = "SELECT t.*, u.full_name as driver_name, dr.id as driver_id
               FROM tours t
               JOIN drivers dr ON t.driver_id = dr.id
               JOIN users u ON dr.user_id = u.id
               WHERE t.id = ?";
$stmt = $conn->prepare($query_tour);
$stmt->bind_param("i", $tour_id);
$stmt->execute();
$tour = $stmt->get_result()->fetch_assoc();

// 2. Fetch Sightseeing Points
$query_sights = "SELECT * FROM tour_sightseeing WHERE tour_id = ? ORDER BY sequence_order";
$stmt_s = $conn->prepare($query_sights);
$stmt_s->bind_param("i", $tour_id);
$stmt_s->execute();
$sights = $stmt_s->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Fetch Driver Movement Route (based on logs after tour date)
// We filter by driver_id and ensure the timestamp is on the tour date
$tour_date = $tour['tour_date'];
$driver_id = $tour['driver_id'];

$query_route = "SELECT latitude, longitude, timestamp
                FROM driver_location_logs
                WHERE driver_id = ? AND DATE(timestamp) = ?
                ORDER BY timestamp ASC";
$stmt_r = $conn->prepare($query_route);
$stmt_r->bind_param("is", $driver_id, $tour_date);
$stmt_r->execute();
$route = $stmt_r->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'status' => 'success',
    'tour' => $tour,
    'sightseeing' => $sights,
    'route' => $route
]);
?>
