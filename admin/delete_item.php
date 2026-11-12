<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

if (isset($_GET['item_id'])) {
    $item_id = intval($_GET['item_id']);
    
    // Security Check: Only allow deletion if status is 'lost' or 'pending_verification'
    $check = $conn->prepare("SELECT status FROM items WHERE item_id = ?");
    $check->bind_param("i", $item_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (in_array($row['status'], ['lost', 'pending_verification'])) {
            // Delete the item
            $stmt = $conn->prepare("DELETE FROM items WHERE item_id = ?");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    $check->close();
}

header('Location: moderate_feed.php?deleted=1');
exit;
?>