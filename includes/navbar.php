<?php
$current_user = $_SESSION['full_name'] ?? null;
$current_role = $_SESSION['role'] ?? null;
$is_logged_in = isset($_SESSION['user_id']);
?>
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand" href="/intelligent-lost-and-found/<?= $is_logged_in ? ($current_role === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php') : 'index.php' ?>">
            <span class="brand-icon">🔍</span>
            <span>Lost &amp; Found</span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <?php if ($is_logged_in): ?>
                    <?php if ($current_role === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="/intelligent-lost-and-found/admin/dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="/intelligent-lost-and-found/admin/manage_claims.php">Claims</a></li>
                        <li class="nav-item"><a class="nav-link" href="/intelligent-lost-and-found/admin/verify_qr.php">Verify QR</a></li>
                        <li class="nav-item"><a class="nav-link" href="/intelligent-lost-and-found/admin/analytics.php">Analytics</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="/intelligent-lost-and-found/user/dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="/intelligent-lost-and-found/user/report_item.php">Report</a></li>
                        <li class="nav-item"><a class="nav-link" href="/intelligent-lost-and-found/user/browse_items.php">Browse</a></li>
                    <?php endif; ?>
                    <li class="nav-item ms-2">
                        <span class="nav-link" style="color: rgba(255,255,255,0.6);">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($current_user) ?>
                        </span>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="btn btn-outline-light btn-sm" href="/intelligent-lost-and-found/auth/logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="/intelligent-lost-and-found/auth/login.php">Login</a></li>
                    <li class="nav-item ms-2"><a class="btn btn-primary btn-sm" href="/intelligent-lost-and-found/auth/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>