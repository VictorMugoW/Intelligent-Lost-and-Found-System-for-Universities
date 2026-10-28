<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
// Default tab is 'pending'
$tab = $_GET['tab'] ?? 'pending'; 

// --- FETCH ALL CLAIMS ---
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
$claims_result = $stmt->get_result();
$stmt->close();

// Organize claims into arrays based on status
$claims_pending = [];
$claims_approved = [];
$claims_rejected = [];

while ($row = $claims_result->fetch_assoc()) {
    if ($row['status'] === 'pending') {
        $claims_pending[] = $row;
    } elseif ($row['status'] === 'approved') {
        $claims_approved[] = $row;
    } elseif ($row['status'] === 'rejected') {
        $claims_rejected[] = $row;
    }
}

// --- FETCH REJECTED COUNTS FOR RE-CLAIM LOGIC ---
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

// Helper to get the active array based on tab
function getActiveClaims($tab, $pending, $approved, $rejected) {
    if ($tab === 'approved') return $approved;
    if ($tab === 'rejected') return $rejected;
    return $pending; // default
}
$active_claims = getActiveClaims($tab, $claims_pending, $claims_approved, $claims_rejected);
?>

<div class="container py-5" style="max-width: 1100px;">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h1 class="section-title mb-1">My Claims</h1>
            <p class="section-subtitle mb-0">Track the status of items you've tried to claim.</p>
        </div>
        <a href="browse_items.php?view=community" class="btn btn-outline-primary fw-bold px-4 py-2">
            <i class="bi bi-search me-2"></i> Find More Items
        </a>
    </div>

    <!-- Tabs Navigation -->
    <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3">
        <li class="nav-item">
            <a class="nav-link fw-bold <?= $tab === 'pending' ? 'active' : '' ?>" 
               href="?tab=pending"
               style="<?= $tab === 'pending' ? 'background: var(--warning); color: var(--black);' : 'background: var(--white); color: var(--gray); border: 1px solid #E2E8F0;' ?>">
                <i class="bi bi-hourglass-split me-1"></i> Pending 
                <span class="badge <?= $tab === 'pending' ? 'bg-dark' : 'bg-secondary' ?> ms-1"><?= count($claims_pending) ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold <?= $tab === 'approved' ? 'active' : '' ?>" 
               href="?tab=approved"
               style="<?= $tab === 'approved' ? 'background: var(--success); color: white;' : 'background: var(--white); color: var(--gray); border: 1px solid #E2E8F0;' ?>">
                <i class="bi bi-check-circle me-1"></i> Approved 
                <span class="badge <?= $tab === 'approved' ? 'bg-dark' : 'bg-secondary' ?> ms-1"><?= count($claims_approved) ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold <?= $tab === 'rejected' ? 'active' : '' ?>" 
               href="?tab=rejected"
               style="<?= $tab === 'rejected' ? 'background: var(--danger); color: white;' : 'background: var(--white); color: var(--gray); border: 1px solid #E2E8F0;' ?>">
                <i class="bi bi-x-circle me-1"></i> Rejected 
                <span class="badge <?= $tab === 'rejected' ? 'bg-dark' : 'bg-secondary' ?> ms-1"><?= count($claims_rejected) ?></span>
            </a>
        </li>
    </ul>

    <!-- Claims Grid -->
    <?php if (empty($active_claims)): ?>
        <div class="card text-center p-5 shadow-sm" style="border-radius: 1rem;">
            <div class="feature-icon mx-auto mb-3" style="background: var(--light); color: var(--gray);">
                <i class="bi bi-inbox"></i>
            </div>
            <h5 class="fw-bold">No <?= $tab ?> claims</h5>
            <p class="text-muted mb-4">
                <?php 
                    if ($tab === 'pending') echo "You don't have any claims awaiting review right now.";
                    elseif ($tab === 'approved') echo "You don't have any approved claims yet.";
                    else echo "Great news! You don't have any rejected claims.";
                ?>
            </p>
            <?php if ($tab === 'pending'): ?>
                <a href="browse_items.php?view=community" class="btn btn-primary px-4 mx-auto" style="width: fit-content;">
                    <i class="bi bi-search me-1"></i> Browse Items to Claim
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($active_claims as $c): ?>
                <div class="col-md-6">
                    
                    <!-- Dynamic border color based on status -->
                    <?php 
                        $border_color = 'var(--warning)'; // pending
                        if ($c['status'] === 'approved') $border_color = 'var(--success)';
                        if ($c['status'] === 'rejected') $border_color = 'var(--danger)';
                    ?>
                    
                    <div class="card h-100" style="border-left: 6px solid <?= $border_color ?>; border-radius: 1rem; box-shadow: var(--shadow-sm);">
                        
                        <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="fw-bold mb-1"><?= htmlspecialchars($c['item_name']) ?></h5>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($c['category']) ?></span>
                            </div>
                            <span class="text-muted small">Claim #<?= $c['claim_id'] ?></span>
                        </div>

                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-4">
                                    <?php if (!empty($c['image_path'])): ?>
                                        <img src="../<?= htmlspecialchars($c['image_path']) ?>" class="img-fluid rounded" style="height:100px; width:100%; object-fit:cover;">
                                    <?php else: ?>
                                        <div style="height:100px; background: <?= $border_color ?>15; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-image" style="font-size: 2rem; color: <?= $border_color ?>;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-8">
                                    <p class="small mb-1"><strong>Location:</strong> <?= htmlspecialchars($c['location'] ?: 'N/A') ?></p>
                                    <p class="small mb-1"><strong>Colour:</strong> <?= htmlspecialchars($c['colour'] ?: 'N/A') ?></p>
                                    <p class="small mb-0"><strong>Submitted:</strong> <?= date('M d, Y', strtotime($c['created_at'])) ?></p>
                                </div>
                            </div>

                            <!-- Status Specific Alerts -->
                            <?php if ($c['status'] === 'approved'): ?>
                                <div class="alert alert-success border-0 shadow-sm mb-0">
                                    <strong class="d-block mb-1"><i class="bi bi-check-circle me-1"></i> Claim Approved!</strong>
                                    <p class="small mb-2">Bring this QR code to the Lost and Found Office to collect your item:</p>
                                    <div class="text-center bg-white p-2 rounded">
                                        <?php if (file_exists("../qrcodes/claim_" . $c['claim_id'] . ".png")): ?>
                                            <img src="../qrcodes/claim_<?= $c['claim_id'] ?>.png" class="img-fluid" style="max-width: 140px;">
                                        <?php else: ?>
                                            <i class="bi bi-qr-code text-muted" style="font-size: 4rem;"></i>
                                        <?php endif; ?>
                                        <p class="mt-2 mb-0"><code class="text-dark"><?= htmlspecialchars($c['qr_token']) ?></code></p>
                                    </div>
                                </div>
                                <a href="download_receipt.php?claim_id=<?= $c['claim_id'] ?>" class="btn btn-success w-100 mt-3 fw-bold">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> Download Receipt
                                </a>

                            <?php elseif ($c['status'] === 'rejected'): ?>
                                <?php
                                $rejected_count = $rejected_counts[$c['item_id']] ?? 1;
                                $can_reclaim = ($c['item_status'] !== 'returned') && ($rejected_count < 2);
                                ?>
                                <div class="alert alert-danger border-0 shadow-sm mb-0">
                                    <strong class="d-block mb-1"><i class="bi bi-x-circle me-1"></i> Claim Rejected</strong>
                                    <?php if (!empty($c['admin_reason'])): ?>
                                        <p class="small mb-0"><strong>Admin:</strong> <?= htmlspecialchars($c['admin_reason']) ?></p>
                                    <?php else: ?>
                                        <p class="small mb-0">Your claim was not approved.</p>
                                    <?php endif; ?>
                                </div>

                                <?php if ($c['item_status'] === 'returned'): ?>
                                    <div class="alert alert-secondary small mt-3 mb-0 border-0">
                                        <i class="bi bi-info-circle me-1"></i> This item has since been returned to its owner.
                                    </div>
                                <?php elseif ($can_reclaim): ?>
                                    <a href="submit_claim.php?item_id=<?= $c['item_id'] ?>" class="btn btn-outline-danger w-100 mt-3 fw-bold">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Submit New Claim
                                    </a>
                                <?php else: ?>
                                    <div class="alert alert-warning small mt-3 mb-0 border-0">
                                        <strong><i class="bi bi-exclamation-triangle me-1"></i> Final Rejection</strong>
                                        <p class="mb-0 mt-1">Please visit the Lost and Found Office in person to discuss this.</p>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <div class="alert alert-warning border-0 shadow-sm mb-0">
                                    <strong class="d-block mb-1"><i class="bi bi-hourglass-split me-1"></i> Awaiting Review</strong>
                                    <p class="small mb-0">Your claim is pending administrator approval. Please check back later.</p>
                                </div>
                            <?php endif; ?>
                            
                            <hr class="my-3">
                            <p class="small text-muted mb-0"><strong>Your reason:</strong> <?= htmlspecialchars($c['claim_reason']) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>