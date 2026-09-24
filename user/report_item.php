<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

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

            if (in_array($image_type, ['jpg', 'jpeg', 'png', 'gif'])) {
                if ($_FILES['image']['size'] <= 5 * 1024 * 1024) {
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        $image_path = "uploads/" . $file_name;
                    }
                } else {
                    $message = 'Image must be less than 5MB.';
                    $message_type = 'danger';
                }
            } else {
                $message = 'Only JPG, JPEG, PNG, and GIF are allowed.';
                $message_type = 'danger';
            }
        }

        if (empty($message)) {
            $stmt = $conn->prepare("INSERT INTO items (user_id, item_name, category, description, colour, location, date_lost_found, image_path, type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $status = ($type === 'lost') ? 'lost' : 'found';
            $stmt->bind_param("isssssssss", $user_id, $item_name, $category, $description, $colour, $location, $date_lost_found, $image_path, $type, $status);

            if ($stmt->execute()) {
                $message = 'Item reported successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to report item. Please try again.';
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Item</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Intelligent Lost and Found</span>
        <div>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">Dashboard</a>
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Report an Item</h4>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Item Type *</label>
                            <select name="type" class="form-select" required>
                                <option value="lost">Lost Item</option>
                                <option value="found">Found Item</option>
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

                        <div class="mb-3">
                            <label class="form-label">Image (max 5MB)</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>

                        <button type="submit" class="btn btn-primary">Submit Report</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>