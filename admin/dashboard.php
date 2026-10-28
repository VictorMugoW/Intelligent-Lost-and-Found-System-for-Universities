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

// Status styling
$status_color = 'success';
$status_text = 'Available Now';
$status_icon = 'check-circle-fill';
if ($office['status'] === 'lunch') { $status_color = 'warning'; $status_text = 'On Lunch Break'; $status_icon = 'cup-hot-fill'; }
elseif ($office['status'] === 'closed') { $status_color = 'danger'; $status_text = 'Closed'; $status_icon = 'x-circle-fill'; }

$page_title = 'Admin Dashboard';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '../includes/admin_layout_top.php';
?>

<!-- Custom CSS for the Dashboard Elements -->
<style>
    /* 1. Fix for the Invisible Stats Text */
    .stat-card-pro {
        display: flex;
        align-items: center;
        padding: 1.5rem;
        border-radius: 1rem;
        color: #ffffff; 
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        transition: transform 0.2s ease;
        position: relative;
        overflow: hidden;
    }
    .stat-card-pro:hover {
        transform: translateY(-5px);
    }
    .stat-card-pro::after {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 100%);
        pointer-events: none;
    }
    .stat-card-pro .icon-box {
        width: 54px;
        height: 54px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        margin-right: 1.25rem;
        color: #ffffff;
    }
    .stat-card-pro h6 {
        color: rgba(255, 255, 255, 0.85) !important;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.8rem;
        margin-bottom: 0.25rem;
    }
    .stat-card-pro h3 {
        color: #ffffff !important; 
        font-weight: 800;
        margin: 0;
        font-size: 2rem;
        line-height: 1;
    }

    /* 2. Office Status Card Polish */
    .office-status-card {
        border-radius: 1rem;
        overflow: hidden;
        position: relative;
    }
    .office-status-icon {
        width: 64px; 
        height: 64px; 
        border-radius: 50%; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        color: white; 
        font-size: 2rem;
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }

    /* 3. Action Required Cards Hover */
    .action-card {
        transition: all 0.25s ease;
        border-radius: 1rem;
        background: #fff;
    }
    .action-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.08) !important;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Overview</h2>
        <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($full_name) ?>. Here is the current system status.</p>
    </div>
    <a href="/intelligent-lost-and-found/user/campus_map.php" target="_blank" class="btn btn-outline-secondary btn-sm fw-bold rounded-pill px-3">
        <i class="bi bi-box-arrow-up-right me-1"></i> View User Map
    </a>
</div>

<?php if ($status_message): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="bi bi-check-circle me-2"></i> <?= htmlspecialchars($status_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Office Status Toggle -->
<div class="card mb-5 border-0 shadow-sm office-status-card" style="border-left: 6px solid var(--bs-<?= $status_color ?>);">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3 p-4">
        <div class="d-flex align-items-center gap-3">
            <div class="office-status-icon bg-<?= $status_color ?>">
                <i class="bi bi-<?= $status_icon ?>"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-1" style="color: #0f172a;">Lost & Found Office: <span class="text-<?= $status_color ?>"><?= $status_text ?></span></h5>
                <p class="text-muted mb-0 fw-medium"><i class="bi bi-clock me-1"></i> Operating Hours: <?= date('g:i A', strtotime($office['opening_time'])) ?> - <?= date('g:i A', strtotime($office['closing_time'])) ?></p>
            </div>
        </div>
        <form method="POST" class="d-flex gap-2 p-2 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
            <select name="office_status" class="form-select fw-bold border-0 shadow-none" style="width: auto; background: transparent; cursor: pointer;">
                <option value="available" <?= $office['status'] === 'available' ? 'selected' : '' ?>>🟢 Open</option>
                <option value="lunch" <?= $office['status'] === 'lunch' ? 'selected' : '' ?>>🟡 Lunch</option>
                <option value="closed" <?= $office['status'] === 'closed' ? 'selected' : '' ?>>🔴 Closed</option>
            </select>
            <button type="submit" class="btn btn-dark fw-bold px-4" style="border-radius: 0.5rem;">Update</button>
        </form>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-4 mb-5">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card-pro" style="background: linear-gradient(135deg, #1E40AF, #3B82F6);">
            <div class="icon-box"><i class="bi bi-people"></i></div>
            <div><h6>Total Users</h6><h3><?= $total_users ?></h3></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card-pro" style="background: linear-gradient(135deg, #0EA5E9, #0284C7);">
            <div class="icon-box"><i class="bi bi-box-seam"></i></div>
            <div><h6>Total Items</h6><h3><?= $total_items ?></h3></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card-pro" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
            <div class="icon-box"><i class="bi bi-clock-history"></i></div>
            <div><h6>Pending Claims</h6><h3><?= $pending_claims ?></h3></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card-pro" style="background: linear-gradient(135deg, #10B981, #059669);">
            <div class="icon-box"><i class="bi bi-check-circle"></i></div>
            <div><h6>Returned Items</h6><h3><?= $returned_items ?></h3></div>
        </div>
    </div>
</div>

<!-- Action Required Section -->
<h5 class="fw-bold mb-3 text-dark"><i class="bi bi-exclamation-circle text-warning me-2"></i>Action Required</h5>
<div class="row g-4">
    <div class="col-md-6">
        <a href="verify_found_items.php" class="card border-0 shadow-sm text-decoration-none h-100 action-card" style="border-left: 5px solid #F59E0B;">
            <div class="card-body d-flex align-items-center p-4">
                <div style="width: 56px; height: 56px; border-radius: 1rem; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-right: 1.25rem;">
                    <i class="bi bi-shield-exclamation"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-dark mb-1">Verify Found Items</h5>
                    <p class="text-muted mb-0 small">
                        <?php if($pending_verifications > 0): ?>
                            <span class="text-danger fw-bold"><?= $pending_verifications ?> items</span> are waiting for you to confirm they are at the office.
                        <?php else: ?>
                            No items are currently awaiting verification.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-md-6">
        <a href="manage_claims.php" class="card border-0 shadow-sm text-decoration-none h-100 action-card" style="border-left: 5px solid #3B82F6;">
            <div class="card-body d-flex align-items-center p-4">
                <div style="width: 56px; height: 56px; border-radius: 1rem; background: #DBEAFE; color: #2563EB; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-right: 1.25rem;">
                    <i class="bi bi-clipboard-check"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-dark mb-1">Manage Student Claims</h5>
                    <p class="text-muted mb-0 small">
                        <?php if($pending_claims > 0): ?>
                            <span class="text-primary fw-bold"><?= $pending_claims ?> claims</span> are pending your approval.
                        <?php else: ?>
                            All claims have been reviewed. Good job!
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </a>
    </div>
</div>

<?php 
require_once '../includes/admin_layout_bottom.php';
require_once '../includes/footer.php'; 
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>