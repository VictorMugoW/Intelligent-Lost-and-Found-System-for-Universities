<?php
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: user/dashboard.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intelligent Lost and Found System for Universities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Intelligent Lost and Found</span>
        <div>
            <a href="auth/login.php" class="btn btn-outline-light btn-sm">Login</a>
            <a href="auth/register.php" class="btn btn-light btn-sm">Register</a>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <h1 class="display-5 fw-bold">Intelligent Lost and Found System</h1>
            <p class="lead mt-3">A web-based platform for universities that helps students, staff, and administrators report, match, and recover lost items efficiently.</p>
            <hr>
            <a href="auth/register.php" class="btn btn-primary btn-lg me-2">Get Started</a>
            <a href="auth/login.php" class="btn btn-outline-primary btn-lg">Login</a>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h5 class="card-title">Intelligent Matching</h5>
                    <p class="card-text small">Automatic weighted-scoring algorithm matches lost and found reports.</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h5 class="card-title">QR Verification</h5>
                    <p class="card-text small">QR code-based claim verification prevents fraudulent claims.</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h5 class="card-title">Guided Reporting</h5>
                    <p class="card-text small">Step-by-step reporting assistant for accurate item details.</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h5 class="card-title">Analytics Dashboard</h5>
                    <p class="card-text small">Real-time insights on recovery rates and campus hotspots.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="text-center text-muted mt-5 mb-3">
    <small>© <?= date('Y') ?> Intelligent Lost and Found System — Strathmore University</small>
</footer>

</body>
</html>