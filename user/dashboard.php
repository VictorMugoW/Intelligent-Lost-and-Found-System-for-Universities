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

// Fetch Stats for Chatbot
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

$page_title = 'Dashboard';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
    /* Chatbot CSS */
    .chat-widget { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }
    
    /* Floating Animation */
    @keyframes floatUpDown {
        0% { transform: translateY(0); }
        50% { transform: translateY(-8px); }
        100% { transform: translateY(0); }
    }
    
    .chat-button { 
        width: 60px; 
        height: 60px; 
        border-radius: 50%; 
        background: #0F172A; /* Dark Slate theme */
        color: white; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 1.8rem; 
        cursor: pointer; 
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.4); 
        transition: transform 0.2s;
        animation: floatUpDown 3s ease-in-out infinite;
        border: 2px solid #3B82F6; /* Blue accent border */
    }
    
    .chat-button:hover { 
        transform: scale(1.1); 
        animation-play-state: paused;
    }
    
    .chat-window { position: absolute; bottom: 75px; right: 0; width: 350px; background: white; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); display: none; overflow: hidden; border: 1px solid #E2E8F0; }
    .chat-window.open { display: block; animation: slideUp 0.3s ease-out; }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .chat-header { background: linear-gradient(135deg, #0F172A, #1E3A8A); color: white; padding: 15px; display: flex; align-items: center; gap: 10px; }
    .chat-body { height: 300px; padding: 15px; overflow-y: auto; background: #F8FAFC; }
    .chat-message { background: white; border: 1px solid #E2E8F0; padding: 10px 15px; border-radius: 12px; border-bottom-left-radius: 2px; margin-bottom: 10px; font-size: 0.9rem; color: #333; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .chat-options { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
    .chat-option { background: white; border: 2px solid #3B82F6; color: #1E40AF; padding: 8px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; text-align: left; }
    .chat-option:hover { background: #EFF6FF; }
</style>

<div class="container mt-5">
    <div class="mb-4">
        <h2 class="section-title">Welcome back, <?= htmlspecialchars($full_name) ?> 👋</h2>
        <p class="section-subtitle">Here's an overview of your lost and found activity</p>
    </div>

    <!-- Office Status Alert -->
    <div class="alert alert-<?= $status_color ?> d-flex align-items-center shadow-sm mb-4" style="border-left: 5px solid currentColor;">
        <i class="bi bi-<?= $status_icon ?> fs-3 me-3"></i>
        <div>
            <h6 class="fw-bold mb-1">Lost & Found Office Status: <?= $status_text ?></h6>
            <p class="mb-0 small">
                Operating hours: <?= date('g:i A', strtotime($office['opening_time'])) ?> - <?= date('g:i A', strtotime($office['closing_time'])) ?>. 
                <?php if ($office['status'] === 'available'): ?>
                    You can visit the office now to collect or drop off items.
                <?php elseif ($office['status'] === 'lunch'): ?>
                    The attendant is currently on a lunch break.
                <?php else: ?>
                    The office is currently closed.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="stat-card stat-blue">
                <h6><i class="bi bi-box-seam"></i> Total Items</h6>
                <h2><?= $total_items ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card stat-dark">
                <h6><i class="bi bi-file-earmark-text"></i> My Reports</h6>
                <h2><?= $my_items ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card stat-accent">
                <h6><i class="bi bi-clipboard-check"></i> My Claims</h6>
                <h2><?= $my_claims ?></h2>
            </div>
        </div>
    </div>

    <div class="mt-5">
        <h4 class="fw-bold mb-3">Quick Actions</h4>
        <div class="row g-4">
            <div class="col-md-4">
                <a href="report_item.php" class="text-decoration-none">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: linear-gradient(135deg, #EF4444, #DC2626);"><i class="bi bi-exclamation-circle"></i></div>
                        <h5>Report Lost Item</h5>
                        <p>Report an item that you have lost.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="browse_items.php" class="text-decoration-none">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-search"></i></div>
                        <h5>Browse &amp; Claim</h5>
                        <p>Found something? Mark it as found. Lost something? Claim it.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="my_claims.php" class="text-decoration-none">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-tags"></i></div>
                        <h5>My Claims</h5>
                        <p>Track the status of claims you've submitted.</p>
                    </div>
                </a>
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
        
        // Hide main options
        document.getElementById('mainOptions').style.display = 'none';

        // Add User Message
        const userMsg = document.createElement('div');
        userMsg.className = 'chat-message';
        userMsg.style.background = '#DBEAFE';
        userMsg.style.borderColor = '#93C5FD';
        userMsg.style.marginLeft = '30px';
        userMsg.style.borderBottomRightRadius = '2px';
        userMsg.style.borderBottomLeftRadius = '12px';
        userMsg.innerHTML = 'Check System Stats';
        chatBody.appendChild(userMsg);

        // Add Bot Reply
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
            
            // Add Reset Button
            const resetDiv = document.createElement('div');
            resetDiv.className = 'chat-options';
            resetDiv.innerHTML = `<button class="chat-option" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i> Back to Menu</button>`;
            chatBody.appendChild(resetDiv);

            // Scroll to bottom
            chatBody.scrollTop = chatBody.scrollHeight;
        }, 600);
        
        chatBody.scrollTop = chatBody.scrollHeight;
    }
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>