<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['title'])) {
    echo json_encode(['success' => false, 'message' => 'Title is required']);
    exit;
}

$title = $data['title'];
$description = isset($data['description']) ? $data['description'] : '';
$icon = isset($data['icon']) ? $data['icon'] : 'fas fa-star';

$sql = "INSERT INTO features (title, description, icon, status) VALUES (?, ?, ?, 'active')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $title, $description, $icon);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Feature added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>