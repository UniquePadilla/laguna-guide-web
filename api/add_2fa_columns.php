<?php
include __DIR__ . '/../db_connect.php';

// Add columns for 2FA
$sql = "ALTER TABLE users 
        ADD COLUMN two_factor_secret VARCHAR(255) DEFAULT NULL,
        ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0";

if ($conn->query($sql) === TRUE) {
    echo "Columns added successfully";
} else {
    // Check if duplicate column error
    if (strpos($conn->error, 'Duplicate column') !== false) {
        echo "Columns already exist";
    } else {
        echo "Error adding columns: " . $conn->error;
    }
}

$conn->close();
?>