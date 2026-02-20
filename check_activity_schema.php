<?php
include 'db_connect.php';
$result = $conn->query("DESCRIBE user_activity");
if ($result) {
    while($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Table user_activity does not exist or error: " . $conn->error;
}
?>