<?php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';
require_once '../lib/MailHelper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business_owner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'] ?? '';

// If email not in session, fetch it
if (empty($email)) {
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $email = $row['email'];
        $_SESSION['email'] = $email;
    }
}

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email not found for user']);
    exit();
}

try {
    $code = rand(100000, 999999);
    $_SESSION['disable_2fa_code'] = $code;
    $_SESSION['disable_2fa_expiry'] = time() + 600; // 10 minutes

    $subject = "Disable 2FA Verification Code - Tourist Guide System";
    $body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 5px;'>
            <h2>Disable Two-Factor Authentication</h2>
            <p>You requested to disable 2FA on your account. Use the code below to confirm:</p>
            <h1 style='color: #e74c3c; letter-spacing: 5px;'>$code</h1>
            <p>This code will expire in 10 minutes.</p>
            <p>If you did not request this, please change your password immediately.</p>
        </div>
    ";

    if (MailHelper::sendEmail($email, $subject, $body)) {
        echo json_encode(['success' => true, 'message' => 'Verification code sent']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email']);
    }

} catch (Exception $e) {
    error_log("Error sending 2FA disable code: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error']);
}
?>
