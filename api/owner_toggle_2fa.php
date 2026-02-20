<?php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business_owner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$enabled = isset($data['enabled']) && $data['enabled'] === true ? 1 : 0;
$user_id = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
    $stmt->bind_param("ii", $enabled, $user_id);
    
    if ($stmt->execute()) {
        $status = $enabled ? "enabled" : "disabled";
        echo json_encode(['success' => true, 'message' => "Two-Factor Authentication has been $status."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update 2FA settings']);
    }

} catch (Exception $e) {
    error_log("Error toggling 2FA: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$conn->close();
?>
