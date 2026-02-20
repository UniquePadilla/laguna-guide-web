<?php
include 'db_connect.php';
$result = $conn->query("SELECT id, username, role FROM users");
while($row = $result->fetch_assoc()) {
    print_r($row);
}
?>