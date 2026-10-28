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

require_once 'config/database.php';

// Live stats
$total_items = $conn->query("SELECT COUNT(*) as c FROM items")->fetch_assoc()['c'];
$returned_items = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'returned'")->fetch_assoc()['c'];
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$total_claims = $conn->query("SELECT COUNT(*) as c FROM claims")->fetch_assoc()['c'];
$recovery_rate = ($total_items > 0) ? round(($returned_items / $total_items) * 100, 1) : 0;

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

<!-- LIVE STATS -->
<section class="container" style="margin-top: -4rem; position: relative; z-index: 3;">
    <div class="row g-4">
        <div class="col-md-3">
            <div class="stat-card stat-blue text-center">
                <div class="stat-number" data-count="<?= $total_items ?>">0</div>
                <div class="stat-label"><i class="bi bi-box-seam"></i> Items Reported</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-success text-center">
                <div class="stat-number" data-count="<?= $returned_items ?>">0</div>
                <div class="stat-label"><i class="bi bi-trophy"></i> Items Returned</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-dark text-center">
                <div class="stat-number" data-count="<?= $recovery_rate ?>" data-suffix="%">0</div>
                <div class="stat-label"><i class="bi bi-graph-up-arrow"></i> Recovery Rate</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-accent text-center">
                <div class="stat-number" data-count="<?= $total_users ?>">0</div>
                <div class="stat-label"><i class="bi bi-people"></i> Active Users</div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section class="container mt-5 pt-4 fade-in-section">
    <div class="text-center mb-4">
        <h2 class="section-title">Why Choose Us</h2>
        <p class="section-subtitle">Five intelligent features that make recovery fast and secure</p>
    </div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon float-icon"><i class="bi bi-diagram-3"></i></div>
                <h5>Intelligent Matching</h5>
                <p>Weighted scoring algorithm matches lost and found reports automatically.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon float-icon" style="animation-delay: 0.3s;"><i class="bi bi-qr-code"></i></div>
                <h5>QR Verification</h5>
                <p>QR code-based verification prevents fraudulent claims at pickup.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon float-icon" style="animation-delay: 0.6s;"><i class="bi bi-chat-square-text"></i></div>
                <h5>Guided Reporting</h5>
                <p>Step-by-step reporting assistant for complete, accurate item details.</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="feature-card">
                <div class="feature-icon float-icon" style="animation-delay: 0.9s; background: linear-gradient(135deg, #0EA5E9, #0284C7);"><i class="bi bi-bell"></i></div>
                <h5>Email Notifications</h5>
                <p>Automatic updates when matches are found, claims are approved, or items are ready for pickup.</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="feature-card">
                <div class="feature-icon float-icon" style="animation-delay: 1.2s; background: linear-gradient(135deg, #10B981, #059669);"><i class="bi bi-bar-chart-line"></i></div>
                <h5>Admin Analytics</h5>
                <p>Real-time insights on recovery rates, hotspots, categories, and trends to guide decision-making.</p>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="container text-center mt-5 pt-5 fade-in-section">
    <h2 class="section-title">How It Works</h2>
    <p class="section-subtitle">Three simple steps to recover your lost items</p>
    <div class="row g-4 mt-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h1 class="text-gradient fw-bold">01</h1>
                    <h5 class="mt-3">Report</h5>
                    <p class="text-muted">Report a lost or found item with descriptions, images, and locations.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h1 class="text-gradient fw-bold">02</h1>
                    <h5 class="mt-3">Match</h5>
                    <p class="text-muted">Our intelligent matching engine compares reports and highlights likely matches.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h1 class="text-gradient fw-bold">03</h1>
                    <h5 class="mt-3">Recover</h5>
                    <p class="text-muted">Submit a claim, get QR verification, and collect your item securely from the office.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="container mt-5 pt-4 mb-5 fade-in-section">
    <div class="card" style="background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 100%); border: none;">
        <div class="card-body p-5 text-center text-white">
            <h2 class="fw-bold mb-3" style="color: white;">Ready to report or reclaim your item?</h2>
            <p class="mb-4" style="color: rgba(255,255,255,0.8); max-width: 600px; margin: 0 auto;">
                Join the Lost and Found community and help reunite people with their belongings.
            </p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="auth/register.php" class="btn btn-light btn-lg">Create Account</a>
                <a href="auth/login.php" class="btn btn-outline-light btn-lg">Login</a>
            </div>
        </div>
    </div>
</section>

<script>
// Fade-in on scroll
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.fade-in-section').forEach(el => observer.observe(el));

// Animated counters
const counters = document.querySelectorAll('.stat-number');
const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const el = entry.target;
            const target = parseFloat(el.dataset.count);
            const suffix = el.dataset.suffix || '';
            const duration = 1500;
            const steps = 60;
            const increment = target / steps;
            let current = 0;
            let step = 0;

            const timer = setInterval(() => {
                step++;
                current += increment;
                if (step >= steps) {
                    current = target;
                    clearInterval(timer);
                }
                el.textContent = (Number.isInteger(target) ? Math.round(current) : current.toFixed(1)) + suffix;
            }, duration / steps);

            counterObserver.unobserve(el);
        }
    });
}, { threshold: 0.5 });

counters.forEach(el => counterObserver.observe(el));
</script>

<?php require_once 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>