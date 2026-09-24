<?php
require_once '../config/database.php';

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

    // Basic validation
    if (empty($full_name) || empty($email) || empty($password) || empty($role)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'danger';
    } else {
        // Check if email already exists
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Intelligent Lost and Found System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Create an Account</h4>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
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

                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Register</button>
                    </form>

                    <div class="mt-3 text-center">
                        Already have an account? <a href="login.php">Login here</a>
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
</body>
</html>