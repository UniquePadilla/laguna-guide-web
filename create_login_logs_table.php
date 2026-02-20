<?php
include 'db_connect.php';

// SQL to create login_logs table
$sql = "CREATE TABLE IF NOT EXISTS login_logs (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table login_logs created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
