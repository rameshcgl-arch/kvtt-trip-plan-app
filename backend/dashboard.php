<?php
// dashboard.php - Role-Based Dashboard v30 (Final Complete Version)
require_once 'db_connection.php';
session_start();

if (!isset($_SESSION['user'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
        $conn = connect_db();
        $username = $_POST['username']; $password = $_POST['password'];
        $stmt = $conn->prepare("SELECT id, password_hash, full_name, role_id FROM users WHERE username = ? AND status = 'Active'");
        $stmt->bind_param("s", $username); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && password_verify($password, $user['password_hash'])) { $_SESSION['user'] = $user; header("Location: dashboard.php"); exit; }
        else { $login_error = "Invalid credentials"; }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'logout') { session_destroy(); header("Location: dashboard.php"); exit; }
if (!isset($_SESSION['user'])): ?>
<!DOCTYPE html>
<html lang="en">
<head><title>Sasta Yatra - Login</title><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height:100vh;">
    <div class="card shadow p-4 mx-3" style="width: 400px; border-radius: 15px;">
        <h3 class="text-center mb-4 text-primary fw-bold">SASTA YATRA</h3>
        <?php if(isset($login_error)) echo "<div class='alert alert-danger py-2 small'>$login_error</div>"; ?>
        <form method="POST"><input type="hidden" name="login_action" value="1">
            <div class="mb-3"><label class="form-label small fw-bold">Username</label><input type="text" name="username" class="form-control shadow-none" required></div>
            <div class="mb-3"><label class="form-label small fw-bold">Password</label><input type="password" name="password" class="form-control shadow-none" required></div>
            <button type="submit" class="btn btn-primary w-100 py-2 shadow-sm">Login</button>
        </form>
    </div>
</body></html>
<?php exit; endif;

$user = $_SESSION['user']; $role_id = $user['role_id']; $user_id = $user['id']; $conn = connect_db();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sasta Yatra</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }
        .sidebar { height: 100vh; background: #2c3e50; color: white; position: fixed; top: 0; left: 0; width: 250px; z-index: 1000; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .main-content { margin-left: 250px; padding: 1.5rem; transition: all 0.3s; min-height: 100vh; }
        .nav-link { color: #ecf0f1; border-radius: 5px; margin: 5px 15px; cursor: pointer; text-align: left; border:none; background:transparent; width: 220px; transition: 0.2s; }
        .nav-link:hover { background: #34495e; }
        .nav-link.active { background: #3498db !important; color: white !important; }
        #map { height: 60vh; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); background: #e5e3df; width: 100%; }
        .stop-card { background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 10px; position: relative; border-left: 4px solid #3498db; }
        .sight-thumb { width: 100px; height: 75px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; }
        .popup-img { width: 100%; max-height: 250px; object-fit: contain; border-radius: 8px; margin-bottom: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        .map-label { background: rgba(255,255,255,0.9); border: 1px solid #3498db; border-radius: 4px; padding: 2px 6px; font-size: 11px; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .sight-marker-icon { border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 10px rgba(0,0,0,0.3); transition: transform 0.2s; }
        .sight-marker-icon:hover { transform: scale(1.2); z-index: 1000 !important; }
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-left: 0; padding: 1rem; }
            .nav-link { width: auto; display: inline-block; margin: 5px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<div class="sidebar shadow">
    <div class="text-center p-3">
        <h4 class="fw-bold text-uppercase m-0">Sasta Yatra</h4>
        <div class="badge bg-primary text-wrap mt-2 small"><?php echo htmlspecialchars($user['full_name']); ?></div>
    </div>
    <div class="nav nav-pills flex-column" id="main-tab" role="tablist">
        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#live-map-pane" id="btn-live-map" type="button">Tracking</button>
        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tours-pane" id="btn-tours" type="button">Manage Tours</button>
        <?php if($role_id == 1 || $role_id == 3): ?>
        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#users-pane" id="btn-users" type="button">Staff List</button>
        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#master-sights-pane" id="btn-master-sights" type="button">Location Library</button>
        <?php endif; ?>
    </div>
    <a href="?action=logout" class="btn btn-outline-danger btn-sm w-75 mx-auto d-block mt-4 shadow-none">Logout</a>
</div>

<main class="main-content">
    <div class="tab-content" id="main-tabContent">
        <!-- Live Map -->
        <div class="tab-pane fade show active" id="live-map-pane" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 m-0 text-dark fw-bold">Live Fleet Tracking</h2>
                <div class="d-flex gap-2 align-items-center bg-white p-2 rounded shadow-sm border">
                    <input type="date" class="form-control form-control-sm" id="map-date-filter" value="<?php echo date('Y-m-d'); ?>" onchange="refreshTourSelector()">
                    <select class="form-select form-select-sm" id="map-tour-selector" style="width: 200px" onchange="loadTourOnMap(this.value)"><option value="">-- Fleet --</option></select>
                </div>
            </div>
            <div id="map"></div>

            <div id="tour-live-summary" class="mt-4 d-none">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-dark text-white fw-bold"><span id="live-tour-name">--</span> - Today's Itinerary</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover m-0 small">
                            <thead class="bg-light"><tr><th>Seq</th><th>Location</th><th>Planned Time</th><th>Actual Visit</th><th>Status</th></tr></thead>
                            <tbody id="live-itinerary-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tours -->
        <div class="tab-pane fade" id="tours-pane" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="m-0 h5 fw-bold">Trips Management</h4>
                <div class="d-flex gap-2">
                    <input type="date" class="form-control form-control-sm" id="tour-list-date-filter" value="" onchange="fetchTours()" placeholder="Filter by Date">
                    <?php if($role_id == 1 || $role_id == 3): ?><button class="btn btn-primary btn-sm px-3 shadow-none" data-bs-toggle="modal" data-bs-target="#addTourModal" onclick="openAddTour()">+ New Trip</button><?php endif; ?>
                </div>
            </div>
            <div class="table-responsive"><table class="table table-sm table-hover bg-white rounded shadow-sm align-middle border"><thead><tr><th>Trip Name</th><th>Date</th><th>Driver</th><th>Status</th><th>Actions</th></tr></thead><tbody id="tour-list-body"></tbody></table></div>
        </div>

        <!-- Staff Management -->
        <?php if($role_id == 1 || $role_id == 3): ?>
        <div class="tab-pane fade" id="users-pane" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3"><h4 class="h5 m-0 fw-bold">Staff Members</h4><button class="btn btn-primary btn-sm px-3 shadow-none" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAddUser()">+ Add Staff</button></div>
            <div class="table-responsive"><table class="table table-sm table-hover bg-white rounded shadow-sm align-middle border"><thead><tr><th>Name</th><th>Phone</th><th>Role</th><th>Car</th><th>Actions</th></tr></thead><tbody id="user-list-body"></tbody></table></div>
        </div>

        <!-- Master Sights -->
        <div class="tab-pane fade" id="master-sights-pane" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3"><h4 class="h5 m-0 fw-bold">Sightseeing Library</h4><button class="btn btn-primary btn-sm px-3 shadow-none" data-bs-toggle="modal" data-bs-target="#masterSightModal" onclick="openAddMasterSight()">+ Add Location</button></div>
            <div class="table-responsive"><table class="table table-sm table-hover bg-white rounded shadow-sm align-middle border"><thead><tr><th>Photo</th><th>City</th><th>Point Name</th><th>Actions</th></tr></thead><tbody id="master-sight-list-body"></tbody></table></div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modals -->
<div class="modal fade" id="tourSummaryModal" data-bs-focus="false"><div class="modal-dialog modal-lg modal-fullscreen-sm-down"><div class="modal-content border-0 shadow"><div class="modal-header bg-dark text-white border-0"><h5 class="modal-title" id="details-title">Tour Info</h5><button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal"></button></div><div class="modal-body p-0"><ul class="nav nav-tabs nav-fill bg-light border-0"><li class="nav-item"><button class="nav-link active py-3 rounded-0 border-0 shadow-none" data-bs-toggle="tab" data-bs-target="#tab-plan">SIGHTSEEING PLAN</button></li><li class="nav-item"><button class="nav-link py-3 rounded-0 border-0 shadow-none" data-bs-toggle="tab" data-bs-target="#tab-team">TEAM CONTACTS</button></li></ul><div class="tab-content p-3"><div class="tab-pane fade show active" id="tab-plan"><div id="tour-summary-body"></div></div><div class="tab-pane fade" id="tab-team"><div id="tour-team-body"></div></div></div></div></div></div></div>

<div class="modal fade" id="userModal" data-bs-focus="false"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow"><form id="addUserForm"><input type="hidden" name="action" id="user-action" value="create"><input type="hidden" name="id" id="user-id"><div class="modal-header bg-primary text-white border-0"><h5 id="user-modal-title">Member Detail</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-3"><input type="text" class="form-control mb-2 shadow-none" name="full_name" id="field-full-name" placeholder="Full Name" required><input type="text" class="form-control mb-2 shadow-none" name="phone" id="field-phone" placeholder="Phone" required><input type="text" class="form-control mb-2 shadow-none" name="username" id="field-username" placeholder="Username" required><input type="password" class="form-control mb-2 shadow-none" name="password" id="field-password" placeholder="Password (blank to keep current)"><select class="form-select mb-2 shadow-none" name="role_id" id="field-role" required onchange="toggleCarField(this.value)"><option value="" disabled selected>Role</option><?php $roles=$conn->query("SELECT id, role_name FROM roles"); while($r=$roles->fetch_assoc()) echo "<option value='{$r['id']}'>{$r['role_name']}</option>"; ?></select><input type="text" class="form-control d-none mb-2 shadow-none" name="car_number" id="field-car-number" placeholder="Vehicle Number"></div><div class="modal-footer border-0"><button type="submit" class="btn btn-primary w-100 shadow-sm">Save Member</button></div></form></div></div></div>

<div class="modal fade" id="addTourModal" data-bs-focus="false"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content border-0 shadow"><form id="addTourForm"><input type="hidden" name="action" id="tour-action" value="create"><input type="hidden" name="tour_id" id="tour-id-edit"><div class="modal-header bg-primary text-white border-0"><h5 id="tour-modal-title">Trip Planning</h5><button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal"></button></div><div class="modal-body p-4">
    <div class="row g-2 mb-3">
        <div class="col-md-6"><label class="small fw-bold">Trip Name</label><input type="text" class="form-control shadow-none" name="tour_name" id="field-tour-name" required></div>
        <div class="col-md-6"><label class="small fw-bold">Trip Date</label><input type="date" class="form-control shadow-none" name="tour_date" id="field-tour-date" required></div>
    </div>
    <div class="row g-2 mb-3">
        <div class="col-md-3"><label class="small fw-bold">Driver</label><select class="form-select shadow-none" name="driver_id" id="driver-list" required></select></div>
        <div class="col-md-3"><label class="small fw-bold text-muted">Guide</label><select class="form-select shadow-none" name="guide_id" id="guide-list"></select></div>
        <div class="col-md-3"><label class="small fw-bold text-muted">Coordinator</label><select class="form-select shadow-none" name="coordinator_id" id="coord-list"></select></div>
        <div class="col-md-3"><label class="small fw-bold text-muted">Guest</label><select class="form-select shadow-none" name="guest_id" id="guest-list"></select></div>
    </div>
    <hr><div class="d-flex justify-content-between align-items-center mb-3"><h6>Sightseeing Stops</h6><button type="button" class="btn btn-outline-primary btn-sm shadow-none" onclick="addNewStopRow()">+ Add Stop</button></div>
    <div id="sightseeing-list"></div>
</div><div class="modal-footer border-0 bg-light"><button type="submit" class="btn btn-primary px-5 shadow-sm">Save Trip Plan</button></div></form></div></div></div>

<div class="modal fade" id="masterSightModal" data-bs-focus="false"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow"><form id="masterSightForm" enctype="multipart/form-data"><input type="hidden" name="action" id="ms-action" value="create"><input type="hidden" name="id" id="ms-id"><input type="hidden" name="existing_image" id="ms-existing-image"><div class="modal-header bg-primary text-white border-0"><h5>Master Location Library</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-3"><input type="text" class="form-control mb-2 shadow-none" name="city_name" id="ms-field-city" placeholder="City" required><input type="text" class="form-control mb-2 shadow-none" name="sight_name" id="ms-field-sight" placeholder="Sight Name" required><div class="row g-2"><div class="col-6"><input type="text" class="form-control mb-2 shadow-none" name="latitude" id="ms-field-lat" placeholder="Latitude" required></div><div class="col-6"><input type="text" class="form-control mb-2 shadow-none" name="longitude" id="ms-field-lng" placeholder="Longitude" required></div></div><label class="small fw-bold">Upload New Photo</label><input type="file" class="form-control mb-2 shadow-none" name="image" accept="image/*"></div><div class="modal-footer border-0"><button type="submit" class="btn btn-primary w-100 shadow-none">Save Location</button></div></form></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const USER_ID = <?php echo $user_id; ?>, ROLE_ID = <?php echo $role_id; ?>;
    let allUsersData = [], allMasterSights = [], allToursData = [];
    var map = L.map('map').setView([25.3176, 82.9739], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    var driverMarkers = {}, tourMarkers = [], routePoly = L.polyline([], {color: '#3498db', weight: 4, opacity: 0.6}).addTo(map), selectedTour = null;

    // CUSTOM CAR ICON
    var carIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/744/744465.png',
        iconSize: [32, 32],
        iconAnchor: [16, 16],
        popupAnchor: [0, -16]
    });

    if (window.history.replaceState) { window.history.replaceState(null, null, window.location.pathname); }

    // --- FETCHERS ---
    async function fetchUsers() { const res = await fetch('manage_users.php'); allUsersData = await res.json(); document.getElementById('user-list-body').innerHTML = allUsersData.map(u => `<tr><td>${u.full_name}</td><td><a href="tel:${u.phone}">${u.phone}</a></td><td>${u.role_name}</td><td>${u.car_number||'--'}</td><td><button class="btn btn-xs btn-outline-primary shadow-none" onclick="openEditUser(${u.id})">Edit</button> <button class="btn btn-xs btn-outline-danger shadow-none" onclick="deleteUser(${u.id})">Del</button></td></tr>`).join(''); }
    async function fetchMasterSights() { const res = await fetch('manage_sightseeing_master.php'); allMasterSights = await res.json(); document.getElementById('master-sight-list-body').innerHTML = allMasterSights.map(s => `<tr><td><img src="${s.image_path||'https://placehold.co/100x75?text=No+Image'}" class="sight-thumb"></td><td>${s.city_name}</td><td>${s.sight_name}</td><td><button class="btn btn-xs btn-outline-primary shadow-none" onclick="openEditMasterSight(${s.id})">Edit</button> <button class="btn btn-xs btn-outline-danger shadow-none" onclick="deleteMasterSight(${s.id})">X</button></td></tr>`).join(''); }
    async function fetchTours() {
        const df = document.getElementById('tour-list-date-filter').value;
        const mdf = document.getElementById('map-date-filter').value;
        const res = await fetch(`manage_tours.php?user_id=${USER_ID}&role_id=${ROLE_ID}`);
        allToursData = await res.json();

        const selector = document.getElementById('map-tour-selector');
        selector.innerHTML = '<option value="">-- Fleet --</option>';

        let autoSelectId = null;
        allToursData.forEach(t => {
            if(t.tour_date == mdf && (t.status == 'In-Progress' || t.status == 'Scheduled')) {
                selector.add(new Option(t.tour_name, t.id));
                if (ROLE_ID != 1 && ROLE_ID != 3 && !autoSelectId) {
                    autoSelectId = t.id;
                }
            }
        });

        if (autoSelectId && !selectedTour) {
            selector.value = autoSelectId;
            loadTourOnMap(autoSelectId);
        }

        document.getElementById('tour-list-body').innerHTML = allToursData.filter(t => !df || t.tour_date == df).map(t => {
            let btns = `<button class="btn btn-xs btn-outline-info shadow-none" onclick="viewTourDetails(${t.id})">Info</button> <button class="btn btn-xs btn-outline-primary shadow-none" onclick="openEditTour(${t.id})">Edit</button>`;
            if(ROLE_ID == 1 || ROLE_ID == 3) {
                btns += (t.status == 'Scheduled') ? ` <button class="btn btn-xs btn-success shadow-none" onclick="updateStatus(${t.id},'In-Progress')">Go</button>` : ` <button class="btn btn-xs btn-dark shadow-none" onclick="updateStatus(${t.id},'End')">X</button>`;
                btns += ` <button class="btn btn-xs btn-danger shadow-none" onclick="deleteTour(${t.id})">Del</button>`;
            }
            return `<tr><td>${t.tour_name}</td><td>${t.tour_date}</td><td>${t.driver_name}</td><td>${t.status}</td><td>${btns}</td></tr>`;
        }).join('');
    }

    // --- ACTIONS ---
    function openAddUser() { document.getElementById('user-modal-title').innerText="Create Member"; document.getElementById('user-action').value="create"; document.getElementById('addUserForm').reset(); bootstrap.Modal.getOrCreateInstance(document.getElementById('userModal')).show(); }
    function openEditUser(id) { const u = allUsersData.find(x=>x.id==id); document.getElementById('user-modal-title').innerText="Edit Staff Member"; document.getElementById('user-action').value="update"; document.getElementById('user-id').value=id; document.getElementById('field-full-name').value=u.full_name; document.getElementById('field-phone').value=u.phone; document.getElementById('field-username').value=u.username; document.getElementById('field-role').value=u.role_id; document.getElementById('field-car-number').value=u.car_number||''; toggleCarField(u.role_id); bootstrap.Modal.getOrCreateInstance(document.getElementById('userModal')).show(); }
    function toggleCarField(v) { document.getElementById('field-car-number').classList.toggle('d-none', v != 5); }
    async function deleteUser(id) { if(confirm("Delete staff?")) { await fetch('manage_users.php',{method:'POST', body:new URLSearchParams({action:'delete', id})}); fetchUsers(); } }

    function openAddMasterSight() { document.getElementById('ms-action').value="create"; document.getElementById('masterSightForm').reset(); }
    function openEditMasterSight(id) { const s = allMasterSights.find(x=>x.id==id); document.getElementById('ms-action').value="update"; document.getElementById('ms-id').value=id; document.getElementById('ms-field-city').value=s.city_name; document.getElementById('ms-field-sight').value=s.sight_name; document.getElementById('ms-field-lat').value=s.latitude; document.getElementById('ms-field-lng').value=s.longitude; document.getElementById('ms-existing-image').value=s.image_path; bootstrap.Modal.getOrCreateInstance(document.getElementById('masterSightModal')).show(); }
    async function deleteMasterSight(id) { if(confirm("Delete location?")) { await fetch('manage_sightseeing_master.php',{method:'POST', body:new URLSearchParams({action:'delete', id})}); fetchMasterSights(); } }

    function openAddTour() { document.getElementById('tour-modal-title').innerText="New Assignment"; document.getElementById('tour-action').value="create"; document.getElementById('addTourForm').reset(); document.getElementById('sightseeing-list').innerHTML=""; }
    async function openEditTour(id) {
        const res = await fetch(`manage_tours.php?tour_id=${id}`); const data = await res.json();
        document.getElementById('tour-modal-title').innerText="Edit Trip Itinerary"; document.getElementById('tour-action').value="create";
        document.getElementById('tour-id-edit').value=id; document.getElementById('field-tour-name').value=data.tour.tour_name; document.getElementById('field-tour-date').value=data.tour.tour_date;
        document.getElementById('sightseeing-list').innerHTML=""; data.sightseeing.forEach(s => addNewStopRow(s));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addTourModal')).show();
    }
    async function updateStatus(id, status) {
        if (!confirm(`Confirm action: Set tour to '${status}'?`)) return;
        await fetch('manage_tours.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'update_status', tour_id: id, status: status })
        });
        fetchTours();
    }
    async function deleteTour(id) {
        if (!confirm('Are you sure you want to delete this tour?')) return;
        await fetch('manage_tours.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'delete', tour_id: id })
        });
        fetchTours();
    }

    function addNewStopRow(preData = null) {
        const list = document.getElementById('sightseeing-list'), rid = Date.now() + Math.random();
        const div = document.createElement('div'); div.className='stop-card shadow-sm';
        const cities = [...new Set(allMasterSights.map(s=>s.city_name))].sort(), cityOpts = cities.map(c=>`<option value="${c}">${c}</option>`).join('');
        div.innerHTML = `<div class="row g-2 align-items-end"><div class="col-md-3"><label class="small fw-bold">City</label><select class="form-select form-select-sm" id="city-${rid}" onchange="updateSightOpts('${rid}',this.value)"><option value="">Select City</option>${cityOpts}<option value="Others">Others</option></select></div><div class="col-md-4"><label class="small fw-bold">Sight</label><select class="form-select form-select-sm" id="sight-${rid}" onchange="handleSightSelect('${rid}',this.value)"><option value="">Select City First</option></select></div><div class="col-md-3"><label class="small fw-bold">Time</label><input type="time" class="form-control form-control-sm" id="time-${rid}"></div><div class="col-md-2 text-end"><button type="button" class="btn btn-danger btn-sm shadow-none" onclick="this.closest('.stop-card').remove()">×</button></div></div><div class="row g-2 mt-2 d-none" id="man-${rid}"><div class="col-4"><input type="text" class="form-control form-control-sm" id="man-name-${rid}" placeholder="Name"></div><div class="col-4"><input type="text" class="form-control form-control-sm" id="man-lat-${rid}" placeholder="Lat"></div><div class="col-4"><input type="text" class="form-control form-control-sm" id="man-lng-${rid}" placeholder="Lng"></div></div><input type="hidden" id="val-name-${rid}"><input type="hidden" id="val-lat-${rid}"><input type="hidden" id="val-lng-${rid}">`;
        list.appendChild(div);
        if(preData) {
            document.getElementById(`city-${rid}`).value = "Others";
            updateSightOpts(rid, "Others");
            document.getElementById(`man-name-${rid}`).value=preData.sight_name;
            document.getElementById(`man-lat-${rid}`).value=preData.latitude;
            document.getElementById(`man-lng-${rid}`).value=preData.longitude;
            document.getElementById(`time-${rid}`).value=preData.expected_time ? preData.expected_time.split(' ')[1] : "";
        }
    }
    function updateSightOpts(rid, city) {
        const sel = document.getElementById(`sight-${rid}`), man = document.getElementById(`man-${rid}`);
        if(city==="Others") { sel.innerHTML='<option value="manual">Manual Entry</option>'; man.classList.remove('d-none'); return; }
        man.classList.add('d-none'); const sights = allMasterSights.filter(s=>s.city_name===city);
        sel.innerHTML = '<option value="">Select Point</option>' + sights.map(s=>`<option value="${s.id}">${s.sight_name}</option>`).join('');
    }
    function handleSightSelect(rid, sid) { if(sid==="manual") return; const s = allMasterSights.find(x=>x.id==sid); document.getElementById(`val-name-${rid}`).value=s.sight_name; document.getElementById(`val-lat-${rid}`).value=s.latitude; document.getElementById(`val-lng-${rid}`).value=s.longitude; }

    // --- TRACKING MAP LOGIC ---
    async function loadTourOnMap(tid) {
        selectedTour = tid;
        if(!tid) {
            tourMarkers.forEach(m=>map.removeLayer(m)); tourMarkers = [];
            routePoly.setLatLngs([]);
            document.getElementById('tour-live-summary').classList.add('d-none');
            return;
        }

        const res = await fetch(`get_tour_details.php?tour_id=${tid}`);
        const data = await res.json();

        // Update Live Summary Table
        document.getElementById('tour-live-summary').classList.remove('d-none');
        document.getElementById('live-tour-name').innerText = data.tour.tour_name;
        document.getElementById('live-itinerary-body').innerHTML = data.sightseeing.map((s, idx) => `
            <tr>
                <td>${idx + 1}</td>
                <td class="fw-bold">${s.sight_name}</td>
                <td>${s.expected_time.split(' ')[1]}</td>
                <td>${s.actual_visit_time ? s.actual_visit_time.split(' ')[1] : '--:--'}</td>
                <td><span class="badge ${s.visit_status=='Visited'?'bg-success':'bg-danger'}">${s.visit_status}</span></td>
            </tr>
        `).join('');

        // Update Map Markers
        tourMarkers.forEach(m => map.removeLayer(m)); tourMarkers = [];
        data.sightseeing.forEach(s => {
            const master = allMasterSights.find(m => m.sight_name === s.sight_name);
            const color = s.visit_status == 'Visited' ? '#2ecc71' : '#e74c3c';
            const imgPath = (master && master.image_path) ? master.image_path : 'https://placehold.co/100x100?text=No+Image';

            const sightIcon = L.divIcon({
                html: `<div class="sight-marker-icon" style="background-image: url('${imgPath}'); background-size: cover; width: 40px; height: 40px; border: 3px solid ${color};"></div>`,
                className: '',
                iconSize: [40, 40],
                iconAnchor: [20, 20]
            });

            const m = L.marker([s.latitude, s.longitude], {icon: sightIcon}).addTo(map);

            const time = s.expected_time.split(' ')[1];
            m.bindTooltip(`<b>${s.sight_name}</b><br><small>Time: ${time}</small>`, {
                permanent: false,
                direction: 'top',
                className: 'map-label',
                offset: [0, -20]
            });

            let pop = `<div style="width: 200px; text-align: center;">
                        <img src="${imgPath}" class="popup-img" style="width: 100%;">
                        <h6 class="fw-bold m-0">${s.sight_name}</h6>
                        <p class="small text-muted mb-1">Status: ${s.visit_status}</p>
                        <p class="small text-primary m-0">Planned Time: ${time}</p>
                      </div>`;
            m.bindPopup(pop);

            tourMarkers.push(m);
        });

        const path = data.route.map(r => [r.latitude, r.longitude]);
        routePoly.setLatLngs(path);

        if (path.length > 0) {
            const currentPos = path[path.length - 1];
            const lastLog = data.route[data.route.length - 1];
            const ts = lastLog.timestamp ? lastLog.timestamp : 'Unknown';

            if (driverMarkers['current']) map.removeLayer(driverMarkers['current']);
            driverMarkers['current'] = L.marker(currentPos, {icon: carIcon}).addTo(map).bindPopup(`
                <div style="text-align: center;">
                    <h6 class="fw-bold mb-1">${data.tour.driver_name}</h6>
                    <p class="small m-0 text-muted">Last Reported: <b class="text-dark">${ts}</b></p>
                    <p class="small mb-2">Today's Trip: ${data.tour.tour_name}</p>
                    <button class="btn btn-xs btn-danger w-100 shadow-none" onclick="sendWakeUpAlert(${data.tour.user_id})">Send Wake-up Alert</button>
                </div>
            `);
            map.fitBounds(path);
        } else if(data.sightseeing.length > 0) {
            const bounds = L.latLngBounds(data.sightseeing.map(s => [s.latitude, s.longitude]));
            map.fitBounds(bounds, {padding: [50, 50]});
        }
    }

    async function sendWakeUpAlert(uid) {
        if(!confirm("Send a high-priority alert to this driver?")) return;
        const res = await fetch('send_alert.php', {
            method: 'POST',
            body: new URLSearchParams({ user_id: uid, type: 'wakeup' })
        });
        const data = await res.json();
        alert(data.message || "Alert sent successfully!");
    }

    async function viewTourDetails(id) {
        const res = await fetch(`get_tour_plan.php?user_id=${USER_ID}&role_id=${ROLE_ID}&tour_id=${id}`); const data = await res.json();
        document.getElementById('details-title').innerText = data.tour.tour_name;
        document.getElementById('tour-summary-body').innerHTML = `<table class="table table-sm small"><thead><tr><th>Stop</th><th>Status</th></tr></thead><tbody>${data.sightseeing.map(s => `<tr><td>${s.sight_name}</td><td>${s.visit_status}</td></tr>`).join('')}</tbody></table>`;
        document.getElementById('tour-team-body').innerHTML = data.team.map(m => `<div class="team-badge small"><b>${m.role_label}:</b> ${m.full_name}<br><small>${m.phone}</small></div>`).join('') || 'None';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('tourSummaryModal')).show();
    }

    // --- FORM HANDLERS ---
    document.getElementById('addTourForm').addEventListener('submit', async (e) => {
        e.preventDefault(); const fd = new FormData(e.target), stops = [];
        document.querySelectorAll('.stop-card').forEach(card => {
            const rid = card.querySelector('select').id.split('-')[1];
            const name = document.getElementById(`val-name-${rid}`).value || document.getElementById(`man-name-${rid}`).value;
            const lat = document.getElementById(`val-lat-${rid}`).value || document.getElementById(`man-lat-${rid}`).value;
            const lng = document.getElementById(`val-lng-${rid}`).value || document.getElementById('man-lng-'+rid).value;
            const time = document.getElementById(`time-${rid}`).value;
            if(name && lat && lng) stops.push({name, lat, lng, time});
        });
        fd.append('sightseeing', JSON.stringify(stops));
        await fetch('manage_tours.php',{method:'POST', body: fd});
        bootstrap.Modal.getInstance(document.getElementById('addTourModal')).hide();
        fetchTours();
    });

    async function updateMap() {
        if(!selectedTour) {
            try {
                const res = await fetch('get_all_drivers.php');
                const drivers = await res.json();
                drivers.forEach(d => {
                    const pos = [parseFloat(d.latitude), parseFloat(d.longitude)];
                    if(pos[0] !== 0) {
                        const ts = d.timestamp ? d.timestamp : '--:--';
                        const popupContent = `
                            <div style="text-align: center;">
                                <h6 class="fw-bold mb-1">${d.full_name}</h6>
                                <p class="small m-0 text-muted">Last Reported: <b class="text-dark">${ts}</b></p>
                                <p class="small m-0 mb-2">Battery: <b>${d.battery_percentage}%</b></p>
                                <button class="btn btn-xs btn-danger w-100 shadow-none" onclick="sendWakeUpAlert(${d.user_id})">Send Wake-up Alert</button>
                            </div>`;

                        if(driverMarkers[d.driver_id]) {
                            driverMarkers[d.driver_id].setLatLng(pos).getPopup().setContent(popupContent);
                        } else {
                            driverMarkers[d.driver_id] = L.marker(pos, {icon: carIcon}).addTo(map).bindPopup(popupContent);
                        }
                    }
                });
            } catch(e){}
        } else {
            loadTourOnMap(selectedTour);
        }
    }

    // Init Page
    fetchTours(); fetchMasterSights(); setInterval(updateMap, 30000); updateMap();
    document.getElementById('btn-live-map').addEventListener('shown.bs.tab', () => setTimeout(() => map.invalidateSize(), 300));
    document.getElementById('btn-tours').addEventListener('shown.bs.tab', fetchTours);
    document.getElementById('btn-users')?.addEventListener('shown.bs.tab', fetchUsers);
    document.getElementById('btn-master-sights')?.addEventListener('shown.bs.tab', fetchMasterSights);
    document.getElementById('addUserForm')?.addEventListener('submit', async (e) => { e.preventDefault(); await fetch('manage_users.php',{method:'POST', body: new FormData(e.target)}); bootstrap.Modal.getInstance(document.getElementById('userModal')).hide(); fetchUsers(); });
    document.getElementById('masterSightForm')?.addEventListener('submit', async (e) => { e.preventDefault(); await fetch('manage_sightseeing_master.php',{method:'POST', body: new FormData(e.target)}); bootstrap.Modal.getInstance(document.getElementById('masterSightModal')).hide(); fetchMasterSights(); });
    function refreshTourSelector() { fetchTours(); }
    document.getElementById('addTourModal').addEventListener('show.bs.modal', async () => {
        const fetchL = async (rid, el) => { const r = await fetch(`manage_tours.php?role_type=${rid}`); const d = await r.json(); document.getElementById(el).innerHTML = '<option value="">None</option>' + d.map(u=>`<option value="${u.id}">${u.full_name}</option>`).join(''); };
        await fetchL(5, 'driver-list'); await fetchL(4, 'guide-list'); await fetchL(2, 'coord-list'); await fetchL(6, 'guest-list');
    });
</script>
</body>
</html>
