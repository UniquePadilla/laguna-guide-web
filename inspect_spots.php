<?php
include 'db_connect.php';
$result = $conn->query("SELECT id, name, entranceFee FROM spots LIMIT 5");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
?>