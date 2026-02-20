<?php
ob_start(); // Start output buffering immediately
include '../db_connect.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Clear any previous output (warnings, whitespace from includes)
ob_clean();

header('Content-Type: application/json');

// Disable error display to prevent HTML error output breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business_owner') {
        throw new Exception('Unauthorized');
    }

    $user_id = $_SESSION['user_id'];
    $reviews = [];

    // Fetch reviews for spots owned by the user
    $sql = "SELECT r.id, r.rating, r.comment, r.created_at, u.username, s.name as spot_name 
            FROM reviews r
            JOIN spots s ON r.spot_id = s.id
            JOIN users u ON r.user_id = u.id
            WHERE s.user_id = ?
            ORDER BY r.created_at DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Robust UTF-8 conversion
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $row[$key] = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                }
            }
        }

        // Robust Date Handling
        $dateStr = $row['created_at'];
        if (!empty($dateStr)) {
            // Check if valid date
            $timestamp = strtotime($dateStr);
            if ($timestamp !== false && $timestamp > 0) {
                $row['formatted_date'] = date("M d, Y", $timestamp);
            } else {
                $row['formatted_date'] = 'Unknown Date';
            }
        } else {
            $row['formatted_date'] = 'Unknown Date';
        }

        $reviews[] = $row;
    }
    
    $stmt->close();
    
    // Final check for output buffer before echoing JSON
    ob_clean();
    echo json_encode(['success' => true, 'reviews' => $reviews], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    ob_clean(); // Clear buffer before error response
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
