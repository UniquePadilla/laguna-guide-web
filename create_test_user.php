<?php
include 'db_connect.php';
$username = 'test_ai_user';
$password = password_hash('password123', PASSWORD_DEFAULT);
$email = 'test@example.com';
$role = 'user';

// Check if exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo "User exists.\n";
} else {
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $password, $email, $role);
    if ($stmt->execute()) {
        echo "User created.\n";
    } else {
        echo "Error: " . $stmt->error . "\n";
    }
}
?>