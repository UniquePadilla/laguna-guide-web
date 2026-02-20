<?php
header('Content-Type: application/json');
session_start();
include '../db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID is required']);
    exit;
}

$id = $data['id'];

// Prepare statement to prevent SQL injection
$stmt = $conn->prepare("DELETE FROM spots WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Spot deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting spot: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>
