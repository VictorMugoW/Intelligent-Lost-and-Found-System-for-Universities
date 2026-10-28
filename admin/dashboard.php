<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Handle office status update
$status_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['office_status'])) {
    $new_status = $_POST['office_status'];
    if (in_array($new_status, ['available', 'lunch', 'closed'])) {
        $stmt = $conn->prepare("UPDATE office_settings SET status = ? WHERE id = 1");
        $stmt->bind_param("s", $new_status);
        $stmt->execute();
        $stmt->close();
        $status_message = "Office status updated successfully.";
    }
}

// Fetch current office status
$office = $conn->query("SELECT status, opening_time, closing_time FROM office_settings WHERE id = 1")->fetch_assoc();
if (!$office) {
    $office = ['status' => 'closed', 'opening_time' => '08:00:00', 'closing_time' => '17:00:00'];
}

$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$total_items = $conn->query("SELECT COUNT(*) as c FROM items")->fetch_assoc()['c'];
$pending_claims = $conn->query("SELECT COUNT(*) as c FROM claims WHERE status = 'pending'")->fetch_assoc()['c'];
$returned_items = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'returned'")->fetch_assoc()['c'];
$pending_verifications = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'pending_verification'")->fetch_assoc()['c'];

// Status styling helpers
$status_color = 'success';
$status_text = 'Available Now';
$status_icon = 'check-circle-fill';
if ($office['status'] === 'lunch') {
    $status_color = 'warning';
    $status_text = 'On Lunch Break';
    $status_icon = 'cup-hot-fill';
} elseif ($office['status'] === 'closed') {
    $status_color = 'danger';
    $status_text = 'Closed';
    $status_icon = 'x-circle-fill';
}

$page_title = 'Admin Dashboard';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="mb-4">
        <h2 class="section-title">Admin Dashboard</h2>
        <p class="section-subtitle">System overview and management</p>
    </div>

    <?php if ($status_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($status_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Office Status Control -->
    <div class="card mb-4 shadow-sm" style="border-left: 5px solid var(--bs-<?= $status_color ?>);">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: var(--bs-<?= $status_color ?>); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.8rem;">
                    <i class="bi bi-<?= $status_icon ?>"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Lost & Found Office Status</h5>
                    <p class="text-muted mb-0">
                        Currently: <strong class="text-<?= $status_color ?>"><?= $status_text ?></strong>
                        <span class="mx-2">|</span>
                        Hours: <?= date('g:i A', strtotime($office['opening_time'])) ?> - <?= date('g:i A', strtotime($office['closing_time'])) ?>
                    </p>
                </div>
            </div>
            <form method="POST" class="d-flex gap-2">
                <select name="office_status" class="form-select" style="width: auto;">
                    <option value="available" <?= $office['status'] === 'available' ? 'selected' : '' ?>>🟢 Available Now</option>
                    <option value="lunch" <?= $office['status'] === 'lunch' ? 'selected' : '' ?>>🟡 On Lunch Break</option>
                    <option value="closed" <?= $office['status'] === 'closed' ? 'selected' : '' ?>>🔴 Closed</option>
                </select>
                <button type="submit" class="btn btn-primary">Update</button>
            </form>
        </div>
    </div>

    <?php if ($pending_verifications > 0): ?>
        <div class="alert alert-warning d-flex justify-content-between align-items-center">
            <div>
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong><?= $pending_verifications ?> item(s)</strong> awaiting verification.
            </div>
            <a href="verify_found_items.php" class="btn btn-warning btn-sm">Review Now</a>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-3">
            <div class="stat-card stat-dark">
                <h6><i class="bi bi-people"></i> Total Users</h6>
                <h2><?= $total_users ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-blue">
                <h6><i class="bi bi-box-seam"></i> Total Items</h6>
                <h2><?= $total_items ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-accent">
                <h6><i class="bi bi-clock-history"></i> Pending Claims</h6>
                <h2><?= $pending_claims ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-success">
                <h6><i class="bi bi-check-circle"></i> Returned</h6>
                <h2><?= $returned_items ?></h2>
            </div>
        </div>
    </div>

    <div class="mt-5">
        <h4 class="fw-bold mb-3">Management</h4>
        <div class="row g-4">
            <div class="col-md-4">
                <a href="verify_found_items.php" class="text-decoration-none">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: linear-gradient(135deg, #F59E0B, #D97706); position: relative;">
                            <i class="bi bi-shield-check"></i>
                            <?php if ($pending_verifications > 0): ?>
                                <span class="badge bg-danger" style="position: absolute; top: -8px; right: -8px;"><?= $pending_verifications ?></span>
                            <?php endif; ?>
                        </div>
                        <h5>Verify Found Items</h5>
                        <p>Confirm items reported as found are physically at the office.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="manage_claims.php" class="text-decoration-none">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-clipboard-check"></i></div>
                        <h5>Manage Claims</h5>
                        <p>Review, approve, or reject pending claims.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="verify_qr.php" class="text-decoration-none">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-qr-code-scan"></i></div>
                        <h5>Verify QR Code</h5>
                        <p>Verify a claimant's QR code at pickup.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="analytics.php" class="text-decoration-none">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-bar-chart-line"></i></div>
                        <h5>Analytics</h5>
                        <p>View recovery rates, hotspots, and trends.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="reports.php" class="text-decoration-none">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: linear-gradient(135deg, #0EA5E9, #0284C7);"><i class="bi bi-file-earmark-arrow-down"></i></div>
                        <h5>Reports</h5>
                        <p>Export system data as CSV for offline analysis.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="campus_map_editor.php" class="text-decoration-none">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: linear-gradient(135deg, #10B981, #059669);"><i class="bi bi-map"></i></div>
                        <h5>Campus Map Editor</h5>
                        <p>Set office GPS location and map out loss hotspots.</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>