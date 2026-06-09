<?php
// get_map_data.php
require_once 'db_connection.php';
header('Content-Type: application/json');

$conn = connect_db();
$type = $_GET['type'] ?? '';

if ($type === 'sightseeing') {
    // Get all sightseeing points for active tours
    $query = "SELECT s.*, t.tour_name, u.full_name as driver_name
              FROM tour_sightseeing s
              JOIN tours t ON s.tour_id = t.id
              JOIN users u ON t.driver_id = u.id
              WHERE t.status = 'In-Progress'";

    $result = $conn->query($query);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
}
?>
