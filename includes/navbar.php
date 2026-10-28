<?php
$current_user = $_SESSION['full_name'] ?? null;
$current_role = $_SESSION['role'] ?? null;
$is_logged_in = isset($_SESSION['user_id']);
$base = '/intelligent-lost-and-found';
?>
<nav class="navbar navbar-expand-lg navbar-custom shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="<?= $base ?>/<?= $is_logged_in ? ($current_role === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php') : 'index.php' ?>">
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
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/admin/dashboard.php">Dashboard</a></li>
                        
                        <!-- Management Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="adminMgmt" role="button" data-bs-toggle="dropdown">
                                Management
                            </a>
                            <ul class="dropdown-menu shadow border-0">
                                <li><a class="dropdown-item" href="<?= $base ?>/admin/verify_found_items.php"><i class="bi bi-shield-check text-warning me-2"></i> Verify Found Items</a></li>
                                <li><a class="dropdown-item" href="<?= $base ?>/admin/manage_claims.php"><i class="bi bi-clipboard-check text-primary me-2"></i> Manage Claims</a></li>
                                <li><a class="dropdown-item" href="<?= $base ?>/admin/manage_users.php"><i class="bi bi-people text-purple me-2" style="color:#8B5CF6;"></i> User Directory</a></li>
                            </ul>
                        </li>

                        <!-- Insights Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="adminInsights" role="button" data-bs-toggle="dropdown">
                                Insights
                            </a>
                            <ul class="dropdown-menu shadow border-0">
                                <li><a class="dropdown-item" href="<?= $base ?>/admin/analytics.php"><i class="bi bi-bar-chart-line text-info me-2"></i> Analytics</a></li>
                                <li><a class="dropdown-item" href="<?= $base ?>/admin/reports.php"><i class="bi bi-file-earmark-arrow-down text-secondary me-2"></i> CSV Reports</a></li>
                                <li><a class="dropdown-item" href="<?= $base ?>/user/campus_map.php"><i class="bi bi-map text-danger me-2"></i> Campus Map</a></li>
                            </ul>
                        </li>

                        <li class="nav-item ms-2">
                            <a href="<?= $base ?>/admin/verify_qr.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                                <i class="bi bi-qr-code-scan"></i> Scan QR
                            </a>
                        </li>

                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/user/dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/user/report_item.php">Report</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/user/browse_items.php">Browse</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/user/campus_map.php">Campus Map</a></li>
                    <?php endif; ?>

                    <!-- User Profile Dropdown (Shared) -->
                    <li class="nav-item dropdown ms-3 border-start border-secondary ps-3">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <span style="width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, #3B82F6, #0EA5E9); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; color: white;">
                                <?= strtoupper(substr($current_user, 0, 1)) ?>
                            </span>
                            <span class="d-none d-lg-block" style="color: white; font-weight: 500;">
                                <?= htmlspecialchars(explode(' ', $current_user)[0]) ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userDropdown" style="min-width: 220px;">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($current_user) ?></div>
                                <div class="text-muted small"><?= ucfirst($current_role) ?></div>
                            </li>
                            <li><a class="dropdown-item py-2" href="<?= $base ?>/<?= $current_role === 'admin' ? 'admin' : 'user' ?>/profile.php"><i class="bi bi-person-circle me-2"></i> My Profile</a></li>
                            <li><a class="dropdown-item py-2" href="<?= $base ?>/<?= $current_role === 'admin' ? 'admin' : 'user' ?>/change_password.php"><i class="bi bi-key me-2"></i> Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger py-2" href="<?= $base ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>/auth/login.php">Login</a></li>
                    <li class="nav-item ms-2"><a class="btn btn-primary btn-sm rounded-pill px-4" href="<?= $base ?>/auth/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>