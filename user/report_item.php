<?php
session_start();
require_once '../config/database.php';
require_once '../includes/matching_engine.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';
$matches = [];

$pre_type = $_GET['type'] ?? 'lost';
if (!in_array($pre_type, ['lost', 'found'])) {
    $pre_type = 'lost';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $colour = trim($_POST['colour']);
    $location = trim($_POST['location']);
    $date_lost_found = $_POST['date_lost_found'];
    $type = $_POST['type'];

    if (empty($item_name) || empty($category) || empty($type)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'danger';
    } else {
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
            $stmt = $conn->prepare("INSERT INTO items (user_id, item_name, category, description, colour, location, date_lost_found, image_path, type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $status = ($type === 'lost') ? 'lost' : 'found';
            $stmt->bind_param("isssssssss", $user_id, $item_name, $category, $description, $colour, $location, $date_lost_found, $image_path, $type, $status);

            if ($stmt->execute()) {
                $new_item_id = $stmt->insert_id;
                $stmt->close();
                $message = 'Item reported successfully!';
                $message_type = 'success';
                $matches = runMatchingEngine($conn, $new_item_id);
            } else {
                $message = 'Failed to report item.';
                $message_type = 'danger';
                $stmt->close();
            }
        }
    }
}

$page_title = 'Report Item';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="feature-icon mx-auto mb-3"><i class="bi bi-plus-circle"></i></div>
                        <h3 class="fw-bold">Report an Item</h3>
                        <p class="text-muted">Provide details about the lost or found item</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Item Type *</label>
                            <select name="type" class="form-select" required>
                                <option value="lost" <?= $pre_type === 'lost' ? 'selected' : '' ?>>Lost Item</option>
                                <option value="found" <?= $pre_type === 'found' ? 'selected' : '' ?>>Found Item</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Item Name *</label>
                            <input type="text" name="item_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category *</label>
                            <select name="category" class="form-select" required>
                                <option value="">-- Select --</option>
                                <option value="Phone">Phone</option>
                                <option value="ID">ID Card</option>
                                <option value="Laptop">Laptop</option>
                                <option value="Wallet">Wallet</option>
                                <option value="Book">Book</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Colour</label>
                            <input type="text" name="colour" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. Library, LT3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date Lost/Found</label>
                            <input type="date" name="date_lost_found" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Image (max 5MB)</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary">Submit Report</button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                </div>
            </div>

            <?php if (!empty($matches)): ?>
                <div class="mt-5">
                    <h4 class="fw-bold mb-3">
                        <i class="bi bi-stars text-primary"></i> Intelligent Match Suggestions
                    </h4>
                    <p class="text-muted">We found <?= count($matches) ?> potential match(es) for your item:</p>

                    <div class="row g-4">
                        <?php foreach ($matches as $match): ?>
                            <div class="col-md-6">
                                <div class="card h-100" style="border: 2px solid #10B981;">
                                    <div class="card-header" style="background: linear-gradient(135deg, #10B981, #059669); color: white;">
                                        <strong><i class="bi bi-check-circle"></i> Match Score: <?= $match['score'] ?>%</strong>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($match['item']['image_path'])): ?>
                                            <img src="../<?= htmlspecialchars($match['item']['image_path']) ?>" class="img-fluid rounded mb-3" style="max-height:180px;">
                                        <?php endif; ?>
                                        <h5 class="fw-bold"><?= htmlspecialchars($match['item']['item_name']) ?></h5>
                                        <p class="small mb-1"><strong>Category:</strong> <?= htmlspecialchars($match['item']['category']) ?></p>
                                        <p class="small mb-1"><strong>Colour:</strong> <?= htmlspecialchars($match['item']['colour'] ?: 'N/A') ?></p>
                                        <p class="small mb-1"><strong>Location:</strong> <?= htmlspecialchars($match['item']['location'] ?: 'N/A') ?></p>
                                        <p class="small mb-2"><strong>Description:</strong> <?= htmlspecialchars($match['item']['description']) ?></p>
                                        <a href="submit_claim.php?item_id=<?= $match['item']['item_id'] ?>" class="btn btn-primary btn-sm w-100">
                                            <i class="bi bi-hand-index"></i> Claim This Match
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($message_type === 'success' && empty($matches)): ?>
                <div class="mt-4 alert alert-info">
                    <i class="bi bi-info-circle"></i> No matches found yet. The system will continue to compare your report against future submissions.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>