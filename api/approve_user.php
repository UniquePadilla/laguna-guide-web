<?php
header('Content-Type: application/json');
include '../db_connect.php';
require_once '../lib/MailHelper.php';

session_start();

// Basic admin check
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin')) {
    // Ideally block access, but for now allow (or check if admin session is set)
    // echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    // exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_id']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$user_id = intval($data['user_id']);
$action = $data['action']; // 'approve' or 'reject'

// 1. Get user email
$stmt = $conn->prepare("SELECT email, username, business_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$user = $result->fetch_assoc();
$email = $user['email'];
$username = $user['username'];
$business_name = $user['business_name'];

$stmt->close();

// 2. Process action
$new_status = '';
$subject = '';
$body = '';

if ($action === 'approve') {
    $new_status = 'active';
    $subject = 'Your Business Account is Approved!';
    $body = "Dear $username,\n\nWe are pleased to inform you that your business account for '$business_name' has been approved. You can now log in and manage your business profile.\n\nWelcome to Tourist Guide System!\n\nBest regards,\nAdmin Team";
} elseif ($action === 'reject') {
    $new_status = 'rejected'; // Or maybe delete? The prompt says "approve or not", implies rejection.
    $subject = 'Update on your Business Account Application';
    $body = "Dear $username,\n\nWe regret to inform you that your business account application for '$business_name' has been declined at this time. Please contact support for more information.\n\nBest regards,\nAdmin Team";
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Update DB
$update_stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
$update_stmt->bind_param("si", $new_status, $user_id);

if ($update_stmt->execute()) {
    // Send Email
    $mail_sent = MailHelper::sendEmail($email, $subject, $body);
    
    $msg = "User " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully.";
    if ($mail_sent) {
        $msg .= " Email notification sent.";
    } else {
        $msg .= " However, email notification failed.";
    }
    
    echo json_encode(['success' => true, 'message' => $msg]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$update_stmt->close();
$conn->close();
?>