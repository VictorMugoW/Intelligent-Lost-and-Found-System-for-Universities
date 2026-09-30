<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// === STAT CARDS ===
$total_items = $conn->query("SELECT COUNT(*) as c FROM items")->fetch_assoc()['c'];
$returned_items = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'returned'")->fetch_assoc()['c'];
$pending_claims = $conn->query("SELECT COUNT(*) as c FROM claims WHERE status = 'pending'")->fetch_assoc()['c'];
$recovery_rate = ($total_items > 0) ? round(($returned_items / $total_items) * 100, 1) : 0;

// === DOUGHNUT: Items by Status ===
$status_data = ['lost' => 0, 'found' => 0, 'returned' => 0];
$res = $conn->query("SELECT status, COUNT(*) as c FROM items GROUP BY status");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $status_data[$row['status']] = (int)$row['c'];
    }
}

// === BAR: Top 5 Lost Categories ===
$cat_labels = [];
$cat_values = [];
$res = $conn->query("SELECT category, COUNT(*) as c FROM items WHERE type = 'lost' GROUP BY category ORDER BY c DESC LIMIT 5");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $cat_labels[] = $row['category'];
        $cat_values[] = (int)$row['c'];
    }
}

// === BAR: Top 5 Hotspot Locations ===
$loc_labels = [];
$loc_values = [];
$res = $conn->query("SELECT location, COUNT(*) as c FROM items WHERE location IS NOT NULL AND location != '' GROUP BY location ORDER BY c DESC LIMIT 5");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $loc_labels[] = $row['location'];
        $loc_values[] = (int)$row['c'];
    }
}

// === LINE: Monthly Reports (Last 6 Months) ===
$month_labels = [];
$month_values = [];
for ($i = 5; $i >= 0; $i--) {
    $month_labels[] = date('M Y', strtotime("-$i months"));
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM items WHERE created_at BETWEEN ? AND ?");
    $stmt->bind_param("ss", $month_start, $month_end);
    $stmt->execute();
    $month_values[] = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
}

$page_title = 'Analytics';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="mb-4">
        <h2 class="section-title">Analytics Dashboard</h2>
        <p class="section-subtitle">Real-time insights on lost and found activity</p>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="stat-card stat-dark">
                <h6><i class="bi bi-box-seam"></i> Total Items</h6>
                <h2><?= $total_items ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-success">
                <h6><i class="bi bi-trophy"></i> Recovery Rate</h6>
                <h2><?= $recovery_rate ?>%</h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-blue">
                <h6><i class="bi bi-clock-history"></i> Pending Claims</h6>
                <h2><?= $pending_claims ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-accent">
                <h6><i class="bi bi-check-circle"></i> Returned Items</h6>
                <h2><?= $returned_items ?></h2>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3"><i class="bi bi-pie-chart"></i> Items by Status</h5>
                    <canvas id="statusChart" style="max-height:300px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3"><i class="bi bi-bar-chart"></i> Top 5 Lost Categories</h5>
                    <canvas id="categoryChart" style="max-height:300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3"><i class="bi bi-geo-alt"></i> Top 5 Hotspot Locations</h5>
                    <canvas id="locationChart" style="max-height:300px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3"><i class="bi bi-graph-up"></i> Monthly Reports (Last 6 Months)</h5>
                    <canvas id="monthlyChart" style="max-height:300px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Lost', 'Found', 'Returned'],
            datasets: [{
                data: [<?= $status_data['lost'] ?>, <?= $status_data['found'] ?>, <?= $status_data['returned'] ?>],
                backgroundColor: ['#EF4444', '#10B981', '#1E40AF'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('categoryChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($cat_labels) ?>,
            datasets: [{ label: 'Lost Items', data: <?= json_encode($cat_values) ?>, backgroundColor: '#EF4444', borderRadius: 8 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });

    new Chart(document.getElementById('locationChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($loc_labels) ?>,
            datasets: [{ label: 'Reports', data: <?= json_encode($loc_values) ?>, backgroundColor: '#0EA5E9', borderRadius: 8 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });

    new Chart(document.getElementById('monthlyChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($month_labels) ?>,
            datasets: [{
                label: 'Reports',
                data: <?= json_encode($month_values) ?>,
                borderColor: '#1E40AF',
                backgroundColor: 'rgba(30, 64, 175, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#1E40AF',
                pointRadius: 5
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>