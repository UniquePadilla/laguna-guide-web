<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$password = $data['password'] ?? '';

// Verify password before disabling (security best practice)
if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required to disable 2FA']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check password
$sql = "SELECT password FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid password']);
    exit;
}

// Disable 2FA
$update_sql = "UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("i", $user_id);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Two-Factor Authentication disabled']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$conn->close();
?>