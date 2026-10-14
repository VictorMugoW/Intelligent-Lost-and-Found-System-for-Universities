<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$type = $_GET['type'] ?? '';
$date = date('Y-m-d');

// Validate type
if (!in_array($type, ['items', 'claims', 'users'])) {
    header('Location: reports.php');
    exit;
}

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_report_' . $date . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// === ITEMS REPORT ===
if ($type === 'items') {
    fputcsv($output, [
        'Item ID',
        'Item Name',
        'Category',
        'Colour',
        'Type',
        'Status',
        'Location',
        'Date Lost/Found',
        'Description',
        'Reported By',
        'Reporter Email',
        'Finder Name',
        'Finder Email',
        'Date Reported'
    ]);

    $query = "
        SELECT 
            i.item_id, i.item_name, i.category, i.colour, i.type, i.status,
            i.location, i.date_lost_found, i.description, i.created_at,
            reporter.full_name AS reporter_name, reporter.email AS reporter_email,
            finder.full_name AS finder_name, finder.email AS finder_email
        FROM items i
        JOIN users reporter ON i.user_id = reporter.user_id
        LEFT JOIN users finder ON i.finder_id = finder.user_id
        ORDER BY i.created_at DESC
    ";
    $result = $conn->query($query);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['item_id'],
            $row['item_name'],
            $row['category'],
            $row['colour'] ?: 'N/A',
            $row['type'],
            $row['status'],
            $row['location'] ?: 'N/A',
            $row['date_lost_found'] ?: 'N/A',
            $row['description'] ?: 'N/A',
            $row['reporter_name'],
            $row['reporter_email'],
            $row['finder_name'] ?: 'N/A',
            $row['finder_email'] ?: 'N/A',
            $row['created_at']
        ]);
    }
}

// === CLAIMS REPORT ===
if ($type === 'claims') {
    fputcsv($output, [
        'Claim ID',
        'Item Name',
        'Item Category',
        'Claimant Name',
        'Claimant Email',
        'Claim Reason',
        'Status',
        'Admin Reason',
        'Supporting Lost Report',
        'Submitted At',
        'QR Token'
    ]);

    $query = "
        SELECT 
            c.claim_id, c.claim_reason, c.status, c.admin_reason,
            c.qr_token, c.created_at, c.supporting_lost_item_id,
            i.item_name, i.category,
            u.full_name AS claimant_name, u.email AS claimant_email
        FROM claims c
        JOIN items i ON c.item_id = i.item_id
        JOIN users u ON c.claimed_by = u.user_id
        ORDER BY c.created_at DESC
    ";
    $result = $conn->query($query);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['claim_id'],
            $row['item_name'],
            $row['category'],
            $row['claimant_name'],
            $row['claimant_email'],
            $row['claim_reason'] ?: 'N/A',
            $row['status'],
            $row['admin_reason'] ?: 'N/A',
            $row['supporting_lost_item_id'] ? 'Yes (Item #' . $row['supporting_lost_item_id'] . ')' : 'No',
            $row['created_at'],
            $row['qr_token'] ?: 'N/A'
        ]);
    }
}

// === USERS REPORT ===
if ($type === 'users') {
    fputcsv($output, [
        'User ID',
        'Full Name',
        'Email',
        'Phone',
        'Role',
        'Student ID',
        'Staff ID',
        'Date Registered'
    ]);

    $query = "
        SELECT 
            user_id, full_name, email, phone, role,
            student_id, staff_id, created_at
        FROM users
        ORDER BY created_at DESC
    ";
    $result = $conn->query($query);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['user_id'],
            $row['full_name'],
            $row['email'],
            $row['phone'] ?: 'N/A',
            $row['role'],
            $row['student_id'] ?: 'N/A',
            $row['staff_id'] ?: 'N/A',
            $row['created_at']
        ]);
    }
}

fclose($output);
exit;
?>