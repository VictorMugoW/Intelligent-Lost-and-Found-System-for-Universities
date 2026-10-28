<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$message_type = '';
$claim_data = null;
$scanned_token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['qr_token'] ?? '');
    $scanned_token = $token;

    if (empty($token)) {
        $message = 'Please enter or scan a QR token.';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("
            SELECT c.claim_id, c.status, c.qr_token, c.qr_generated_at,
                   i.item_name, i.category, i.location, i.description,
                   u.full_name AS claimant, u.email, u.phone
            FROM claims c
            JOIN items i ON c.item_id = i.item_id
            JOIN users u ON c.claimed_by = u.user_id
            WHERE c.qr_token = ?
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $claim_data = $result->fetch_assoc();
            $message = 'QR code verified successfully!';
            $message_type = 'success';
        } else {
            $message = 'Invalid QR token. No matching claim found.';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

$page_title = 'Verify QR Code';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="feature-icon mx-auto mb-3"><i class="bi bi-qr-code-scan"></i></div>
                        <h3 class="fw-bold">Verify QR Code</h3>
                        <p class="text-muted">Scan with camera or enter token manually</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <?php if (!$claim_data): ?>
                        <!-- Tabs -->
                        <ul class="nav nav-pills mb-4" style="gap: 10px;">
                            <li class="nav-item">
                                <button class="nav-link active" id="tab-camera" data-bs-toggle="pill" data-bs-target="#pane-camera" type="button"
                                        style="background: linear-gradient(135deg, #1E40AF, #3B82F6); color: white; border: none;">
                                    <i class="bi bi-camera-video"></i> Camera Scanner
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" id="tab-manual" data-bs-toggle="pill" data-bs-target="#pane-manual" type="button"
                                        style="background: white; color: #0F172A; border: 2px solid #E2E8F0;">
                                    <i class="bi bi-keyboard"></i> Manual Entry
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- Camera Tab -->
                            <div class="tab-pane fade show active" id="pane-camera">
                                <div class="alert alert-info small">
                                    <i class="bi bi-info-circle"></i> Hold the QR code in front of your camera. The system will detect it automatically.
                                </div>
                                <div id="qr-reader" style="width: 100%; max-width: 500px; margin: 0 auto; border-radius: 12px; overflow: hidden;"></div>
                                <div id="camera-status" class="text-center text-muted small mt-3">
                                    <i class="bi bi-camera"></i> Initialising camera...
                                </div>

                                <!-- Hidden form to submit scanned code -->
                                <form method="POST" id="scanForm" class="d-none">
                                    <input type="hidden" name="qr_token" id="scanned_token" value="">
                                </form>
                            </div>

                            <!-- Manual Tab -->
                            <div class="tab-pane fade" id="pane-manual">
                                <form method="POST">
                                    <label class="form-label">QR Token</label>
                                    <div class="input-group">
                                        <input type="text" name="qr_token" class="form-control" placeholder="e.g. CLAIM-1-309AEB76" required>
                                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Verify</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($claim_data): ?>
                        <div class="card" style="border: 2px solid #10B981; box-shadow: none;">
                            <div class="card-header" style="background: linear-gradient(135deg, #10B981, #059669); color: white;">
                                <h5 class="mb-0"><i class="bi bi-check-circle-fill"></i> Claim Verified</h5>
                            </div>
                            <div class="card-body">
                                <h5 class="fw-bold">Item Details</h5>
                                <ul class="list-group mb-3">
                                    <li class="list-group-item"><strong>Item:</strong> <?= htmlspecialchars($claim_data['item_name']) ?></li>
                                    <li class="list-group-item"><strong>Category:</strong> <?= htmlspecialchars($claim_data['category']) ?></li>
                                    <li class="list-group-item"><strong>Location:</strong> <?= htmlspecialchars($claim_data['location'] ?: 'N/A') ?></li>
                                    <li class="list-group-item"><strong>Description:</strong> <?= nl2br(htmlspecialchars($claim_data['description'])) ?></li>
                                </ul>

                                <h5 class="fw-bold">Claimant Details</h5>
                                <ul class="list-group mb-3">
                                    <li class="list-group-item"><strong>Name:</strong> <?= htmlspecialchars($claim_data['claimant']) ?></li>
                                    <li class="list-group-item"><strong>Email:</strong> <?= htmlspecialchars($claim_data['email']) ?></li>
                                    <li class="list-group-item"><strong>Phone:</strong> <?= htmlspecialchars($claim_data['phone'] ?: 'N/A') ?></li>
                                </ul>

                                <div class="alert alert-info">
                                    <strong>Action:</strong> Verify the claimant's physical student/staff ID before releasing the item.
                                </div>

                                <div class="d-flex gap-2 mt-3">
                                    <a href="verify_qr.php" class="btn btn-outline-primary">
                                        <i class="bi bi-arrow-clockwise"></i> Scan Another
                                    </a>
                                    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Load html5-qrcode library -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<script>
<?php if (!$claim_data): ?>
document.addEventListener('DOMContentLoaded', function() {
    const statusEl = document.getElementById('camera-status');
    const qrReaderEl = document.getElementById('qr-reader');
    const scanForm = document.getElementById('scanForm');
    const tokenInput = document.getElementById('scanned_token');

    function onScanSuccess(decodedText, decodedResult) {
        // Stop scanner
        if (html5QrScanner) {
            html5QrScanner.stop().then(() => {
                html5QrScanner.clear();
            }).catch(err => console.warn(err));
        }

        // Flash success message
        statusEl.innerHTML = '<i class="bi bi-check-circle-fill" style="color: #10B981;"></i> QR detected! Verifying...';
        statusEl.style.color = '#10B981';

        // Submit the form
        tokenInput.value = decodedText;
        scanForm.submit();
    }

    function onScanError(errorMessage) {
        // Ignore scan errors (they happen constantly while camera runs)
    }

    // Start scanner
    let html5QrScanner = new Html5Qrcode("qr-reader");
    Html5Qrcode.getCameras().then(devices => {
        if (devices && devices.length) {
            // Prefer back camera if multiple exist
            const cameraId = devices.length > 1 ? devices[devices.length - 1].id : devices[0].id;
            html5QrScanner.start(
                cameraId,
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 }
                },
                onScanSuccess,
                onScanError
            ).then(() => {
                statusEl.innerHTML = '<i class="bi bi-camera-video-fill" style="color: #10B981;"></i> Camera active — point at a QR code';
                statusEl.style.color = '#10B981';
            }).catch(err => {
                statusEl.innerHTML = '<i class="bi bi-camera-video-off"></i> Could not start camera: ' + err;
                statusEl.style.color = '#EF4444';
            });
        } else {
            statusEl.innerHTML = '<i class="bi bi-camera-video-off"></i> No camera found on this device.';
            statusEl.style.color = '#EF4444';
        }
    }).catch(err => {
        statusEl.innerHTML = '<i class="bi bi-camera-video-off"></i> Camera access denied or unavailable.';
        statusEl.style.color = '#EF4444';
    });

    // Switch tab styling
    const tabCamera = document.getElementById('tab-camera');
    const tabManual = document.getElementById('tab-manual');
    tabCamera.addEventListener('shown.bs.tab', function() {
        tabCamera.style.background = 'linear-gradient(135deg, #1E40AF, #3B82F6)';
        tabCamera.style.color = 'white';
        tabManual.style.background = 'white';
        tabManual.style.color = '#0F172A';
        tabManual.style.border = '2px solid #E2E8F0';
    });
    tabManual.addEventListener('shown.bs.tab', function() {
        tabManual.style.background = 'linear-gradient(135deg, #1E40AF, #3B82F6)';
        tabManual.style.color = 'white';
        tabCamera.style.background = 'white';
        tabCamera.style.color = '#0F172A';
        tabCamera.style.border = '2px solid #E2E8F0';
        // Stop camera when switching away
        if (html5QrScanner) {
            html5QrScanner.stop().catch(() => {});
        }
    });
});
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>