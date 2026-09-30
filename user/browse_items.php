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

$sql = "SELECT i.*, u.full_name FROM items i JOIN users u ON i.user_id = u.user_id WHERE 1=1";
$params = [];
$types = "";

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

$page_title = 'Browse Items';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="mb-4">
        <h2 class="section-title">Browse Items</h2>
        <p class="section-subtitle">Search through all reported lost and found items</p>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
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

    <div class="row g-4">
        <?php if ($items->num_rows === 0): ?>
            <div class="col-12">
                <div class="alert alert-info">No items found matching your criteria.</div>
            </div>
        <?php else: ?>
            <?php while ($item = $items->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="card h-100">
                        <?php if (!empty($item['image_path'])): ?>
                            <img src="../<?= htmlspecialchars($item['image_path']) ?>" class="card-img-top" style="height:220px; object-fit:cover;">
                        <?php else: ?>
                            <div style="height:220px; background: linear-gradient(135deg, #1E40AF, #3B82F6); display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-image" style="font-size: 3rem; color: rgba(255,255,255,0.5);"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <div class="mb-2">
                                <span class="badge bg-<?= $item['type'] === 'lost' ? 'danger' : 'success' ?>">
                                    <?= ucfirst($item['type']) ?>
                                </span>
                                <span class="badge bg-<?= $item['status'] === 'returned' ? 'primary' : 'secondary' ?>">
                                    <?= ucfirst($item['status']) ?>
                                </span>
                            </div>
                            <h5 class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></h5>
                            <p class="small text-muted mb-2">
                                <strong>Category:</strong> <?= htmlspecialchars($item['category']) ?><br>
                                <strong>Colour:</strong> <?= htmlspecialchars($item['colour'] ?: 'N/A') ?><br>
                                <strong>Location:</strong> <?= htmlspecialchars($item['location'] ?: 'N/A') ?><br>
                                <strong>Date:</strong> <?= htmlspecialchars($item['date_lost_found'] ?: 'N/A') ?><br>
                                <strong>Reported by:</strong> <?= htmlspecialchars($item['full_name']) ?>
                            </p>
                            <?php if ($item['status'] !== 'returned'): ?>
                                <a href="submit_claim.php?item_id=<?= $item['item_id'] ?>" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-hand-index"></i> Claim This Item
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm w-100" disabled>
                                    <i class="bi bi-check-circle"></i> Returned
                                </button>
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