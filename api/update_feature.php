<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id']) || !isset($data['title'])) {
    echo json_encode(['success' => false, 'message' => 'ID and Title are required']);
    exit;
}

$id = $data['id'];
$title = $data['title'];
$description = isset($data['description']) ? $data['description'] : '';
$icon = isset($data['icon']) ? $data['icon'] : 'fas fa-star';

$sql = "UPDATE features SET title = ?, description = ?, icon = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssi", $title, $description, $icon, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Feature updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>