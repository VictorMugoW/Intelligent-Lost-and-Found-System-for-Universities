<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$filter_type = $_GET['type'] ?? 'all';
$filter_category = $_GET['category'] ?? 'all';
$filter_status = $_GET['status'] ?? 'lost'; // lost, found, returned, all

$sql = "SELECT i.*, u.full_name FROM items i JOIN users u ON i.user_id = u.user_id WHERE 1=1";
$params = [];
$types = "";

// Status filter (lost, found, returned, all)
if ($filter_status === 'lost') {
    $sql .= " AND i.status = 'lost'";
} elseif ($filter_status === 'found') {
    $sql .= " AND i.status = 'found'";
} elseif ($filter_status === 'returned') {
    $sql .= " AND i.status = 'returned'";
}
// 'all' shows everything

if (!empty($search)) {
    $sql .= " AND (i.item_name LIKE ? OR i.description LIKE ? OR i.location LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}
if ($filter_type === 'lost' || $filter_type === 'found') {
    $sql .= " AND i.type = ?";
    $params[] = $filter_type;
    $types .= "s";
}
if ($filter_category !== 'all' && !empty($filter_category)) {
    $sql .= " AND i.category = ?";
    $params[] = $filter_category;
    $types .= "s";
}
$sql .= " ORDER BY i.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items = $stmt->get_result();

// Counts for tabs
$lost_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'lost'")->fetch_assoc()['c'];
$found_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'found'")->fetch_assoc()['c'];
$returned_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'returned'")->fetch_assoc()['c'];
$all_count = $lost_count + $found_count + $returned_count;

$page_title = 'Browse Items';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

function tabUrl($status, $search, $type, $category) {
    $url = "?status=$status";
    if (!empty($search)) $url .= "&search=" . urlencode($search);
    if ($type !== 'all') $url .= "&type=$type";
    if ($category !== 'all') $url .= "&category=$category";
    return $url;
}
?>

<div class="container mt-5">
    <div class="mb-4">
        <h2 class="section-title">Browse Items</h2>
        <p class="section-subtitle">Items transition through: <strong>Lost → Found → Returned</strong></p>
    </div>

    <!-- STATUS TABS -->
    <ul class="nav nav-pills mb-4" style="gap: 10px; flex-wrap: wrap;">
        <li class="nav-item">
            <a class="nav-link" href="<?= tabUrl('lost', $search, $filter_type, $filter_category) ?>"
               style="<?= $filter_status === 'lost' ? 'background: linear-gradient(135deg, #EF4444, #DC2626); color: white;' : 'background: white; color: #0F172A; border: 2px solid #E2E8F0;' ?>">
                <i class="bi bi-exclamation-circle"></i> Lost (<?= $lost_count ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= tabUrl('found', $search, $filter_type, $filter_category) ?>"
               style="<?= $filter_status === 'found' ? 'background: linear-gradient(135deg, #10B981, #059669); color: white;' : 'background: white; color: #0F172A; border: 2px solid #E2E8F0;' ?>">
                <i class="bi bi-check-circle"></i> Found (<?= $found_count ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= tabUrl('returned', $search, $filter_type, $filter_category) ?>"
               style="<?= $filter_status === 'returned' ? 'background: linear-gradient(135deg, #1E40AF, #3B82F6); color: white;' : 'background: white; color: #0F172A; border: 2px solid #E2E8F0;' ?>">
                <i class="bi bi-archive"></i> Returned (<?= $returned_count ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= tabUrl('all', $search, $filter_type, $filter_category) ?>"
               style="<?= $filter_status === 'all' ? 'background: linear-gradient(135deg, #0F172A, #1E293B); color: white;' : 'background: white; color: #0F172A; border: 2px solid #E2E8F0;' ?>">
                <i class="bi bi-grid"></i> All (<?= $all_count ?>)
            </a>
        </li>
    </ul>

    <!-- FILTER FORM -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, description, or location" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-select">
                        <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Types</option>
                        <option value="lost" <?= $filter_type === 'lost' ? 'selected' : '' ?>>Lost</option>
                        <option value="found" <?= $filter_type === 'found' ? 'selected' : '' ?>>Found</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="all">All Categories</option>
                        <?php foreach (['Phone','ID','Laptop','Wallet','Book','Other'] as $cat): ?>
                            <option value="<?= $cat ?>" <?= $filter_category === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    </div>

    <!-- ITEMS GRID -->
    <div class="row g-4">
        <?php if ($items->num_rows === 0): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <?php
                    if ($filter_status === 'lost') echo 'No lost items currently awaiting recovery.';
                    elseif ($filter_status === 'found') echo 'No recovered items waiting to be claimed.';
                    elseif ($filter_status === 'returned') echo 'No returned items yet.';
                    else echo 'No items found matching your criteria.';
                    ?>
                </div>
            </div>
        <?php else: ?>
            <?php while ($item = $items->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="card h-100" <?= $item['status'] === 'returned' ? 'style="opacity: 0.7;"' : '' ?>>
                        <?php if (!empty($item['image_path'])): ?>
                            <img src="../<?= htmlspecialchars($item['image_path']) ?>" class="card-img-top" style="height:220px; object-fit:cover; <?= $item['status'] === 'returned' ? 'filter: grayscale(60%);' : '' ?>">
                        <?php else: ?>
                            <div style="height:220px; background: linear-gradient(135deg, <?php
                                if ($item['status'] === 'returned') echo '#64748B, #475569';
                                elseif ($item['status'] === 'found') echo '#10B981, #059669';
                                else echo '#EF4444, #DC2626';
                            ?>); display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-<?php
                                    if ($item['status'] === 'returned') echo 'archive-fill';
                                    elseif ($item['status'] === 'found') echo 'check-circle';
                                    else echo 'exclamation-circle';
                                ?>" style="font-size: 3rem; color: rgba(255,255,255,0.5);"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <div class="mb-2">
                                <span class="badge bg-<?php
                                    if ($item['status'] === 'returned') echo 'primary';
                                    elseif ($item['status'] === 'found') echo 'success';
                                    else echo 'danger';
                                ?>">
                                    <?= ucfirst($item['status']) ?>
                                </span>
                                <span class="badge bg-secondary">
                                    <?= htmlspecialchars($item['category']) ?>
                                </span>
                            </div>
                            <h5 class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></h5>
                            <p class="small text-muted mb-2">
                                <strong>Colour:</strong> <?= htmlspecialchars($item['colour'] ?: 'N/A') ?><br>
                                <strong>Location:</strong> <?= htmlspecialchars($item['location'] ?: 'N/A') ?><br>
                                <strong>Date:</strong> <?= htmlspecialchars($item['date_lost_found'] ?: 'N/A') ?><br>
                                <strong>Reported by:</strong> <?= htmlspecialchars($item['full_name']) ?>
                            </p>

                            <?php if ($item['status'] === 'returned'): ?>
                                <button class="btn btn-secondary btn-sm w-100" disabled>
                                    <i class="bi bi-archive"></i> Already Returned
                                </button>
                            <?php elseif ($item['status'] === 'lost'): ?>
                                <?php if ($item['user_id'] == $user_id): ?>
                                    <button class="btn btn-outline-secondary btn-sm w-100" disabled>
                                        <i class="bi bi-hourglass-split"></i> Your Lost Report
                                    </button>
                                <?php else: ?>
                                    <a href="mark_found.php?item_id=<?= $item['item_id'] ?>" class="btn btn-success btn-sm w-100">
                                        <i class="bi bi-hand-thumbs-up"></i> I Found This Item
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="submit_claim.php?item_id=<?= $item['item_id'] ?>" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-hand-index"></i> Claim This Item
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>