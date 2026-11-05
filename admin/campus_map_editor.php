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
    $lat = floatval($_POST['lat']);
    $lng = floatval($_POST['lng']);
    $stmt = $conn->prepare("UPDATE office_location SET latitude = ?, longitude = ? WHERE id = 1");
    $stmt->bind_param("dd", $lat, $lng);
    if ($stmt->execute()) {
        $message = "Office location updated!";
        $message_type = "success";
    }
    $stmt->close();
}

// Handle Map Drag/Update for an EXISTING or NEW location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_hotspot'])) {
    $name = trim($_POST['loc_name']);
    $lat = floatval($_POST['loc_lat']);
    $lng = floatval($_POST['loc_lng']);
    
    // Check if it exists (case-insensitive)
    $check = $conn->prepare("SELECT id FROM campus_locations WHERE LOWER(location_name) = LOWER(?)");
    $check->bind_param("s", $name);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $id = $row['id'];
        $stmt = $conn->prepare("UPDATE campus_locations SET latitude = ?, longitude = ?, is_mapped = TRUE WHERE id = ?");
        $stmt->bind_param("ddi", $lat, $lng, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO campus_locations (location_name, latitude, longitude, is_mapped, report_count) VALUES (?, ?, ?, TRUE, 0)");
        $stmt->bind_param("sdd", $name, $lat, $lng);
    }
    $stmt->execute();
    $stmt->close();
    $check->close();
    
    $message = "Hotspot '$name' mapped successfully!";
    $message_type = "success";
}

// Fetch current data
$office = $conn->query("SELECT * FROM office_location WHERE id = 1")->fetch_assoc();
$mapped = $conn->query("SELECT * FROM campus_locations WHERE is_mapped = TRUE ORDER BY location_name ASC");

// Fetch Unmapped Suggestions (From Analytics)
$unmapped_sql = "
    SELECT TRIM(location) as location_name, COUNT(*) as report_count 
    FROM items 
    WHERE location IS NOT NULL AND TRIM(location) != '' 
    AND LOWER(TRIM(location)) NOT IN (SELECT LOWER(location_name) FROM campus_locations WHERE is_mapped = TRUE)
    GROUP BY LOWER(TRIM(location)) 
    ORDER BY report_count DESC
";
$unmapped = $conn->query($unmapped_sql);

$page_title = 'Manage Campus Map';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    /* Fix heights and borders */
    .editor-row { border-radius: 1rem; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    #editorMap { height: 600px; width: 100%; z-index: 1; }
    
    /* Mutes the bright OSM colors */
    .leaflet-tile-pane { filter: saturate(0.7) contrast(1.1) brightness(0.95); }

    .sidebar-panel { background: #f8fafc; border-right: 1px solid #e2e8f0; height: 600px; overflow-y: auto; }
    .loc-item { cursor: pointer; transition: all 0.2s; border-left: 4px solid transparent; }
    .loc-item:hover { background: #e2e8f0; }
    .loc-item.active { background: #dbeafe; border-left-color: #3b82f6; }
</style>

<div class="container mt-4 mb-5">
    <div class="mb-4">
        <h2 class="section-title">Map Configuration</h2>
        <p class="section-subtitle">Map your campus buildings and set the office location</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show shadow-sm">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-0 editor-row">
        
        <!-- LEFT SIDEBAR -->
        <div class="col-md-4 sidebar-panel p-3">
            
            <!-- Office Section -->
            <div class="card mb-3 border-0 shadow-sm" style="border-left: 4px solid var(--primary);">
                <div class="card-body p-3">
                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-building"></i> Office Location</h6>
                    <p class="small text-muted mb-2">Click anywhere on the map to set the center, then click save.</p>
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="update_office" value="1">
                        <input type="hidden" name="lat" id="office_lat" value="<?= $office['latitude'] ?>">
                        <input type="hidden" name="lng" id="office_lng" value="<?= $office['longitude'] ?>">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100 fw-bold">Save Map Center as Office</button>
                    </form>
                </div>
            </div>

            <!-- Unmapped Suggestions -->
            <h6 class="fw-bold mt-4 mb-2 text-dark"><i class="bi bi-stars text-warning"></i> Analytics Suggestions</h6>
            <p class="small text-muted mb-2">Locations typed by users that need to be mapped. Click one to drop a pin.</p>
            <div class="list-group mb-4 shadow-sm rounded" id="unmapped-list">
                <?php if ($unmapped->num_rows === 0): ?>
                    <div class="p-3 text-center text-muted small bg-white">All locations mapped! 🎉</div>
                <?php else: ?>
                    <?php while($u = $unmapped->fetch_assoc()): ?>
                        <a href="#" class="list-group-item list-group-item-action loc-item d-flex justify-content-between align-items-center" 
                           data-name="<?= htmlspecialchars($u['location_name']) ?>">
                            <?= htmlspecialchars($u['location_name']) ?>
                            <span class="badge bg-danger rounded-pill"><?= $u['report_count'] ?></span>
                        </a>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>

            <!-- Mapped Locations -->
            <h6 class="fw-bold mt-4 mb-2 text-dark"><i class="bi bi-check-circle text-success"></i> Mapped Hotspots</h6>
            <div class="list-group mb-4 shadow-sm rounded">
                <?php while($m = $mapped->fetch_assoc()): ?>
                    <a href="#" class="list-group-item list-group-item-action loc-item-mapped" 
                       data-lat="<?= $m['latitude'] ?>" data-lng="<?= $m['longitude'] ?>" data-name="<?= htmlspecialchars($m['location_name']) ?>">
                        <i class="bi bi-geo-alt text-danger me-1"></i> <?= htmlspecialchars($m['location_name']) ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- RIGHT MAP AREA -->
        <div class="col-md-8 position-relative">
            <div id="editorMap"></div>
            
            <!-- Floating Save Form (Hidden by default) -->
            <div id="save-pin-panel" class="position-absolute bg-white p-3 shadow-lg rounded" style="bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 1000; display: none; min-width: 350px; border: 2px solid var(--primary);">
                <form method="POST" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="save_hotspot" value="1">
                    <input type="hidden" name="loc_name" id="save_loc_name">
                    <input type="hidden" name="loc_lat" id="save_loc_lat">
                    <input type="hidden" name="loc_lng" id="save_loc_lng">
                    <div class="flex-grow-1">
                        <span class="d-block fw-bold text-dark" id="save_loc_name_display">Location Name</span>
                        <small class="text-muted" id="save_loc_coords">0.0000, 0.0000</small>
                    </div>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Save Pin</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const officeLat = <?= $office ? $office['latitude'] : -1.3102 ?>;
    const officeLng = <?= $office ? $office['longitude'] : 36.8123 ?>;
    
    // Init Map with OpenStreetMap (No API key needed) - Zoom 15
    const map = L.map('editorMap').setView([officeLat, officeLng], 15);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    // Add Office Pin
    const officeIcon = L.divIcon({
        className: '',
        html: '<div style="background: #1E40AF; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.5);"></div>',
        iconSize: [24, 24], iconAnchor: [12, 12]
    });
    L.marker([officeLat, officeLng], {icon: officeIcon}).addTo(map).bindPopup('Current Office');

    let activeMarker = null;

    // Handle clicking an unmapped suggestion
    document.querySelectorAll('.loc-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.loc-item').forEach(el => el.classList.remove('active'));
            this.classList.add('active');

            const name = this.getAttribute('data-name');
            if (activeMarker) map.removeLayer(activeMarker);

            const center = map.getCenter();
            activeMarker = L.marker(center, {draggable: true}).addTo(map);
            
            const savePanel = document.getElementById('save-pin-panel');
            savePanel.style.display = 'block';
            document.getElementById('save_loc_name').value = name;
            document.getElementById('save_loc_name_display').innerText = "Mapping: " + name;
            
            updateMarkerCoords(center.lat, center.lng);

            activeMarker.on('dragend', function() {
                const pos = activeMarker.getLatLng();
                updateMarkerCoords(pos.lat, pos.lng);
            });
        });
    });

    // Handle clicking already mapped locations (Fly to them)
    document.querySelectorAll('.loc-item-mapped').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const lat = parseFloat(this.getAttribute('data-lat'));
            const lng = parseFloat(this.getAttribute('data-lng'));
            const name = this.getAttribute('data-name');
            
            map.flyTo([lat, lng], 17, { duration: 1.5 });
            
            if (activeMarker) map.removeLayer(activeMarker);
            activeMarker = L.marker([lat, lng], {draggable: true}).addTo(map);
            
            const savePanel = document.getElementById('save-pin-panel');
            savePanel.style.display = 'block';
            document.getElementById('save_loc_name').value = name;
            document.getElementById('save_loc_name_display').innerText = "Editing: " + name;
            
            updateMarkerCoords(lat, lng);

            activeMarker.on('dragend', function() {
                const pos = activeMarker.getLatLng();
                updateMarkerCoords(pos.lat, pos.lng);
            });
        });
    });

    // Map click to set office
    map.on('click', function(e) {
        document.getElementById('office_lat').value = e.latlng.lat.toFixed(6);
        document.getElementById('office_lng').value = e.latlng.lng.toFixed(6);
    });

    function updateMarkerCoords(lat, lng) {
        document.getElementById('save_loc_lat').value = lat.toFixed(6);
        document.getElementById('save_loc_lng').value = lng.toFixed(6);
        document.getElementById('save_loc_coords').innerText = lat.toFixed(5) + ", " + lng.toFixed(5);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>