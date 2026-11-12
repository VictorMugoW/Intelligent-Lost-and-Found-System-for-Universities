<?php
session_start();
require_once '../config/database.php';
require_once '../libraries/fpdf/fpdf.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = intval($_GET['user_id']);

// Fetch user data and return count
$stmt = $conn->prepare("
    SELECT u.full_name, 
    (SELECT COUNT(*) FROM items i WHERE i.user_id = u.user_id AND i.type = 'found' AND i.status = 'returned') as return_count 
    FROM users u WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || $user['return_count'] < 2) {
    die("User does not qualify for a certificate yet.");
}

$count = $user['return_count'];
$badge = ($count >= 7) ? 'GOLD' : (($count >= 4) ? 'SILVER' : 'BRONZE');
$badge_color = ($count >= 7) ? [218, 165, 32] : (($count >= 4) ? [192, 192, 192] : [205, 127, 50]); // RGB for Gold/Silver/Bronze

// Create Landscape A4 PDF
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();

// --- BORDER ---
$pdf->SetDrawColor(30, 64, 175); // Primary Blue
$pdf->SetLineWidth(2);
$pdf->Rect(10, 10, 277, 190); // Outer Border
$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(1);
$pdf->Rect(15, 15, 267, 180); // Inner Border

// --- HEADER ---
$pdf->SetY(35);
$pdf->SetFont('Arial', 'B', 24);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 15, 'STRATHMORE UNIVERSITY', 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(0, 10, 'Lost & Found Department', 0, 1, 'C');

// --- TITLE ---
$pdf->SetY(70);
$pdf->SetFont('Arial', 'B', 32);
$pdf->SetTextColor(30, 64, 175);
$pdf->Cell(0, 15, 'CERTIFICATE OF TRUSTWORTHINESS', 0, 1, 'C');

// --- BODY ---
$pdf->SetY(100);
$pdf->SetFont('Arial', '', 16);
$pdf->SetTextColor(51, 65, 85);
$pdf->Cell(0, 10, 'This certificate is proudly presented to', 0, 1, 'C');

$pdf->SetY(115);
$pdf->SetFont('Arial', 'B', 28);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 15, strtoupper($user['full_name']), 0, 1, 'C');

$pdf->SetY(140);
$pdf->SetFont('Arial', '', 14);
$pdf->SetTextColor(51, 65, 85);
$pdf->Cell(0, 10, 'In recognition of their outstanding honesty and integrity in returning', 0, 1, 'C');
$pdf->Cell(0, 10, 'a total of ' . $count . ' lost items to their rightful owners.', 0, 1, 'C');

// --- BADGE TEXT ---
$pdf->SetY(170);
$pdf->SetFont('Arial', 'B', 18);
$pdf->SetTextColor($badge_color[0], $badge_color[1], $badge_color[2]);
$pdf->Cell(0, 10, 'Awarded the ' . $badge . ' Guardian Badge', 0, 1, 'C');

// --- SIGNATURES ---
$pdf->SetY(210);
$pdf->SetFont('Arial', 'I', 12);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(130, 10, '_____________________________', 0, 0, 'C');
$pdf->Cell(130, 10, '_____________________________', 0, 1, 'C');

$pdf->Cell(130, 5, 'Vice Chancellor', 0, 0, 'C');
$pdf->Cell(130, 5, 'Lost & Found Coordinator', 0, 1, 'C');

// Output
$pdf->Output('D', 'Certificate_' . str_replace(' ', '_', $user['full_name']) . '.pdf');
?>