<?php
include '../db_connect.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Disable error display to prevent HTML error output breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business_owner') {
        throw new Exception('Unauthorized');
    }

    $user_id = $_SESSION['user_id'];
    $name = $_POST['name'] ?? '';
    $type = $_POST['type'] ?? 'Nature';
    $description = $_POST['description'] ?? '';
    $location = $_POST['location'] ?? '';
    $openTime = $_POST['openTime'] ?? '08:00';
    $closeTime = $_POST['closeTime'] ?? '17:00';
    $entranceFee = $_POST['entranceFee'] ?? 'Free';
    $contact = $_POST['contact'] ?? '';
    $highlights = isset($_POST['highlights']) ? json_encode(array_map('trim', explode(',', $_POST['highlights']))) : json_encode([]);

    if (empty($name) || empty($location)) {
        throw new Exception('Name and Location are required');
    }

    // Image Upload Handling
    $imagePath = 'laguna-photos/default.jpg'; // Default or placeholder

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../laguna-photos/';
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }
        
        $fileName = time() . '_' . basename($_FILES['image']['name']);
        // Sanitize filename
        $fileName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $fileName);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $imagePath = 'laguna-photos/' . $fileName;
        } else {
            throw new Exception('Failed to move uploaded file');
        }
    }

    $sql = "INSERT INTO spots (user_id, name, type, description, location, openTime, closeTime, entranceFee, contact, highlights, image) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $stmt->bind_param("issssssssss", $user_id, $name, $type, $description, $location, $openTime, $closeTime, $entranceFee, $contact, $highlights, $imagePath);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Spot added successfully']);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }

    $stmt->close();

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
