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
$personal_tab = $_SESSION['tab'] ?? $_GET['tab'] ?? 'lost'; 

// --- COUNTS ---
$comm_lost_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'lost'")->fetch_assoc()['c'];
$my_lost_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE user_id = $user_id AND status IN ('lost', 'pending_verification')")->fetch_assoc()['c'];
$my_returned_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE user_id = $user_id AND status = 'returned'")->fetch_assoc()['c']; 

// --- 1. STANDARD CATEGORY ICONS ---
$placeholders = [
    'phone'  => ['icon' => 'bi-phone', 'bg' => '#e0f2fe', 'color' => '#0284c7'],
    'id'     => ['icon' => 'bi-person-vcard', 'bg' => '#f3e8ff', 'color' => '#9333ea'],
    'laptop' => ['icon' => 'bi-laptop', 'bg' => '#cffafe', 'color' => '#0891b2'],
    'wallet' => ['icon' => 'bi-wallet2', 'bg' => '#dcfce7', 'color' => '#16a34a'],
    'book'   => ['icon' => 'bi-book', 'bg' => '#fef3c7', 'color' => '#d97706'],
    'unknown'=> ['icon' => 'bi-question-circle', 'bg' => '#f1f5f9', 'color' => '#64748b'] // The Fallback
];

// --- 2. SMART KEYWORD DICTIONARY (For "Other" items) ---
// If the category is "Other", we scan the title for these words.
$keyword_rules = [
    'bottle'   => ['icon' => 'bi-cup-straw', 'bg' => '#ffedd5', 'color' => '#ea580c'],
    'flask'    => ['icon' => 'bi-cup-straw', 'bg' => '#ffedd5', 'color' => '#ea580c'],
    'hydro'    => ['icon' => 'bi-cup-straw', 'bg' => '#ffedd5', 'color' => '#ea580c'],
    'umbrella' => ['icon' => 'bi-umbrella', 'bg' => '#dbeafe', 'color' => '#2563eb'],
    'key'      => ['icon' => 'bi-key', 'bg' => '#fef9c3', 'color' => '#ca8a04'],
    'keychain' => ['icon' => 'bi-key', 'bg' => '#fef9c3', 'color' => '#ca8a04'],
    'charger'  => ['icon' => 'bi-plug', 'bg' => '#f3f4f6', 'color' => '#4b5563'],
    'cable'    => ['icon' => 'bi-plug', 'bg' => '#f3f4f6', 'color' => '#4b5563'],
    'glasses'  => ['icon' => 'bi-eyeglasses', 'bg' => '#fce7f3', 'color' => '#db2777'],
    'specs'    => ['icon' => 'bi-eyeglasses', 'bg' => '#fce7f3', 'color' => '#db2777']
];

// --- SQL QUERY BUILDER ---
$params = [];
$types = "";

if ($view_mode === 'community') {
    $sql = "SELECT i.*, u.full_name FROM items i JOIN users u ON i.user_id = u.user_id WHERE i.status = 'lost'";
} else {
    $sql = "SELECT i.*, u.full_name FROM items i JOIN users u ON i.user_id = u.user_id WHERE i.user_id = ?";
    $params[] = $user_id; 
    $types .= "i";
    
    if ($personal_tab === 'lost') {
        $sql .= " AND i.status IN ('lost', 'pending_verification')";
    } elseif ($personal_tab === 'returned') {
        $sql .= " AND i.status = 'returned'";
    }
}

if (!empty($search)) {
    $sql .= " AND (i.item_name LIKE ? OR i.description LIKE ? OR i.location LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}

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

<style>
    .item-card {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        background: #ffffff;
        transition: all 0.25s ease;
        overflow: hidden;
    }
    .item-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.08) !important;
        border-color: #cbd5e1;
    }
    .item-img-wrapper {
        height: 200px;
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid #f1f5f9;
        position: relative;
        overflow: hidden;
    }
    .item-img-wrapper img.uploaded {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    /* Icon Placeholder Style */
    .icon-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 5rem; 
        transition: transform 0.3s ease;
    }
    .item-card:hover .icon-placeholder {
        transform: scale(1.1);
    }

    .status-border {
        position: absolute;
        top: 0; left: 0; bottom: 0;
        width: 6px;
        z-index: 2;
    }
</style>

<div class="container py-5" style="max-width: 1200px;">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h1 class="section-title mb-1">Directory</h1>
            <p class="section-subtitle mb-0">Browse community reports or manage your items.</p>
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
    <div class="card mb-5" style="box-shadow: var(--shadow-md); border: none;">
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
                    <div class="feature-icon mx-auto mb-3"><i class="bi bi-inbox"></i></div>
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
                
                $accent_color = 'var(--danger)';
                $status_text = 'LOST';
                $status_color = 'var(--danger)';
                
                if ($is_pending) {
                    $accent_color = 'var(--warning)';
                    $status_text = 'PENDING';
                    $status_color = 'var(--warning)';
                } elseif ($item['status'] === 'returned') {
                    $accent_color = 'var(--success)';
                    $status_text = 'RETURNED';
                    $status_color = 'var(--success)';
                }

                // --- THE SMART ICON LOGIC ---
                $cat_lower = strtolower($item['category']);
                $item_name_lower = strtolower($item['item_name']);
                
                $icon_data = $placeholders['unknown']; // Default Fallback (Question Mark)
                
                // 1. Check if it's a standard category
                if (isset($placeholders[$cat_lower])) {
                    $icon_data = $placeholders[$cat_lower];
                } 
                
                // 2. If it's "Other", scan the title for keywords
                if ($cat_lower === 'other' || $cat_lower === '') {
                    $keyword_matched = false;
                    foreach ($keyword_rules as $keyword => $data) {
                        if (strpos($item_name_lower, $keyword) !== false) {
                            $icon_data = $data;
                            $keyword_matched = true;
                            break; // Stop searching once we find a match
                        }
                    }
                    // If no keyword matched, it stays as the 'unknown' question mark
                }

                $has_upload = !empty($item['image_path']);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 item-card shadow-sm position-relative">
                        
                        <div class="status-border" style="background: <?= $accent_color ?>;"></div>

                        <!-- Image or Icon Area -->
                        <div class="item-img-wrapper">
                            <?php if ($has_upload): ?>
                                <img src="../<?= htmlspecialchars($item['image_path']) ?>" class="uploaded" alt="<?= htmlspecialchars($item['item_name']) ?>">
                            <?php else: ?>
                                <!-- Beautiful Smart Icon Placeholder -->
                                <div class="icon-placeholder" style="background-color: <?= $icon_data['bg'] ?>; color: <?= $icon_data['color'] ?>;">
                                    <i class="bi <?= $icon_data['icon'] ?>"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Card Body -->
                        <div class="card-body d-flex flex-column p-4">
                            <div class="mb-2 d-flex gap-2">
                                <span class="badge rounded-pill" style="background: <?= $status_color ?>; color: white; padding: 0.5em 0.8em;">
                                    <?= $status_text ?>
                                </span>
                                <span class="badge rounded-pill bg-light text-dark border" style="padding: 0.5em 0.8em;">
                                    <?= htmlspecialchars($item['category']) ?>
                                </span>
                            </div>
                            
                            <h5 class="fw-bold mb-3 mt-1 text-dark"><?= htmlspecialchars($item['item_name']) ?></h5>
                            
                            <div class="text-muted small mb-4 flex-grow-1" style="line-height: 1.8;">
                                <div class="text-truncate"><i class="bi bi-geo-alt-fill me-2" style="color: var(--primary);"></i> <?= htmlspecialchars($item['location'] ?: 'Location not specified') ?></div>
                                <div><i class="bi bi-calendar-event-fill me-2" style="color: var(--primary);"></i> <?= htmlspecialchars($item['date_lost_found'] ?: 'Date not specified') ?></div>
                                <div><i class="bi bi-person-fill me-2" style="color: var(--primary);"></i> By: <strong><?= $is_my_report ? 'You' : htmlspecialchars($item['full_name']) ?></strong></div>
                            </div>

                            <!-- Actions -->
                            <?php if ($view_mode === 'community'): ?>
                                <?php if ($is_my_report): ?>
                                    <button class="btn btn-outline-secondary w-100 fw-bold rounded-pill" disabled>
                                        <i class="bi bi-person-check me-1"></i> Your Report
                                    </button>
                                <?php else: ?>
                                    <a href="mark_found.php?item_id=<?= $item['item_id'] ?>" class="btn btn-success w-100 fw-bold rounded-pill">
                                        <i class="bi bi-hand-thumbs-up me-1"></i> I Found This
                                    </a>
                                <?php endif; ?>
                                
                            <?php elseif ($view_mode === 'personal'): ?>
                                <?php if ($personal_tab === 'lost'): ?>
                                    <?php if ($is_pending): ?>
                                        <div class="alert alert-warning small mb-0 p-2 border-0 text-center fw-semibold rounded-pill">
                                            <i class="bi bi-info-circle me-1"></i> Found by someone. Verifying.
                                        </div>
                                    <?php else: ?>
                                        <button onclick="confirmResolve(<?= $item['item_id'] ?>)" class="btn btn-outline-success w-100 fw-bold rounded-pill">
                                            <i class="bi bi-check-circle me-1"></i> I Found It Myself
                                        </button>
                                    <?php endif; ?>
                                    
                                <?php elseif ($personal_tab === 'returned'): ?>
                                    <button class="btn btn-success text-white w-100 fw-bold rounded-pill" disabled style="opacity: 0.9;">
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

<script>
function confirmResolve(itemId) {
    if (confirm("Did you find this item yourself? \n\nIt will be marked as 'Returned' and removed from the community list.")) {
        window.location.href = "mark_resolved.php?item_id=" + itemId;
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>