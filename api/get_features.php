<?php
header('Content-Type: application/json');
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$sql = "SELECT * FROM features ORDER BY id ASC";
$result = $conn->query($sql);

$features = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $features[] = $row;
    }
}

echo json_encode($features);
$conn->close();
?>