<?php
// update_fcm_token.php - Update user's Firebase token
require_once 'db_connection.php';
header('Content-Type: application/json');

$conn = connect_db();

$user_id = $_POST['user_id'] ?? null;
$token = $_POST['fcm_token'] ?? null;

if (!$user_id || !$token) {
    echo json_encode(['status' => 'error', 'message' => 'User ID and Token are required']);
    exit;
}

$stmt = $conn->prepare("UPDATE users SET fcm_token = ? WHERE id = ?");
$stmt->bind_param("si", $token, $user_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Token updated successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $conn->error]);
}
?>
