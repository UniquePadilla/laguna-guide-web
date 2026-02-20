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
$password = $data['password'] ?? '';
$user_id = $_SESSION['user_id'];

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit();
}

try {
    // Verify password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            // Enable 2FA
            $update_stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 1 WHERE id = ?");
            $update_stmt->bind_param("i", $user_id);
            
            if ($update_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Two-Factor Authentication Enabled']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to enable 2FA']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }

} catch (Exception $e) {
    error_log("Error enabling 2FA: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$conn->close();
?>
