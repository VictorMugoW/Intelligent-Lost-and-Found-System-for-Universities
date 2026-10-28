<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM users WHERE role != 'admin'";
if (!empty($search)) {
    $sql .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%' OR student_id LIKE '%$search%')";
}
$sql .= " ORDER BY created_at DESC";

$users = $conn->query($sql);

$page_title = 'Manage Users';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '../includes/admin_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
    <div>
        <h2 class="fw-bold mb-1">User Directory</h2>
        <p class="text-muted mb-0">View and manage all students and staff</p>
    </div>
    <form method="GET" class="d-flex gap-2" style="max-width: 400px; width: 100%;">
        <input type="text" name="search" class="form-control" placeholder="Search by name, email or ID" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
    </form>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Contact</th>
                        <th class="text-center">Reports</th>
                        <th class="text-center">Claims</th>
                        <th class="text-center">Returned Items</th>
                        <th>Badge</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No users found.</td></tr>
                    <?php else: ?>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <?php
                            $uid = $user['user_id'];
                            
                            $r_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE user_id = $uid AND type = 'lost'")->fetch_assoc()['c'];
                            $c_count = $conn->query("SELECT COUNT(*) as c FROM claims WHERE claimed_by = $uid")->fetch_assoc()['c'];
                            $f_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE finder_id = $uid AND status = 'returned'")->fetch_assoc()['c'];
                            
                            // NEW BADGE LOGIC
                            $badge = '<span class="badge bg-secondary">Newbie</span>';
                            if ($f_count >= 7) {
                                $badge = '<span class="badge bg-warning text-dark"><i class="bi bi-award-fill"></i> Gold Guardian</span>';
                            } elseif ($f_count >= 4) {
                                $badge = '<span class="badge bg-light text-dark border"><i class="bi bi-award-fill text-secondary"></i> Silver Finder</span>';
                            } elseif ($f_count >= 1) {
                                $badge = '<span class="badge bg-light text-dark border" style="border-color:#cd7f32!important; color:#cd7f32!important;"><i class="bi bi-award-fill"></i> Bronze Finder</span>';
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 35px; height: 35px; border-radius: 50%; background: #E2E8F0; color: #0F172A; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($user['full_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($user['student_id'] ?: $user['staff_id']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-primary"><?= ucfirst($user['role']) ?></span></td>
                                <td>
                                    <div class="small"><?= htmlspecialchars($user['email']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($user['phone'] ?: 'No phone') ?></div>
                                </td>
                                <td class="text-center"><h5 class="mb-0"><?= $r_count ?></h5></td>
                                <td class="text-center"><h5 class="mb-0"><?= $c_count ?></h5></td>
                                <td class="text-center text-success fw-bold"><h5 class="mb-0"><?= $f_count ?></h5></td>
                                <td><?= $badge ?></td>
                                <td>
                                    <a href="user_details.php?id=<?= $uid ?>" class="btn btn-sm btn-outline-primary">View <i class="bi bi-arrow-right"></i></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
require_once '../includes/admin_layout_bottom.php';
require_once '../includes/footer.php'; 
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>