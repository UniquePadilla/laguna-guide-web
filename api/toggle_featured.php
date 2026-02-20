<?php
header('Content-Type: application/json');
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id']) || !isset($data['featured'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$id = (int)$data['id'];
$featured = (int)$data['featured']; // 0 or 1

$stmt = $conn->prepare("UPDATE spots SET featured = ? WHERE id = ?");
$stmt->bind_param("ii", $featured, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Status updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>