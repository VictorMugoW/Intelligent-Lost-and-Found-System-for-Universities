<?php
session_start();
require_once '../config/database.php';

$message = '';
$message_type = '';
$token = $_GET['token'] ?? '';

$is_valid_token = false;
$user_id = null;

if (!empty($token)) {
    // 1. Fetch user by token (Let PHP handle the time check, not MySQL)
    $stmt = $conn->prepare("SELECT user_id, reset_token_expiry FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // 2. Check if expired using PHP's time()
        $expiry_time = strtotime($user['reset_token_expiry']);
        $current_time = time();
        
        if ($current_time <= $expiry_time) {
            $is_valid_token = true;
            $user_id = $user['user_id'];
        } else {
            $message = 'This reset link has expired. Please request a new one.';
            $message_type = 'danger';
        }
    } else {
        $message = 'Invalid reset link. The token does not match our records.';
        $message_type = 'danger';
    }
    $stmt->close();
} else {
    $message = 'No token provided.';
    $message_type = 'danger';
}

// 3. Handle the new password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_valid_token) {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    
    if (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
        $message_type = 'danger';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
        $message_type = 'danger';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and clear the token so it can't be used again
        $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?");
        $update->bind_param("si", $hashed, $user_id);
        
        if ($update->execute()) {
            $message = 'Password updated successfully! You can now login.';
            $message_type = 'success';
            $is_valid_token = false; // Hide the form after success
        } else {
            $message = 'Failed to update password. Please try again.';
            $message_type = 'danger';
        }
        $update->close();
    }
}

$page_title = 'Reset Password';
require_once '../includes/header.php';
?>

<div class="container py-5 d-flex justify-content-center" style="min-height: 80vh; align-items: center;">
    <div class="card shadow-lg border-0" style="width: 100%; max-width: 500px; border-radius: 1rem;">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <div class="feature-icon mx-auto mb-3" style="background: <?= $is_valid_token ? 'var(--success)' : 'var(--danger)' ?>;">
                    <i class="bi <?= $is_valid_token ? 'bi-shield-check' : 'bi-exclamation-triangle' ?>"></i>
                </div>
                <h3 class="fw-bold"><?= $is_valid_token ? 'Set New Password' : 'Link Error' ?></h3>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> border-0 shadow-sm text-center">
                    <?= htmlspecialchars($message) ?>
                    <?php if ($message_type === 'success'): ?>
                        <div class="mt-3">
                            <a href="login.php" class="btn btn-success w-100 fw-bold">Go to Login</a>
                        </div>
                    <?php elseif (!$is_valid_token): ?>
                        <div class="mt-3">
                            <a href="forgot_password.php" class="btn btn-outline-danger w-100 fw-bold">Request New Link</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($is_valid_token): ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold">New Password</label>
                    <input type="password" name="password" class="form-control form-control-lg" required minlength="8">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control form-control-lg" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Update Password</button>
            </form>
            <?php endif; ?>
            
            <?php if (!$is_valid_token && !$message): ?>
                 <div class="text-center mt-4">
                    <a href="login.php" class="text-decoration-none fw-bold" style="color: var(--primary);">
                        <i class="bi bi-arrow-left me-1"></i> Back to Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
</body>
</html>