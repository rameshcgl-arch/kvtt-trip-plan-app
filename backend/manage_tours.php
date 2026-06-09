<?php
// manage_tours.php - Production Version with Enhanced Error Logging
require_once 'db_connection.php';
header('Content-Type: application/json');

// Enable error reporting for debugging (Remove in final production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

$conn = connect_db();
$method = $_SERVER['REQUEST_METHOD'];

// Get user info from request
$user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
$role_id = $_GET['role_id'] ?? $_POST['role_id'] ?? null;

if ($method === 'GET') {
    // 1. Fetch Users for Dropdowns (Admin/Manager only)
    if (isset($_GET['role_type'])) {
        $role_id_filter = $_GET['role_type'];

        if ($role_id_filter == 5) {
            // DRIVERS: Must get drivers.id
            $query = "SELECT dr.id, u.full_name FROM drivers dr JOIN users u ON dr.user_id = u.id WHERE u.status = 'Active'";
        } else {
            // OTHERS: Get users.id
            $query = "SELECT id, full_name FROM users WHERE role_id = ? AND status = 'Active'";
        }

        $stmt = $conn->prepare($query);
        if ($role_id_filter != 5) { $stmt->bind_param("i", $role_id_filter); }
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    // 2. Fetch Single Tour Details
    if (isset($_GET['tour_id'])) {
        $tour_id = $_GET['tour_id'];
        $query = "SELECT t.*, u.full_name as driver_name FROM tours t JOIN drivers d ON t.driver_id = d.id JOIN users u ON d.user_id = u.id WHERE t.id = ?";
        $stmt = $conn->prepare($query); $stmt->bind_param("i", $tour_id); $stmt->execute();
        $tour = $stmt->get_result()->fetch_assoc();
        $query_sights = "SELECT * FROM tour_sightseeing WHERE tour_id = ? ORDER BY sequence_order";
        $stmt_s = $conn->prepare($query_sights); $stmt_s->bind_param("i", $tour_id); $stmt_s->execute();
        $sights = $stmt_s->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['status' => 'success', 'tour' => $tour, 'sightseeing' => $sights]);
        exit;
    }

    // 3. List Assigned Tours
    $query = "SELECT t.*, u.full_name as driver_name FROM tours t
              LEFT JOIN drivers d ON t.driver_id = d.id
              LEFT JOIN users u ON d.user_id = u.id";

    if ($role_id == 5) { $query .= " WHERE t.driver_id = (SELECT id FROM drivers WHERE user_id = ?)"; }
    elseif ($role_id == 4) { $query .= " WHERE t.guide_id = ?"; }
    elseif ($role_id == 2) { $query .= " WHERE t.coordinator_id = ?"; }
    elseif ($role_id == 6) { $query .= " WHERE t.guest_id = ?"; }

    $query .= " ORDER BY t.tour_date DESC";
    $stmt = $conn->prepare($query);
    if ($role_id && !in_array($role_id, [1, 3])) { $stmt->bind_param("i", $user_id); }
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));

} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $tour_id = $_POST['tour_id'];
        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM tour_sightseeing WHERE tour_id = $tour_id");
            $conn->query("DELETE FROM driver_location_logs WHERE tour_id = $tour_id");
            $conn->query("DELETE FROM tours WHERE id = $tour_id");
            $conn->commit();
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) { $conn->rollback(); echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
        exit;
    } elseif ($action === 'update_status') {
        $stmt = $conn->prepare("UPDATE tours SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $_POST['status'], $_POST['tour_id']);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
    } else {
        // CREATE NEW TOUR
        $tour_name = $_POST['tour_name'] ?? '';
        $tour_date = $_POST['tour_date'] ?? '';
        $driver_id = $_POST['driver_id'] ?? null;
        $guide_id = !empty($_POST['guide_id']) ? $_POST['guide_id'] : null;
        $coord_id = !empty($_POST['coordinator_id']) ? $_POST['coordinator_id'] : null;
        $guest_id = !empty($_POST['guest_id']) ? $_POST['guest_id'] : null;
        $sights_json = $_POST['sightseeing'] ?? '[]';
        $sights = json_decode($sights_json, true);

        if (!$tour_name || !$tour_date || !$driver_id) {
            echo json_encode(['status' => 'error', 'message' => 'Tour Name, Date and Driver are mandatory. Data: ' . print_r($_POST, true)]);
            exit;
        }

        $conn->begin_transaction();
        try {
            // First, ensure the share_token column is handled or removed if not in schema
            $check_col = $conn->query("SHOW COLUMNS FROM tours LIKE 'share_token'");
            if ($check_col->num_rows > 0) {
                $share_token = bin2hex(random_bytes(16));
                $stmt = $conn->prepare("INSERT INTO tours (tour_name, tour_date, driver_id, guide_id, coordinator_id, guest_id, status, share_token) VALUES (?, ?, ?, ?, ?, ?, 'Scheduled', ?)");
                $stmt->bind_param("ssiiiis", $tour_name, $tour_date, $driver_id, $guide_id, $coord_id, $guest_id, $share_token);
            } else {
                $stmt = $conn->prepare("INSERT INTO tours (tour_name, tour_date, driver_id, guide_id, coordinator_id, guest_id, status) VALUES (?, ?, ?, ?, ?, ?, 'Scheduled')");
                $stmt->bind_param("ssiiii", $tour_name, $tour_date, $driver_id, $guide_id, $coord_id, $guest_id);
            }

            if (!$stmt->execute()) {
                throw new Exception("Tour table insert failed: " . $stmt->error);
            }

            $tour_id = $conn->insert_id;

            if (is_array($sights) && !empty($sights)) {
                $stmt_s = $conn->prepare("INSERT INTO tour_sightseeing (tour_id, sight_name, latitude, longitude, expected_time, sequence_order) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($sights as $i => $p) {
                    $seq = $i + 1;
                    $dt = $tour_date . ' ' . (isset($p['time']) ? $p['time'] : '00:00:00');
                    $stmt_s->bind_param("isddsi", $tour_id, $p['name'], $p['lat'], $p['lng'], $dt, $seq);
                    if (!$stmt_s->execute()) {
                         throw new Exception("Sightseeing insert failed: " . $stmt_s->error);
                    }
                }
            }

            $conn->commit();
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
?>
