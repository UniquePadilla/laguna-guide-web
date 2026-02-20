<?php
include 'db_connect.php';

// Check if column exists
$check = $conn->query("SHOW COLUMNS FROM spots LIKE 'featured'");
if ($check->num_rows == 0) {
    // Add column
    $sql = "ALTER TABLE spots ADD COLUMN featured TINYINT(1) DEFAULT 0";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'featured' added successfully.";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} else {
    echo "Column 'featured' already exists.";
}

$conn->close();
?>