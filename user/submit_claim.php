<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$item_id = intval($_GET['item_id'] ?? 0);

if ($item_id <= 0) {
    header('Location: browse_items.php');
    exit;
}

$stmt = $conn->prepare("SELECT i.*, u.full_name AS reporter FROM items i JOIN users u ON i.user_id = u.user_id WHERE i.item_id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    header('Location: browse_items.php');
    exit;
}

$message = '';
$message_type = '';

$check = $conn->prepare("SELECT claim_id FROM claims WHERE item_id = ? AND claimed_by = ? AND status != 'rejected'");
$check->bind_param("ii", $item_id, $user_id);
$check->execute();
$already_claimed = $check->get_result()->num_rows > 0;
$check->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_claimed) {
    $claim_reason = trim($_POST['claim_reason']);
    if (empty($claim_reason)) {
        $message = 'Please provide a reason.';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO claims (item_id, claimed_by, claim_reason, status) VALUES (?, ?, ?, 'pending')");
        $stmt->bind_param("iis", $item_id, $user_id, $claim_reason);
        if ($stmt->execute()) {
            $message = 'Claim submitted successfully! Awaiting admin approval.';
            $message_type = 'success';
            $already_claimed = true;
        } else {
            $message = 'Failed to submit claim.';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

$page_title = 'Submit Claim';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="feature-icon mx-auto mb-3"><i class="bi bi-hand-index"></i></div>
                        <h3 class="fw-bold">Submit a Claim</h3>
                        <p class="text-muted">Verify that this item belongs to you</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <div class="card bg-light mb-4" style="box-shadow: none;">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Item Details</h5>
                            <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($item['item_name']) ?></p>
                            <p class="mb-1"><strong>Category:</strong> <?= htmlspecialchars($item['category']) ?></p>
                            <p class="mb-1"><strong>Colour:</strong> <?= htmlspecialchars($item['colour'] ?: 'N/A') ?></p>
                            <p class="mb-1"><strong>Location:</strong> <?= htmlspecialchars($item['location'] ?: 'N/A') ?></p>
                            <p class="mb-1"><strong>Date:</strong> <?= htmlspecialchars($item['date_lost_found'] ?: 'N/A') ?></p>
                            <p class="mb-0"><strong>Reported by:</strong> <?= htmlspecialchars($item['reporter']) ?></p>
                        </div>
                    </div>

                    <?php if ($already_claimed): ?>
                        <div class="alert alert-info">You have already submitted a claim for this item.</div>
                        <a href="browse_items.php" class="btn btn-primary">Back to Browse</a>
                    <?php elseif ($item['status'] === 'returned'): ?>
                        <div class="alert alert-secondary">This item has already been returned.</div>
                        <a href="browse_items.php" class="btn btn-primary">Back to Browse</a>
                    <?php else: ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Why is this item yours? *</label>
                                <textarea name="claim_reason" class="form-control" rows="4" required placeholder="Provide details that help the admin verify ownership..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Claim</button>
                            <a href="browse_items.php" class="btn btn-outline-secondary">Cancel</a>
                        </form>
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