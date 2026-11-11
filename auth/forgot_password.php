<?php
session_start();
require_once '../config/database.php';
require_once '../config/mailer.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
        $update->bind_param("ssi", $token, $expiry, $user['user_id']);
        $update->execute();
        $update->close();
        
        // Send Email
        $reset_link = "http://localhost/intelligent-lost-and-found/auth/reset_password.php?token=" . $token;
        $subject = "Password Reset Request";
        $body = "Hello " . $user['full_name'] . ",\n\nClick the link below to reset your password. This link expires in 1 hour.\n\n" . $reset_link;
        
        // NEW: Check if the email actually sent
        $email_sent = sendEmail($email, $subject, $body);
        
        if ($email_sent) {
            $message = 'If an account exists with that email, a password reset link has been sent.';
            $message_type = 'success';
        } else {
            $message = 'Error: The system could not send the email. Please check your mailer configuration.';
            $message_type = 'danger';
        }
        
    } else {
        // For security, don't reveal if email exists or not (but we show success anyway)
        $message = 'If an account exists with that email, a password reset link has been sent.';
        $message_type = 'success';
    }
    $stmt->close();
}
$page_title = 'Forgot Password';
require_once '../includes/header.php';
?>

<div class="container py-5 d-flex justify-content-center" style="min-height: 80vh; align-items: center;">
    <div class="card shadow-lg border-0" style="width: 100%; max-width: 500px; border-radius: 1rem;">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <div class="feature-icon mx-auto mb-3" style="background: var(--warning);">
                    <i class="bi bi-key-fill"></i>
                </div>
                <h3 class="fw-bold">Forgot Password?</h3>
                <p class="text-muted">Enter your email and we'll send you a link to reset it.</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> border-0 shadow-sm"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-4">
                    <label class="form-label fw-bold">Email Address</label>
                    <input type="email" name="email" class="form-control form-control-lg" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Send Reset Link</button>
            </form>
            
            <div class="text-center mt-4">
                <a href="login.php" class="text-decoration-none fw-bold" style="color: var(--primary);">
                    <i class="bi bi-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
</body>
</html>