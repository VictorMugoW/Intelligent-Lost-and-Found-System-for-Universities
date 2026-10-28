<?php
$current_user = $_SESSION['full_name'] ?? null;
$current_role = $_SESSION['role'] ?? null;
$is_logged_in = isset($_SESSION['user_id']);
$base = '/intelligent-lost-and-found';
?>
<nav class="navbar navbar-expand-lg navbar-custom">
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
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/admin/verify_found_items.php">Verifications</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/admin/manage_claims.php">Claims</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/admin/verify_qr.php">Verify QR</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/admin/analytics.php">Analytics</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/admin/reports.php">Reports</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/user/campus_map.php">Campus Map</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/user/dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/user/report_item.php">Report</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/user/browse_items.php">Browse</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/user/campus_map.php">Campus Map</a></li>
                    <?php endif; ?>

                    <li class="nav-item dropdown ms-2">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2"
                           href="#"
                           id="userDropdown"
                           role="button"
                           data-bs-toggle="dropdown"
                           aria-expanded="false"
                           style="color: white; font-weight: 500;">
                            <span style="width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, #3B82F6, #0EA5E9); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;">
                                <?= strtoupper(substr($current_user, 0, 1)) ?>
                            </span>
                            <span style="max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars(explode(' ', $current_user)[0]) ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown" style="min-width: 220px; padding: 8px; border: none; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.15);">
                            <li class="px-3 py-2" style="border-bottom: 1px solid #E2E8F0;">
                                <div style="font-weight: 700; color: #0F172A; font-size: 0.9rem;">
                                    <?= htmlspecialchars($current_user) ?>
                                </div>
                                <div style="color: #64748B; font-size: 0.8rem;">
                                    <?= ucfirst($current_role) ?>
                                </div>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= $base ?>/<?= $current_role === 'admin' ? 'admin' : 'user' ?>/profile.php" style="border-radius: 8px; padding: 8px 12px;">
                                    <i class="bi bi-person-circle me-2"></i> My Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= $base ?>/<?= $current_role === 'admin' ? 'admin' : 'user' ?>/change_password.php" style="border-radius: 8px; padding: 8px 12px;">
                                    <i class="bi bi-key me-2"></i> Change Password
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= $base ?>/auth/logout.php" style="border-radius: 8px; padding: 8px 12px;">
                                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>/auth/login.php">Login</a></li>
                    <li class="nav-item ms-2"><a class="btn btn-primary btn-sm" href="<?= $base ?>/auth/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>