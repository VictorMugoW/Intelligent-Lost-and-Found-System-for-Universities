<?php
session_start();
require_once '../config/database.php';
require_once '../includes/matching_engine.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$message = '';
$message_type = '';
$matches = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $colour = trim($_POST['colour']);
    $location = trim($_POST['location']);
    $date_lost_found = $_POST['date_lost_found'];
    
    $type = 'found';
    $status = 'found'; 
    $loss_context = "Logged by Admin (Walk-in item deposited at office)";

    if (empty($item_name) || empty($category) || empty($location) || empty($colour) || empty($date_lost_found)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'danger';
    } else {
        // AUTO-MAP LOGIC
        $loc_check = $conn->prepare("SELECT id, report_count FROM campus_locations WHERE location_name = ?");
        $loc_check->bind_param("s", $location);
        $loc_check->execute();
        $loc_result = $loc_check->get_result();

        if ($loc_result->num_rows > 0) {
            $loc_row = $loc_result->fetch_assoc();
            $new_count = $loc_row['report_count'] + 1;
            $update_loc = $conn->prepare("UPDATE campus_locations SET report_count = ? WHERE id = ?");
            $update_loc->bind_param("ii", $new_count, $loc_row['id']);
            $update_loc->execute();
            $update_loc->close();
        } else {
            $default_lat = -1.3102;
            $default_lng = 36.8123;
            $insert_loc = $conn->prepare("INSERT INTO campus_locations (location_name, latitude, longitude, report_count, is_mapped) VALUES (?, ?, ?, 1, FALSE)");
            $insert_loc->bind_param("sdd", $location, $default_lat, $default_lng);
            $insert_loc->execute();
            $insert_loc->close();
        }
        $loc_check->close();

        $image_path = null;
        if (!empty($_FILES['image']['name'])) {
            $target_dir = "../uploads/";
            $file_name = time() . "_" . basename($_FILES['image']['name']);
            $target_file = $target_dir . $file_name;
            $image_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            if (in_array($image_type, ['jpg', 'jpeg', 'png', 'gif']) && $_FILES['image']['size'] <= 5 * 1024 * 1024) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image_path = "uploads/" . $file_name;
                }
            } else {
                $message = 'Invalid image. Max 5MB, formats: JPG, PNG, GIF.';
                $message_type = 'danger';
            }
        }

        if (empty($message)) {
            $stmt = $conn->prepare("INSERT INTO items (user_id, item_name, category, description, loss_context, colour, location, date_lost_found, image_path, type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssssss", $admin_id, $item_name, $category, $description, $loss_context, $colour, $location, $date_lost_found, $image_path, $type, $status);

            if ($stmt->execute()) {
                $new_item_id = $stmt->insert_id;
                $stmt->close();
                $message = 'Walk-in item logged successfully! It is now live in the system.';
                $message_type = 'success';
                $matches = runMatchingEngine($conn, $new_item_id);
            } else {
                $message = 'Failed to log item.';
                $message_type = 'danger';
                $stmt->close();
            }
        }
    }
}

$page_title = 'Log Walk-in Item';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '../includes/admin_layout_top.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-8">
            
            <!-- Header & Back Button -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1" style="color: #0f172a;"><i class="bi bi-box-arrow-in-down text-success me-2"></i> Log Walk-in Item</h3>
                    <p class="text-muted mb-0">Record an item dropped off at the office by staff or security.</p>
                </div>
                <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill fw-bold px-3">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>

            <?php if ($message && $message_type === 'success'): ?>
                <!-- SUCCESS STATE -->
                <div class="card border-0 shadow-sm text-center" style="border-radius: 1rem;">
                    <div class="card-body p-5">
                        <div class="mx-auto mb-4" style="width: 80px; height: 80px; background: #dcfce7; color: #10B981; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem;">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <h3 class="fw-bold text-dark">Item Logged Successfully!</h3>
                        <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>

                        <?php if (!empty($matches)): ?>
                            <div class="alert alert-warning text-start border-0 shadow-sm" style="border-radius: 1rem; background-color: #fffbeb;">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3" style="font-size: 2rem; color: #d97706;"><i class="bi bi-stars"></i></div>
                                    <div>
                                        <h5 class="fw-bold text-dark mb-0">Intelligent Match Found!</h5>
                                        <p class="text-muted small mb-0">A student has already reported this item as lost.</p>
                                    </div>
                                </div>
                                
                                <?php foreach ($matches as $match): ?>
                                    <div class="bg-white p-3 rounded-3 border d-flex align-items-center justify-content-between mb-2">
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($match['item']['item_name']) ?></div>
                                            <div class="small text-muted">Match Score: <span class="badge bg-success"><?= $match['score'] ?>%</span></div>
                                        </div>
                                        <a href="user_details.php?id=<?= $match['item']['user_id'] ?>" class="btn btn-sm btn-warning fw-bold rounded-pill px-3">
                                            View Student
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-3 justify-content-center mt-4">
                            <a href="log_found_item.php" class="btn btn-success rounded-pill px-4 fw-bold"><i class="bi bi-plus-circle me-1"></i> Log Another</a>
                            <a href="browse_items.php" class="btn btn-outline-primary rounded-pill px-4 fw-bold">View Directory</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- FORM STATE -->
                <div class="card border-0 shadow-sm" style="border-radius: 1rem;">
                    <div class="card-body p-4 p-md-5">
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $message_type ?> border-0 shadow-sm"><i class="bi bi-exclamation-circle me-2"></i> <?= htmlspecialchars($message) ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted small">Item Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-box"></i></span>
                                        <input type="text" name="item_name" class="form-control" placeholder="e.g. Black Leather Wallet" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted small">Category <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-grid"></i></span>
                                        <select name="category" class="form-select" required>
                                            <option value="">Select Category...</option>
                                            <option value="Phone">Phone</option>
                                            <option value="ID">ID</option>
                                            <option value="Laptop">Laptop</option>
                                            <option value="Wallet">Wallet</option>
                                            <option value="Book">Book</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted small">Colour <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-palette"></i></span>
                                        <input type="text" name="colour" class="form-control" placeholder="e.g. Black, Blue" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted small">Where was it found? <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-geo-alt"></i></span>
                                        <input type="text" name="location" class="form-control" placeholder="e.g. Gate A, Library" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-muted small">Date Found <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-calendar-date"></i></span>
                                    <input type="date" name="date_lost_found" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-muted small">Description (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-text-paragraph"></i></span>
                                    <textarea name="description" class="form-control" rows="3" placeholder="Any distinguishing features..."></textarea>
                                </div>
                            </div>

                            <div class="mb-5">
                                <label class="form-label fw-bold text-muted small">Upload an Image (Optional)</label>
                                <input type="file" name="image" class="form-control" accept="image/*">
                                <div class="form-text mt-1"><i class="bi bi-info-circle me-1"></i>Max 5MB. JPG, PNG, GIF.</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm" style="font-size: 1.1rem;">
                                <i class="bi bi-box-arrow-in-down me-2"></i> Log Item as Found
                            </button>
                        </form>

                    </div>
                </div>
            <?php endif; ?>
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