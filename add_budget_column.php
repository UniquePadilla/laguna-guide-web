<?php
require_once 'db_connect.php';

$sql = "ALTER TABLE user_preferences ADD COLUMN budget VARCHAR(50) DEFAULT NULL AFTER session_id";

if ($conn->query($sql) === TRUE) {
    echo "Column 'budget' added successfully";
} else {
    echo "Error adding column: " . $conn->error;
}

$conn->close();
?>
