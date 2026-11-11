<?php
session_start();
require_once '../config/database.php';
require_once '../libraries/fpdf/fpdf.php'; // Adjust path if your fpdf folder is named differently

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Fetch the latest AI summary
$latest_ai = $conn->query("SELECT summary_text, created_at FROM ai_summaries ORDER BY created_at DESC LIMIT 1")->fetch_assoc();

if (!$latest_ai) {
    die("No AI insights have been generated yet.");
}

// Initialize FPDF
$pdf = new FPDF();
$pdf->AddPage();

// --- Header ---
$pdf->SetFont('Arial', 'B', 20);
$pdf->SetTextColor(30, 64, 175); // Primary Blue
$pdf->Cell(0, 15, 'Intelligent Lost & Found System', 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(15, 23, 42); // Dark Slate
$pdf->Cell(0, 10, 'AI Analytics Insights Report', 0, 1, 'C');

$pdf->SetFont('Arial', 'I', 10);
$pdf->SetTextColor(100, 116, 139); // Gray
$pdf->Cell(0, 10, 'Generated on: ' . date('M d, Y \a\t g:i A', strtotime($latest_ai['created_at'])), 0, 1, 'C');

$pdf->Ln(10);

// --- Horizontal Line ---
$pdf->SetDrawColor(226, 232, 240);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(10);

// --- Body ---
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(51, 65, 85); // Dark Gray Text

// FPDF doesn't handle UTF-8 perfectly out of the box, so we clean the text
$clean_text = utf8_decode($latest_ai['summary_text']); 
$pdf->MultiCell(0, 10, $clean_text, 0, 'L');

$pdf->Ln(20);

// --- Footer / Signature ---
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 10, 'This report was generated locally using Ollama AI. No student data was sent to external servers.', 0, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 10, 'Signed by: ___________________________', 0, 1, 'L');
$pdf->Cell(0, 5, 'University Lost & Found Administration', 0, 1, 'L');

// Output the PDF to the browser as a download
$pdf->Output('D', 'AI_Insights_Report_' . date('Ymd') . '.pdf');
?>