<?php
session_start();
require_once '../config/database.php';
require_once '../config/mailer.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$message_type = '';

if (isset($_GET['award_sent'])) {
    $message = "Award email sent to the student successfully!";
    $message_type = "success";
} elseif (isset($_GET['award_error'])) {
    $message = "Failed to send the award email. Please check your mail configuration.";
    $message_type = "danger";
}

$search = trim($_GET['search'] ?? '');

if (isset($_GET['toggle_status']) && isset($_GET['user_id'])) {
    $target_id = intval($_GET['user_id']);
    $new_status = $_GET['toggle_status'] === 'active' ? 'suspended' : 'active';
    
    if ($target_id !== $_SESSION['user_id']) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        $stmt->bind_param("si", $new_status, $target_id);
        if ($stmt->execute()) {
            $message = "User status updated successfully to " . strtoupper($new_status);
            $message_type = "success";
        }
        $stmt->close();
    } else {
        $message = "You cannot suspend your own account!";
        $message_type = "danger";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $student_id = trim($_POST['student_id'] ?? '');
    $staff_id = trim($_POST['staff_id'] ?? '');
    
    $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $message = "Email already exists!";
        $message_type = "danger";
    } else {
        $temp_password = 'Strathmore#' . rand(1000, 9999);
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        if ($role === 'student') {
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, student_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("sssss", $full_name, $email, $hashed_password, $role, $student_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, staff_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("sssss", $full_name, $email, $hashed_password, $role, $staff_id);
        }
        
        if ($stmt->execute()) {
            $subject = "Welcome to the University Lost & Found System";
            $body = "Hello $full_name,\n\nAn account has been created for you by the Administrator.\n\nYour Login Details:\nEmail: $email\nTemporary Password: $temp_password\n\nPlease log in and change your password immediately.";
            sendEmail($email, $subject, $body);
            $message = "User added successfully! A welcome email has been sent.";
            $message_type = "success";
        } else {
            $message = "Failed to add user.";
            $message_type = "danger";
        }
        $stmt->close();
    }
    $check->close();
}

$search_sql = "";
if (!empty($search)) {
    $esc_search = $conn->real_escape_string($search);
    $search_sql = " AND (u.full_name LIKE '%$esc_search%' OR u.email LIKE '%$esc_search%' OR u.student_id LIKE '%$esc_search%' OR u.staff_id LIKE '%$esc_search%') ";
}

$student_query = "
    SELECT u.*, 
    (SELECT COUNT(*) FROM items i WHERE (i.user_id = u.user_id AND i.type = 'found' AND i.status = 'returned') OR (i.finder_id = u.user_id AND i.status IN ('found', 'returned'))) as return_count 
    FROM users u 
    WHERE u.role = 'student' $search_sql
    ORDER BY u.created_at DESC
";
$students_result = $conn->query($student_query);

$staff_query = "
    SELECT u.*, 
    (SELECT COUNT(*) FROM items i WHERE (i.user_id = u.user_id AND i.type = 'found' AND i.status = 'returned') OR (i.finder_id = u.user_id AND i.status IN ('found', 'returned'))) as return_count 
    FROM users u 
    WHERE u.role = 'staff' $search_sql
    ORDER BY u.created_at DESC
";
$staff_result = $conn->query($staff_query);

$page_title = 'Manage Users';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '../includes/admin_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1" style="color: #0f172a;">Manage Users</h2>
        <p class="text-muted mb-0">Add, view, or suspend system users.</p>
    </div>
    <button class="btn btn-primary fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-person-plus-fill me-2"></i> Add New User
    </button>
</div>

<?php if ($message): ?>
    <!-- TOAST TRIGGER -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast("<?= htmlspecialchars(addslashes($message)) ?>", "<?= $message_type ?>");
        });
    </script>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4" style="border-radius: 1rem;">
    <div class="card-body p-3">
        <form method="GET" class="d-flex gap-2">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by Name, Email, Student ID or Staff ID..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-dark fw-bold px-4">Search</button>
            <?php if (!empty($search)): ?>
                <a href="manage_users.php" class="btn btn-outline-danger fw-bold">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<ul class="nav nav-pills mb-4 gap-2" id="pills-tab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-bold rounded-pill px-4" id="pills-students-tab" data-bs-toggle="pill" data-bs-target="#pills-students" type="button" role="tab">
            <i class="bi bi-mortarboard me-1"></i> Students (<?= $students_result->num_rows ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold rounded-pill px-4 text-dark bg-white border" id="pills-staff-tab" data-bs-toggle="pill" data-bs-target="#pills-staff" type="button" role="tab">
            <i class="bi bi-briefcase me-1"></i> Staff (<?= $staff_result->num_rows ?>)
        </button>
    </li>
</ul>

<div class="tab-content" id="pills-tabContent">
    
    <!-- STUDENTS TAB -->
    <div class="tab-pane fade show active" id="pills-students" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius: 1rem;">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3">Name</th>
                                <th class="py-3">Email / ID</th>
                                <th class="py-3">Status</th>
                                <th class="py-3 text-end pe-4">Actions & Badges</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($students_result->num_rows === 0): ?>
                                <!-- CUSTOM EMPTY STATE -->
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <div class="d-flex flex-column align-items-center justify-content-center">
                                            <div class="mb-3" style="width: 80px; height: 80px; background: #f8fafc; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                <i class="bi bi-search text-muted fs-1"></i>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1">No students found</h6>
                                            <p class="text-muted small mb-0">Try adjusting your search terms or add a new student.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while($u = $students_result->fetch_assoc()): ?>
                                    <?php 
                                        $count = $u['return_count'];
                                        $admin_badge = null;
                                        if ($count >= 7) $admin_badge = ['label' => 'Gold', 'icon' => 'bi-trophy-fill', 'bg' => '#FEF3C7', 'text' => '#B45309', 'border' => '#F59E0B'];
                                        elseif ($count >= 4) $admin_badge = ['label' => 'Silver', 'icon' => 'bi-award-fill', 'bg' => '#F1F5F9', 'text' => '#475569', 'border' => '#94A3B8'];
                                        elseif ($count >= 2) $admin_badge = ['label' => 'Bronze', 'icon' => 'bi-shield-fill-check', 'bg' => '#FFF7ED', 'text' => '#9A3412', 'border' => '#EA580C'];
                                    ?>
                                    <tr class="align-middle">
                                        <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($u['full_name']) ?></td>
                                        <td class="text-muted">
                                            <?= htmlspecialchars($u['email']) ?><br>
                                            <small class="fw-bold text-dark">ID: <?= htmlspecialchars($u['student_id'] ?: 'N/A') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>">
                                                <?= ucfirst($u['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="d-flex justify-content-end align-items-center flex-wrap gap-2">
                                                <?php if ($admin_badge): ?>
                                                    <span class="badge py-2 px-3 border" style="background: <?= $admin_badge['bg'] ?>; color: <?= $admin_badge['text'] ?>; border-color: <?= $admin_badge['border'] ?> !important;">
                                                        <i class="bi <?= $admin_badge['icon'] ?>"></i> <?= $admin_badge['label'] ?> (<?= $count ?>)
                                                    </span>
                                                    <a href="send_award.php?user_id=<?= $u['user_id'] ?>" class="btn btn-sm btn-dark rounded-pill fw-bold" onclick="return confirm('Send the <?= $admin_badge['label'] ?> award email to this user?');">
                                                        <i class="bi bi-envelope-paper-fill"></i> Award
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($u['user_id'] !== $_SESSION['user_id']): ?>
                                                    <a href="?toggle_status=<?= $u['status'] ?>&user_id=<?= $u['user_id'] ?>&search=<?= urlencode($search) ?>" 
                                                       class="btn btn-sm btn-<?= $u['status'] === 'active' ? 'outline-danger' : 'outline-success' ?> rounded-pill fw-bold"
                                                       onclick="return confirm('Are you sure?');">
                                                        <?= $u['status'] === 'active' ? '<i class="bi bi-slash-circle"></i> Suspend' : '<i class="bi bi-check-circle"></i> Activate' ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- STAFF TAB -->
    <div class="tab-pane fade" id="pills-staff" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius: 1rem;">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3">Name</th>
                                <th class="py-3">Email / ID</th>
                                <th class="py-3">Status</th>
                                <th class="py-3 text-end pe-4">Actions & Badges</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($staff_result->num_rows === 0): ?>
                                <!-- CUSTOM EMPTY STATE -->
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <div class="d-flex flex-column align-items-center justify-content-center">
                                            <div class="mb-3" style="width: 80px; height: 80px; background: #f8fafc; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                <i class="bi bi-briefcase text-muted fs-1"></i>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1">No staff members found</h6>
                                            <p class="text-muted small mb-0">Try adjusting your search terms or add a new staff member.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while($u = $staff_result->fetch_assoc()): ?>
                                    <?php 
                                        $count = $u['return_count'];
                                        $admin_badge = null;
                                        if ($count >= 7) $admin_badge = ['label' => 'Gold', 'icon' => 'bi-trophy-fill', 'bg' => '#FEF3C7', 'text' => '#B45309', 'border' => '#F59E0B'];
                                        elseif ($count >= 4) $admin_badge = ['label' => 'Silver', 'icon' => 'bi-award-fill', 'bg' => '#F1F5F9', 'text' => '#475569', 'border' => '#94A3B8'];
                                        elseif ($count >= 2) $admin_badge = ['label' => 'Bronze', 'icon' => 'bi-shield-fill-check', 'bg' => '#FFF7ED', 'text' => '#9A3412', 'border' => '#EA580C'];
                                    ?>
                                    <tr class="align-middle">
                                        <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($u['full_name']) ?></td>
                                        <td class="text-muted">
                                            <?= htmlspecialchars($u['email']) ?><br>
                                            <small class="fw-bold text-dark">ID: <?= htmlspecialchars($u['staff_id'] ?: 'N/A') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>">
                                                <?= ucfirst($u['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="d-flex justify-content-end align-items-center flex-wrap gap-2">
                                                <?php if ($admin_badge): ?>
                                                    <span class="badge py-2 px-3 border" style="background: <?= $admin_badge['bg'] ?>; color: <?= $admin_badge['text'] ?>; border-color: <?= $admin_badge['border'] ?> !important;">
                                                        <i class="bi <?= $admin_badge['icon'] ?>"></i> <?= $admin_badge['label'] ?> (<?= $count ?>)
                                                    </span>
                                                    <a href="send_award.php?user_id=<?= $u['user_id'] ?>" class="btn btn-sm btn-dark rounded-pill fw-bold" onclick="return confirm('Send the <?= $admin_badge['label'] ?> award email to this user?');">
                                                        <i class="bi bi-envelope-paper-fill"></i> Award
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($u['user_id'] !== $_SESSION['user_id']): ?>
                                                    <a href="?toggle_status=<?= $u['status'] ?>&user_id=<?= $u['user_id'] ?>&search=<?= urlencode($search) ?>" 
                                                       class="btn btn-sm btn-<?= $u['status'] === 'active' ? 'outline-danger' : 'outline-success' ?> rounded-pill fw-bold"
                                                       onclick="return confirm('Are you sure?');">
                                                        <?= $u['status'] === 'active' ? '<i class="bi bi-slash-circle"></i> Suspend' : '<i class="bi bi-check-circle"></i> Activate' ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
            <div class="modal-header bg-primary text-white border-0" style="border-radius: 1rem 1rem 0 0;">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i> Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="add_user" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Address *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Role *</label>
                        <select name="role" class="form-select" required onchange="toggleIdFields(this.value)">
                            <option value="student">Student</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="mb-3" id="studentField">
                        <label class="form-label fw-bold">Student ID Number</label>
                        <input type="text" name="student_id" class="form-control" placeholder="e.g. 169641">
                    </div>
                    <div class="mb-3 d-none" id="staffField">
                        <label class="form-label fw-bold">Staff ID Number</label>
                        <input type="text" name="staff_id" class="form-control" placeholder="e.g. STF-001">
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleIdFields(role) {
    if (role === 'student') {
        document.getElementById('studentField').classList.remove('d-none');
        document.getElementById('staffField').classList.add('d-none');
    } else {
        document.getElementById('studentField').classList.add('d-none');
        document.getElementById('staffField').classList.remove('d-none');
    }
}
</script>

<?php 
require_once '../includes/admin_layout_bottom.php';
require_once '../includes/footer.php'; 
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>