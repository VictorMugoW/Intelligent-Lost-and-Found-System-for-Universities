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

// Fetch item details
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

// Check if already claimed by this user (pending/approved)
$check = $conn->prepare("SELECT claim_id FROM claims WHERE item_id = ? AND claimed_by = ? AND status != 'rejected'");
$check->bind_param("ii", $item_id, $user_id);
$check->execute();
$existing = $check->get_result();
$already_claimed = $existing->num_rows > 0;
$check->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_claimed) {
    $claim_reason = trim($_POST['claim_reason']);

    if (empty($claim_reason)) {
        $message = 'Please provide a reason for your claim.';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO claims (item_id, claimed_by, claim_reason, status) VALUES (?, ?, ?, 'pending')");
        $stmt->bind_param("iis", $item_id, $user_id, $claim_reason);

        if ($stmt->execute()) {
            $message = 'Claim submitted successfully! Awaiting admin approval.';
            $message_type = 'success';
            $already_claimed = true;
        } else {
            $message = 'Failed to submit claim. Please try again.';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Claim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Intelligent Lost and Found</span>
        <div>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">Dashboard</a>
            <a href="browse_items.php" class="btn btn-outline-light btn-sm">Browse Items</a>
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">Submit a Claim</h4>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <h5>Item Details</h5>
                    <ul class="list-group mb-3">
                        <li class="list-group-item"><strong>Name:</strong> <?= htmlspecialchars($item['item_name']) ?></li>
                        <li class="list-group-item"><strong>Category:</strong> <?= htmlspecialchars($item['category']) ?></li>
                        <li class="list-group-item"><strong>Colour:</strong> <?= htmlspecialchars($item['colour'] ?: 'N/A') ?></li>
                        <li class="list-group-item"><strong>Location:</strong> <?= htmlspecialchars($item['location'] ?: 'N/A') ?></li>
                        <li class="list-group-item"><strong>Date:</strong> <?= htmlspecialchars($item['date_lost_found'] ?: 'N/A') ?></li>
                        <li class="list-group-item"><strong>Reported by:</strong> <?= htmlspecialchars($item['reporter']) ?></li>
                        <li class="list-group-item"><strong>Description:</strong> <?= nl2br(htmlspecialchars($item['description'])) ?></li>
                    </ul>

                    <?php if ($already_claimed): ?>
                        <div class="alert alert-info">You have already submitted a claim for this item.</div>
                    <?php elseif ($item['status'] === 'returned'): ?>
                        <div class="alert alert-secondary">This item has already been returned.</div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Why is this item yours? *</label>
                                <textarea name="claim_reason" class="form-control" rows="4" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-warning">Submit Claim</button>
                            <a href="browse_items.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>