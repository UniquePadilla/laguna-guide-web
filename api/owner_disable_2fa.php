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
$code = $data['code'] ?? '';
$user_id = $_SESSION['user_id'];

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Code is required']);
    exit();
}

if (!isset($_SESSION['disable_2fa_code'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please request a new code.']);
    exit();
}

if (time() > $_SESSION['disable_2fa_expiry']) {
    unset($_SESSION['disable_2fa_code']);
    echo json_encode(['success' => false, 'message' => 'Code expired.']);
    exit();
}

if ($code == $_SESSION['disable_2fa_code']) {
    // Disable 2FA
    try {
        $update_stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 0 WHERE id = ?");
        $update_stmt->bind_param("i", $user_id);
        
        if ($update_stmt->execute()) {
            unset($_SESSION['disable_2fa_code']);
            echo json_encode(['success' => true, 'message' => 'Two-Factor Authentication Disabled']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to disable 2FA']);
        }
    } catch (Exception $e) {
        error_log("Error disabling 2FA: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
}

$conn->close();
?>
