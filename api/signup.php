<?php
header('Content-Type: application/json');
include '../db_connect.php';

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

$username = $data['username'];
$email = $data['email'];
$password = password_hash($data['password'], PASSWORD_DEFAULT);
$role = isset($data['role']) ? strtolower((string)$data['role']) : 'user'; // Default to user

// Validate role
if ($role !== 'user' && $role !== 'admin' && $role !== 'business_owner') {
    $role = 'user'; // Default to user if invalid
}

// Business Owner specific handling
$status = 'active';
$business_name = null;
$business_address = null;
$permit_number = null;
$contact_number = null;

if ($role === 'business_owner') {
    $status = 'pending';
    if (empty($data['business_name']) || empty($data['business_address']) || empty($data['permit_number']) || empty($data['contact_number'])) {
        echo json_encode(['success' => false, 'message' => 'All business fields are required']);
        exit;
    }
    $business_name = $data['business_name'];
    $business_address = $data['business_address'];
    $permit_number = $data['permit_number'];
    $contact_number = $data['contact_number'];
}

// Check if username or email already exists
$check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
$check_stmt = $conn->prepare($check_sql);
if (!$check_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error (prepare check): ' . $conn->error]);
    exit;
}
$check_stmt->bind_param("ss", $username, $email);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Username or Email already exists']);
    exit;
}
$check_stmt->close();

// Insert new user
$sql = "INSERT INTO users (username, email, password, role, status, business_name, business_address, permit_number, contact_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error (prepare insert): ' . $conn->error]);
    exit;
}
$stmt->bind_param("sssssssss", $username, $email, $password, $role, $status, $business_name, $business_address, $permit_number, $contact_number);

if ($stmt->execute()) {
    $msg = ($role === 'business_owner') 
        ? 'Account created successfully. Please wait for admin approval.' 
        : 'Account created successfully';
    echo json_encode(['success' => true, 'message' => $msg]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating account: ' . $conn->error]);
}

$stmt->close();
$conn->close();
