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

$user_id = $_SESSION['user_id'];
$new_email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';

if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

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
        if (!password_verify($password, $row['password'])) {
            echo json_encode(['success' => false, 'message' => 'Incorrect password']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    // Check if email is already taken by another user
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_stmt->bind_param("si", $new_email, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email address is already in use']);
        exit();
    }

    // Update email
    $update_stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_email, $user_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['email'] = $new_email; // Update session
        echo json_encode(['success' => true, 'message' => 'Email updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update email']);
    }

} catch (Exception $e) {
    error_log("Error updating email: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$conn->close();
?>
