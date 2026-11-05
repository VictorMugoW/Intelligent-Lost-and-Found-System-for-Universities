<?php
session_start();
require_once '../config/database.php';
require_once '../config/mailer.php'; // This loads the sendEmail() function

// --- HANDLE CANCEL 2FA ---
if (isset($_GET['cancel']) && $_GET['cancel'] == 1) {
    unset($_SESSION['2fa_user_id']); 
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../user/dashboard.php');
    }
    exit;
}

$message = '';
$message_type = '';
$step = isset($_SESSION['2fa_user_id']) ? 'verify' : 'login';

// --- HANDLE OTP VERIFICATION (STEP 2) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $otp = trim($_POST['otp']);
    $user_id = $_SESSION['2fa_user_id'];
    
    $stmt = $conn->prepare("SELECT user_id, full_name, email, role, otp_code, otp_expires_at FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['otp_code'] === $otp && strtotime($row['otp_expires_at']) > time()) {
            // SUCCESS! Log them in.
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['full_name'] = $row['full_name'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['role'] = $row['role'];
            
            // Clear OTP and session
            $conn->query("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE user_id = $user_id");
            unset($_SESSION['2fa_user_id']);
            
            if ($row['role'] === 'admin') {
                header('Location: ../admin/dashboard.php');
            } else {
                header('Location: ../user/dashboard.php');
            }
            exit;
        } else {
            $message = 'Invalid or expired OTP. Please try again.';
            $message_type = 'danger';
        }
    }
    $stmt->close();
}

// --- HANDLE LOGIN (STEP 1) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $message = 'Please enter both email and password.';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("SELECT user_id, full_name, email, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                
                // --- 2FA LOGIC TRIGGERED ---
                $otp = sprintf("%06d", mt_rand(1, 999999)); // Generate 6-digit OTP
                $expires = date('Y-m-d H:i:s', strtotime('+10 minutes')); // 10 min expiry
                
                // Save OTP to DB
                $update = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE user_id = ?");
                $update->bind_param("ssi", $otp, $expires, $user['user_id']);
                $update->execute();
                $update->close();
                
                // Store user ID temporarily in session
                $_SESSION['2fa_user_id'] = $user['user_id'];
                
                // --- SEND EMAIL VIA PHPMAILER ---
                $to = $user['email'];
                $subject = "Your Login Verification Code";
                $body = "Hello " . $user['full_name'] . ",\n\nYour 6-digit verification code is: " . $otp . "\n\nThis code expires in 10 minutes.";
                
                // Call the function from config/mailer.php
                $email_sent = sendEmail($to, $subject, $body);
                
                if (!$email_sent) {
                    $message = 'We could not send the email. Please check your connection or contact support.';
                    $message_type = 'warning';
                    // Note: The OTP is still saved in the database, so you can test by checking phpMyAdmin
                } else {
                    $message = 'We have emailed you a 6-digit verification code. Please check your inbox.';
                    $message_type = 'success';
                }
                
                $step = 'verify'; // Switch view to OTP form
                
            } else {
                $message = 'Invalid email or password.';
                $message_type = 'danger';
            }
        } else {
            $message = 'Invalid email or password.';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

$page_title = 'Login';
require_once '../includes/header.php';
?>

<style>
    body { background-color: #f4f7f6; }
    .login-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .login-card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        overflow: hidden;
        width: 100%;
        max-width: 950px;
    }
    .login-left {
        background: linear-gradient(135deg, #0d6efd 0%, #0a4b9e 100%);
        color: #ffffff;
        padding: 4rem 3rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
    }
    .login-left::after {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: url('data:image/svg+xml;utf8,<svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100%" height="100%" fill="url(%23dots)"/></svg>');
        z-index: 0;
    }
    .login-left > * { position: relative; z-index: 1; }
    .login-right { padding: 4rem 3rem; background: #ffffff; }
    .feature-icon-large { font-size: 3.5rem; margin-bottom: 1rem; color: #ffffff; }
</style>

<div class="login-wrapper">
    <div class="container" style="max-width: 950px;">
        
        <div class="mb-3">
            <a href="../index.php" class="text-decoration-none fw-bold" style="color: #0d6efd;">
                <i class="bi bi-arrow-left me-1"></i> Back to Home
            </a>
        </div>

        <div class="card login-card">
            <div class="row g-0">
                
                <div class="col-md-5 login-left d-none d-md-flex">
                    <div class="text-center">
                        <i class="bi bi-shield-lock feature-icon-large"></i>
                        <h2 class="fw-bolder mb-3" style="letter-spacing: -0.5px;">Lost & Found</h2>
                        <p class="mb-0 fw-medium" style="color: rgba(255, 255, 255, 0.9); font-size: 1.1rem; line-height: 1.6;">
                            The intelligent platform to reunite people with their belongings securely.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-7 login-right">
                    
                    <div class="text-center mb-4 d-md-none">
                         <div class="feature-icon mx-auto mb-3" style="background: #0d6efd; color: white; border-radius: 50%; width: 70px; height: 70px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-shield-lock fs-2"></i>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <?php if ($step === 'verify'): ?>
                            <h3 class="fw-bold text-dark">Check Your Email</h3>
                            <p class="text-muted">Enter the 6-digit code sent to your email.</p>
                        <?php else: ?>
                            <h3 class="fw-bold text-dark">Welcome Back</h3>
                            <p class="text-muted">Please sign in to your account.</p>
                        <?php endif; ?>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?> border-0 shadow-sm" role="alert">
                            <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i> <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($step === 'verify'): ?>
                        <!-- STEP 2: OTP FORM -->
                        <form method="POST">
                            <input type="hidden" name="action" value="verify_otp">
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-muted small">6-Digit Verification Code</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                                    <input type="text" name="otp" class="form-control text-center fw-bold fs-4 letter-spacing-2" placeholder="000000" maxlength="6" required autocomplete="off">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-4">
                                Verify & Sign In <i class="bi bi-shield-check ms-2"></i>
                            </button>
                            
                            <div class="text-center">
                                <a href="login.php?cancel=1" class="text-muted small text-decoration-none">
                                    <i class="bi bi-arrow-left me-1"></i> Cancel and go back
                                </a>
                            </div>
                        </form>

                    <?php else: ?>
                        <!-- STEP 1: LOGIN FORM -->
                        <form method="POST">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-muted small">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="d-flex justify-content-between">
                                    <label class="form-label fw-semibold text-muted small">Password</label>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Enter your password" required>
                                    
                                    <!-- THE EYE TOGGLE -->
                                    <span class="input-group-text" style="cursor: pointer;" onclick="togglePassword()">
                                        <i class="bi bi-eye" id="eyeIcon"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-4">
                                Sign In <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="text-center mt-3 pt-4 border-top">
                        <span class="text-muted small">Don't have an account?</span>
                        <a href="register.php" class="fw-bold text-decoration-none small ms-1">Create an account</a>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<script>
// Password Visibility Toggle
function togglePassword() {
    var input = document.getElementById('passwordInput');
    var icon = document.getElementById('eyeIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>