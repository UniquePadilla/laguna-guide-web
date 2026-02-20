<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$user_id = $_SESSION['user_id'];
$spot_id = $data['spot_id'];
$type = $data['type']; // 'favorite' or 'visit' or 'booking' or 'direction'

if ($type === 'direction') {
    // For direction, we always add a new log entry (or you could check duplicates if you want unique per user/spot)
    // Here we'll just insert it to track engagement
    $ins = $conn->prepare("INSERT INTO user_activity (user_id, spot_id, activity_type) VALUES (?, ?, ?)");
    $ins->bind_param("iis", $user_id, $spot_id, $type);
    $ins->execute();
    $action = 'added';
} else {
    // Check if exists
    $check = $conn->prepare("SELECT id FROM user_activity WHERE user_id=? AND spot_id=? AND activity_type=?");
    $check->bind_param("iis", $user_id, $spot_id, $type);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // Remove (Toggle OFF)
        $del = $conn->prepare("DELETE FROM user_activity WHERE user_id=? AND spot_id=? AND activity_type=?");
        $del->bind_param("iis", $user_id, $spot_id, $type);
        $del->execute();
        $action = 'removed';
    } else {
        // Add (Toggle ON)
        $ins = $conn->prepare("INSERT INTO user_activity (user_id, spot_id, activity_type) VALUES (?, ?, ?)");
        $ins->bind_param("iis", $user_id, $spot_id, $type);
        $ins->execute();
        $action = 'added';
    }
}

echo json_encode(['success' => true, 'action' => $action]);
?>