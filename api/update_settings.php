<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);

$username = isset($data['username']) ? trim($data['username']) : null;
$email = isset($data['email']) ? trim($data['email']) : null;
$current_password = isset($data['current_password']) ? $data['current_password'] : null;
$new_password = isset($data['new_password']) ? $data['new_password'] : null;

if (!$username || !$email) {
    echo json_encode(['success' => false, 'message' => 'Username and Email are required']);
    exit;
}

// Verify current password if provided (required for changing sensitive info or password)
// For simple profile updates (name/email), we might not enforce password, but it's good practice.
// However, the UI has a separate Security section.
// Let's require current password ONLY if changing the password.
// For profile info, we can just update.

// Update Profile Info
$sql = "UPDATE users SET username = ?, email = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $username, $email, $user_id);

if ($stmt->execute()) {
    // Update Session
    $_SESSION['username'] = $username;
    
    // Handle Password Update
    if (!empty($new_password)) {
        if (empty($current_password)) {
            echo json_encode(['success' => false, 'message' => 'Profile updated, but Password change requires Current Password']);
            exit;
        }

        // Verify current password
        $verify_sql = "SELECT password FROM users WHERE id = ?";
        $v_stmt = $conn->prepare($verify_sql);
        $v_stmt->bind_param("i", $user_id);
        $v_stmt->execute();
        $result = $v_stmt->get_result();
        $user = $result->fetch_assoc();

        if (password_verify($current_password, $user['password'])) {
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pw_sql = "UPDATE users SET password = ? WHERE id = ?";
            $pw_stmt = $conn->prepare($update_pw_sql);
            $pw_stmt->bind_param("si", $new_hashed_password, $user_id);
            if ($pw_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Profile and Password updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating password']);
            }
        } else {
             echo json_encode(['success' => false, 'message' => 'Profile updated, but Invalid Current Password']);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating profile: ' . $conn->error]);
}

$conn->close();
?>