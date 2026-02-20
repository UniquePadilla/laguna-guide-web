<?php
include 'db_connect.php';
$sql = "ALTER TABLE user_activity ADD COLUMN status VARCHAR(20) DEFAULT 'pending'";
if ($conn->query($sql) === TRUE) {
    echo "Column 'status' added successfully";
} else {
    echo "Error adding column: " . $conn->error;
}
?>