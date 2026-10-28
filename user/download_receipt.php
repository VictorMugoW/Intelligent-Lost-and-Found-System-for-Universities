<?php
session_start();
require_once '../config/database.php';
require_once '../libraries/fpdf/fpdf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$claim_id = intval($_GET['claim_id'] ?? 0);

if ($claim_id <= 0) {
    header('Location: my_claims.php');
    exit;
}

// Fetch claim + item + user data (ensure it belongs to this user and is approved)
$stmt = $conn->prepare("
    SELECT c.claim_id, c.status, c.qr_token, c.qr_generated_at,
           i.item_name, i.category, i.colour, i.location, i.description,
           u.full_name, u.email, u.phone, u.student_id, u.staff_id
    FROM claims c
    JOIN items i ON c.item_id = i.item_id
    JOIN users u ON c.claimed_by = u.user_id
    WHERE c.claim_id = ? AND c.claimed_by = ? AND c.status = 'approved'
");
$stmt->bind_param("ii", $claim_id, $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    die("Receipt not available. The claim must be approved first.");
}

// Verify QR code image exists
$qr_path = '../qrcodes/claim_' . $claim_id . '.png';
$has_qr = file_exists($qr_path);

// --- BUILD THE PDF ---
class PDF extends FPDF
{
    // Custom Header
    function Header()
    {
        // Dark blue top bar
        $this->SetFillColor(15, 23, 42); // #0F172A
        $this->Rect(0, 0, 210, 35, 'F');
        
        // White brand text
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 18);
        $this->SetXY(15, 12);
        $this->Cell(0, 8, 'INTELLIGENT LOST & FOUND', 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);
        $this->SetXY(15, 22);
        $this->Cell(0, 6, 'Official Collection Receipt', 0, 1, 'L');
        
        $this->SetTextColor(0, 0, 0);
        $this->Ln(15);
    }

    // Custom Footer
    function Footer()
    {
        $this->SetY(-20);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5, 'This is a system-generated receipt. Present this document along with your student/staff ID at the Lost & Found office.', 0, 1, 'C');
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Reference
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(30, 64, 175); // Blue
$pdf->Cell(0, 8, 'Reference: CLAIM-' . str_pad($claim_id, 5, '0', STR_PAD_LEFT), 0, 1, 'L');
$pdf->Ln(2);

// Status badge
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(16, 185, 129); // Green
$pdf->Cell(0, 6, 'STATUS: APPROVED - READY FOR COLLECTION', 0, 1, 'L');
$pdf->Ln(5);

// --- Two-column layout: Details on left, QR on right ---
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(120, 8, 'ITEM DETAILS', 0, 0, 'L');
$pdf->Cell(0, 8, 'VERIFICATION CODE', 0, 1, 'L');
$pdf->Ln(2);

$startY = $pdf->GetY();

// Left column - item details
$pdf->SetFont('Arial', '', 10);
$pdf->SetX(15);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(35, 6, 'Item Name:', 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(80, 6, $data['item_name'], 0, 1);

$pdf->SetX(15);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(35, 6, 'Category:', 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(80, 6, $data['category'], 0, 1);

$pdf->SetX(15);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(35, 6, 'Colour:', 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(80, 6, $data['colour'] ?: 'N/A', 0, 1);

$pdf->SetX(15);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(35, 6, 'Location Found:', 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->MultiCell(80, 6, $data['location'] ?: 'N/A');

$pdf->SetX(15);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(35, 6, 'Description:', 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->MultiCell(80, 6, $data['description'] ?: 'N/A');

// Right column - QR Code
if ($has_qr) {
    $pdf->Image($qr_path, 150, $startY - 2, 40, 40);
}

$pdf->Ln(5);

// --- Claimant details ---
$pdf->SetDrawColor(226, 232, 240);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, 'CLAIMANT DETAILS', 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 10);
$pdf->SetX(15);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(35, 6, 'Full Name:', 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(140, 6, $data['full_name'], 0, 1);

$pdf->SetX(15);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(35, 6, 'Email:', 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(140, 6, $data['email'], 0, 1);

$pdf->SetX(15);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(35, 6, 'Phone:', 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(140, 6, $data['phone'] ?: 'N/A', 0, 1);

if (!empty($data['student_id'])) {
    $pdf->SetX(15);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(35, 6, 'Student No.:', 0, 0);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(140, 6, $data['student_id'], 0, 1);
}

if (!empty($data['staff_id'])) {
    $pdf->SetX(15);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(35, 6, 'Staff No.:', 0, 0);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(140, 6, $data['staff_id'], 0, 1);
}

$pdf->Ln(5);

// --- QR Token ---
if (!empty($data['qr_token'])) {
    $pdf->SetFillColor(241, 245, 249);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 7, 'QR Token: ' . $data['qr_token'], 0, 1, 'C', true);
    $pdf->Ln(3);
}

// --- Important Instructions ---
$pdf->SetFillColor(254, 243, 199);
$pdf->SetDrawColor(245, 158, 11);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(146, 64, 14);
$pdf->Cell(0, 7, 'COLLECTION INSTRUCTIONS', 'TLR', 1, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(146, 64, 14);
$pdf->MultiCell(180, 5, 
    "1. Visit the Lost and Found Office during working hours (Mon-Fri, 8:00 AM - 5:00 PM).\n" .
    "2. Present this printed receipt together with your student/staff ID card.\n" .
    "3. Allow the attendant to scan the QR code on this document for verification.\n" .
    "4. Collect your item and confirm receipt at the desk.\n" .
    "5. Items not collected within 14 days of approval may be forfeited per university policy.",
    'BLR', 'L', true
);

$pdf->Ln(8);

// --- Signature Line ---
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(90, 6, 'Claimant Signature: _____________________', 0, 0, 'L');
$pdf->Cell(90, 6, 'Attendant Signature: _____________________', 0, 1, 'L');

$pdf->Ln(4);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(120, 120, 120);
$pdf->Cell(0, 5, 'Approved on: ' . date('F j, Y, g:i A', strtotime($data['qr_generated_at'])), 0, 1, 'L');

// Output
$filename = 'Collection_Receipt_CLAIM-' . str_pad($claim_id, 5, '0', STR_PAD_LEFT) . '.pdf';
$pdf->Output('D', $filename);
exit;
?>