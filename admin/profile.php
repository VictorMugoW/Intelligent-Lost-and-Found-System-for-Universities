<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT user_id, full_name, email, phone, role, student_id, staff_id, created_at FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: ../auth/logout.php');
    exit;
}

$updated = isset($_GET['updated']) && $_GET['updated'] === '1';

$page_title = 'My Profile';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <?php if ($updated): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> Profile updated successfully.
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-4">
                        <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #1E40AF, #3B82F6); display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 2rem; flex-shrink: 0;">
                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                        </div>
                        <div style="flex-grow: 1;">
                            <h3 class="fw-bold mb-1"><?= htmlspecialchars($user['full_name']) ?></h3>
                            <p class="text-muted mb-0">
                                <span class="badge" style="background: linear-gradient(135deg, #1E40AF, #3B82F6);">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                                <span class="text-muted ms-2">Member since <?= date('M Y', strtotime($user['created_at'])) ?></span>
                            </p>
                        </div>
                        <a href="edit_profile.php" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit Profile
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header" style="background: linear-gradient(135deg, #0F172A, #1E293B); color: white;">
                    <h5 class="mb-0"><i class="bi bi-person-badge"></i> Account Details</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4 text-muted">Full Name</div>
                        <div class="col-md-8 fw-bold"><?= htmlspecialchars($user['full_name']) ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 text-muted">Email</div>
                        <div class="col-md-8">
                            <span class="fw-bold"><?= htmlspecialchars($user['email']) ?></span>
                            <span class="badge bg-secondary ms-2" style="font-size: 0.65rem;">
                                <i class="bi bi-lock-fill"></i> Locked by institution
                            </span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 text-muted">Phone</div>
                        <div class="col-md-8 fw-bold"><?= htmlspecialchars($user['phone'] ?: 'Not provided') ?></div>
                    </div>
                    <?php if (!empty($user['staff_id'])): ?>
                        <div class="row mb-3">
                            <div class="col-md-4 text-muted">Staff Number</div>
                            <div class="col-md-8 fw-bold"><?= htmlspecialchars($user['staff_id']) ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="row mb-0">
                        <div class="col-md-4 text-muted">Role</div>
                        <div class="col-md-8 fw-bold"><?= ucfirst($user['role']) ?></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #0F172A, #1E293B); color: white;">
                    <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Security</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Keep your admin account secure by updating your password regularly.</p>
                    <a href="change_password.php" class="btn btn-outline-primary">
                        <i class="bi bi-key"></i> Change Password
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>