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

$page_title = 'Intelligent Lost and Found System for Universities';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<!-- HERO -->
<section class="hero">
    <div class="container hero-content text-center">
        <h1>Intelligent Lost and Found<br><span class="text-gradient">for Universities</span></h1>
        <p class="lead">A smart web-based platform that helps students and staff report, match, and recover lost items with intelligent features.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="auth/register.php" class="btn btn-light btn-lg">Get Started</a>
            <a href="auth/login.php" class="btn btn-outline-light btn-lg">Login</a>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section class="container" style="margin-top: -4rem; position: relative; z-index: 3;">
    <div class="row g-4">
        <div class="col-md-3">
            <div class="feature-card">
                <div class="feature-icon"><i class="bi bi-diagram-3"></i></div>
                <h5>Intelligent Matching</h5>
                <p>Weighted scoring algorithm matches lost and found reports automatically.</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="feature-card">
                <div class="feature-icon"><i class="bi bi-qr-code"></i></div>
                <h5>QR Verification</h5>
                <p>QR code-based verification prevents fraudulent claims at pickup.</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="feature-card">
                <div class="feature-icon"><i class="bi bi-chat-square-text"></i></div>
                <h5>Guided Reporting</h5>
                <p>Step-by-step reporting assistant for complete, accurate item details.</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="feature-card">
                <div class="feature-icon"><i class="bi bi-bar-chart-line"></i></div>
                <h5>Analytics Dashboard</h5>
                <p>Real-time insights on recovery rates, categories, and hotspots.</p>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="container text-center mt-5 pt-5">
    <h2 class="section-title">How It Works</h2>
    <p class="section-subtitle">Three simple steps to recover your lost items</p>
    <div class="row g-4 mt-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h1 class="text-gradient fw-bold">01</h1>
                    <h5 class="mt-3">Report</h5>
                    <p class="text-muted">Report a lost or found item using our guided reporting assistant with images and details.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h1 class="text-gradient fw-bold">02</h1>
                    <h5 class="mt-3">Match</h5>
                    <p class="text-muted">Our intelligent matching engine automatically compares reports and suggests matches.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h1 class="text-gradient fw-bold">03</h1>
                    <h5 class="mt-3">Recover</h5>
                    <p class="text-muted">Claim your item, get QR code verification, and collect it securely from the lost and found office.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>