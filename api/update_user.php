<?php
header('Content-Type: application/json');
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$data = json_decode(file_get_contents('php://input'), true);

// Determine target user ID
if (isset($data['id'])) {
    $target_user_id = $data['id'];
} else {
    $target_user_id = $current_user_id;
}

// Permission check
if (!$is_admin && $current_user_id != $target_user_id) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

// Fetch current user data to fill in missing fields if necessary
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $target_user_id);
$stmt->execute();
$current_user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current_user_data) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Prepare fields to update
$username = isset($data['username']) ? $data['username'] : $current_user_data['username'];
$email = isset($data['email']) ? $data['email'] : $current_user_data['email'];

// Role update logic
if ($is_admin && isset($data['role'])) {
    $role = $data['role'];
} else {
    $role = $current_user_data['role'];
}

// Contact number update (handle both 'phone' and 'contact_number')
$contact_number = $current_user_data['contact_number'] ?? '';
if (isset($data['contact_number'])) {
    $contact_number = $data['contact_number'];
} elseif (isset($data['phone'])) {
    $contact_number = $data['phone'];
}

// Password update
$password_sql = "";
$types = "ssssi";
$params = [$username, $email, $role, $contact_number, $target_user_id];

if (!empty($data['password'])) {
    $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $password_sql = ", password = ?";
    $types = "sssssi";
    $params = [$username, $email, $role, $contact_number, $password_hash, $target_user_id];
}

$sql = "UPDATE users SET username = ?, email = ?, role = ?, contact_number = ?" . $password_sql . " WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    // If contact_number column doesn't exist, this might fail. 
    // Ideally we should check if column exists, but we know from schema it does.
    // However, if the query fails for some reason (e.g. strict mode on undefined column), we should catch it.
    // Let's try to update without contact_number if it fails? No, we saw the schema.
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit();
}

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    // Update session if user updated themselves
    if ($current_user_id == $target_user_id) {
        $_SESSION['username'] = $username;
        // email might be stored in session too? usually not used heavily but good to keep consistent if so.
    }
    echo json_encode(['success' => true, 'message' => 'User updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating user: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
