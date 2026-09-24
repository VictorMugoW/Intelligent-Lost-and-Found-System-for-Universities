<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$message_type = '';
$claim_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['qr_token']);

    if (empty($token)) {
        $message = 'Please enter a QR token.';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("
            SELECT c.claim_id, c.status, c.qr_token, c.qr_generated_at,
                   i.item_name, i.category, i.location, i.description,
                   u.full_name AS claimant, u.email, u.phone
            FROM claims c
            JOIN items i ON c.item_id = i.item_id
            JOIN users u ON c.claimed_by = u.user_id
            WHERE c.qr_token = ?
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $claim_data = $result->fetch_assoc();
            $message = 'QR code verified successfully!';
            $message_type = 'success';
        } else {
            $message = 'Invalid QR token. No matching claim found.';
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
    <title>Verify QR Code</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Admin Panel — Verify QR</span>
        <div>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">Dashboard</a>
            <a href="manage_claims.php" class="btn btn-outline-light btn-sm">Manage Claims</a>
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h3>Verify QR Code</h3>
    <p class="text-muted">Enter the QR token from the claimant's email to verify ownership.</p>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="mb-4">
        <div class="input-group">
            <input type="text" name="qr_token" class="form-control" placeholder="e.g. CLAIM-1-309AEB76" required>
            <button type="submit" class="btn btn-primary">Verify</button>
        </div>
    </form>

    <?php if ($claim_data): ?>
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">✅ Claim Verified</h5>
            </div>
            <div class="card-body">
                <h5>Item Details</h5>
                <ul class="list-group mb-3">
                    <li class="list-group-item"><strong>Item:</strong> <?= htmlspecialchars($claim_data['item_name']) ?></li>
                    <li class="list-group-item"><strong>Category:</strong> <?= htmlspecialchars($claim_data['category']) ?></li>
                    <li class="list-group-item"><strong>Location:</strong> <?= htmlspecialchars($claim_data['location'] ?: 'N/A') ?></li>
                    <li class="list-group-item"><strong>Description:</strong> <?= nl2br(htmlspecialchars($claim_data['description'])) ?></li>
                </ul>

                <h5>Claimant Details</h5>
                <ul class="list-group mb-3">
                    <li class="list-group-item"><strong>Name:</strong> <?= htmlspecialchars($claim_data['claimant']) ?></li>
                    <li class="list-group-item"><strong>Email:</strong> <?= htmlspecialchars($claim_data['email']) ?></li>
                    <li class="list-group-item"><strong>Phone:</strong> <?= htmlspecialchars($claim_data['phone'] ?: 'N/A') ?></li>
                </ul>

                <div class="alert alert-info">
                    <strong>Action:</strong> Verify the claimant's physical student/staff ID before releasing the item.
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>