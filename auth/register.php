<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ../user/dashboard.php');
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $student_id = trim($_POST['student_id'] ?? '');
    $staff_id = trim($_POST['staff_id'] ?? '');

    if (empty($full_name) || empty($email) || empty($password) || empty($role)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'danger';
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = 'Email already registered. Please login.';
            $message_type = 'danger';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            if ($role === 'student') {
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, student_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $full_name, $email, $phone, $hashed_password, $role, $student_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, staff_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $full_name, $email, $phone, $hashed_password, $role, $staff_id);
            }

            if ($stmt->execute()) {
                $message = 'Registration successful! You can now login.';
                $message_type = 'success';
            } else {
                $message = 'Registration failed. Please try again.';
                $message_type = 'danger';
            }
            $stmt->close();
        }
        $check->close();
    }
}

$page_title = 'Register';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container" style="margin-top: 4rem;">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="feature-icon mx-auto mb-3"><i class="bi bi-person-plus"></i></div>
                        <h3 class="fw-bold">Create Account</h3>
                        <p class="text-muted">Join the Lost and Found community</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role" id="role" class="form-select" required onchange="toggleIdField()">
                                <option value="student">Student</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                        <div class="mb-3" id="studentIdField">
                            <label class="form-label">Student Registration Number</label>
                            <input type="text" name="student_id" class="form-control">
                        </div>
                        <div class="mb-3 d-none" id="staffIdField">
                            <label class="form-label">Staff Number</label>
                            <input type="text" name="staff_id" class="form-control">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Register</button>
                    </form>

                    <div class="text-center mt-4">
                        <span class="text-muted">Already have an account?</span>
                        <a href="login.php" class="fw-bold">Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleIdField() {
    const role = document.getElementById('role').value;
    document.getElementById('studentIdField').classList.toggle('d-none', role !== 'student');
    document.getElementById('staffIdField').classList.toggle('d-none', role !== 'staff');
}
toggleIdField();
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>