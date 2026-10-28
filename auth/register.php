<?php
session_start();
require_once '../config/database.php';
require_once '../includes/password_helper.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ../user/dashboard.php');
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone_suffix = trim($_POST['phone_suffix'] ?? '');
    $password = $_POST['password'];
    $role = $_POST['role'];
    $student_id = trim($_POST['student_id'] ?? '');
    $staff_id = trim($_POST['staff_id'] ?? '');

    $phone = '';
    if (!empty($phone_suffix)) {
        $phone = '+254' . $phone_suffix;
    }

    if (empty($full_name) || empty($email) || empty($password) || empty($role)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'danger';
    } elseif (!empty($phone_suffix) && (strlen($phone_suffix) !== 9 || !ctype_digit($phone_suffix))) {
        $message = 'Phone number must be exactly 9 digits (e.g., 703364745).';
        $message_type = 'danger';
    } else {
        $pw_errors = validatePassword($password);
        if (!empty($pw_errors)) {
            $message = 'Password must contain: ' . implode(', ', $pw_errors) . '.';
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
}

$page_title = 'Register';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            
            <!-- Back to Home -->
            <a href="../index.php" class="text-decoration-none fw-bold mb-4 d-inline-block" style="color: var(--primary);">
                <i class="bi bi-arrow-left me-1"></i> Back to Home
            </a>

            <div class="card border-0 shadow-lg" style="border-radius: 1rem; overflow: hidden;">
                <!-- Card Header Gradient -->
                <div class="card-header text-white text-center py-4" style="background: linear-gradient(135deg, var(--primary-dark), var(--primary-light)); border: none;">
                    <div class="feature-icon mx-auto mb-3" style="background: rgba(255,255,255,0.2); width: 64px; height: 64px; border-radius: 1rem; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <h3 class="fw-bold mb-1" style="color: white;">Create Account</h3>
                    <p class="mb-0" style="color: rgba(255,255,255,0.8);">Join the Lost & Found community</p>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?> border-0 shadow-sm" role="alert">
                            <i class="bi bi-info-circle me-2"></i> <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="registerForm">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" placeholder="John Doe" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold">+254</span>
                                    <input type="text" name="phone_suffix" id="phone_suffix" class="form-control" placeholder="703364745" maxlength="9" inputmode="numeric" pattern="[0-9]{9}" autocomplete="off">
                                </div>
                                <div class="form-text" id="phone_help" style="font-size: 0.75rem;">Enter the 9 digits after +254.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">I am a... <span class="text-danger">*</span></label>
                                <select name="role" id="role" class="form-select" required onchange="toggleIdField()">
                                    <option value="student">Student</option>
                                    <option value="staff">Staff Member</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3" id="studentIdField">
                            <label class="form-label">Student Registration Number</label>
                            <input type="text" name="student_id" class="form-control" placeholder="e.g., S1234567">
                        </div>

                        <div class="mb-3 d-none" id="staffIdField">
                            <label class="form-label">Staff Number</label>
                            <input type="text" name="staff_id" class="form-control" placeholder="e.g., STF-001">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Create a strong password" required autocomplete="new-password">
                            
                            <div class="mt-3 p-3 rounded" id="pw_rules" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <div class="small text-muted mb-2 fw-bold">Password must contain:</div>
                                <div class="pw-rule" id="rule_len" style="font-size: 0.85rem; color: #64748B;"><i class="bi bi-circle"></i> At least 8 characters</div>
                                <div class="pw-rule" id="rule_lower" style="font-size: 0.85rem; color: #64748B;"><i class="bi bi-circle"></i> One lowercase letter (a-z)</div>
                                <div class="pw-rule" id="rule_upper" style="font-size: 0.85rem; color: #64748B;"><i class="bi bi-circle"></i> One uppercase letter (A-Z)</div>
                                <div class="pw-rule" id="rule_digit" style="font-size: 0.85rem; color: #64748B;"><i class="bi bi-circle"></i> One number (0-9)</div>
                                <div class="pw-rule" id="rule_special" style="font-size: 0.85rem; color: #64748B;"><i class="bi bi-circle"></i> One special character (!@#$%^&*)</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                            Create Account <i class="bi bi-check-circle ms-1"></i>
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-4 border-top">
                        <span class="text-muted small">Already have an account?</span>
                        <a href="login.php" class="fw-bold text-decoration-none small ms-1" style="color: var(--primary);">Sign In</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Toggle Student/Staff ID field
function toggleIdField() {
    const role = document.getElementById('role').value;
    document.getElementById('studentIdField').classList.toggle('d-none', role !== 'student');
    document.getElementById('staffIdField').classList.toggle('d-none', role !== 'staff');
}
toggleIdField();

// Phone validation
const phoneInput = document.getElementById('phone_suffix');
const phoneHelp = document.getElementById('phone_help');
phoneInput.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
    if (this.value.length > 9) this.value = this.value.slice(0, 9);
    const len = this.value.length;
    if (len === 0) {
        this.classList.remove('is-valid', 'is-invalid');
        phoneHelp.innerHTML = 'Enter the 9 digits after +254. Example: 703364745';
        phoneHelp.style.color = '';
    } else if (len === 9) {
        this.classList.add('is-valid');
        this.classList.remove('is-invalid');
        phoneHelp.innerHTML = '✓ Valid number: +254' + this.value;
        phoneHelp.style.color = '#10B981';
    } else {
        this.classList.add('is-invalid');
        this.classList.remove('is-valid');
        phoneHelp.innerHTML = 'Need ' + (9 - len) + ' more digit' + ((9 - len) === 1 ? '' : 's');
        phoneHelp.style.color = '#EF4444';
    }
});
phoneInput.addEventListener('keypress', function(e) {
    if (e.key.length === 1 && !/\d/.test(e.key)) e.preventDefault();
});

// Password live validation
const pwInput = document.getElementById('password');
const rules = {
    len:     { el: document.getElementById('rule_len'),     check: v => v.length >= 8,            label: 'At least 8 characters' },
    lower:   { el: document.getElementById('rule_lower'),   check: v => /[a-z]/.test(v),          label: 'One lowercase letter (a-z)' },
    upper:   { el: document.getElementById('rule_upper'),   check: v => /[A-Z]/.test(v),          label: 'One uppercase letter (A-Z)' },
    digit:   { el: document.getElementById('rule_digit'),   check: v => /[0-9]/.test(v),          label: 'One number (0-9)' },
    special: { el: document.getElementById('rule_special'), check: v => /[^a-zA-Z0-9]/.test(v),   label: 'One special character (!@#$%^&*)' }
};

pwInput.addEventListener('input', function() {
    const v = this.value;
    Object.values(rules).forEach(r => {
        if (r.check(v)) {
            r.el.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + r.label;
            r.el.style.color = '#10B981';
        } else {
            r.el.innerHTML = '<i class="bi bi-circle"></i> ' + r.label;
            r.el.style.color = '#64748B';
        }
    });
});

// Form submission validation
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const phoneValue = phoneInput.value;
    if (phoneValue.length > 0 && phoneValue.length !== 9) {
        e.preventDefault();
        alert('Phone number must be exactly 9 digits.');
        phoneInput.focus();
        return;
    }
    const pw = pwInput.value;
    const failed = Object.values(rules).filter(r => !r.check(pw));
    if (failed.length > 0) {
        e.preventDefault();
        alert('Password does not meet all requirements. Missing: ' + failed.map(f => f.label).join(', '));
        pwInput.focus();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>