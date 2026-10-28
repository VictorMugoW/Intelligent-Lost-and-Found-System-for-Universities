<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$filter_category = $_GET['category'] ?? 'all';

$view_mode = $_GET['view'] ?? 'community'; 
$personal_tab = $_GET['tab'] ?? 'lost'; 

// --- COUNTS ---
// Community Count: All lost items in the system
$comm_lost_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'lost'")->fetch_assoc()['c'];

// Personal Counts: Strictly filtered by the logged-in user
$my_lost_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE user_id = $user_id AND status IN ('lost', 'pending_verification')")->fetch_assoc()['c'];
$my_returned_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE user_id = $user_id AND status = 'returned'")->fetch_assoc()['c']; 

// --- SQL QUERY BUILDER ---
$params = [];
$types = "";

if ($view_mode === 'community') {
    // COMMUNITY FEED: Shows ALL lost items.
    $sql = "SELECT i.*, u.full_name FROM items i JOIN users u ON i.user_id = u.user_id WHERE i.status = 'lost'";
} else {
    // PERSONAL DASHBOARD: Strictly shows only this user's items
    $sql = "SELECT i.*, u.full_name FROM items i JOIN users u ON i.user_id = u.user_id WHERE i.user_id = ?";
    $params[] = $user_id; 
    $types .= "i";
    
    if ($personal_tab === 'lost') {
        $sql .= " AND i.status IN ('lost', 'pending_verification')";
    } elseif ($personal_tab === 'returned') {
        $sql .= " AND i.status = 'returned'";
    }
}

// Apply Search
if (!empty($search)) {
    $sql .= " AND (i.item_name LIKE ? OR i.description LIKE ? OR i.location LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}

// Apply Category
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

$page_title = 'Directory';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

function buildUrl($view, $tab, $search, $category) {
    $url = "?view=$view&tab=$tab";
    if (!empty($search)) $url .= "&search=" . urlencode($search);
    if ($category !== 'all') $url .= "&category=$category";
    return $url;
}
?>

<div class="container py-5" style="max-width: 1200px;">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h1 class="section-title mb-1">Directory</h1>
            <p class="section-subtitle mb-0">Browse community reports or manage your lost and returned items.</p>
        </div>
        <a href="report_item.php" class="btn btn-primary fw-bold px-4 py-2">
            <i class="bi bi-plus-lg me-2"></i> Report New Item
        </a>
    </div>

    <!-- Mode Switcher -->
    <div class="text-center mb-5">
        <div class="d-inline-flex p-1 rounded-pill shadow-sm" style="background: var(--white); border: 2px solid #E2E8F0;">
            <a href="<?= buildUrl('community', 'lost', $search, $filter_category) ?>" 
               class="btn rounded-pill px-4 fw-bold <?= $view_mode === 'community' ? 'text-white' : 'text-muted' ?>"
               style="<?= $view_mode === 'community' ? 'background: var(--primary);' : '' ?>">
                <i class="bi bi-globe me-2"></i> Community (<?= $comm_lost_count ?>)
            </a>
            <a href="<?= buildUrl('personal', 'lost', $search, $filter_category) ?>" 
               class="btn rounded-pill px-4 fw-bold <?= $view_mode === 'personal' ? 'text-white' : 'text-muted' ?>"
               style="<?= $view_mode === 'personal' ? 'background: var(--primary);' : '' ?>">
                <i class="bi bi-person me-2"></i> My Dashboard
            </a>
        </div>
    </div>

    <!-- Sub-Tabs (Personal Mode only) -->
    <?php if ($view_mode === 'personal'): ?>
        <ul class="nav nav-pills justify-content-center mb-5 gap-2">
            <li class="nav-item">
                <a class="nav-link <?= $personal_tab === 'lost' ? 'active' : '' ?> fw-bold" 
                   href="<?= buildUrl('personal', 'lost', $search, $filter_category) ?>"
                   style="<?= $personal_tab === 'lost' ? 'background: var(--warning); color: var(--black);' : 'background: var(--white); color: var(--gray); border: 1px solid #E2E8F0;' ?>">
                    My Lost Reports <?php if($my_lost_count > 0): ?><span class="badge bg-dark ms-1"><?= $my_lost_count ?></span><?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $personal_tab === 'returned' ? 'active' : '' ?> fw-bold" 
                   href="<?= buildUrl('personal', 'returned', $search, $filter_category) ?>"
                   style="<?= $personal_tab === 'returned' ? 'background: var(--warning); color: var(--black);' : 'background: var(--white); color: var(--gray); border: 1px solid #E2E8F0;' ?>">
                    My Returned Items <?php if($my_returned_count > 0): ?><span class="badge bg-dark ms-1"><?= $my_returned_count ?></span><?php endif; ?>
                </a>
            </li>
        </ul>
    <?php endif; ?>

    <!-- Search Toolbar -->
    <div class="card mb-5" style="box-shadow: var(--shadow-md);">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-center m-0">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view_mode) ?>">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($personal_tab) ?>">
                
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by name, description, or location..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                
                <div class="col-md-4">
                    <select name="category" class="form-select">
                        <option value="all">All Categories</option>
                        <?php foreach (['Phone','ID','Laptop','Wallet','Book','Other'] as $cat): ?>
                            <option value="<?= $cat ?>" <?= $filter_category === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark w-100 fw-bold">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Items Grid -->
    <div class="row g-4">
        <?php if ($items->num_rows === 0): ?>
            <div class="col-12">
                <div class="card text-center p-5">
                    <div class="feature-icon mx-auto mb-3">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <h5 class="fw-bold">No items found</h5>
                    <p class="text-muted mb-4">
                        <?= $view_mode === 'community' ? 'There are currently no lost items reported by the community.' : 'You have no items in this category.' ?>
                    </p>
                    <a href="report_item.php" class="btn btn-primary px-4" style="width: fit-content; margin: 0 auto;">Report an Item</a>
                </div>
            </div>
        <?php else: ?>
            <?php while ($item = $items->fetch_assoc()): ?>
                <?php
                $is_pending = ($item['status'] === 'pending_verification');
                $is_my_report = ($item['user_id'] == $user_id); 
                
                // Set visual accents based on status
                $accent_color = 'var(--danger)';
                $icon_class = 'bi-exclamation-triangle';
                $status_text = 'LOST';
                $status_color = 'var(--danger)';
                
                if ($is_pending) {
                    $accent_color = 'var(--warning)';
                    $icon_class = 'bi-hourglass-split';
                    $status_text = 'PENDING';
                    $status_color = 'var(--warning)';
                } elseif ($item['status'] === 'returned') {
                    // CHANGED TO GREEN SUCCESS COLORS
                    $accent_color = 'var(--success)';
                    $icon_class = 'bi-trophy'; 
                    $status_text = 'RETURNED';
                    $status_color = 'var(--success)';
                }
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100" style="border-left: 6px solid <?= $accent_color ?>;">
                        
                        <?php if (!empty($item['image_path'])): ?>
                            <img src="../<?= htmlspecialchars($item['image_path']) ?>" style="height: 200px; object-fit: cover; width: 100%;">
                        <?php else: ?>
                            <!-- FIXED PLACEHOLDER: Soft background, trophy, and "Reunited" text -->
                            <div style="height: 200px; background: <?= $accent_color ?>10; display: flex; flex-direction: column; align-items: center; justify-content: center; border-bottom: 1px solid #e2e8f0;">
                                <i class="bi <?= $icon_class ?> mb-2" style="font-size: 3.5rem; color: <?= $accent_color ?>; opacity: 0.8;"></i>
                                <?php if ($item['status'] === 'returned'): ?>
                                    <span class="fw-bold" style="color: <?= $accent_color ?>; letter-spacing: 1px; font-size: 0.85rem;">REUNITED WITH OWNER</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column">
                            <div class="mb-3 d-flex gap-2">
                                <span class="badge" style="background: <?= $status_color ?>; color: white;">
                                    <?= $status_text ?>
                                </span>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($item['category']) ?></span>
                            </div>
                            
                            <h5 class="fw-bold mb-2"><?= htmlspecialchars($item['item_name']) ?></h5>
                            
                            <div class="text-muted small mb-4 flex-grow-1">
                                <div class="mb-1"><i class="bi bi-geo-alt me-2" style="color: var(--primary);"></i> <?= htmlspecialchars($item['location'] ?: 'Location not specified') ?></div>
                                <div class="mb-1"><i class="bi bi-calendar-event me-2" style="color: var(--primary);"></i> <?= htmlspecialchars($item['date_lost_found'] ?: 'Date not specified') ?></div>
                                <div><i class="bi bi-person me-2" style="color: var(--primary);"></i> By: <strong class="text-dark"><?= $is_my_report ? 'You' : htmlspecialchars($item['full_name']) ?></strong></div>
                            </div>

                            <!-- Actions -->
                            <?php if ($view_mode === 'community'): ?>
                                <?php if ($is_my_report): ?>
                                    <button class="btn btn-outline-secondary w-100 fw-bold" disabled>
                                        <i class="bi bi-person-check me-1"></i> Your Report
                                    </button>
                                <?php else: ?>
                                    <a href="mark_found.php?item_id=<?= $item['item_id'] ?>" class="btn btn-success w-100 fw-bold">
                                        <i class="bi bi-hand-thumbs-up me-1"></i> I Found This
                                    </a>
                                <?php endif; ?>
                                
                            <?php elseif ($view_mode === 'personal'): ?>
                                <?php if ($personal_tab === 'lost'): ?>
                                    <?php if ($is_pending): ?>
                                        <div class="alert alert-warning small mb-0 p-2 border-0 text-center fw-semibold">
                                            <i class="bi bi-info-circle me-1"></i> Found by someone. Verifying.
                                        </div>
                                    <?php else: ?>
                                        <button class="btn btn-light text-muted w-100 fw-bold border" disabled>
                                            <i class="bi bi-hourglass-split me-1"></i> Searching
                                        </button>
                                    <?php endif; ?>
                                    
                                <?php elseif ($personal_tab === 'returned'): ?>
                                    <!-- FIXED BUTTON: Solid Green -->
                                    <button class="btn btn-success text-white w-100 fw-bold" disabled style="opacity: 0.9;">
                                        <i class="bi bi-trophy me-1"></i> Successfully Returned
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>