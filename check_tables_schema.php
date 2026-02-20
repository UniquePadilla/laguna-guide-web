<?php
include 'db_connect.php';

echo "Users Table:\n";
$result = $conn->query("DESCRIBE users");
while($row = $result->fetch_assoc()) { print_r($row); }

echo "\nSpots Table:\n";
$result = $conn->query("DESCRIBE spots");
while($row = $result->fetch_assoc()) { print_r($row); }
?>