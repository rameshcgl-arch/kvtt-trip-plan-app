<?php
// auth.php - Final Role-Based Login
require_once 'db_connection.php';
header('Content-Type: application/json');

$conn = connect_db();
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (!$username || !$password) {
    echo json_encode(['status' => 'error', 'message' => 'Credentials required']);
    exit;
}

$stmt = $conn->prepare("SELECT id, password_hash, full_name, role_id FROM users WHERE username = ? AND status = 'Active'");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user && password_verify($password, $user['password_hash'])) {
    $response_id = $user['id'];

    // If Driver, we need the driver_id from the drivers table for consistency
    if ($user['role_id'] == 5) {
        $stmt_d = $conn->prepare("SELECT id FROM drivers WHERE user_id = ?");
        $stmt_d->bind_param("i", $user['id']);
        $stmt_d->execute();
        $drv = $stmt_d->get_result()->fetch_assoc();
        if ($drv) $response_id = $drv['id'];
    }

    echo json_encode([
        'status' => 'success',
        'user' => [
            'id' => $response_id,
            'full_name' => $user['full_name'],
            'role_id' => $user['role_id'],
            'username' => $username
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
}
?>
