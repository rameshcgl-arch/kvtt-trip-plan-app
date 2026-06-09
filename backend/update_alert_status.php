<?php
// update_alert_status.php - Called by Android App when notification is received
require_once 'db_connection.php';
header('Content-Type: application/json');

$conn = connect_db();

$user_id = $_POST['user_id'] ?? null;
$status = $_POST['status'] ?? 'Received'; // Default to Received

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
    exit;
}

// Update the alert status for this user
$stmt = $conn->prepare("UPDATE users SET last_alert_status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $user_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Status updated to ' . $status]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed']);
}
?>
