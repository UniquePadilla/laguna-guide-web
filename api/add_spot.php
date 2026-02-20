<?php
header('Content-Type: application/json');
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Since we are using FormData in JS, we access data via $_POST and $_FILES
$name = isset($_POST['name']) ? $_POST['name'] : '';
$category = isset($_POST['category']) ? $_POST['category'] : '';
$location = isset($_POST['location']) ? $_POST['location'] : '';

// Validate required fields
if (empty($name) || empty($category) || empty($location)) {
    echo json_encode(['success' => false, 'message' => 'Name, Category, and Location are required']);
    exit;
}

$type = isset($_POST['type']) ? $_POST['type'] : 'destination';
$description = isset($_POST['description']) ? $_POST['description'] : '';
$openTime = isset($_POST['openTime']) ? $_POST['openTime'] : '08:00';
$closeTime = isset($_POST['closeTime']) ? $_POST['closeTime'] : '17:00';
$entranceFee = isset($_POST['entranceFee']) ? $_POST['entranceFee'] : 'Free';
$contact = isset($_POST['contact']) ? $_POST['contact'] : '';
$highlights = isset($_POST['highlights']) ? json_encode(array_map('trim', explode(',', $_POST['highlights']))) : json_encode([]);

// Image Upload Handling
$imagePath = './laguna-photos/default.jpg'; // Default

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../laguna-photos/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = time() . '_' . basename($_FILES['image']['name']);
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
        $imagePath = 'laguna-photos/' . $fileName;
    } else {
        // Failed to upload, proceed with default or error? 
        // Let's proceed but warn or just log
    }
}

$sql = "INSERT INTO spots (name, type, category, description, location, openTime, closeTime, entranceFee, contact, highlights, image) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssssssss", $name, $type, $category, $description, $location, $openTime, $closeTime, $entranceFee, $contact, $highlights, $imagePath);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Location added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>