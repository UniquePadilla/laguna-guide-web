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
    
    // Initialize stats
    $stats = [
        'avg_rating' => 0,
        'total_reviews' => 0,
        'total_spots' => 0
    ];

    // 1. Get review stats (avg rating and total reviews)
    $sql_reviews = "SELECT 
                COALESCE(AVG(r.rating), 0) as avg_rating,
                COUNT(r.id) as total_reviews
            FROM reviews r
            JOIN spots s ON r.spot_id = s.id
            WHERE s.user_id = ?";
            
    $stmt = $conn->prepare($sql_reviews);
    if (!$stmt) {
        throw new Exception('Database error (Reviews): ' . $conn->error);
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stats['avg_rating'] = round((float)$row['avg_rating'], 1);
        $stats['total_reviews'] = (int)$row['total_reviews'];
    }
    $stmt->close();

    // 2. Get total spots count
    $sql_spots = "SELECT COUNT(*) as total_spots FROM spots WHERE user_id = ?";
    $stmt = $conn->prepare($sql_spots);
    if (!$stmt) {
        throw new Exception('Database error (Spots): ' . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stats['total_spots'] = (int)$row['total_spots'];
    }
    $stmt->close();

    ob_clean();
    echo json_encode(['success' => true, 'stats' => $stats], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
