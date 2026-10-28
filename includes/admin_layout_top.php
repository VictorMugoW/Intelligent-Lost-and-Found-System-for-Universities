<style>
    /* Admin Wrapper Layout */
    .admin-wrapper { display: flex; min-height: 100vh; background: #F8FAFC; margin-top: 1rem; }
    .admin-sidebar { width: 260px; background: #0F172A; color: white; flex-shrink: 0; display: none; flex-direction: column; padding-top: 1rem; }
    .admin-content { flex-grow: 1; padding: 2rem; overflow-x: hidden; width: 100%; }
    
    @media (min-width: 992px) {
        .admin-sidebar { display: flex; }
    }

    .sidebar-link { display: flex; align-items: center; padding: 1rem 1.5rem; color: #94A3B8; text-decoration: none; font-weight: 500; transition: all 0.2s; border-left: 3px solid transparent; }
    .sidebar-link:hover, .sidebar-link.active { background: rgba(59, 130, 246, 0.1); color: white; border-left-color: #3B82F6; }
    .sidebar-link i { font-size: 1.2rem; margin-right: 12px; width: 24px; text-align: center; }
    .sidebar-section { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: #475569; padding: 1.5rem 1.5rem 0.5rem; font-weight: 700; }
</style>

<div class="admin-wrapper">
    <?php require_once 'admin_sidebar.php'; ?>
    <div class="admin-content">