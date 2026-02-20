<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';
require_once '../lib/MailHelper.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    // Get user email
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $email = $user['email'];
    $stmt->close();
    
    if (!$email) {
        throw new Exception("Email not found for this user.");
    }
    
    // Generate 6-digit code
    $code = rand(100000, 999999);
    
    // Store code in session (valid for 10 minutes)
    $_SESSION['temp_2fa_code'] = $code;
    $_SESSION['temp_2fa_expiry'] = time() + 600;
    
    // Send Email
    $subject = "Verify Identity: Enable Two-Factor Authentication";
    $body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 5px;'>
            <h2>Verify Your Identity</h2>
            <p>You have requested to enable Two-Factor Authentication (2FA) for your account.</p>
            <p>To confirm that you are the one making this request, please enter the following verification code:</p>
            <h1 style='color: #4a90e2; letter-spacing: 5px;'>$code</h1>
            <p>This code will expire in 10 minutes.</p>
            <p><strong>If you did not initiate this request, please change your password immediately.</strong></p>
        </div>
    ";
    
    if (MailHelper::sendEmail($email, $subject, $body)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Verification code sent to ' . $email,
            'email' => $email
        ]);
    } else {
        throw new Exception("Failed to send email. Please check server logs and mail configuration.");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
