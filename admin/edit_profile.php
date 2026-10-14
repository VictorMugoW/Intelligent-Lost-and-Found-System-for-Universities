<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch fresh user data
$stmt = $conn->prepare("SELECT user_id, full_name, email, phone, role, student_id, staff_id FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: ../auth/logout.php');
    exit;
}

$message = '';
$message_type = '';

// Extract the 9-digit suffix from the stored phone (if it starts with +254)
$current_suffix = '';
if (!empty($user['phone']) && strpos($user['phone'], '+254') === 0) {
    $current_suffix = substr($user['phone'], 4);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone_suffix = trim($_POST['phone_suffix'] ?? '');

    if (empty($full_name)) {
        $message = 'Full name is required.';
        $message_type = 'danger';
    } elseif (!empty($phone_suffix) && (strlen($phone_suffix) !== 9 || !ctype_digit($phone_suffix))) {
        $message = 'Phone number must be exactly 9 digits (e.g., 703364745).';
        $message_type = 'danger';
    } else {
        $phone = '';
        if (!empty($phone_suffix)) {
            $phone = '+254' . $phone_suffix;
        }

        $update = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?");
        $update->bind_param("ssi", $full_name, $phone, $user_id);

        if ($update->execute()) {
            $_SESSION['full_name'] = $full_name;
            header('Location: profile.php?updated=1');
            exit;
        } else {
            $message = 'Failed to update profile. Please try again.';
            $message_type = 'danger';
        }
        $update->close();
    }
}

$page_title = 'Edit Profile';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="feature-icon mx-auto mb-3"><i class="bi bi-pencil"></i></div>
                        <h3 class="fw-bold">Edit Profile</h3>
                        <p class="text-muted">Update your personal information</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <form method="POST" id="editForm">
                        <!-- Full Name -->
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>

                        <!-- Email (locked) -->
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                            <div class="form-text" style="color: #64748B;">
                                <i class="bi bi-lock-fill"></i> Email is managed by the institution and cannot be changed here.
                            </div>
                        </div>

                        <!-- Phone with +254 prefix -->
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold" style="background: #0F172A; color: white; border: 2px solid #0F172A;">+254</span>
                                <input type="text"
                                       name="phone_suffix"
                                       id="phone_suffix"
                                       class="form-control"
                                       placeholder="703364745"
                                       maxlength="9"
                                       inputmode="numeric"
                                       pattern="[0-9]{9}"
                                       autocomplete="off"
                                       value="<?= htmlspecialchars($current_suffix) ?>">
                            </div>
                            <div class="form-text" id="phone_help">Enter the 9 digits after +254. Example: 703364745</div>
                        </div>

                        <!-- Role (locked) -->
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" disabled>
                            <div class="form-text" style="color: #64748B;">
                                <i class="bi bi-lock-fill"></i> Role is fixed.
                            </div>
                        </div>

                        <!-- Student/Staff ID (locked) -->
                        <?php if ($user['role'] === 'student' && !empty($user['student_id'])): ?>
                            <div class="mb-3">
                                <label class="form-label">Student Registration Number</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['student_id']) ?>" disabled>
                                <div class="form-text" style="color: #64748B;">
                                    <i class="bi bi-lock-fill"></i> Registration number cannot be changed.
                                </div>
                            </div>
                        <?php elseif ($user['role'] !== 'student' && !empty($user['staff_id'])): ?>
                            <div class="mb-3">
                                <label class="form-label">Staff Number</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['staff_id']) ?>" disabled>
                                <div class="form-text" style="color: #64748B;">
                                    <i class="bi bi-lock-fill"></i> Staff number cannot be changed.
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Save Changes
                            </button>
                            <a href="profile.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Phone input validation (same as register)
const phoneInput = document.getElementById('phone_suffix');
const phoneHelp = document.getElementById('phone_help');

function updatePhoneUI() {
    const len = phoneInput.value.length;
    if (len === 0) {
        phoneInput.classList.remove('is-valid', 'is-invalid');
        phoneHelp.innerHTML = 'Enter the 9 digits after +254. Example: 703364745';
        phoneHelp.style.color = '';
    } else if (len === 9) {
        phoneInput.classList.add('is-valid');
        phoneInput.classList.remove('is-invalid');
        phoneHelp.innerHTML = '✓ Valid number: +254' + phoneInput.value;
        phoneHelp.style.color = '#10B981';
    } else {
        phoneInput.classList.add('is-invalid');
        phoneInput.classList.remove('is-valid');
        phoneHelp.innerHTML = 'Need ' + (9 - len) + ' more digit' + ((9 - len) === 1 ? '' : 's') + ' (total 9 required)';
        phoneHelp.style.color = '#EF4444';
    }
}

phoneInput.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
    if (this.value.length > 9) this.value = this.value.slice(0, 9);
    updatePhoneUI();
});

phoneInput.addEventListener('keypress', function(e) {
    if (e.key.length === 1 && !/\d/.test(e.key)) e.preventDefault();
});

// Run once on load (for pre-filled values)
updatePhoneUI();

// Form validation
document.getElementById('editForm').addEventListener('submit', function(e) {
    const phoneValue = phoneInput.value;
    if (phoneValue.length > 0 && phoneValue.length !== 9) {
        e.preventDefault();
        alert('Phone number must be exactly 9 digits. You entered ' + phoneValue.length + '.');
        phoneInput.focus();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>