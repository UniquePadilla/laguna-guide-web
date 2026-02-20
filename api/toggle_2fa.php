<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$enabled = isset($data['enabled']) && $data['enabled'] ? 1 : 0;
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
$stmt->bind_param("ii", $enabled, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => '2FA settings updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating 2FA']);
}
$stmt->close();
$conn->close();
?>