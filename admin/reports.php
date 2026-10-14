<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = 'Reports';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Quick stats for preview
$total_items = $conn->query("SELECT COUNT(*) as c FROM items")->fetch_assoc()['c'];
$total_claims = $conn->query("SELECT COUNT(*) as c FROM claims")->fetch_assoc()['c'];
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$total_returned = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'returned'")->fetch_assoc()['c'];
?>

<div class="container mt-5">
    <div class="mb-4">
        <h2 class="section-title">Reports & Export</h2>
        <p class="section-subtitle">Download system data as CSV files for offline analysis</p>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="stat-card stat-blue">
                <h6><i class="bi bi-box-seam"></i> Total Items</h6>
                <h2><?= $total_items ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-accent">
                <h6><i class="bi bi-clipboard-check"></i> Total Claims</h6>
                <h2><?= $total_claims ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-dark">
                <h6><i class="bi bi-people"></i> Total Users</h6>
                <h2><?= $total_users ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-success">
                <h6><i class="bi bi-check-circle"></i> Returned Items</h6>
                <h2><?= $total_returned ?></h2>
            </div>
        </div>
    </div>

    <h4 class="fw-bold mb-3">Available Reports</h4>
    <div class="row g-4">
        <!-- Items Report -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center p-4">
                    <div class="feature-icon mx-auto mb-3" style="background: linear-gradient(135deg, #1E40AF, #3B82F6);">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <h5 class="fw-bold">Items Report</h5>
                    <p class="text-muted small">All lost, found, and returned items with reporter, category, status, and dates.</p>
                    <p class="text-muted small mb-3"><strong><?= $total_items ?></strong> records</p>
                    <a href="export_csv.php?type=items" class="btn btn-primary w-100">
                        <i class="bi bi-download"></i> Download CSV
                    </a>
                </div>
            </div>
        </div>

        <!-- Claims Report -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center p-4">
                    <div class="feature-icon mx-auto mb-3" style="background: linear-gradient(135deg, #0EA5E9, #0284C7);">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <h5 class="fw-bold">Claims Report</h5>
                    <p class="text-muted small">All claim submissions with claimant, item, status, admin decision, and reasons.</p>
                    <p class="text-muted small mb-3"><strong><?= $total_claims ?></strong> records</p>
                    <a href="export_csv.php?type=claims" class="btn btn-primary w-100">
                        <i class="bi bi-download"></i> Download CSV
                    </a>
                </div>
            </div>
        </div>

        <!-- Users Report -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center p-4">
                    <div class="feature-icon mx-auto mb-3" style="background: linear-gradient(135deg, #0F172A, #1E293B);">
                        <i class="bi bi-people"></i>
                    </div>
                    <h5 class="fw-bold">Users Report</h5>
                    <p class="text-muted small">All registered users with role, contact info, and date registered (no passwords).</p>
                    <p class="text-muted small mb-3"><strong><?= $total_users ?></strong> records</p>
                    <a href="export_csv.php?type=users" class="btn btn-primary w-100">
                        <i class="bi bi-download"></i> Download CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-4">
        <i class="bi bi-info-circle"></i>
        <strong>About CSV exports:</strong> Files can be opened in Microsoft Excel, Google Sheets, or any spreadsheet application. Filenames include the date of export for easy tracking.
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>