<?php
// Get the current page name to highlight the active link
$current_page = basename($_SERVER['PHP_SELF']);

// Re-fetch pending counts for the sidebar badges
$sidebar_pending_verifications = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'pending_verification'")->fetch_assoc()['c'];
$sidebar_pending_claims = $conn->query("SELECT COUNT(*) as c FROM claims WHERE status = 'pending'")->fetch_assoc()['c'];
?>
<div class="admin-sidebar rounded-end shadow">
    <div class="px-4 py-3 mb-2">
        <h5 class="fw-bold mb-0 text-white">Admin Panel</h5>
        <small class="text-muted">Lost & Found System</small>
    </div>
    
    <div class="sidebar-section">Main</div>
    <a href="/intelligent-lost-and-found/admin/dashboard.php" class="sidebar-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
        <i class="bi bi-grid-1x2-fill"></i> Dashboard
    </a>
    
    <div class="sidebar-section">Management</div>
    <a href="/intelligent-lost-and-found/admin/verify_found_items.php" class="sidebar-link <?= ($current_page == 'verify_found_items.php') ? 'active' : '' ?>">
        <i class="bi bi-shield-check"></i> Verify Items 
        <?php if($sidebar_pending_verifications > 0): ?><span class="badge bg-danger ms-auto"><?= $sidebar_pending_verifications ?></span><?php endif; ?>
    </a>
    <a href="/intelligent-lost-and-found/admin/manage_claims.php" class="sidebar-link <?= ($current_page == 'manage_claims.php') ? 'active' : '' ?>">
        <i class="bi bi-clipboard-check"></i> Manage Claims
        <?php if($sidebar_pending_claims > 0): ?><span class="badge bg-danger ms-auto"><?= $sidebar_pending_claims ?></span><?php endif; ?>
    </a>
    <a href="/intelligent-lost-and-found/admin/manage_users.php" class="sidebar-link <?= ($current_page == 'manage_users.php' || $current_page == 'user_details.php') ? 'active' : '' ?>">
        <i class="bi bi-people"></i> User Directory
    </a>
    <a href="/intelligent-lost-and-found/admin/verify_qr.php" class="sidebar-link <?= ($current_page == 'verify_qr.php') ? 'active' : '' ?>">
        <i class="bi bi-qr-code-scan"></i> Scan QR
    </a>
    
    <div class="sidebar-section">Insights</div>
    <a href="/intelligent-lost-and-found/admin/analytics.php" class="sidebar-link <?= ($current_page == 'analytics.php') ? 'active' : '' ?>">
        <i class="bi bi-bar-chart-line"></i> Analytics
    </a>
    <a href="/intelligent-lost-and-found/admin/reports.php" class="sidebar-link <?= ($current_page == 'reports.php') ? 'active' : '' ?>">
        <i class="bi bi-file-earmark-arrow-down"></i> CSV Reports
    </a>
    <a href="/intelligent-lost-and-found/admin/campus_map_editor.php" class="sidebar-link <?= ($current_page == 'campus_map_editor.php') ? 'active' : '' ?>">
        <i class="bi bi-map"></i> Edit Campus Map
    </a>
</div>