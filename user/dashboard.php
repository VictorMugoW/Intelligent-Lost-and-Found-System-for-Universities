<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];

$total_items = $conn->query("SELECT COUNT(*) as c FROM items")->fetch_assoc()['c'];

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM items WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_items = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM claims WHERE claimed_by = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_claims = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$page_title = 'Dashboard';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="mb-4">
        <h2 class="section-title">Welcome back, <?= htmlspecialchars($full_name) ?> 👋</h2>
        <p class="section-subtitle">Here's an overview of your lost and found activity</p>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="stat-card stat-blue">
                <h6><i class="bi bi-box-seam"></i> Total Items</h6>
                <h2><?= $total_items ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card stat-dark">
                <h6><i class="bi bi-file-earmark-text"></i> My Reports</h6>
                <h2><?= $my_items ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card stat-accent">
                <h6><i class="bi bi-clipboard-check"></i> My Claims</h6>
                <h2><?= $my_claims ?></h2>
            </div>
        </div>
    </div>

    <div class="mt-5">
        <h4 class="fw-bold mb-3">Quick Actions</h4>
        <div class="row g-4">
            <div class="col-md-4">
                <a href="report_item.php?type=lost" class="text-decoration-none">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: linear-gradient(135deg, #EF4444, #DC2626);"><i class="bi bi-exclamation-circle"></i></div>
                        <h5>Report Lost Item</h5>
                        <p>Report an item that you have lost.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="browse_items.php" class="text-decoration-none">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-search"></i></div>
                        <h5>Browse &amp; Claim</h5>
                        <p>Found something? Mark it as found. Lost something? Claim it.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="my_claims.php" class="text-decoration-none">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-tags"></i></div>
                        <h5>My Claims</h5>
                        <p>Track the status of claims you've submitted.</p>
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