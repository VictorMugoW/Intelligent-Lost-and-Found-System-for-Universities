<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT c.claim_id, c.claim_reason, c.status, c.qr_token, c.qr_generated_at, c.created_at,
           c.admin_reason, c.item_id,
           i.item_id, i.item_name, i.category, i.colour, i.location, i.description, i.image_path, i.status AS item_status
    FROM claims c
    JOIN items i ON c.item_id = i.item_id
    WHERE c.claimed_by = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$claims = $stmt->get_result();
$stmt->close();

// Count rejected claims per item (to know if user has already been rejected twice)
$rejected_counts = [];
$rc = $conn->prepare("SELECT item_id, COUNT(*) as c FROM claims WHERE claimed_by = ? AND status = 'rejected' GROUP BY item_id");
$rc->bind_param("i", $user_id);
$rc->execute();
$rc_result = $rc->get_result();
while ($row = $rc_result->fetch_assoc()) {
    $rejected_counts[$row['item_id']] = $row['c'];
}
$rc->close();

$page_title = 'My Claims';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="mb-4">
        <h2 class="section-title">My Claims</h2>
        <p class="section-subtitle">Track the status of claims you've submitted</p>
    </div>

    <?php if ($claims->num_rows === 0): ?>
        <div class="card">
            <div class="card-body text-center p-5">
                <div class="feature-icon mx-auto mb-3"><i class="bi bi-tags"></i></div>
                <h5 class="fw-bold">No claims yet</h5>
                <p class="text-muted">You haven't submitted any claims. Browse items to find something that belongs to you.</p>
                <a href="browse_items.php" class="btn btn-primary mt-2">
                    <i class="bi bi-search"></i> Browse Items
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php while ($c = $claims->fetch_assoc()): ?>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header" style="background: <?php
                            if ($c['status'] === 'approved') echo 'linear-gradient(135deg, #10B981, #059669)';
                            elseif ($c['status'] === 'rejected') echo 'linear-gradient(135deg, #EF4444, #DC2626)';
                            else echo 'linear-gradient(135deg, #F59E0B, #D97706)';
                        ?>; color: white;">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>Claim #<?= $c['claim_id'] ?></strong>
                                <span class="badge bg-light text-dark">
                                    <?= ucfirst($c['status']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <?php if (!empty($c['image_path'])): ?>
                                        <img src="../<?= htmlspecialchars($c['image_path']) ?>" class="img-fluid rounded" style="max-height:140px; object-fit:cover; width:100%;">
                                    <?php else: ?>
                                        <div style="height:140px; background: linear-gradient(135deg, #1E40AF, #3B82F6); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-image" style="font-size: 2rem; color: rgba(255,255,255,0.5);"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-8">
                                    <h5 class="fw-bold mb-2"><?= htmlspecialchars($c['item_name']) ?></h5>
                                    <p class="small mb-1"><strong>Category:</strong> <?= htmlspecialchars($c['category']) ?></p>
                                    <p class="small mb-1"><strong>Colour:</strong> <?= htmlspecialchars($c['colour'] ?: 'N/A') ?></p>
                                    <p class="small mb-1"><strong>Location:</strong> <?= htmlspecialchars($c['location'] ?: 'N/A') ?></p>
                                    <p class="small mb-1"><strong>Submitted:</strong> <?= date('M d, Y', strtotime($c['created_at'])) ?></p>
                                </div>
                            </div>

                            <hr>

                            <p class="small mb-2"><strong>Your reason:</strong> <?= htmlspecialchars($c['claim_reason']) ?></p>

                            <?php if ($c['status'] === 'approved' && !empty($c['qr_token'])): ?>
                                <div class="alert alert-success small mb-0">
                                    <strong><i class="bi bi-check-circle"></i> Claim Approved!</strong>
                                    <p class="mb-1 mt-1">Bring this QR code to the lost and found office to collect your item:</p>
                                    <div class="text-center mt-2">
                                        <img src="../qrcodes/claim_<?= $c['claim_id'] ?>.png" class="rounded bg-white p-2" style="max-width: 180px;">
                                        <p class="mt-1 mb-0"><code><?= htmlspecialchars($c['qr_token']) ?></code></p>
                                    </div>
                                </div>

                            <?php elseif ($c['status'] === 'rejected'): ?>
                                <?php
                                $rejected_count = $rejected_counts[$c['item_id']] ?? 1;
                                $can_reclaim = ($c['item_status'] !== 'returned') && ($rejected_count < 2);
                                ?>
                                <div class="alert alert-danger small mb-2">
                                    <strong><i class="bi bi-x-circle"></i> Claim Rejected</strong>
                                    <?php if (!empty($c['admin_reason'])): ?>
                                        <p class="mb-0 mt-1"><strong>Reason from Admin:</strong> <?= htmlspecialchars($c['admin_reason']) ?></p>
                                    <?php else: ?>
                                        <p class="mb-0 mt-1">Your claim was not approved.</p>
                                    <?php endif; ?>
                                </div>

                                <?php if ($c['item_status'] === 'returned'): ?>
                                    <div class="alert alert-secondary small mb-0">
                                        <i class="bi bi-info-circle"></i> This item has since been returned to its owner.
                                    </div>
                                <?php elseif ($can_reclaim): ?>
                                    <div class="alert alert-info small mb-2">
                                        <i class="bi bi-lightbulb"></i> If you have additional evidence, you may submit a new claim.
                                    </div>
                                    <a href="submit_claim.php?item_id=<?= $c['item_id'] ?>" class="btn btn-primary btn-sm w-100">
                                        <i class="bi bi-arrow-clockwise"></i> Submit New Claim with More Evidence
                                    </a>
                                <?php else: ?>
                                    <div class="alert alert-warning small mb-0">
                                        <strong><i class="bi bi-exclamation-triangle"></i> Second Claim Rejected</strong>
                                        <p class="mb-0 mt-1">
                                            Since your claim has been rejected more than once for this item,
                                            please visit the <strong>Lost and Found Office</strong> in person
                                            to discuss this matter with the attendant.
                                        </p>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <div class="alert alert-warning small mb-0">
                                    <strong><i class="bi bi-hourglass-split"></i> Awaiting Review</strong>
                                    <p class="mb-0 mt-1">Your claim is pending administrator approval. Please check back later.</p>
                                </div>
                            <?php endif; ?>
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