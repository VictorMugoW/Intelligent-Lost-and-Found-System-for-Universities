<?php
session_start();
require_once '../config/database.php';
require_once '../includes/password_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'Please fill in all fields.';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'New password and confirmation do not match.';
        $message_type = 'danger';
    } elseif ($current_password === $new_password) {
        $message = 'New password must be different from your current password.';
        $message_type = 'danger';
    } else {
        $pw_errors = validatePassword($new_password);
        if (!empty($pw_errors)) {
            $message = 'Password must contain: ' . implode(', ', $pw_errors) . '.';
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row || !password_verify($current_password, $row['password'])) {
                $message = 'Current password is incorrect.';
                $message_type = 'danger';
            } else {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $update->bind_param("si", $new_hash, $user_id);

                if ($update->execute()) {
                    $message = 'Password changed successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to update password. Please try again.';
                    $message_type = 'danger';
                }
                $update->close();
            }
        }
    }
}

$page_title = 'Change Password';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="feature-icon mx-auto mb-3"><i class="bi bi-key"></i></div>
                        <h3 class="fw-bold">Change Password</h3>
                        <p class="text-muted">Keep your account secure</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <?php if ($message_type !== 'success'): ?>
                        <form method="POST" id="pwForm">
                            <div class="mb-3">
                                <label class="form-label">Current Password *</label>
                                <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">New Password *</label>
                                <input type="password" name="new_password" id="new_password" class="form-control" required autocomplete="new-password">
                                <div class="mt-2" id="pw_rules">
                                    <div class="pw-rule" id="rule_len" style="font-size: 0.85rem; color: #64748B;">
                                        <i class="bi bi-circle"></i> At least 8 characters
                                    </div>
                                    <div class="pw-rule" id="rule_lower" style="font-size: 0.85rem; color: #64748B;">
                                        <i class="bi bi-circle"></i> One lowercase letter (a-z)
                                    </div>
                                    <div class="pw-rule" id="rule_upper" style="font-size: 0.85rem; color: #64748B;">
                                        <i class="bi bi-circle"></i> One uppercase letter (A-Z)
                                    </div>
                                    <div class="pw-rule" id="rule_digit" style="font-size: 0.85rem; color: #64748B;">
                                        <i class="bi bi-circle"></i> One number (0-9)
                                    </div>
                                    <div class="pw-rule" id="rule_special" style="font-size: 0.85rem; color: #64748B;">
                                        <i class="bi bi-circle"></i> One special character (!@#$%^&*)
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Confirm New Password *</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required autocomplete="new-password">
                                <div class="form-text" id="confirm_help"></div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Change Password
                                </button>
                                <a href="profile.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center">
                            <a href="profile.php" class="btn btn-primary">Back to Profile</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const newPw = document.getElementById('new_password');
const confirmPw = document.getElementById('confirm_password');
const confirmHelp = document.getElementById('confirm_help');

const rules = {
    len:     { el: document.getElementById('rule_len'),     check: v => v.length >= 8,            label: 'At least 8 characters' },
    lower:   { el: document.getElementById('rule_lower'),   check: v => /[a-z]/.test(v),          label: 'One lowercase letter (a-z)' },
    upper:   { el: document.getElementById('rule_upper'),   check: v => /[A-Z]/.test(v),          label: 'One uppercase letter (A-Z)' },
    digit:   { el: document.getElementById('rule_digit'),   check: v => /[0-9]/.test(v),          label: 'One number (0-9)' },
    special: { el: document.getElementById('rule_special'), check: v => /[^a-zA-Z0-9]/.test(v),   label: 'One special character (!@#$%^&*)' }
};

if (newPw) {
    newPw.addEventListener('input', function() {
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
}

if (confirmPw) {
    confirmPw.addEventListener('input', function() {
        if (this.value.length === 0) {
            confirmHelp.innerHTML = '';
            this.classList.remove('is-valid', 'is-invalid');
        } else if (this.value === newPw.value) {
            confirmHelp.innerHTML = '✓ Passwords match';
            confirmHelp.style.color = '#10B981';
            this.classList.add('is-valid');
            this.classList.remove('is-invalid');
        } else {
            confirmHelp.innerHTML = '✗ Passwords do not match';
            confirmHelp.style.color = '#EF4444';
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
        }
    });
}

document.getElementById('pwForm')?.addEventListener('submit', function(e) {
    const pw = newPw.value;
    const failed = Object.values(rules).filter(r => !r.check(pw));
    if (failed.length > 0) {
        e.preventDefault();
        alert('Password does not meet all requirements. Missing: ' + failed.map(f => f.label).join(', '));
        newPw.focus();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>