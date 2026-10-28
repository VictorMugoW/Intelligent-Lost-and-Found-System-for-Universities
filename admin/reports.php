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
require_once '../includes/admin_layout_top.php';

$total_items = $conn->query("SELECT COUNT(*) as c FROM items")->fetch_assoc()['c'];
$total_claims = $conn->query("SELECT COUNT(*) as c FROM claims")->fetch_assoc()['c'];
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$total_returned = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'returned'")->fetch_assoc()['c'];
?>

<!-- Custom CSS specifically for the report stat cards -->
<style>
    .stat-card-pro {
        display: flex;
        align-items: center;
        padding: 1.5rem;
        border-radius: 1rem;
        color: #ffffff; /* Forces ALL text to be white */
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        transition: transform 0.2s ease;
        position: relative;
        overflow: hidden;
    }
    .stat-card-pro:hover {
        transform: translateY(-5px);
    }
    /* Adds a subtle light overlay to make the cards feel premium */
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
        color: #ffffff !important; /* Forces the numbers to be white */
        font-weight: 800;
        margin: 0;
        font-size: 2rem;
        line-height: 1;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Reports & Export</h2>
        <p class="text-muted mb-0">Download system data as CSV files for offline analysis</p>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card-pro" style="background: linear-gradient(135deg, #1E40AF, #3B82F6);">
            <div class="icon-box"><i class="bi bi-box-seam"></i></div>
            <div><h6>Total Items</h6><h3><?= $total_items ?></h3></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card-pro" style="background: linear-gradient(135deg, #0EA5E9, #0284C7);">
            <div class="icon-box"><i class="bi bi-clipboard-check"></i></div>
            <div><h6>Total Claims</h6><h3><?= $total_claims ?></h3></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <!-- Dark card - now the text is forced white via CSS -->
        <div class="stat-card-pro" style="background: linear-gradient(135deg, #0F172A, #1E293B);">
            <div class="icon-box"><i class="bi bi-people"></i></div>
            <div><h6>Total Users</h6><h3><?= $total_users ?></h3></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card-pro" style="background: linear-gradient(135deg, #10B981, #059669);">
            <div class="icon-box"><i class="bi bi-check-circle"></i></div>
            <div><h6>Returned Items</h6><h3><?= $total_returned ?></h3></div>
        </div>
    </div>
</div>

<h5 class="fw-bold mb-3">Available Reports</h5>
<div class="row g-4">
    <!-- Items Report -->
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm" style="border-radius: 1rem;">
            <div class="card-body text-center p-4">
                <div class="feature-icon mx-auto mb-3" style="background: linear-gradient(135deg, #1E40AF, #3B82F6); color: white; width: 64px; height: 64px; border-radius: 1rem; display: flex; align-items: center; justify-content: center; font-size: 1.75rem;">
                    <i class="bi bi-box-seam"></i>
                </div>
                <h5 class="fw-bold">Items Report</h5>
                <p class="text-muted small">All lost, found, and returned items with reporter, category, status, and dates.</p>
                <p class="text-muted small mb-3"><strong><?= $total_items ?></strong> records</p>
                <a href="export_csv.php?type=items" class="btn btn-primary w-100 fw-bold" style="border-radius: 0.5rem;">
                    <i class="bi bi-download me-1"></i> Download CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Claims Report -->
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm" style="border-radius: 1rem;">
            <div class="card-body text-center p-4">
                <div class="feature-icon mx-auto mb-3" style="background: linear-gradient(135deg, #0EA5E9, #0284C7); color: white; width: 64px; height: 64px; border-radius: 1rem; display: flex; align-items: center; justify-content: center; font-size: 1.75rem;">
                    <i class="bi bi-clipboard-check"></i>
                </div>
                <h5 class="fw-bold">Claims Report</h5>
                <p class="text-muted small">All claim submissions with claimant, item, status, admin decision, and reasons.</p>
                <p class="text-muted small mb-3"><strong><?= $total_claims ?></strong> records</p>
                <a href="export_csv.php?type=claims" class="btn btn-primary w-100 fw-bold" style="border-radius: 0.5rem;">
                    <i class="bi bi-download me-1"></i> Download CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Users Report -->
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm" style="border-radius: 1rem;">
            <div class="card-body text-center p-4">
                <div class="feature-icon mx-auto mb-3" style="background: linear-gradient(135deg, #0F172A, #1E293B); color: white; width: 64px; height: 64px; border-radius: 1rem; display: flex; align-items: center; justify-content: center; font-size: 1.75rem;">
                    <i class="bi bi-people"></i>
                </div>
                <h5 class="fw-bold">Users Report</h5>
                <p class="text-muted small">All registered users with role, contact info, and date registered (no passwords).</p>
                <p class="text-muted small mb-3"><strong><?= $total_users ?></strong> records</p>
                <a href="export_csv.php?type=users" class="btn btn-primary w-100 fw-bold" style="border-radius: 0.5rem;">
                    <i class="bi bi-download me-1"></i> Download CSV
                </a>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4 shadow-sm border-0" style="border-radius: 0.75rem;">
    <i class="bi bi-info-circle me-2"></i>
    <strong>About CSV exports:</strong> Files can be opened in Microsoft Excel, Google Sheets, or any spreadsheet application. Filenames include the date of export for easy tracking.
</div>

<?php 
require_once '../includes/admin_layout_bottom.php';
require_once '../includes/footer.php'; 
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>