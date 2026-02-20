<?php
header('Content-Type: application/json');
include '../db_connect.php';

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['name']) || !isset($data['email']) || !isset($data['message'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

$name = $data['name'];
$email = $data['email'];
$message = $data['message'];

$stmt = $conn->prepare("INSERT INTO messages (name, email, message) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $message);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>
