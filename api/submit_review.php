<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to submit a review']);
    exit;
}

// Check if user is a regular user (not business owner or admin)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Only user accounts can submit reviews.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['spot_id']) || !isset($data['rating'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$user_id = $_SESSION['user_id'];
$spot_id = $data['spot_id'];
$rating = intval($data['rating']);
$comment = isset($data['comment']) ? trim($data['comment']) : '';

// Validate rating
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating']);
    exit;
}

// Check if user already reviewed this spot
$check = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND spot_id = ?");
$check->bind_param("ii", $user_id, $spot_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    // Update existing review
    $stmt = $conn->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE user_id = ? AND spot_id = ?");
    $stmt->bind_param("isii", $rating, $comment, $user_id, $spot_id);
    $action = "updated";
} else {
    // Insert new review
    $stmt = $conn->prepare("INSERT INTO reviews (user_id, spot_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $user_id, $spot_id, $rating, $comment);
    $action = "submitted";
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => "Review $action successfully"]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$stmt->close();
$check->close();
$conn->close();
?>