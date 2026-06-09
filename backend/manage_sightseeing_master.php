<?php
// manage_sightseeing_master.php - Handle Master Sightseeing Data & Images
require_once 'db_connection.php';
header('Content-Type: application/json');

$conn = connect_db();
$method = $_SERVER['REQUEST_METHOD'];

// Ensure upload directory exists
$upload_dir = 'uploads/sights/';
if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

if ($method === 'GET') {
    $city = $_GET['city'] ?? null;
    $query = "SELECT * FROM sightseeing_master";
    if ($city) {
        $query .= " WHERE city_name = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $city);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query . " ORDER BY city_name, sight_name");
    }
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));

} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $id = $_POST['id'];
        // Remove image file if exists
        $res = $conn->query("SELECT image_path FROM sightseeing_master WHERE id = $id");
        if($img = $res->fetch_assoc()) { if(file_exists($img['image_path'])) unlink($img['image_path']); }

        $conn->query("DELETE FROM sightseeing_master WHERE id = $id");
        echo json_encode(['status' => 'success']);
        exit;
    }

    $city = $_POST['city_name'];
    $sight = $_POST['sight_name'];
    $lat = $_POST['latitude'];
    $lng = $_POST['longitude'];
    $image_path = $_POST['existing_image'] ?? '';

    // Handle Image Upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $ext;
        $target = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $image_path = $target;
        }
    }

    if ($action === 'create') {
        $stmt = $conn->prepare("INSERT INTO sightseeing_master (city_name, sight_name, latitude, longitude, image_path) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdds", $city, $sight, $lat, $lng, $image_path);
    } else {
        $id = $_POST['id'];
        $stmt = $conn->prepare("UPDATE sightseeing_master SET city_name=?, sight_name=?, latitude=?, longitude=?, image_path=? WHERE id=?");
        $stmt->bind_param("ssddsi", $city, $sight, $lat, $lng, $image_path, $id);
    }

    if ($stmt->execute()) echo json_encode(['status' => 'success']);
    else echo json_encode(['status' => 'error', 'message' => $conn->error]);
}
?>
