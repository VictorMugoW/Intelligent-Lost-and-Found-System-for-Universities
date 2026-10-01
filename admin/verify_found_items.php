<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'], $_POST['action'])) {
    $item_id = intval($_POST['item_id']);
    $action = $_POST['action'];

    if ($action === 'verify') {
        $stmt = $conn->prepare("UPDATE items SET status = 'found', verified_by = ?, verified_at = NOW() WHERE item_id = ? AND status = 'pending_verification'");
        $stmt->bind_param("ii", $admin_id, $item_id);
        $stmt->execute();
        $stmt->close();
        $message = "Item verified and published. It is now visible in the Found tab.";
        $message_type = "success";
    } elseif ($action === 'reject') {
        $reason = trim($_POST['reject_reason'] ?? '');
        $stmt = $conn->prepare("UPDATE items SET status = 'lost', finder_id = NULL, found_location = NULL, found_date = NULL WHERE item_id = ? AND status = 'pending_verification'");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $stmt->close();
        $message = "Verification rejected. Item returned to Lost status." . (!empty($reason) ? " Reason: $reason" : "");
        $message_type = "warning";
    }
}

$pending = $conn->query("
    SELECT i.*, 
           reporter.full_name AS reporter_name, reporter.email AS reporter_email,
           finder.full_name AS finder_name, finder.email AS finder_email, finder.phone AS finder_phone
    FROM items i
    JOIN users reporter ON i.user_id = reporter.user_id
    LEFT JOIN users finder ON i.finder_id = finder.user_id
    WHERE i.status = 'pending_verification'
    ORDER BY i.created_at DESC
");

$page_title = 'Verify Found Items';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="mb-4">
        <h2 class="section-title">Pending Verifications</h2>
        <p class="section-subtitle">Items marked as found — awaiting physical verification at the office</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($pending->num_rows === 0): ?>
        <div class="card">
            <div class="card-body text-center p-5">
                <div class="feature-icon mx-auto mb-3" style="background: linear-gradient(135deg, #10B981, #059669);"><i class="bi bi-shield-check"></i></div>
                <h5 class="fw-bold">All clear!</h5>
                <p class="text-muted mb-0">No items are currently awaiting verification.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php while ($item = $pending->fetch_assoc()): ?>
                <div class="col-md-6">
                    <div class="card h-100" style="border: 2px solid #F59E0B;">
                        <div class="card-header" style="background: linear-gradient(135deg, #F59E0B, #D97706); color: white;">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong><i class="bi bi-hourglass-split"></i> Pending Verification</strong>
                                <small>Item #<?= $item['item_id'] ?></small>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($item['image_path'])): ?>
                                <img src="../<?= htmlspecialchars($item['image_path']) ?>" class="img-fluid rounded mb-3" style="max-height:220px; object-fit:cover; width:100%;">
                            <?php endif; ?>

                            <h5 class="fw-bold mb-2"><?= htmlspecialchars($item['item_name']) ?></h5>
                            <p class="small mb-1"><strong>Category:</strong> <?= htmlspecialchars($item['category']) ?></p>
                            <p class="small mb-1"><strong>Colour:</strong> <?= htmlspecialchars($item['colour'] ?: 'N/A') ?></p>
                            <p class="small mb-1"><strong>Original Location:</strong> <?= htmlspecialchars($item['location'] ?: 'N/A') ?></p>
                            <p class="small mb-3"><strong>Description:</strong> <?= htmlspecialchars($item['description']) ?></p>

                            <hr>

                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="fw-bold small text-uppercase" style="color: #EF4444;">
                                        <i class="bi bi-person-exclamation"></i> Reported Lost By
                                    </h6>
                                    <p class="small mb-0"><?= htmlspecialchars($item['reporter_name']) ?></p>
                                    <p class="small text-muted mb-0"><?= htmlspecialchars($item['reporter_email']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold small text-uppercase" style="color: #10B981;">
                                        <i class="bi bi-person-check"></i> Found By
                                    </h6>
                                    <p class="small mb-0"><?= htmlspecialchars($item['finder_name'] ?? 'Unknown') ?></p>
                                    <p class="small text-muted mb-0"><?= htmlspecialchars($item['finder_email'] ?? '') ?></p>
                                    <p class="small text-muted mb-0"><?= htmlspecialchars($item['finder_phone'] ?? '') ?></p>
                                </div>
                            </div>

                            <div class="alert alert-info small mt-3 mb-3">
                                <strong><i class="bi bi-geo-alt"></i> Found at:</strong> <?= htmlspecialchars($item['found_location'] ?: 'N/A') ?><br>
                                <strong><i class="bi bi-calendar"></i> Found on:</strong> <?= htmlspecialchars($item['found_date'] ?: 'N/A') ?>
                            </div>

                            <form method="POST" class="mb-2">
                                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                <button type="submit" name="action" value="verify" class="btn btn-success w-100">
                                    <i class="bi bi-shield-check"></i> Verify — Item Received at Office
                                </button>
                            </form>

                            <form method="POST">
                                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Rejection reason (optional):</label>
                                    <input type="text" name="reject_reason" class="form-control form-control-sm" placeholder="e.g. Item never deposited at office">
                                </div>
                                <button type="submit" name="action" value="reject" class="btn btn-outline-danger w-100 btn-sm">
                                    <i class="bi bi-x-circle"></i> Reject — Item Not Received
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>