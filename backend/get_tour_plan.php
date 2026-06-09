<?php
// get_tour_plan.php - Real-time Team & Phone Data
require_once 'db_connection.php';
header('Content-Type: application/json');

$conn = connect_db();
$user_id = $_GET['user_id'] ?? null;
$role_id = $_GET['role_id'] ?? null;

if (!$user_id || !$role_id) {
    echo json_encode(['status' => 'error', 'message' => 'Identification missing']);
    exit;
}

// 1. Find the tour
$query = "SELECT t.* FROM tours t";
if ($role_id == 5) $query .= " WHERE t.driver_id = ?";
elseif ($role_id == 4) $query .= " WHERE t.guide_id = ?";
elseif ($role_id == 2) $query .= " WHERE t.coordinator_id = ?";
elseif ($role_id == 6) $query .= " WHERE t.guest_id = ?";
else $query .= " WHERE 1=1";

$query .= " AND t.status = 'In-Progress' LIMIT 1";
$stmt = $conn->prepare($query);
if ($role_id != 1 && $role_id != 3) $stmt->bind_param("i", $user_id);
$stmt->execute();
$tour = $stmt->get_result()->fetch_assoc();

if ($tour) {
    $tour_id = $tour['id'];
    $pts = $conn->query("SELECT * FROM tour_sightseeing WHERE tour_id = $tour_id ORDER BY sequence_order")->fetch_all(MYSQLI_ASSOC);

    // 2. Fetch REAL team member details
    $team = [];
    $assignments = [
        'Driver' => ['id' => $tour['driver_id'], 'table' => 'drivers'],
        'Guide' => ['id' => $tour['guide_id'], 'table' => 'users'],
        'Coordinator' => ['id' => $tour['coordinator_id'], 'table' => 'users'],
        'Guest' => ['id' => $tour['guest_id'], 'table' => 'users']
    ];

    foreach ($assignments as $label => $info) {
        if (!$info['id']) continue;
        if ($info['table'] === 'drivers') {
            $q = "SELECT u.full_name, u.phone, d.car_number FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.id = ?";
        } else {
            $q = "SELECT full_name, phone, '' as car_number FROM users WHERE id = ?";
        }
        $st = $conn->prepare($q); $st->bind_param("i", $info['id']); $st->execute();
        $member = $st->get_result()->fetch_assoc();
        if ($member) {
            $member['role_label'] = $label;
            $team[] = $member;
        }
    }

    echo json_encode(['status' => 'success', 'tour' => $tour, 'sightseeing' => $pts, 'team' => $team]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No active tour found']);
}
?>
