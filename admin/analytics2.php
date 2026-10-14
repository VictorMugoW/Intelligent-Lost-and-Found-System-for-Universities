<?php
echo "TEST 1: file reached<br>";

require_once '../config/database.php';
echo "TEST 2: db ok<br>";

echo "TEST 3: " . date('Y-m-d H:i:s') . "<br>";

$count = $conn->query("SELECT COUNT(*) as c FROM items")->fetch_assoc()['c'];
echo "TEST 4: $count items<br>";

echo "TEST 5: DONE!";
?>