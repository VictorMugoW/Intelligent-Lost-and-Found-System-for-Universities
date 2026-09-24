<?php
require_once 'config/database.php';

echo "Database connected successfully!<br>";
echo "Connected to: " . $conn->host_info . "<br>";

$result = $conn->query("SELECT COUNT(*) as total FROM users");
$row = $result->fetch_assoc();
echo "Total users: " . $row['total'];
?>