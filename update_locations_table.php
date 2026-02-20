<?php
include 'db_connect.php';

// Add user_id column to locations table to link spots to owners
$sql = "ALTER TABLE locations ADD COLUMN user_id INT DEFAULT NULL";
if ($conn->query($sql) === TRUE) {
    echo "Column 'user_id' added successfully to locations table\n";
    
    // Add foreign key constraint (optional but good for integrity)
    // $sql_fk = "ALTER TABLE locations ADD CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id)";
    // $conn->query($sql_fk);
} else {
    echo "Error adding column 'user_id': " . $conn->error . "\n";
}

$conn->close();
?>