<?php
include 'db_connect.php';

$sql = "SELECT * FROM login_logs ORDER BY id DESC LIMIT 5";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "0 results";
}
$conn->close();
?>