<?php
// Kashi Varanasi Tours & Travels CRM
// Database Connection File - Forced IST Timezone

function connect_db() {
    $DB_HOST = 'localhost';
    $DB_USER = 'u632290564_sasta';
    $DB_PASSWORD = 'Amitrai@12345';
    $DB_NAME = 'u632290564_sasta';
    $DB_PORT = 3306;

    // Suppress the default warning on connection failure so we can send a clean JSON response
    mysqli_report(MYSQLI_REPORT_OFF);

    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_NAME, $DB_PORT);

    if ($conn->connect_error) {
        http_response_code(503); // Service Unavailable
        // Manually echo a JSON error and stop the script
        echo json_encode([
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]);
        exit(); // Important to stop the script here
    }

    // Set the character set to utf8mb4 to support a wide range of characters.
    $conn->set_charset("utf8mb4");

    // FORCE MYSQL TO USE IST (+05:30)
    $conn->query("SET time_zone = '+05:30'");

    return $conn;
}

// FORCE PHP TO USE IST
date_default_timezone_set('Asia/Kolkata');
?>
