<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Fetch Office Location
$office = $conn->query("SELECT * FROM office_location WHERE id = 1")->fetch_assoc();

// Fetch ONLY locations that have been mapped (is_mapped = TRUE)
$locations = $conn->query("SELECT * FROM campus_locations WHERE is_mapped = TRUE");

$hotspots = [];
while ($loc = $locations->fetch_assoc()) {
    $hotspots[] = $loc;
}

$page_title = 'Campus Map';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    #map { height: 600px; width: 100%; border-radius: 14px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1); z-index: 1;}
    .hotspot-pin {
        background-color: #EF4444;
        border-radius: 50%;
        border: 3px solid white;
        box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
    }
    .office-pin {
        background-color: #1E40AF;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        border: 3px solid white;
        box-shadow: 0 0 10px rgba(30, 64, 175, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 14px;
    }
</style>

<div class="container mt-5">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="section-title">Campus Map & Hotspots</h2>
            <p class="section-subtitle">View the Lost & Found office location and areas with high item loss</p>
        </div>
        <div>
            <span class="badge bg-primary px-3 py-2"><i class="bi bi-building"></i> Lost & Found Office</span>
            <span class="badge bg-danger px-3 py-2 ms-2"><i class="bi bi-geo-alt"></i> Item Hotspot</span>
        </div>
    </div>

    <div id="map"></div>
    
    <div class="alert alert-info mt-4">
        <i class="bi bi-info-circle"></i>
        <strong>Hotspots</strong> indicate areas where items are frequently lost or found. 
        If you lost an item in one of these areas, check the <a href="browse_items.php?status=found" class="alert-link">Found Items</a> list.
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const officeLat = <?= $office ? $office['latitude'] : -1.3102 ?>;
    const officeLng = <?= $office ? $office['longitude'] : 36.8123 ?>;
    
    const map = L.map('map').setView([officeLat, officeLng], 16);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);

    // Add Office Pin
    const officeIcon = L.divIcon({
        className: '',
        html: '<div class="office-pin"><i class="bi bi-building"></i></div>',
        iconSize: [30, 30],
        iconAnchor: [15, 15]
    });
    
    L.marker([officeLat, officeLng], {icon: officeIcon})
        .addTo(map)
        .bindPopup('<strong>Lost & Found Office</strong><br>Drop off or collect items here.')
        .openPopup();

    // Add Hotspot Pins based on report_count
    const hotspots = <?= json_encode($hotspots) ?>;
    
    hotspots.forEach(function(spot) {
        // Calculate pin size based on number of reports (min 24px, max 50px)
        let size = 24 + (spot.report_count * 3);
        if (size > 50) size = 50;
        
        const hotspotIcon = L.divIcon({
            className: '',
            html: `<div class="hotspot-pin" style="width:${size}px; height:${size}px; font-size:${size/3}px;">${spot.report_count}</div>`,
            iconSize: [size, size],
            iconAnchor: [size/2, size/2]
        });
        
        L.marker([spot.latitude, spot.longitude], {icon: hotspotIcon})
            .addTo(map)
            .bindPopup(`<strong>${spot.location_name}</strong><br>${spot.report_count} item(s) reported here.`);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>