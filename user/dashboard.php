<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];

// Get stats
$total_items = 0;
$my_items = 0;
$my_claims = 0;

$r1 = $conn->query("SELECT COUNT(*) as c FROM items");
if ($r1) $total_items = $r1->fetch_assoc()['c'];

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM items WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_items = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM claims WHERE claimed_by = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_claims = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Intelligent Lost and Found</span>
        <div>
            <span class="text-white me-3">Welcome, <?= htmlspecialchars($full_name) ?></span>
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h3>User Dashboard</h3>
    <p class="text-muted">Logged in as: <?= htmlspecialchars($role) ?></p>

    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5>Total Items</h5>
                    <h2><?= $total_items ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5>My Reports</h5>
                    <h2><?= $my_items ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>My Claims</h5>
                    <h2><?= $my_claims ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <a href="#" class="btn btn-primary">Report Lost Item</a>
        <a href="#" class="btn btn-success">Report Found Item</a>
        <a href="#" class="btn btn-info">Browse Items</a>
    </div>
</div>

</body>
</html>