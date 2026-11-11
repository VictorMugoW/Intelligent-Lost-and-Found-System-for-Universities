<?php
session_start();
require_once '../config/database.php';
require_once '../config/mailer.php'; // For sending welcome emails

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$message_type = '';

// Handle User Suspension/Reactivation
if (isset($_GET['toggle_status']) && isset($_GET['user_id'])) {
    $target_id = intval($_GET['user_id']);
    $new_status = $_GET['toggle_status'] === 'active' ? 'suspended' : 'active';
    
    // Prevent admin from suspending themselves
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

// Handle Adding a New User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $student_id = trim($_POST['student_id'] ?? '');
    $staff_id = trim($_POST['staff_id'] ?? '');
    
    // Check if email exists
    $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $message = "Email already exists!";
        $message_type = "danger";
    } else {
        // Generate a temporary password
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
            // Send Welcome Email
            $subject = "Welcome to the University Lost & Found System";
            $body = "Hello $full_name,\n\nAn account has been created for you by the Administrator.\n\nYour Login Details:\nEmail: $email\nTemporary Password: $temp_password\n\nPlease log in and change your password immediately.";
            sendEmail($email, $subject, $body);

            $message = "User added successfully! A welcome email with their temporary password has been sent.";
            $message_type = "success";
        } else {
            $message = "Failed to add user.";
            $message_type = "danger";
        }
        $stmt->close();
    }
    $check->close();
}

// Fetch all users
$users_result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");

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
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show border-0 shadow-sm">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="border-radius: 1rem;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3">Name</th>
                        <th class="py-3">Email</th>
                        <th class="py-3">Role</th>
                        <th class="py-3">Status</th>
                        <th class="py-3 text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($u = $users_result->fetch_assoc()): ?>
                        <tr class="align-middle">
                            <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($u['full_name']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="badge bg-<?= $u['role'] === 'admin' ? 'dark' : 'primary' ?> bg-opacity-10 text-<?= $u['role'] === 'admin' ? 'dark' : 'primary' ?> border border-<?= $u['role'] === 'admin' ? 'dark' : 'primary' ?> px-2 py-1">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($u['status']) ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <?php if ($u['user_id'] !== $_SESSION['user_id']): ?>
                                    <a href="?toggle_status=<?= $u['status'] ?>&user_id=<?= $u['user_id'] ?>" 
                                       class="btn btn-sm btn-<?= $u['status'] === 'active' ? 'outline-danger' : 'outline-success' ?> rounded-pill fw-bold"
                                       onclick="return confirm('Are you sure you want to <?= $u['status'] === 'active' ? 'suspend' : 'reactivate' ?> this user?');">
                                        <?= $u['status'] === 'active' ? '<i class="bi bi-slash-circle"></i> Suspend' : '<i class="bi bi-check-circle"></i> Activate' ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
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
                    <div class="alert alert-info border-0 small mb-0">
                        <i class="bi bi-info-circle me-1"></i> A temporary password will be generated and emailed to the user automatically.
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