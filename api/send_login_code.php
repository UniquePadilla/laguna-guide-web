<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';
require_once '../lib/MailHelper.php';

// Check if partial login session exists
if (!isset($_SESSION['partial_login_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

$userId = $_SESSION['partial_login_user_id'];

// Get user email
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$email = $user['email'];
$stmt->close();

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Email not found for this user.']);
    exit;
}

// Generate 6-digit code
$code = rand(100000, 999999);

// Store code in session (valid for 10 minutes)
$_SESSION['login_2fa_code'] = $code;
$_SESSION['login_2fa_expiry'] = time() + 600;

// Send Email
$subject = "Login Verification Code - Tourist Guide System";
$body = "
    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 5px;'>
        <h2>Login Verification</h2>
        <p>Your verification code for logging in is:</p>
        <h1 style='color: #4a90e2; letter-spacing: 5px;'>$code</h1>
        <p>This code will expire in 10 minutes.</p>
        <p>If you did not attempt to login, please change your password immediately.</p>
    </div>
";

if (MailHelper::sendEmail($email, $subject, $body)) {
    echo json_encode(['success' => true, 'message' => 'Verification code sent to your email.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send verification email. Please check server logs and mail configuration.']);
}

$conn->close();
?>
