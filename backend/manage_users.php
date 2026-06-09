<?php
// manage_users.php - Enhanced with Edit and Delete
require_once 'db_connection.php';
header('Content-Type: application/json');

$conn = connect_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Check if drivers table has car_number or vehicle_id/something else
    $check_car = $conn->query("SHOW COLUMNS FROM drivers LIKE 'car_number'");
    $car_col = ($check_car->num_rows > 0) ? "d.car_number" : "'' as car_number";

    $query = "SELECT u.id, u.username, u.full_name, u.phone, u.status, u.role_id, r.role_name, $car_col
              FROM users u
              JOIN roles r ON u.role_id = r.id
              LEFT JOIN drivers d ON u.id = d.user_id
              ORDER BY u.id DESC";
    $result = $conn->query($query);
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));

} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $id = $_POST['id'] ?? $_POST['user_id'] ?? null;

    if ($action === 'delete') {
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
            exit;
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT id FROM drivers WHERE user_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $driver_res = $stmt->get_result()->fetch_assoc();

            if ($driver_res) {
                $driver_id = $driver_res['id'];
                $conn->query("DELETE FROM driver_location_logs WHERE driver_id = $driver_id");
                $conn->query("DELETE FROM drivers WHERE id = $driver_id");
            }

            $stmt_u = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt_u->bind_param("i", $id);
            $stmt_u->execute();

            $conn->commit();
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    $full_name = $_POST['full_name'] ?? null;
    $username = $_POST['username'] ?? null;
    $role_id = $_POST['role_id'] ?? null;
    $phone = $_POST['phone'] ?? '';
    $car_number = $_POST['car_number'] ?? '';

    if ($action === 'create') {
        $password = $_POST['password'] ?? null;
        if (!$full_name || !$username || !$password || !$role_id) {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
            exit;
        }
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, password_hash, role_id, phone) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssis", $full_name, $username, $password_hash, $role_id, $phone);
            $stmt->execute();
            $user_id = $conn->insert_id;

            if ($role_id == 5) { // Driver
                $check_car = $conn->query("SHOW COLUMNS FROM drivers LIKE 'car_number'");
                if ($check_car->num_rows > 0) {
                    $stmt_d = $conn->prepare("INSERT INTO drivers (user_id, car_number) VALUES (?, ?)");
                    $stmt_d->bind_param("is", $user_id, $car_number);
                } else {
                    $stmt_d = $conn->prepare("INSERT INTO drivers (user_id) VALUES (?)");
                    $stmt_d->bind_param("i", $user_id);
                }
                $stmt_d->execute();
            }
            $conn->commit();
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) { $conn->rollback(); echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }

    } elseif ($action === 'update') {
        if (!$id || !$full_name || !$username || !$role_id) {
            echo json_encode(['status' => 'error', 'message' => 'Missing data for update. ID: ' . $id]);
            exit;
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, role_id = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("ssisi", $full_name, $username, $role_id, $phone, $id);
            $stmt->execute();

            if (!empty($_POST['password'])) {
                $new_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt_p = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt_p->bind_param("si", $new_hash, $id);
                $stmt_p->execute();
            }

            if ($role_id == 5) {
                $res = $conn->query("SELECT id FROM drivers WHERE user_id = $id");
                $check_car = $conn->query("SHOW COLUMNS FROM drivers LIKE 'car_number'");

                if ($res->num_rows > 0) {
                    if ($check_car->num_rows > 0) {
                        $stmt_d = $conn->prepare("UPDATE drivers SET car_number = ? WHERE user_id = ?");
                        $stmt_d->bind_param("si", $car_number, $id);
                    }
                } else {
                    if ($check_car->num_rows > 0) {
                        $stmt_d = $conn->prepare("INSERT INTO drivers (user_id, car_number) VALUES (?, ?)");
                        $stmt_d->bind_param("is", $id, $car_number);
                    } else {
                        $stmt_d = $conn->prepare("INSERT INTO drivers (user_id) VALUES (?)");
                        $stmt_d->bind_param("i", $id);
                    }
                }
                if (isset($stmt_d)) $stmt_d->execute();
            } else {
                $conn->query("DELETE FROM drivers WHERE user_id = $id");
            }

            $conn->commit();
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) { $conn->rollback(); echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
    }
}
?>
