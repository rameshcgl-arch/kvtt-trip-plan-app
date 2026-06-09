<?php
// view_tour.php - Public Live Tracking View
require_once 'db_connection.php';
$conn = connect_db();

$token = $_GET['token'] ?? null;

if (!$token) {
    die("Invalid access link.");
}

// Fetch tour details using the token
$query = "SELECT id, tour_name, tour_date FROM tours WHERE share_token = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $token);
$stmt->execute();
$tour = $stmt->get_result()->fetch_assoc();

if (!$tour) {
    die("Tour not found or link expired.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Tour Tracking - <?php echo $tour['tour_name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map { height: 80vh; border-radius: 10px; margin-top: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .info-panel { background: white; padding: 15px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="info-panel d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0"><?php echo $tour['tour_name']; ?></h4>
            <small class="text-muted">Date: <?php echo $tour['tour_date']; ?></small>
        </div>
        <span id="live-indicator" class="badge bg-success">LIVE TRACKING ACTIVE</span>
    </div>

    <div id="map"></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    var map = L.map('map').setView([25.3176, 82.9739], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    var tourId = <?php echo $tour['id']; ?>;
    var markers = [];
    var routeLine = L.polyline([], {color: 'blue'}).addTo(map);
    var driverMarker = null;

    async function refreshTracking() {
        try {
            const res = await fetch(`get_tour_details.php?tour_id=${tourId}`);
            const data = await res.json();

            // 1. Clear points
            markers.forEach(m => map.removeLayer(m));
            markers = [];

            // 2. Plot Sightseeing
            data.sightseeing.forEach(s => {
                const color = s.visit_status === 'Visited' ? 'green' : 'red';
                const m = L.circleMarker([s.latitude, s.longitude], { color: color, radius: 8 }).addTo(map)
                    .bindPopup(`<b>${s.sight_name}</b><br>Status: ${s.visit_status}`);
                markers.push(m);
            });

            // 3. Draw Route & Driver
            const path = data.route.map(r => [r.latitude, r.longitude]);
            routeLine.setLatLngs(path);

            if (path.length > 0) {
                const lastPos = path[path.length - 1];
                if (!driverMarker) {
                    driverMarker = L.marker(lastPos).addTo(map).bindPopup("Current Location");
                } else {
                    driverMarker.setLatLng(lastPos);
                }
                map.panTo(lastPos);
            }
        } catch (e) { console.error(e); }
    }

    setInterval(refreshTracking, 30000);
    refreshTracking();
</script>
</body>
</html>
