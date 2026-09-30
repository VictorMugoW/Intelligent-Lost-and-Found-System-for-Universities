<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$full_name = $_SESSION['full_name'];
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$total_items = $conn->query("SELECT COUNT(*) as c FROM items")->fetch_assoc()['c'];
$pending_claims = $conn->query("SELECT COUNT(*) as c FROM claims WHERE status = 'pending'")->fetch_assoc()['c'];
$returned_items = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'returned'")->fetch_assoc()['c'];

$page_title = 'Admin Dashboard';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="mb-4">
        <h2 class="section-title">Admin Dashboard</h2>
        <p class="section-subtitle">System overview and management</p>
    </div>

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
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>