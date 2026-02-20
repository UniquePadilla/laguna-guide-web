<?php
include '../db_connect.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business_owner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID required']);
    exit();
}

$sql = "SELECT * FROM spots WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => true, 'spot' => $result->fetch_assoc()]);
} else {
    echo json_encode(['success' => false, 'message' => 'Spot not found or unauthorized']);
}

$stmt->close();
$conn->close();
?>