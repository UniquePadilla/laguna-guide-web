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
    $spot_id = $_POST['spot_id'] ?? null;
    $name = $_POST['name'] ?? '';
    $type = $_POST['type'] ?? 'Nature';
    $description = $_POST['description'] ?? '';
    $location = $_POST['location'] ?? '';
    $openTime = $_POST['openTime'] ?? '08:00';
    $closeTime = $_POST['closeTime'] ?? '17:00';
    $entranceFee = $_POST['entranceFee'] ?? 'Free';
    $contact = $_POST['contact'] ?? '';
    $highlights = isset($_POST['highlights']) ? json_encode(array_map('trim', explode(',', $_POST['highlights']))) : json_encode([]);

    if (!$spot_id) {
        throw new Exception('Spot ID required');
    }

    // Check if spot belongs to user
    $check = $conn->prepare("SELECT id, image FROM spots WHERE id = ? AND user_id = ?");
    if (!$check) {
        throw new Exception('Database prepare error (check): ' . $conn->error);
    }
    $check->bind_param("ii", $spot_id, $user_id);
    $check->execute();
    $res = $check->get_result();
    if ($res->num_rows === 0) {
        throw new Exception('Spot not found or unauthorized');
    }
    $row = $res->fetch_assoc();
    $currentImage = $row['image'];
    $check->close();

    // Image Upload Handling
    $imagePath = $currentImage;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../laguna-photos/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['image']['name']);
        // Sanitize
        $fileName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $fileName);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $imagePath = 'laguna-photos/' . $fileName;
        }
    }

    $sql = "UPDATE spots SET name=?, type=?, description=?, location=?, openTime=?, closeTime=?, entranceFee=?, contact=?, highlights=?, image=? WHERE id=? AND user_id=?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("ssssssssssii", $name, $type, $description, $location, $openTime, $closeTime, $entranceFee, $contact, $highlights, $imagePath, $spot_id, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Spot updated successfully']);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }

    $stmt->close();

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
