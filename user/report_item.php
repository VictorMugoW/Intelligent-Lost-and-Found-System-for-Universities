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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $loss_context = trim($_POST['loss_context'] ?? ''); // NEW: Capture loss context
    $colour = trim($_POST['colour']);
    $location = trim($_POST['location']);
    $date_lost_found = $_POST['date_lost_found'];
    $type = 'lost'; 

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
            // UPDATED: Added loss_context to the INSERT query and bind_param
            $stmt = $conn->prepare("INSERT INTO items (user_id, item_name, category, description, loss_context, colour, location, date_lost_found, image_path, type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $status = 'lost';
            $stmt->bind_param("issssssssss", $user_id, $item_name, $category, $description, $loss_context, $colour, $location, $date_lost_found, $image_path, $type, $status);

            if ($stmt->execute()) {
                $new_item_id = $stmt->insert_id;
                $stmt->close();
                $message = 'Lost item reported successfully!';
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

$page_title = 'Report Lost Item';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
    .wizard-step { display: none; }
    .wizard-step.active { display: block; animation: fadeIn 0.4s ease-in-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .progress-bar-custom { height: 6px; background: #E2E8F0; border-radius: 10px; margin-bottom: 30px; overflow: hidden; }
    .progress-fill { height: 100%; background: linear-gradient(135deg, #1E40AF, #3B82F6); transition: width 0.3s ease; }
    .category-btn { border: 2px solid #E2E8F0; border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s; background: white; }
    .category-btn:hover { border-color: #3B82F6; transform: translateY(-2px); }
    .category-btn.selected { border-color: #1E40AF; background: #EFF6FF; }
    .category-btn i { font-size: 2rem; color: #1E40AF; display: block; margin-bottom: 10px; }
    
    /* Highlight invalid fields */
    .form-control.is-invalid, .form-select.is-invalid {
        border-color: #dc3545;
        padding-right: calc(1.5em + 0.75rem);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
</style>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            
            <?php if ($message && $message_type === 'success'): ?>
                <!-- SUCCESS STATE -->
                <div class="card text-center shadow">
                    <div class="card-body p-5">
                        <div class="feature-icon mx-auto mb-3" style="background: linear-gradient(135deg, #10B981, #059669); width: 80px; height: 80px; font-size: 2.5rem;">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h3 class="fw-bold">Report Submitted!</h3>
                        <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>

                        <?php if (!empty($matches)): ?>
                            <div class="alert alert-info text-start">
                                <h5 class="fw-bold"><i class="bi bi-stars"></i> Match Found!</h5>
                                <p class="small mb-3">We found <?= count($matches) ?> item(s) in the system that closely match your description.</p>
                                <?php foreach ($matches as $match): ?>
                                    <div class="d-flex align-items-center bg-white p-3 rounded mb-2 border">
                                        <div class="me-3">
                                            <span class="badge bg-success" style="font-size: 1rem;"><?= $match['score'] ?>%</span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <strong><?= htmlspecialchars($match['item']['item_name']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($match['item']['location']) ?></small>
                                        </div>
                                        <a href="submit_claim.php?item_id=<?= $match['item']['item_id'] ?>" class="btn btn-primary btn-sm">Claim</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2 justify-content-center mt-4">
                            <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                            <a href="browse_items.php" class="btn btn-outline-primary">Browse Items</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- WIZARD FORM -->
                <div class="card shadow">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold">Report a Lost Item</h3>
                            <p class="text-muted">Answer a few quick questions</p>
                        </div>

                        <?php if ($message): ?>
                            <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
                        <?php endif; ?>

                        <div class="progress-bar-custom">
                            <div class="progress-fill" id="progressBar" style="width: 0%;"></div>
                        </div>

                        <form method="POST" enctype="multipart/form-data" id="wizardForm">
                            
                            <!-- STEP 1: Category -->
                            <div class="wizard-step active" id="step1">
                                <h5 class="fw-bold mb-3">1. What type of item is it? *</h5>
                                <div class="row g-3">
                                    <?php 
                                    $cats = [
                                        'Phone' => 'phone', 'ID' => 'card-heading', 'Laptop' => 'laptop', 'Wallet' => 'wallet2', 'Book' => 'book', 'Other' => 'box'
                                    ];
                                    foreach ($cats as $name => $icon): ?>
                                        <div class="col-4">
                                            <div class="category-btn" onclick="selectCategory('<?= $name ?>')" id="cat-<?= $name ?>">
                                                <i class="bi bi-<?= $icon ?>"></i>
                                                <span class="fw-bold small"><?= $name ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="category" id="input_category" required>
                                <div class="d-flex justify-content-end mt-4">
                                    <button type="button" class="btn btn-primary" onclick="nextStep(2)" id="btnNext1" disabled>Next <i class="bi bi-arrow-right"></i></button>
                                </div>
                            </div>

                            <!-- STEP 2: Name & Colour -->
                            <div class="wizard-step" id="step2">
                                <h5 class="fw-bold mb-3">2. Item Details</h5>
                                <div class="mb-3">
                                    <label class="form-label">What is the item's name? *</label>
                                    <input type="text" name="item_name" id="item_name" class="form-control" placeholder="e.g. Samsung A32, Student ID" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">What colour is it? *</label>
                                    <input type="text" name="colour" id="colour" class="form-control" placeholder="e.g. Black, Blue" required>
                                </div>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-outline-secondary" onclick="prevStep(1)">Back</button>
                                    <button type="button" class="btn btn-primary" onclick="nextStep(3)">Next <i class="bi bi-arrow-right"></i></button>
                                </div>
                            </div>

                            <!-- STEP 3: Location & Date -->
                            <div class="wizard-step" id="step3">
                                <h5 class="fw-bold mb-3">3. Where and when?</h5>
                                <div class="mb-3">
                                    <label class="form-label">Where did you lose it? *</label>
                                    <input type="text" name="location" id="location" class="form-control" placeholder="e.g. Library, LT1, Cafeteria" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">When did you lose it? *</label>
                                    <input type="date" name="date_lost_found" id="date_lost_found" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-outline-secondary" onclick="prevStep(2)">Back</button>
                                    <button type="button" class="btn btn-primary" onclick="nextStep(4)">Next <i class="bi bi-arrow-right"></i></button>
                                </div>
                            </div>

                            <!-- STEP 4: Description, Loss Context & Image -->
                            <div class="wizard-step" id="step4">
                                <h5 class="fw-bold mb-3">4. Final Details</h5>
                                <div class="mb-3">
                                    <label class="form-label">Description (Optional)</label>
                                    <textarea name="description" class="form-control" rows="2" placeholder="Any distinguishing features like scratches, stickers, brand, etc."></textarea>
                                </div>

                                <!-- NEW: LOSS CONTEXT FIELD -->
                                <div class="mb-3">
                                    <label class="form-label">How did you lose it? (Optional)</label>
                                    <textarea name="loss_context" class="form-control" rows="2" placeholder="e.g. Fell out of my pocket, left it in the library..."></textarea>
                                    <div class="form-text"><i class="bi bi-info-circle"></i> Your answer helps the university understand common loss patterns to improve campus awareness.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Upload an Image (Optional)</label>
                                    <input type="file" name="image" class="form-control" accept="image/*">
                                    <div class="form-text">Max 5MB. JPG, PNG, GIF.</div>
                                </div>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-outline-secondary" onclick="prevStep(3)">Back</button>
                                    <button type="submit" class="btn btn-success"><i class="bi bi-send"></i> Submit Report</button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    let currentStep = 1;
    const totalSteps = 4;

    function updateProgress() {
        const percent = ((currentStep - 1) / (totalSteps - 1)) * 100;
        document.getElementById('progressBar').style.width = percent + '%';
    }

    // Validates fields before moving forward
    function nextStep(step) {
        const currentStepDiv = document.getElementById('step' + currentStep);
        
        // Find all inputs/selects/textareas inside the current step that are marked required
        const requiredFields = currentStepDiv.querySelectorAll('input[required], select[required], textarea[required]');
        
        let isValid = true;

        requiredFields.forEach(field => {
            // Check if the field is empty
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid'); // Add red border
            } else {
                field.classList.remove('is-invalid'); // Remove red border if filled
            }
        });

        if (isValid) {
            // If everything is valid, move to the next step
            currentStepDiv.classList.remove('active');
            currentStep = step;
            document.getElementById('step' + currentStep).classList.add('active');
            updateProgress();
        } else {
            // If invalid, do nothing. The red borders will show the user what they missed.
        }
    }

    function prevStep(step) {
        document.getElementById('step' + currentStep).classList.remove('active');
        currentStep = step;
        document.getElementById('step' + currentStep).classList.add('active');
        updateProgress();
    }

    function selectCategory(cat) {
        // Remove selected class from all
        document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('selected'));
        // Add selected class to clicked
        document.getElementById('cat-' + cat).classList.add('selected');
        // Set hidden input value
        document.getElementById('input_category').value = cat;
        // Enable Next button
        document.getElementById('btnNext1').disabled = false;
    }

    // Remove the red error border as soon as the user starts typing
    document.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                this.classList.remove('is-invalid');
            }
        });
    });

    // Initialize progress bar
    updateProgress();
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>