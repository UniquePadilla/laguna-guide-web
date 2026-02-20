<?php
include 'db_connect.php';

// Add user_id column to spots table to link spots to owners
$sql = "ALTER TABLE spots ADD COLUMN user_id INT DEFAULT NULL";
if ($conn->query($sql) === TRUE) {
    echo "Column 'user_id' added successfully to spots table\n";
} else {
    echo "Error adding column 'user_id': " . $conn->error . "\n";
}

$conn->close();
?>