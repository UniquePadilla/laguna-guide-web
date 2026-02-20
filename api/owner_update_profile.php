<?php
header('Content-Type: application/json');
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business_owner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$business_name = $_POST['business_name'] ?? '';
$business_address = $_POST['business_address'] ?? '';
$permit_number = $_POST['permit_number'] ?? '';
$contact_number = $_POST['contact_number'] ?? '';

if (empty($business_name)) {
    echo json_encode(['success' => false, 'message' => 'Business Name is required']);
    exit;
}

$sql = "UPDATE users SET business_name=?, business_address=?, permit_number=?, contact_number=? WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssi", $business_name, $business_address, $permit_number, $contact_number, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>