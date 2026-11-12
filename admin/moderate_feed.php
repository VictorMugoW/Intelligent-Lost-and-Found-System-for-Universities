<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$message_type = '';

if (isset($_GET['deleted'])) {
    $message = "Item successfully removed from the community feed.";
    $message_type = "success";
}

// Fetch all active community items
$search = trim($_GET['search'] ?? '');
$search_sql = "";
if (!empty($search)) {
    $esc = $conn->real_escape_string($search);
    $search_sql = " AND (i.item_name LIKE '%$esc%' OR i.description LIKE '%$esc%' OR u.full_name LIKE '%$esc%')";
}

$query = "
    SELECT i.*, u.full_name 
    FROM items i 
    JOIN users u ON i.user_id = u.user_id 
    WHERE i.status IN ('lost', 'pending_verification') $search_sql
    ORDER BY i.created_at DESC
";
$items_result = $conn->query($query);

$page_title = 'Moderate Feed';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '../includes/admin_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1" style="color: #0f172a;">Moderate Community Feed</h2>
        <p class="text-muted mb-0">Review, search, and remove inappropriate or spam items.</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show border-0 shadow-sm">
        <i class="bi bi-check-circle me-2"></i> <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Search Bar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius: 1rem;">
    <div class="card-body p-3">
        <form method="GET" class="d-flex gap-2">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by item name, description, or user..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-dark fw-bold px-4">Search</button>
            <?php if (!empty($search)): ?>
                <a href="moderate_feed.php" class="btn btn-outline-danger fw-bold">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 1rem;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3">Item Details</th>
                        <th class="py-3">Reported By</th>
                        <th class="py-3">Status</th>
                        <th class="py-3 text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($items_result->num_rows === 0): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">No items found in the feed.</td></tr>
                    <?php else: ?>
                        <?php while($item = $items_result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($item['item_name']) ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 300px;"><?= htmlspecialchars($item['description']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($item['full_name']) ?></div>
                                    <div class="small text-muted"><?= date('M d, Y', strtotime($item['created_at'])) ?></div>
                                </td>
                                <td>
                                    <?php if($item['status'] === 'lost'): ?>
                                        <span class="badge bg-danger">Lost</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending Verification</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="delete_item.php?item_id=<?= $item['item_id'] ?>" 
                                       class="btn btn-sm btn-outline-danger fw-bold rounded-pill"
                                       onclick="return confirm('Are you sure you want to permanently delete this post? This action cannot be undone.');">
                                        <i class="bi bi-trash3-fill me-1"></i> Delete Post
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
require_once '../includes/admin_layout_bottom.php';
require_once '../includes/footer.php'; 
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>