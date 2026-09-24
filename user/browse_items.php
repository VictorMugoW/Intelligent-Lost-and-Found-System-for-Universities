<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Filters
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Items</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Intelligent Lost and Found</span>
        <div>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">Dashboard</a>
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h3 class="mb-3">Browse Items</h3>

    <form method="GET" class="row g-2 mb-4">
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
            <button type="submit" class="btn btn-primary w-100">Go</button>
        </div>
    </form>

    <div class="row">
        <?php if ($items->num_rows === 0): ?>
            <div class="col-12">
                <div class="alert alert-info">No items found.</div>
            </div>
        <?php else: ?>
            <?php while ($item = $items->fetch_assoc()): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <?php if (!empty($item['image_path'])): ?>
                            <img src="../<?= htmlspecialchars($item['image_path']) ?>" class="card-img-top" style="height:200px; object-fit:cover;">
                        <?php else: ?>
                            <div class="bg-secondary text-white text-center d-flex align-items-center justify-content-center" style="height:200px;">
                                <span>No Image</span>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <span class="badge bg-<?= $item['type'] === 'lost' ? 'danger' : 'success' ?> mb-2">
                                <?= ucfirst($item['type']) ?>
                            </span>
                            <span class="badge bg-<?= $item['status'] === 'returned' ? 'primary' : 'secondary' ?> mb-2">
                                <?= ucfirst($item['status']) ?>
                            </span>
                            <h5 class="card-title"><?= htmlspecialchars($item['item_name']) ?></h5>
                            <p class="card-text small">
                                <strong>Category:</strong> <?= htmlspecialchars($item['category']) ?><br>
                                <strong>Colour:</strong> <?= htmlspecialchars($item['colour'] ?: 'N/A') ?><br>
                                <strong>Location:</strong> <?= htmlspecialchars($item['location'] ?: 'N/A') ?><br>
                                <strong>Date:</strong> <?= htmlspecialchars($item['date_lost_found'] ?: 'N/A') ?><br>
                                <strong>Reported by:</strong> <?= htmlspecialchars($item['full_name']) ?>
                            </p>
                            <?php if ($item['status'] !== 'returned'): ?>
                                <a href="submit_claim.php?item_id=<?= $item['item_id'] ?>" class="btn btn-sm btn-primary">Claim This Item</a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary" disabled>Returned</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>