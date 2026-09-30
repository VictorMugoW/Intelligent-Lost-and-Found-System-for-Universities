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

$page_title = 'Verify QR Code';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="feature-icon mx-auto mb-3"><i class="bi bi-qr-code-scan"></i></div>
                        <h3 class="fw-bold">Verify QR Code</h3>
                        <p class="text-muted">Enter the QR token from the claimant's email to verify ownership</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <form method="POST" class="mb-4">
                        <div class="input-group">
                            <input type="text" name="qr_token" class="form-control" placeholder="e.g. CLAIM-1-309AEB76" required>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Verify</button>
                        </div>
                    </form>

                    <?php if ($claim_data): ?>
                        <div class="card" style="border: 2px solid #10B981; box-shadow: none;">
                            <div class="card-header" style="background: linear-gradient(135deg, #10B981, #059669); color: white;">
                                <h5 class="mb-0"><i class="bi bi-check-circle-fill"></i> Claim Verified</h5>
                            </div>
                            <div class="card-body">
                                <h5 class="fw-bold">Item Details</h5>
                                <ul class="list-group mb-3">
                                    <li class="list-group-item"><strong>Item:</strong> <?= htmlspecialchars($claim_data['item_name']) ?></li>
                                    <li class="list-group-item"><strong>Category:</strong> <?= htmlspecialchars($claim_data['category']) ?></li>
                                    <li class="list-group-item"><strong>Location:</strong> <?= htmlspecialchars($claim_data['location'] ?: 'N/A') ?></li>
                                    <li class="list-group-item"><strong>Description:</strong> <?= nl2br(htmlspecialchars($claim_data['description'])) ?></li>
                                </ul>

                                <h5 class="fw-bold">Claimant Details</h5>
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
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>