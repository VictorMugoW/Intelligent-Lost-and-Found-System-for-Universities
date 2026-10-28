<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];

// Fetch current office status
$office = $conn->query("SELECT status, opening_time, closing_time FROM office_settings WHERE id = 1")->fetch_assoc();
if (!$office) {
    $office = ['status' => 'closed', 'opening_time' => '08:00:00', 'closing_time' => '17:00:00'];
}

$status_color = 'success';
$status_text = 'Available Now';
$status_icon = 'check-circle-fill';
if ($office['status'] === 'lunch') {
    $status_color = 'warning';
    $status_text = 'On Lunch Break';
    $status_icon = 'cup-hot-fill';
} elseif ($office['status'] === 'closed') {
    $status_color = 'danger';
    $status_text = 'Closed';
    $status_icon = 'x-circle-fill';
}

// Fetch Stats for Chatbot & Display
$total_items = $conn->query("SELECT COUNT(*) as c FROM items")->fetch_assoc()['c'];
$returned_items = $conn->query("SELECT COUNT(*) as c FROM items WHERE status = 'returned'")->fetch_assoc()['c'];
$recovery_rate = ($total_items > 0) ? round(($returned_items / $total_items) * 100, 1) : 0;

// User specific stats
$stmt = $conn->prepare("SELECT COUNT(*) as c FROM items WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_items = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM claims WHERE claimed_by = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_claims = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// Fetch Recent Activity (Last 3 reports by this user)
$recent_items = $conn->prepare("SELECT item_name, type, status, created_at FROM items WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$recent_items->bind_param("i", $user_id);
$recent_items->execute();
$recent_result = $recent_items->get_result();
$recent_items->close();

$page_title = 'Dashboard';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
    /* Chatbot CSS */
    .chat-widget { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }
    @keyframes floatUpDown { 0% { transform: translateY(0); } 50% { transform: translateY(-8px); } 100% { transform: translateY(0); } }
    .chat-button { width: 60px; height: 60px; border-radius: 50%; background: #0F172A; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; cursor: pointer; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.4); transition: transform 0.2s; animation: floatUpDown 3s ease-in-out infinite; border: 2px solid #3B82F6; }
    .chat-button:hover { transform: scale(1.1); animation-play-state: paused; }
    .chat-window { position: absolute; bottom: 75px; right: 0; width: 350px; background: white; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); display: none; overflow: hidden; border: 1px solid #E2E8F0; }
    .chat-window.open { display: block; animation: slideUp 0.3s ease-out; }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .chat-header { background: linear-gradient(135deg, #0F172A, #1E3A8A); color: white; padding: 15px; display: flex; align-items: center; gap: 10px; }
    .chat-body { height: 300px; padding: 15px; overflow-y: auto; background: #F8FAFC; }
    .chat-message { background: white; border: 1px solid #E2E8F0; padding: 10px 15px; border-radius: 12px; border-bottom-left-radius: 2px; margin-bottom: 10px; font-size: 0.9rem; color: #333; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .chat-options { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
    .chat-option { background: white; border: 2px solid #3B82F6; color: #1E40AF; padding: 8px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; text-align: left; }
    .chat-option:hover { background: #EFF6FF; }

    /* Professional Dashboard CSS */
    .welcome-card {
        background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 100%);
        color: white;
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.2);
    }
    .welcome-card::after {
        content: '\F4E4';
        font-family: 'bootstrap-icons';
        position: absolute;
        right: -20px;
        bottom: -20px;
        font-size: 10rem;
        opacity: 0.1;
        transform: rotate(-15deg);
    }
    .welcome-card h2 { color: white; font-weight: 800; margin-bottom: 0.5rem; }
    .welcome-card p { color: rgba(255,255,255,0.8); margin: 0; font-size: 1.1rem; }
    
    .stat-card-pro {
        border-radius: 16px;
        padding: 1.5rem;
        color: white;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        transition: transform 0.2s;
    }
    .stat-card-pro:hover { transform: translateY(-3px); }
    .stat-card-pro .icon-box {
        width: 50px; height: 50px; border-radius: 12px;
        background: rgba(255,255,255,0.2);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem;
    }
    .stat-card-pro h6 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; margin: 0; color: white; }
    .stat-card-pro h3 { font-size: 1.8rem; font-weight: 800; margin: 0; line-height: 1; color: white; }
    
    .activity-item {
        display: flex;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid #E2E8F0;
        transition: background 0.2s;
    }
    .activity-item:hover { background: #F8FAFC; }
    .activity-icon {
        width: 40px; height: 40px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin-right: 1rem; font-size: 1.2rem;
    }
</style>

<div class="container mt-5">
    
    <!-- Personalized Welcome Banner -->
    <div class="welcome-card d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div style="position: relative; z-index: 2;">
            <h2>Hello, <?= htmlspecialchars($full_name) ?> 👋</h2>
            <p>Welcome to your dashboard. Here is what's happening with your items today.</p>
        </div>
        <div style="position: relative; z-index: 2;">
            <span class="badge bg-light text-dark px-3 py-2 fs-6">
                <i class="bi bi-shield-check text-primary"></i> <?= ucfirst($role) ?> Account
            </span>
        </div>
    </div>

    <!-- Office Status Alert -->
    <div class="alert alert-<?= $status_color ?> d-flex align-items-center shadow-sm mb-4" style="border-left: 5px solid currentColor; border-radius: 12px;">
        <i class="bi bi-<?= $status_icon ?> fs-3 me-3"></i>
        <div>
            <h6 class="fw-bold mb-1">Lost & Found Office: <?= $status_text ?></h6>
            <p class="mb-0 small">
                Hours: <?= date('g:i A', strtotime($office['opening_time'])) ?> - <?= date('g:i A', strtotime($office['closing_time'])) ?>. 
                <?php if ($office['status'] === 'available'): ?>
                    You can visit the office now.
                <?php elseif ($office['status'] === 'lunch'): ?>
                    The attendant is currently on a lunch break.
                <?php else: ?>
                    The office is currently closed.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card-pro" style="background: linear-gradient(135deg, #1E40AF, #3B82F6);">
                <div class="icon-box"><i class="bi bi-box-seam"></i></div>
                <div>
                    <h6>Total Items</h6>
                    <h3><?= $total_items ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <!-- FIXED COLOR HERE -->
            <div class="stat-card-pro" style="background: linear-gradient(135deg, #0F172A, #1E3A8A);">
                <div class="icon-box"><i class="bi bi-file-earmark-text"></i></div>
                <div>
                    <h6>My Reports</h6>
                    <h3><?= $my_items ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-pro" style="background: linear-gradient(135deg, #0EA5E9, #0284C7);">
                <div class="icon-box"><i class="bi bi-clipboard-check"></i></div>
                <div>
                    <h6>My Claims</h6>
                    <h3><?= $my_claims ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Quick Actions -->
        <div class="col-md-7">
            <h4 class="fw-bold mb-3">Quick Actions</h4>
            <div class="row g-3">
                <div class="col-md-6">
                    <a href="report_item.php" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #EF4444;">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3">
                                    <div style="width: 45px; height: 45px; border-radius: 10px; background: #FEE2E2; color: #EF4444; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                                        <i class="bi bi-exclamation-circle"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-0 text-dark">Report Lost Item</h5>
                                        <p class="text-muted small mb-0">Lost something? Start here.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="browse_items.php" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #3B82F6;">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3">
                                    <div style="width: 45px; height: 45px; border-radius: 10px; background: #DBEAFE; color: #3B82F6; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                                        <i class="bi bi-search"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-0 text-dark">Browse & Claim</h5>
                                        <p class="text-muted small mb-0">Search found items.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="my_claims.php" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #10B981;">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3">
                                    <div style="width: 45px; height: 45px; border-radius: 10px; background: #D1FAE5; color: #10B981; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                                        <i class="bi bi-tags"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-0 text-dark">My Claims</h5>
                                        <p class="text-muted small mb-0">Track claim status.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="campus_map.php" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #F59E0B;">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3">
                                    <div style="width: 45px; height: 45px; border-radius: 10px; background: #FEF3C7; color: #F59E0B; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                                        <i class="bi bi-map"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-0 text-dark">Campus Map</h5>
                                        <p class="text-muted small mb-0">View hotspots.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-md-5">
            <h4 class="fw-bold mb-3">Recent Activity</h4>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if ($recent_result->num_rows === 0): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-clock-history fs-1 mb-2"></i>
                            <p class="mb-0">No activity yet.</p>
                            <a href="report_item.php" class="btn btn-sm btn-primary mt-3">Make your first report</a>
                        </div>
                    <?php else: ?>
                        <?php while ($item = $recent_result->fetch_assoc()): ?>
                            <?php 
                                $icon_color = '#EF4444'; $icon_bg = '#FEE2E2'; $icon_class = 'exclamation-circle';
                                if ($item['status'] === 'returned') { $icon_color = '#10B981'; $icon_bg = '#D1FAE5'; $icon_class = 'check-circle'; }
                                elseif ($item['type'] === 'found') { $icon_color = '#3B82F6'; $icon_bg = '#DBEAFE'; $icon_class = 'search'; }
                            ?>
                            <div class="activity-item">
                                <div class="activity-icon" style="background: <?= $icon_bg ?>; color: <?= $icon_color ?>;">
                                    <i class="bi bi-<?= $icon_class ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($item['item_name']) ?></h6>
                                    <small class="text-muted">
                                        <?= date('M d, g:i A', strtotime($item['created_at'])) ?>
                                    </small>
                                </div>
                                <span class="badge bg-<?= $item['status'] === 'returned' ? 'success' : ($item['type'] === 'lost' ? 'danger' : 'primary') ?>">
                                    <?= ucfirst($item['status']) ?>
                                </span>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CHATBOT WIDGET -->
<div class="chat-widget">
    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            <i class="bi bi-robot fs-4"></i>
            <div>
                <div class="fw-bold" style="font-size: 0.95rem;">L&F Assistant</div>
                <div style="font-size: 0.75rem; opacity: 0.8;">Online</div>
            </div>
            <i class="bi bi-x-lg ms-auto" style="cursor: pointer;" onclick="toggleChat()"></i>
        </div>
        <div class="chat-body" id="chatBody">
            <div class="chat-message">
                Hey <strong><?= htmlspecialchars(explode(' ', $full_name)[0]) ?></strong>! 👋<br>
                How can I help you today?
            </div>
            
            <div class="chat-options" id="mainOptions">
                <button class="chat-option" onclick="botShowStats()"><i class="bi bi-bar-chart"></i> Check System Stats</button>
                <button class="chat-option" onclick="window.location.href='report_item.php'"><i class="bi bi-plus-circle"></i> Report a Lost Item</button>
                <button class="chat-option" onclick="window.location.href='browse_items.php?status=found'"><i class="bi bi-search"></i> Browse Found Items</button>
                <button class="chat-option" onclick="window.location.href='campus_map.php'"><i class="bi bi-map"></i> View Campus Map</button>
            </div>
        </div>
    </div>
    <div class="chat-button" onclick="toggleChat()">
        <i class="bi bi-robot"></i>
    </div>
</div>

<script>
    function toggleChat() {
        const win = document.getElementById('chatWindow');
        win.classList.toggle('open');
    }

    function botShowStats() {
        const chatBody = document.getElementById('chatBody');
        document.getElementById('mainOptions').style.display = 'none';

        const userMsg = document.createElement('div');
        userMsg.className = 'chat-message';
        userMsg.style.background = '#DBEAFE';
        userMsg.style.borderColor = '#93C5FD';
        userMsg.style.marginLeft = '30px';
        userMsg.style.borderBottomRightRadius = '2px';
        userMsg.style.borderBottomLeftRadius = '12px';
        userMsg.innerHTML = 'Check System Stats';
        chatBody.appendChild(userMsg);

        setTimeout(() => {
            const botMsg = document.createElement('div');
            botMsg.className = 'chat-message';
            botMsg.innerHTML = `
                Here are the live stats! 📊<br><br>
                📦 <strong>Total Items:</strong> <?= $total_items ?><br>
                ✅ <strong>Recovered:</strong> <?= $returned_items ?><br>
                📈 <strong>Recovery Rate:</strong> <?= $recovery_rate ?>%<br><br>
                You can check the <a href="campus_map.php" style="color:#1E40AF; font-weight:bold;">Campus Map</a> to see where most items are lost.
            `;
            chatBody.appendChild(botMsg);
            
            const resetDiv = document.createElement('div');
            resetDiv.className = 'chat-options';
            resetDiv.innerHTML = `<button class="chat-option" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i> Back to Menu</button>`;
            chatBody.appendChild(resetDiv);
            chatBody.scrollTop = chatBody.scrollHeight;
        }, 600);
        chatBody.scrollTop = chatBody.scrollHeight;
    }
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>