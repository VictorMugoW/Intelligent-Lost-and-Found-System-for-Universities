<?php
session_start();
require_once '../config/database.php';
require_once '../libraries/phpqrcode-master/qrlib.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$message_type = '';

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_id'], $_POST['action'])) {
    $claim_id = intval($_POST['claim_id']);
    $action = $_POST['action'];

    if ($action === 'approve') {
        // Get item_id for this claim
        $r = $conn->prepare("SELECT item_id, claimed_by FROM claims WHERE claim_id = ?");
        $r->bind_param("i", $claim_id);
        $r->execute();
        $row = $r->get_result()->fetch_assoc();
        $r->close();

        if ($row) {
            $item_id = $row['item_id'];

            // Generate unique QR token
            $qr_token = 'CLAIM-' . $claim_id . '-' . strtoupper(bin2hex(random_bytes(4)));
            $qr_file = '../qrcodes/claim_' . $claim_id . '.png';

            // Generate QR code image
            QRcode::png($qr_token, $qr_file, QR_ECLEVEL_L, 10);

            // Update claim: status, qr_token, qr_generated_at
            $stmt = $conn->prepare("UPDATE claims SET status = 'approved', qr_token = ?, qr_generated_at = NOW() WHERE claim_id = ?");
            $stmt->bind_param("si", $qr_token, $claim_id);
            $stmt->execute();
            $stmt->close();

            // Update item status to returned
            $conn->query("UPDATE items SET status = 'returned' WHERE item_id = $item_id");

            $message = "Claim approved. QR code generated and saved.";
            $message_type = "success";
        }
    } elseif ($action === 'reject') {
        $conn->query("UPDATE claims SET status = 'rejected' WHERE claim_id = $claim_id");
        $message = "Claim rejected.";
        $message_type = "warning";
    }
}

// Fetch pending claims
$claims = $conn->query("
    SELECT c.claim_id, c.claim_reason, c.status, c.created_at,
           i.item_id, i.item_name, i.category, i.colour, i.location, i.description, i.image_path,
           u.full_name AS claimant, u.email AS claimant_email
    FROM claims c
    JOIN items i ON c.item_id = i.item_id
    JOIN users u ON c.claimed_by = u.user_id
    WHERE c.status = 'pending'
    ORDER BY c.created_at DESC
");

// Fetch recently approved claims with QR
$approved = $conn->query("
    SELECT c.claim_id, c.qr_token, c.qr_generated_at, i.item_name, u.full_name AS claimant
    FROM claims c
    JOIN items i ON c.item_id = i.item_id
    JOIN users u ON c.claimed_by = u.user_id
    WHERE c.status = 'approved'
    ORDER BY c.qr_generated_at DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Claims</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Admin Panel — Manage Claims</span>
        <div>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">Dashboard</a>
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <h3>Pending Claims</h3>
    <?php if ($claims->num_rows === 0): ?>
        <div class="alert alert-info">No pending claims.</div>
    <?php else: ?>
        <div class="row">
            <?php while ($c = $claims->fetch_assoc()): ?>
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-warning text-dark">
                            <strong>Claim #<?= $c['claim_id'] ?></strong>
                        </div>
                        <div class="card-body">
                            <?php if ($c['image_path']): ?>
                                <img src="../<?= htmlspecialchars($c['image_path']) ?>" class="img-fluid mb-2" style="max-height:200px;">
                            <?php endif; ?>
                            <h5><?= htmlspecialchars($c['item_name']) ?></h5>
                            <p class="small mb-1"><strong>Category:</strong> <?= htmlspecialchars($c['category']) ?></p>
                            <p class="small mb-1"><strong>Colour:</strong> <?= htmlspecialchars($c['colour'] ?: 'N/A') ?></p>
                            <p class="small mb-1"><strong>Location:</strong> <?= htmlspecialchars($c['location'] ?: 'N/A') ?></p>
                            <p class="small mb-1"><strong>Description:</strong> <?= htmlspecialchars($c['description']) ?></p>
                            <hr>
                            <p class="small mb-1"><strong>Claimant:</strong> <?= htmlspecialchars($c['claimant']) ?></p>
                            <p class="small mb-1"><strong>Email:</strong> <?= htmlspecialchars($c['claimant_email']) ?></p>
                            <p class="small mb-2"><strong>Reason:</strong> <?= htmlspecialchars($c['claim_reason']) ?></p>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="claim_id" value="<?= $c['claim_id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

    <hr class="my-4">

    <h3>Recently Approved (QR Generated)</h3>
    <?php if ($approved->num_rows === 0): ?>
        <div class="alert alert-secondary">No approved claims yet.</div>
    <?php else: ?>
        <table class="table table-bordered bg-white">
            <thead>
                <tr>
                    <th>Claim ID</th>
                    <th>Item</th>
                    <th>Claimant</th>
                    <th>QR Code</th>
                    <th>Token</th>
                    <th>Generated At</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = $approved->fetch_assoc()): ?>
                    <tr>
                        <td><?= $a['claim_id'] ?></td>
                        <td><?= htmlspecialchars($a['item_name']) ?></td>
                        <td><?= htmlspecialchars($a['claimant']) ?></td>
                        <td>
                            <img src="../qrcodes/claim_<?= $a['claim_id'] ?>.png" width="80" alt="QR">
                        </td>
                        <td><code><?= htmlspecialchars($a['qr_token']) ?></code></td>
                        <td><?= $a['qr_generated_at'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>