<?php
include 'db_connect.php';
$res = $conn->query('SELECT id, username FROM users LIMIT 1');
if ($row = $res->fetch_assoc()) {
    print_r($row);
} else {
    echo "No users found. Creating one...\n";
    $conn->query("INSERT INTO users (username, email, password) VALUES ('testuser', 'test@example.com', 'password')");
    echo "User created with ID: " . $conn->insert_id . "\n";
}
?>