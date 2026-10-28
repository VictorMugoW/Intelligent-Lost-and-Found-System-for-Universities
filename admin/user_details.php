<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$target_user_id = intval($_GET['id'] ?? 0);
if ($target_user_id <= 0) {
    header('Location: manage_users.php');
    exit;
}

// Fetch User Info
$user = $conn->query("SELECT * FROM users WHERE user_id = $target_user_id")->fetch_assoc();
if (!$user) {
    header('Location: manage_users.php');
    exit;
}

// Fetch Stats
$lost_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE user_id = $target_user_id AND type = 'lost'")->fetch_assoc()['c'];
$claims_count = $conn->query("SELECT COUNT(*) as c FROM claims WHERE claimed_by = $target_user_id")->fetch_assoc()['c'];
$returned_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE finder_id = $target_user_id AND status = 'returned'")->fetch_assoc()['c'];

// Fetch Lists
$lost_items = $conn->query("SELECT * FROM items WHERE user_id = $target_user_id AND type = 'lost' ORDER BY created_at DESC");
$found_items = $conn->query("SELECT * FROM items WHERE finder_id = $target_user_id AND status = 'returned' ORDER BY created_at DESC");
$user_claims = $conn->query("SELECT c.*, i.item_name FROM claims c JOIN items i ON c.item_id = i.item_id WHERE c.claimed_by = $target_user_id ORDER BY c.created_at DESC");

// Badge Logic
$badge_name = 'Newbie';
$badge_icon = 'person-circle';
$badge_color = 'secondary';
if ($returned_count >= 6) {
    $badge_name = 'Gold Guardian'; $badge_icon = 'award-fill'; $badge_color = 'warning';
} elseif ($returned_count >= 3) {
    $badge_name = 'Silver Finder'; $badge_icon = 'award-fill'; $badge_color = 'light';
} elseif ($returned_count >= 1) {
    $badge_name = 'Bronze Finder'; $badge_icon = 'award-fill'; $badge_color = 'light';
}

$page_title = 'User Details';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5 mb-5">
    <div class="mb-4">
        <a href="manage_users.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i> Back to Users</a>
    </div>

    <!-- User Header Profile -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px; overflow: hidden;">
        <div class="card-body p-4" style="background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 100%); color: white;">
            <div class="d-flex align-items-center gap-4 flex-wrap">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold;">
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <div class="flex-grow-1">
                    <h3 class="mb-1"><?= htmlspecialchars($user['full_name']) ?></h3>
                    <p class="mb-2 opacity-75">
                        <i class="bi bi-envelope"></i> <?= htmlspecialchars($user['email']) ?> | 
                        <i class="bi bi-telephone"></i> <?= htmlspecialchars($user['phone'] ?: 'N/A') ?>
                    </p>
                    <span class="badge bg-<?= $badge_color ?> <?= $badge_color === 'light' ? 'text-dark' : '' ?> px-3 py-2">
                        <i class="bi bi-<?= $badge_icon ?>"></i> <?= $badge_name ?>
                    </span>
                </div>
                <div class="text-end">
                    <h6 class="opacity-75 mb-0">Role</div>
                    <h3 class="mb-0"><?= ucfirst($user['role']) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center p-3">
                <h2 class="fw-bold text-danger mb-0"><?= $lost_count ?></h2>
                <p class="text-muted mb-0">Lost Items Reported</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center p-3">
                <h2 class="fw-bold text-primary mb-0"><?= $claims_count ?></h2>
                <p class="text-muted mb-0">Claims Submitted</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center p-3">
                <h2 class="fw-bold text-success mb-0"><?= $returned_count ?></h2>
                <p class="text-muted mb-0">Items Returned to Others</p>
            </div>
        </div>
    </div>

    <!-- Activity Tabs -->
    <ul class="nav nav-tabs mb-3" id="userTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#lost">Lost Reports (<?= $lost_count ?>)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#found">Items Found (<?= $returned_count ?>)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#claims">Claims (<?= $claims_count ?>)</button>
        </li>
    </ul>

    <div class="tab-content" id="userTabsContent">
        <!-- Lost Items -->
        <div class="tab-pane fade show active" id="lost">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if ($lost_items->num_rows === 0): ?>
                        <p class="p-4 text-muted mb-0">No lost items reported.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php while($i = $lost_items->fetch_assoc()): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                                    <div>
                                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($i['item_name']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($i['location']) ?> | <?= date('M d, Y', strtotime($i['created_at'])) ?></small>
                                    </div>
                                    <span class="badge bg-<?= $i['status'] === 'returned' ? 'success' : 'danger' ?>"><?= ucfirst($i['status']) ?></span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Found Items (Returned) -->
        <div class="tab-pane fade" id="found">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if ($found_items->num_rows === 0): ?>
                        <p class="p-4 text-muted mb-0">No items found yet. This user hasn't returned any items.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php while($i = $found_items->fetch_assoc()): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                                    <div>
                                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($i['item_name']) ?></h6>
                                        <small class="text-muted">Found at <?= htmlspecialchars($i['found_location']) ?> | <?= date('M d, Y', strtotime($i['found_date'])) ?></small>
                                    </div>
                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> Returned</span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Claims -->
        <div class="tab-pane fade" id="claims">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if ($user_claims->num_rows === 0): ?>
                        <p class="p-4 text-muted mb-0">No claims submitted.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php while($c = $user_claims->fetch_assoc()): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                                    <div>
                                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($c['item_name']) ?></h6>
                                        <small class="text-muted">Reason: <?= htmlspecialchars($c['claim_reason']) ?></small>
                                    </div>
                                    <span class="badge bg-<?= $c['status'] === 'approved' ? 'success' : ($c['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= ucfirst($c['status']) ?></span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>