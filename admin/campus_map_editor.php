<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$message_type = '';

// Handle Office Location Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_office'])) {
    $lat = $_POST['lat'];
    $lng = $_POST['lng'];
    $conn->query("UPDATE office_location SET latitude = $lat, longitude = $lng WHERE id = 1");
    $message = "Office location updated!";
    $message_type = "success";
}

// Handle Map Drag/Update for an EXISTING location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_hotspot'])) {
    $loc_id = intval($_POST['loc_id']);
    $lat = $_POST['loc_lat'];
    $lng = $_POST['loc_lng'];
    
    $stmt = $conn->prepare("UPDATE campus_locations SET latitude = ?, longitude = ?, is_mapped = TRUE WHERE id = ?");
    $stmt->bind_param("ddi", $lat, $lng, $loc_id);
    $stmt->execute();
    $stmt->close();
    
    $message = "Hotspot mapped successfully!";
    $message_type = "success";
}

// Handle Adding a BRAND NEW location manually
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new_location'])) {
    $name = $conn->real_escape_string($_POST['new_loc_name']);
    $lat = $_POST['new_loc_lat'];
    $lng = $_POST['new_loc_lng'];
    
    $check = $conn->query("SELECT id FROM campus_locations WHERE location_name = '$name'");
    if ($check->num_rows > 0) {
        $conn->query("UPDATE campus_locations SET latitude = $lat, longitude = $lng, is_mapped = TRUE WHERE location_name = '$name'");
        $message = "Location '$name' updated and mapped!";
    } else {
        $conn->query("INSERT INTO campus_locations (location_name, latitude, longitude, is_mapped, report_count) VALUES ('$name', $lat, $lng, TRUE, 0)");
        $message = "New location '$name' added to the map!";
    }
    $message_type = "success";
}

// Fetch current data
$office = $conn->query("SELECT * FROM office_location WHERE id = 1")->fetch_assoc();
$unmapped = $conn->query("SELECT * FROM campus_locations WHERE is_mapped = FALSE ORDER BY report_count DESC");
$mapped = $conn->query("SELECT * FROM campus_locations WHERE is_mapped = TRUE ORDER BY report_count DESC");

$page_title = 'Edit Campus Map';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #editorMap { height: 500px; width: 100%; border-radius: 14px; z-index: 1; }
</style>

<div class="container mt-5 mb-5">
    <div class="mb-4">
        <h2 class="section-title">Manage Campus Map</h2>
        <p class="section-subtitle">Set the office location and map out hotspots</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Map Area -->
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-body">
                    <div id="editorMap"></div>
                    <div class="mt-3 text-muted small">
                        <i class="bi bi-info-circle"></i> Click anywhere on the map to get coordinates (Lat/Lng). Use the forms to the right to save them.
                    </div>
                </div>
            </div>
        </div>

        <!-- Forms Area -->
        <div class="col-md-5">
            
            <!-- Update Office Form -->
            <div class="card mb-4 shadow-sm" style="border-left: 5px solid #1E40AF;">
                <div class="card-header bg-primary text-white py-2">
                    <h6 class="mb-0"><i class="bi bi-building"></i> Office Location</h6>
                </div>
                <div class="card-body py-3">
                    <form method="POST">
                        <input type="hidden" name="update_office" value="1">
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Latitude</label>
                                <input type="text" name="lat" id="office_lat" class="form-control form-control-sm" value="<?= $office['latitude'] ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Longitude</label>
                                <input type="text" name="lng" id="office_lng" class="form-control form-control-sm" value="<?= $office['longitude'] ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Save Office Location</button>
                    </form>
                </div>
            </div>

            <!-- Manually Add a New Location -->
            <div class="card mb-4 shadow-sm" style="border-left: 5px solid #10B981;">
                <div class="card-header bg-success text-white py-2">
                    <h6 class="mb-0"><i class="bi bi-plus-circle"></i> Add Brand New Location</h6>
                </div>
                <div class="card-body py-3">
                    <form method="POST">
                        <input type="hidden" name="add_new_location" value="1">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Location Name</label>
                            <input type="text" name="new_loc_name" class="form-control form-control-sm" placeholder="e.g. LT1, Library" required>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Latitude</label>
                                <input type="text" name="new_loc_lat" id="new_loc_lat" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Longitude</label>
                                <input type="text" name="new_loc_lng" id="new_loc_lng" class="form-control form-control-sm" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success btn-sm w-100">Save New Location</button>
                    </form>
                </div>
            </div>

            <!-- Map Unmapped Locations -->
            <div class="card shadow-sm" style="border-left: 5px solid #F59E0B;">
                <div class="card-header bg-warning text-dark py-2">
                    <h6 class="mb-0"><i class="bi bi-geo-alt"></i> Map Pending Locations</h6>
                </div>
                <div class="card-body py-3">
                    <?php if ($unmapped->num_rows === 0): ?>
                        <p class="text-muted small mb-0">All reported locations are mapped!</p>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="update_hotspot" value="1">
                            <div class="mb-2">
                                <label class="form-label small fw-bold">Select Pending Location</label>
                                <select name="loc_id" class="form-select form-select-sm" required>
                                    <?php while($u = $unmapped->fetch_assoc()): ?>
                                        <option value="<?= $u['id'] ?>"><?= $u['location_name'] ?> (<?= $u['report_count'] ?> reports)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Latitude</label>
                                    <input type="text" name="loc_lat" id="loc_lat" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Longitude</label>
                                    <input type="text" name="loc_lng" id="loc_lng" class="form-control form-control-sm" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-warning btn-sm w-100">Map Pending Location</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const officeLat = <?= $office['latitude'] ?>;
    const officeLng = <?= $office['longitude'] ?>;
    
    const map = L.map('editorMap').setView([officeLat, officeLng], 16);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    // Draw mapped hotspots
    <?php 
    $mapped->data_seek(0);
    while ($h = $mapped->fetch_assoc()): 
    ?>
        L.marker([<?= $h['latitude'] ?>, <?= $h['longitude'] ?>])
            .addTo(map)
            .bindPopup("<strong><?= $h['location_name'] ?></strong><br><?= $h['report_count'] ?> reports");
    <?php endwhile; ?>

    // Map click listener
    map.on('click', function(e) {
        const lat = e.latlng.lat.toFixed(6);
        const lng = e.latlng.lng.toFixed(6);
        
        // Auto-fill ALL forms
        document.getElementById('office_lat').value = lat;
        document.getElementById('office_lng').value = lng;
        document.getElementById('new_loc_lat').value = lat;
        document.getElementById('new_loc_lng').value = lng;
        document.getElementById('loc_lat').value = lat;
        document.getElementById('loc_lng').value = lng;
        
        L.popup()
            .setLatLng(e.latlng)
            .setContent("Coordinates: " + lat + ", " + lng)
            .openOn(map);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>