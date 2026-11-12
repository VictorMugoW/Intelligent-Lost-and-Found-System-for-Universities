<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$office = $conn->query("SELECT * FROM office_location WHERE id = 1")->fetch_assoc();

// Fetch locations and count CURRENT items for accurate sizing
$hotspots_sql = "
    SELECT cl.location_name, cl.latitude, cl.longitude, COUNT(i.item_id) as current_reports
    FROM campus_locations cl
    LEFT JOIN items i ON LOWER(TRIM(i.location)) = LOWER(cl.location_name) AND i.status IN ('lost', 'found')
    WHERE cl.is_mapped = TRUE
    GROUP BY cl.id
    ORDER BY current_reports DESC
";
$hotspots_result = $conn->query($hotspots_sql);
$hotspots = [];
while($row = $hotspots_result->fetch_assoc()) {
    if ($row['current_reports'] > 0) {
        $hotspots[] = $row;
    }
}

$page_title = 'Campus Map';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<!-- Leaflet CSS, Locate Control CSS & Routing Machine CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.79.0/dist/L.Control.Locate.min.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />

<style>
    /* Container styling */
    .map-container-wrapper { position: relative; border-radius: 1rem; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
    #campus-map { height: 550px; width: 100%; z-index: 1; }
    
    /* Mutes the bright OSM colors so your UI pops */
    .leaflet-tile-pane { filter: saturate(0.7) contrast(1.1) brightness(0.95); }

    /* Pulsing Office Pin */
    .office-pin-wrapper {
        background-color: var(--primary, #1E40AF);
        width: 36px; height: 36px;
        border-radius: 50% 50% 50% 0;
        transform: rotate(-45deg);
        border: 3px solid white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        display: flex; align-items: center; justify-content: center;
    }
    .office-pin-wrapper i { transform: rotate(45deg); color: white; font-size: 16px; }
    .office-pulse {
        animation: pulse-ring 2s infinite;
        border-radius: 50%; height: 36px; width: 36px;
        position: absolute; top: 0; left: 0;
        box-shadow: 0 0 0 0 rgba(30, 64, 175, 0.7);
    }
    @keyframes pulse-ring {
        0% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(30, 64, 175, 0.7); }
        70% { transform: scale(1.5); box-shadow: 0 0 0 15px rgba(30, 64, 175, 0); }
        100% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(30, 64, 175, 0); }
    }

    /* Hotspot Pins */
    .hotspot-pin {
        background-color: var(--danger, #EF4444);
        border-radius: 50%; border: 3px solid white;
        box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4);
        display: flex; align-items: center; justify-content: center;
        color: white; font-weight: 800;
    }

    /* Legend */
    .map-legend {
        position: absolute; bottom: 20px; right: 20px;
        background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
        padding: 1rem; border-radius: 0.75rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        z-index: 1000; border: 1px solid #e2e8f0; font-size: 0.85rem;
    }
    .legend-item { display: flex; align-items: center; margin-bottom: 0.5rem; }
    .legend-item:last-child { margin-bottom: 0; }

    /* Hide the default routing instructions box */
    .leaflet-routing-container { display: none !important; }
</style>

<div class="container py-5" style="max-width: 1200px;">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h2 class="fw-bold mb-1" style="color: #0f172a;">Campus Map & Hotspots</h2>
            <p class="text-muted mb-0">Locate the office and view areas with high item loss.</p>
        </div>
        <div>
            <a href="browse_items.php" class="btn btn-primary fw-bold px-4">
                <i class="bi bi-search me-1"></i> Browse Items
            </a>
        </div>
    </div>

    <div class="map-container-wrapper">
        <div id="campus-map"></div>
        
        <!-- Floating Legend -->
        <div class="map-legend">
            <h6 class="fw-bold mb-2" style="font-size: 0.9rem;">Map Legend</h6>
            <div class="legend-item">
                <div style="width: 14px; height: 14px; background: var(--primary, #1E40AF); border-radius: 50%; margin-right: 8px;"></div>
                <span>Lost & Found Office</span>
            </div>
            <div class="legend-item">
                <div style="width: 14px; height: 14px; background: var(--danger, #EF4444); border-radius: 50%; margin-right: 8px;"></div>
                <span>Hotspot (Size = Frequency)</span>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet, Locate Control & Routing Machine JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.79.0/dist/L.Control.Locate.min.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const officeLat = <?= $office ? $office['latitude'] : -1.3102 ?>;
    const officeLng = <?= $office ? $office['longitude'] : 36.8123 ?>;
    
    // Init Map with standard OpenStreetMap (Exactly as you had it)
    const map = L.map('campus-map').setView([officeLat, officeLng], 15); 
    
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // --- ROUTING LOGIC VARIABLES ---
    let userLocation = null;
    let routingControl = null;
    let fallbackLine = null;

    // Add Locate Me Button
    L.control.locate({
        position: 'topleft',
        strings: { title: "Find my location" },
        locateOptions: { enableHighAccuracy: true }
    }).addTo(map);

    // Save the user's location when they click the Locate button
    map.on('locationfound', function(e) {
        userLocation = e.latlng;
    });

    // --- DRAW ROUTE FUNCTION ---
    function drawRouteTo(destLat, destLng) {
        if (!userLocation) {
            alert("Please click the Locate Me button (top left) first so we know where you are starting from!");
            return;
        }

        // Clear previous routes
        if (routingControl) map.removeControl(routingControl);
        if (fallbackLine) map.removeLayer(fallbackLine);

        try {
            // Attempt real routing via OSRM
            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(userLocation.lat, userLocation.lng),
                    L.latLng(destLat, destLng)
                ],
                lineOptions: {
                    styles: [{ color: '#1E40AF', weight: 5, opacity: 0.8, dashArray: '5, 10' }]
                },
                show: false, 
                addWaypoints: false,
                fitSelectedRoutes: true,
                createMarker: function() { return null; } // Don't add extra markers
            }).addTo(map);

            // The Safety Net: If OSRM fails, draw a straight line
            routingControl.on('routingerror', function(e) {
                console.warn("Routing server failed. Drawing straight line fallback.");
                map.removeControl(routingControl);
                drawStraightLine(userLocation, L.latLng(destLat, destLng));
            });

        } catch (error) {
            drawStraightLine(userLocation, L.latLng(destLat, destLng));
        }
    }

    // Fallback Function
    function drawStraightLine(start, end) {
        if (fallbackLine) map.removeLayer(fallbackLine);
        fallbackLine = L.polyline([start, end], {
            color: '#1E40AF', weight: 4, dashArray: '5, 10', opacity: 0.8
        }).addTo(map);
        map.fitBounds(fallbackLine.getBounds(), { padding: [50, 50] });
    }

    // --- ADD OFFICE PIN ---
    const officeHtml = `
        <div class="position-relative">
            <div class="office-pulse"></div>
            <div class="office-pin-wrapper"><i class="bi bi-building"></i></div>
        </div>
    `;
    const officeIcon = L.divIcon({
        className: 'bg-transparent', html: officeHtml,
        iconSize: [36, 36], iconAnchor: [18, 36], popupAnchor: [0, -36]
    });
    
    L.marker([officeLat, officeLng], {icon: officeIcon})
        .addTo(map)
        .bindPopup(`
            <div class="text-center">
                <strong class="text-primary d-block mb-1">Lost & Found Office</strong>
                <span class="small text-muted d-block mb-2">Drop off or collect items here.</span>
                <button class="btn btn-sm btn-primary fw-bold w-100 rounded-pill" onclick="drawRouteTo(${officeLat}, ${officeLng})">
                    <i class="bi bi-signpost-split"></i> Get Route
                </button>
            </div>
        `)
        .openPopup();

    // --- ADD HOTSPOT PINS ---
    const hotspots = <?= json_encode($hotspots) ?>;
    
    hotspots.forEach(function(spot) {
        let size = 30 + (spot.current_reports * 4);
        if (size > 50) size = 50;
        
        const icon = L.divIcon({
            className: 'bg-transparent',
            html: `<div class="hotspot-pin" style="width:${size}px; height:${size}px; font-size:${size/2.8}px;">${spot.current_reports}</div>`,
            iconSize: [size, size], iconAnchor: [size/2, size/2]
        });
        
        L.marker([spot.latitude, spot.longitude], {icon: icon})
            .addTo(map)
            .bindPopup(`
                <div class="text-center">
                    <strong class="d-block mb-1">${spot.location_name}</strong>
                    <span class="small text-muted d-block mb-2">${spot.current_reports} item(s) reported here.</span>
                    <button class="btn btn-sm btn-danger fw-bold w-100 rounded-pill" onclick="drawRouteTo(${spot.latitude}, ${spot.longitude})">
                        <i class="bi bi-signpost-split"></i> Get Route
                    </button>
                </div>
            `);
    });

    // Expose the function to the global scope so the popup buttons can call it
    window.drawRouteTo = drawRouteTo;
});
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>