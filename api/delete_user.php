<?php
header('Content-Type: application/json');
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if current user is admin
$current_user_id = $_SESSION['user_id'];
$check_admin = $conn->query("SELECT role FROM users WHERE id = $current_user_id");
$current_role = $check_admin->fetch_assoc()['role'];

if ($current_role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

$user_id = $data['id'];

// Prevent deleting self
if ($user_id == $current_user_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
    exit();
}

$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting user: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>