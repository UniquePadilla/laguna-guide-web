<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);
$code = $data['code'] ?? '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Code is required']);
    exit;
}

if (!isset($_SESSION['partial_login_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

$user_id = $_SESSION['partial_login_user_id'];

// Check Session Code
if (!isset($_SESSION['login_2fa_code'])) {
     echo json_encode(['success' => false, 'message' => 'Verification session expired. Please login again.']);
     exit;
}

if (time() > $_SESSION['login_2fa_expiry']) {
    unset($_SESSION['login_2fa_code']);
    unset($_SESSION['login_2fa_expiry']);
    echo json_encode(['success' => false, 'message' => 'Verification code expired.']);
    exit;
}

if ($code == $_SESSION['login_2fa_code']) {
    // Valid 2FA
    
    // Get user info again to be sure
    $sql = "SELECT id, username, email, role FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    
    // Clear partial session and codes
    unset($_SESSION['partial_login_user_id']);
    unset($_SESSION['login_2fa_code']);
    unset($_SESSION['login_2fa_expiry']);
    
    $redirect = 'index.php';
    if ($user['role'] === 'admin') {
        $redirect = 'admin/admin.php';
    } elseif ($user['role'] === 'business_owner') {
        $redirect = 'owner/owner.php';
    }
    
    // Log the login
    if ($user['role'] !== 'admin') {
         $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
         $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
         $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
         if ($log_stmt) {
             $log_stmt->bind_param("iss", $user['id'], $ip_address, $user_agent);
             $log_stmt->execute();
             $log_stmt->close();
         }
    }
    
    echo json_encode(['success' => true, 'message' => 'Login successful', 'redirect' => $redirect]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid 2FA code']);
}

$conn->close();
?>
