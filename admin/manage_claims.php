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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_id'], $_POST['action'])) {
    $claim_id = intval($_POST['claim_id']);
    $action = $_POST['action'];

    if ($action === 'approve') {
        $r = $conn->prepare("SELECT item_id FROM claims WHERE claim_id = ?");
        $r->bind_param("i", $claim_id);
        $r->execute();
        $row = $r->get_result()->fetch_assoc();
        $r->close();

        if ($row) {
            $item_id = $row['item_id'];
            $qr_token = 'CLAIM-' . $claim_id . '-' . strtoupper(bin2hex(random_bytes(4)));
            $qr_file = '../qrcodes/claim_' . $claim_id . '.png';

            QRcode::png($qr_token, $qr_file, QR_ECLEVEL_L, 10);

            $stmt = $conn->prepare("UPDATE claims SET status = 'approved', qr_token = ?, qr_generated_at = NOW(), admin_reason = NULL WHERE claim_id = ?");
            $stmt->bind_param("si", $qr_token, $claim_id);
            $stmt->execute();
            $stmt->close();

            $conn->query("UPDATE items SET status = 'returned' WHERE item_id = $item_id");

            $message = "Claim approved. QR code generated.";
            $message_type = "success";
        }
    } elseif ($action === 'reject') {
        $reason = trim($_POST['admin_reason'] ?? '');
        if (empty($reason)) {
            $message = "Please provide a reason for rejecting this claim.";
            $message_type = "danger";
        } else {
            $stmt = $conn->prepare("UPDATE claims SET status = 'rejected', admin_reason = ? WHERE claim_id = ?");
            $stmt->bind_param("si", $reason, $claim_id);
            $stmt->execute();
            $stmt->close();
            $message = "Claim rejected with reason.";
            $message_type = "warning";
        }
    }
}

$claims = $conn->query("
    SELECT c.claim_id, c.claim_reason, c.status, c.created_at, c.supporting_lost_item_id,
           i.item_id, i.item_name, i.category, i.colour, i.location, i.description, i.image_path,
           u.full_name AS claimant, u.email AS claimant_email
    FROM claims c
    JOIN items i ON c.item_id = i.item_id
    JOIN users u ON c.claimed_by = u.user_id
    WHERE c.status = 'pending'
    ORDER BY c.created_at DESC
");

$approved = $conn->query("
    SELECT c.claim_id, c.qr_token, c.qr_generated_at, i.item_name, u.full_name AS claimant
    FROM claims c
    JOIN items i ON c.item_id = i.item_id
    JOIN users u ON c.claimed_by = u.user_id
    WHERE c.status = 'approved'
    ORDER BY c.qr_generated_at DESC
    LIMIT 10
");

$page_title = 'Manage Claims';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="mb-4">
        <h2 class="section-title">Manage Claims</h2>
        <p class="section-subtitle">Review pending claims and approve or reject them</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <h4 class="fw-bold mb-3">Pending Claims</h4>
    <?php if ($claims->num_rows === 0): ?>
        <div class="alert alert-info">No pending claims at the moment.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php while ($c = $claims->fetch_assoc()): ?>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header" style="background: linear-gradient(135deg, #F59E0B, #D97706); color: white;">
                            <strong>Claim #<?= $c['claim_id'] ?></strong>
                        </div>
                        <div class="card-body">
                            <?php if ($c['image_path']): ?>
                                <img src="../<?= htmlspecialchars($c['image_path']) ?>" class="img-fluid rounded mb-3" style="max-height:200px;">
                            <?php endif; ?>
                            <h5 class="fw-bold"><?= htmlspecialchars($c['item_name']) ?></h5>
                            <p class="small mb-1"><strong>Category:</strong> <?= htmlspecialchars($c['category']) ?></p>
                            <p class="small mb-1"><strong>Colour:</strong> <?= htmlspecialchars($c['colour'] ?: 'N/A') ?></p>
                            <p class="small mb-1"><strong>Location:</strong> <?= htmlspecialchars($c['location'] ?: 'N/A') ?></p>
                            <p class="small mb-2"><strong>Description:</strong> <?= htmlspecialchars($c['description']) ?></p>
                            <hr>
                            <p class="small mb-1"><strong>Claimant:</strong> <?= htmlspecialchars($c['claimant']) ?></p>
                            <p class="small mb-1"><strong>Email:</strong> <?= htmlspecialchars($c['claimant_email']) ?></p>
                            <p class="small mb-3"><strong>Claim Reason:</strong> <?= htmlspecialchars($c['claim_reason']) ?></p>

                            <?php
                            // If there's supporting evidence, fetch the lost report
                            if (!empty($c['supporting_lost_item_id'])):
                                $lost_id = intval($c['supporting_lost_item_id']);
                                $lost = $conn->query("SELECT item_name, description, colour, location, date_lost_found, created_at FROM items WHERE item_id = $lost_id")->fetch_assoc();
                                if ($lost):
                            ?>
                                <div class="card mb-3" style="border: 2px solid #10B981; box-shadow: none;">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold mb-2" style="color: #059669;">
                                            <i class="bi bi-shield-check"></i> Supporting Evidence
                                        </h6>
                                        <p class="small mb-1"><strong>Claimant's own Lost Report:</strong> <?= htmlspecialchars($lost['item_name']) ?></p>
                                        <p class="small mb-1"><strong>Reported on:</strong> <?= date('M d, Y', strtotime($lost['created_at'])) ?></p>
                                        <p class="small mb-1"><strong>Colour:</strong> <?= htmlspecialchars($lost['colour'] ?: 'N/A') ?></p>
                                        <p class="small mb-1"><strong>Location:</strong> <?= htmlspecialchars($lost['location'] ?: 'N/A') ?></p>
                                        <p class="small mb-0"><strong>Description:</strong> <?= htmlspecialchars($lost['description']) ?></p>
                                    </div>
                                </div>
                            <?php endif; endif; ?>

                            <!-- Approve Form -->
                            <form method="POST" class="mb-2">
                                <input type="hidden" name="claim_id" value="<?= $c['claim_id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm w-100">
                                    <i class="bi bi-check-circle"></i> Approve Claim
                                </button>
                            </form>

                            <!-- Reject Form (with reason) -->
                            <form method="POST">
                                <input type="hidden" name="claim_id" value="<?= $c['claim_id'] ?>">
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Reason for Rejection (required if rejecting):</label>
                                    <textarea name="admin_reason" class="form-control form-control-sm" rows="2" placeholder="e.g. The description does not match the item's details"></textarea>
                                </div>
                                <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm w-100">
                                    <i class="bi bi-x-circle"></i> Reject Claim
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

    <hr class="my-5">

    <h4 class="fw-bold mb-3">Recently Approved (QR Generated)</h4>
    <?php if ($approved->num_rows === 0): ?>
        <div class="alert alert-secondary">No approved claims yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
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
                            <td><strong>#<?= $a['claim_id'] ?></strong></td>
                            <td><?= htmlspecialchars($a['item_name']) ?></td>
                            <td><?= htmlspecialchars($a['claimant']) ?></td>
                            <td><img src="../qrcodes/claim_<?= $a['claim_id'] ?>.png" width="70" class="rounded"></td>
                            <td><code><?= htmlspecialchars($a['qr_token']) ?></code></td>
                            <td><?= $a['qr_generated_at'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>