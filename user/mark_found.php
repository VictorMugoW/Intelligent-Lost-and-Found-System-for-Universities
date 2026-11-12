<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (isset($_GET['item_id'])) {
    $item_id = intval($_GET['item_id']);
    $finder_id = $_SESSION['user_id'];

    // Update the item: set status to pending_verification and save the finder's ID
    $stmt = $conn->prepare("UPDATE items SET status = 'pending_verification', finder_id = ? WHERE item_id = ? AND status = 'lost'");
    $stmt->bind_param("ii", $finder_id, $item_id);
    $stmt->execute();
    $stmt->close();
}

// Redirect back to the community feed
header('Location: browse_items.php?view=community');
exit;
?>