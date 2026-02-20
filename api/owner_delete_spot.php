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
    $data = json_decode(file_get_contents('php://input'), true);
    $spot_id = $data['id'] ?? null;

    if (!$spot_id) {
        throw new Exception('Spot ID required');
    }

    $sql = "DELETE FROM spots WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $stmt->bind_param("ii", $spot_id, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Spot deleted successfully']);
        } else {
            throw new Exception('Spot not found or unauthorized');
        }
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }

    $stmt->close();

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
