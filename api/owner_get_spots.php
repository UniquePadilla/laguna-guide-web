<?php
ob_start();
include '../db_connect.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_clean(); // Clear buffer

header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business_owner') {
        throw new Exception('Unauthorized');
    }

    $user_id = $_SESSION['user_id'];
    $spots = [];

    $sql = "SELECT s.*, 
                   COALESCE(AVG(r.rating), 0) as avg_rating,
                   COUNT(r.id) as review_count
            FROM spots s
            LEFT JOIN reviews r ON s.id = r.spot_id
            WHERE s.user_id = ?
            GROUP BY s.id
            ORDER BY s.id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $row[$key] = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                }
            }
        }
        $row['avg_rating'] = round((float)$row['avg_rating'], 1);
        $spots[] = $row;
    }
    
    $stmt->close();
    
    ob_clean();
    echo json_encode(['success' => true, 'spots' => $spots], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
