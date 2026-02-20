<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$code = $data['code'] ?? '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Code is required']);
    exit;
}

if (!isset($_SESSION['temp_2fa_code'])) {
    echo json_encode(['success' => false, 'message' => 'Verification session expired. Please try again.']);
    exit;
}

if (time() > $_SESSION['temp_2fa_expiry']) {
    unset($_SESSION['temp_2fa_code']);
    unset($_SESSION['temp_2fa_expiry']);
    echo json_encode(['success' => false, 'message' => 'Verification code expired.']);
    exit;
}

if ($code == $_SESSION['temp_2fa_code']) {
    
    $user_id = $_SESSION['user_id'];
    
    $sql = "UPDATE users SET two_factor_enabled = 1, two_factor_secret = NULL WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        unset($_SESSION['temp_2fa_code']);
        unset($_SESSION['temp_2fa_expiry']);
        echo json_encode(['success' => true, 'message' => 'Two-Factor Authentication (Email) enabled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
}

$conn->close();
?>
