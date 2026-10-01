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

$stmt = $conn->prepare("SELECT i.*, u.full_name AS reporter FROM items i JOIN users u ON i.user_id = u.user_id WHERE i.item_id = ? AND i.status = 'lost'");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    header('Location: browse_items.php');
    exit;
}

if ($item['user_id'] == $user_id) {
    header('Location: browse_items.php');
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $found_location = trim($_POST['found_location']);
    $found_date = $_POST['found_date'];

    if (empty($found_location) || empty($found_date)) {
        $message = 'Please provide both location and date.';
        $message_type = 'danger';
    } else {
        $update = $conn->prepare("UPDATE items SET status = 'pending_verification', finder_id = ?, found_location = ?, found_date = ? WHERE item_id = ?");
        $update->bind_param("issi", $user_id, $found_location, $found_date, $item_id);
        if ($update->execute()) {
            $message = 'Thank you! Your report has been submitted for admin verification.';
            $message_type = 'success';
        } else {
            $message = 'Failed to update. Please try again.';
            $message_type = 'danger';
        }
        $update->close();
    }
}

$page_title = 'Mark Item as Found';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="feature-icon mx-auto mb-3" style="background: linear-gradient(135deg, #10B981, #059669);"><i class="bi bi-hand-thumbs-up"></i></div>
                        <h3 class="fw-bold">I Found This Item</h3>
                        <p class="text-muted">Thank you for helping reunite this item with its owner</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
                        <?php if ($message_type === 'success'): ?>
                            <div class="alert alert-info small">
                                <i class="bi bi-info-circle"></i>
                                <strong>Next step:</strong> Please deposit the item at the Lost and Found Office. An administrator will verify it and make it available for claiming.
                            </div>
                            <a href="browse_items.php" class="btn btn-primary">Back to Browse</a>
                        <?php endif; ?>
                    <?php else: ?>

                        <div class="card bg-light mb-4" style="box-shadow: none;">
                            <div class="card-body">
                                <h5 class="fw-bold mb-3">Item Details</h5>
                                <p class="mb-1"><strong>Item:</strong> <?= htmlspecialchars($item['item_name']) ?></p>
                                <p class="mb-1"><strong>Category:</strong> <?= htmlspecialchars($item['category']) ?></p>
                                <p class="mb-1"><strong>Colour:</strong> <?= htmlspecialchars($item['colour'] ?: 'N/A') ?></p>
                                <p class="mb-1"><strong>Reported by:</strong> <?= htmlspecialchars($item['reporter']) ?></p>
                                <p class="mb-0"><strong>Original location:</strong> <?= htmlspecialchars($item['location'] ?: 'N/A') ?></p>
                            </div>
                        </div>

                        <div class="alert alert-warning small">
                            <strong><i class="bi bi-exclamation-triangle"></i> Important:</strong>
                            After submitting this form, please take the item to the <strong>Lost and Found Office</strong>.
                            An administrator will verify it before it becomes visible for claiming.
                        </div>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Where did you find it? *</label>
                                <input type="text" name="found_location" class="form-control" placeholder="e.g. Library 2nd floor" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">When did you find it? *</label>
                                <input type="date" name="found_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Submit for Verification
                            </button>
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