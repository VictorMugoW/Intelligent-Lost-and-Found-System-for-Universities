<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (isset($_GET['item_id'])) {
    $item_id = intval($_GET['item_id']);
    $user_id = $_SESSION['user_id'];

    // Make sure the user actually owns this item and it's currently 'lost'
    $stmt = $conn->prepare("UPDATE items SET status = 'returned' WHERE item_id = ? AND user_id = ? AND status = 'lost'");
    $stmt->bind_param("ii", $item_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Redirect back to their dashboard
header('Location: browse_items.php?view=personal&tab=lost');
exit;
?>