<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Stat cards
$total_items = $conn->query("SELECT COUNT(*) as c FROM items")->fetch_assoc()['c'];
$returned_items = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'returned'")->fetch_assoc()['c'];
$pending_claims = $conn->query("SELECT COUNT(*) as c FROM claims WHERE status = 'pending'")->fetch_assoc()['c'];
$recovery_rate = ($total_items > 0) ? round(($returned_items / $total_items) * 100, 1) : 0;

// Doughnut data
$status_data = ['lost' => 0, 'found' => 0, 'returned' => 0];
$res = $conn->query("SELECT status, COUNT(*) as c FROM items GROUP BY status");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if (isset($status_data[$row['status']])) {
            $status_data[$row['status']] = (int)$row['c'];
        }
    }
}

// Bar: Top categories
$cat_labels = [];
$cat_values = [];
$res = $conn->query("SELECT category, COUNT(*) as c FROM items WHERE type = 'lost' GROUP BY category ORDER BY c DESC LIMIT 5");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $cat_labels[] = $row['category'];
        $cat_values[] = (int)$row['c'];
    }
}

// Bar: Hotspots
$loc_labels = [];
$loc_values = [];
$res = $conn->query("SELECT location, COUNT(*) as c FROM items WHERE location IS NOT NULL AND location != '' GROUP BY location ORDER BY c DESC LIMIT 5");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $loc_labels[] = $row['location'];
        $loc_values[] = (int)$row['c'];
    }
}

// Line: Monthly
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

// Prepare JSON for Charts
$json_status = json_encode([$status_data['lost'], $status_data['found'], $status_data['returned']]);
$json_cat_labels = json_encode($cat_labels);
$json_cat_values = json_encode($cat_values);
$json_loc_labels = json_encode($loc_labels);
$json_loc_values = json_encode($loc_values);
$json_month_labels = json_encode($month_labels);
$json_month_values = json_encode($month_values);

$page_title = 'Analytics';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '../includes/admin_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1" style="color: #0f172a;">Analytics Dashboard</h2>
        <p class="text-muted mb-0">Real-time insights on lost and found activity.</p>
    </div>
    <div>
        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill fw-bold">
            <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> Live Data
        </span>
    </div>
</div>

<!-- New Premium Stats Row -->
<div class="row g-4 mb-5">
    <!-- Total Items -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card-premium" style="background: linear-gradient(135deg, #1E40AF, #3B82F6);">
            <div class="label">Total Items</div>
            <div class="value"><?= $total_items ?></div>
            <i class="bi bi-box-seam icon-wrapper"></i>
        </div>
    </div>
    <!-- Recovery Rate -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card-premium" style="background: linear-gradient(135deg, #10B981, #059669);">
            <div class="label">Recovery Rate</div>
            <div class="value"><?= $recovery_rate ?>%</div>
            <i class="bi bi-trophy icon-wrapper"></i>
        </div>
    </div>
    <!-- Pending Claims -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card-premium" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
            <div class="label">Pending Claims</div>
            <div class="value"><?= $pending_claims ?></div>
            <i class="bi bi-clock-history icon-wrapper"></i>
        </div>
    </div>
    <!-- Returned Items -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card-premium" style="background: linear-gradient(135deg, #0EA5E9, #0284C7);">
            <div class="label">Returned Items</div>
            <div class="value"><?= $returned_items ?></div>
            <i class="bi bi-check-circle icon-wrapper"></i>
        </div>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="chart-widget">
            <div class="chart-header">
                <h5 class="chart-title"><i class="bi bi-pie-chart text-primary me-2"></i> Items by Status</h5>
            </div>
            <div class="chart-body">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-widget">
            <div class="chart-header">
                <h5 class="chart-title"><i class="bi bi-bar-chart-fill text-danger me-2"></i> Top 5 Lost Categories</h5>
            </div>
            <div class="chart-body">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="chart-widget">
            <div class="chart-header">
                <h5 class="chart-title"><i class="bi bi-geo-alt-fill text-info me-2"></i> Top 5 Hotspot Locations</h5>
            </div>
            <div class="chart-body">
                <canvas id="locationChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-widget">
            <div class="chart-header">
                <h5 class="chart-title"><i class="bi bi-graph-up-arrow text-success me-2"></i> Monthly Reports (Last 6 Months)</h5>
            </div>
            <div class="chart-body">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    Chart.defaults.color = '#64748b';
    
    // 1. Doughnut Chart
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: { 
            labels: ['Lost', 'Found', 'Returned'], 
            datasets: [{ 
                data: <?= $json_status ?>, 
                backgroundColor: ['#EF4444', '#10B981', '#3B82F6'],
                borderWidth: 3,
                borderColor: '#ffffff',
                hoverOffset: 8
            }] 
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            cutout: '75%',
            plugins: { 
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 25, font: { weight: '600', size: 13 } } } 
            } 
        }
    });

    // 2. Bar Chart (Categories) - Fixed Labels
    new Chart(document.getElementById('categoryChart'), {
        type: 'bar',
        data: { 
            labels: <?= $json_cat_labels ?>, 
            datasets: [{ 
                label: 'Lost Items', 
                data: <?= $json_cat_values ?>, 
                backgroundColor: 'rgba(239, 68, 68, 0.9)', 
                borderRadius: 6,
                barThickness: 35
            }] 
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            plugins: { legend: { display: false } }, 
            scales: { 
                y: { 
                    beginAtZero: true, 
                    ticks: { stepSize: 1 }, 
                    grid: { borderDash: [4, 4], color: '#f1f5f9', drawBorder: false } 
                },
                x: { 
                    grid: { display: false },
                    ticks: { 
                        font: { size: 12, weight: '600' },
                        maxRotation: 0, // Forces horizontal text
                        minRotation: 0
                    }
                }
            } 
        }
    });

    // 3. Bar Chart (Hotspots) - Fixed Zigzag Labels
    new Chart(document.getElementById('locationChart'), {
        type: 'bar',
        data: { 
            labels: <?= $json_loc_labels ?>, 
            datasets: [{ 
                label: 'Reports', 
                data: <?= $json_loc_values ?>, 
                backgroundColor: 'rgba(14, 165, 233, 0.9)', 
                borderRadius: 6,
                barThickness: 35
            }] 
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            plugins: { legend: { display: false } }, 
            scales: { 
                y: { 
                    beginAtZero: true, 
                    ticks: { stepSize: 1 }, 
                    grid: { borderDash: [4, 4], color: '#f1f5f9', drawBorder: false } 
                },
                x: { 
                    grid: { display: false },
                    ticks: { 
                        font: { size: 11, weight: '600' }, // Slightly smaller font for long locations
                        maxRotation: 0,  // FORCES TEXT TO BE HORIZONTAL (Fixes the zigzag!)
                        minRotation: 0,
                        autoSkip: false // Forces all 5 labels to show
                    }
                }
            } 
        }
    });

    // 4. Line Chart (Monthly)
    new Chart(document.getElementById('monthlyChart'), {
        type: 'line',
        data: { 
            labels: <?= $json_month_labels ?>, 
            datasets: [{ 
                label: 'Reports', 
                data: <?= $json_month_values ?>, 
                borderColor: '#10B981', 
                backgroundColor: 'rgba(16, 185, 129, 0.1)', 
                borderWidth: 3, 
                fill: true, 
                tension: 0.4, // Smooth curved line
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#10B981',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }] 
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            plugins: { legend: { display: false } }, 
            scales: { 
                y: { 
                    beginAtZero: true, 
                    ticks: { stepSize: 1 }, 
                    grid: { borderDash: [4, 4], color: '#f1f5f9', drawBorder: false } 
                },
                x: { 
                    grid: { display: false } 
                }
            } 
        }
    });
});
</script>

<?php 
require_once '../includes/admin_layout_bottom.php';
require_once '../includes/footer.php'; 
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>