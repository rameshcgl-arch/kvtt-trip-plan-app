<?php
// send_alert.php - Send Firebase Notification with Acknowledgement Tracking
require_once 'db_connection.php';
header('Content-Type: application/json');

$conn = connect_db();

$user_id = $_POST['user_id'] ?? null;
$type = $_POST['type'] ?? 'wakeup';

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
    exit;
}

// 1. Fetch the FCM Token for this user
$stmt = $conn->prepare("SELECT fcm_token, full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result || empty($result['fcm_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'Driver is offline or App not registered with Firebase']);
    exit;
}

$token = $result['fcm_token'];
$driver_name = $result['full_name'];

// 2. Prepare Firebase Request
// IMPORTANT: Replace this with your actual Server Key from Firebase Console
$server_key = 'YOUR_FIREBASE_SERVER_KEY';
$url = 'https://fcm.googleapis.com/fcm/send';

// Unique Alert ID using timestamp
$alert_id = time();

$title = 'Wake-up Alert!';
$body = "Hi $driver_name, Admin is requesting a location update.";

$notification = [
    'title' => $title,
    'body' => $body,
    'sound' => 'default',
    'android_channel_id' => 'wakeup_urgent_v3', // MATCHING Android Channel ID v3
    'priority' => 'high'
];

$data = [
    'type' => 'wakeup',
    'user_id' => $user_id,
    'alert_id' => $alert_id,
    'title' => $title,
    'body' => $body
];

$post_data = [
    'to' => $token,
    'notification' => $notification,
    'data' => $data,
    'priority' => 'high'
];

// 3. Update Database before sending
$now = date('Y-m-d H:i:s');
$conn->query("UPDATE users SET last_alert_sent = '$now', last_alert_status = 'Sent' WHERE id = $user_id");

// 4. Send using CURL
$headers = [
    'Authorization: key=' . $server_key,
    'Content-Type: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo json_encode(['status' => 'success', 'message' => "Alert sent to $driver_name. Waiting for receipt..."]);
} else {
    echo json_encode(['status' => 'error', 'message' => "Firebase Error: " . $response]);
}
?>
