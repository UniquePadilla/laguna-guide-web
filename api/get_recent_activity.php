<?php
header('Content-Type: application/json');
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $sql = "SELECT 
                ua.id, 
                u.username, 
                s.name AS spot_name, 
                ua.activity_type, 
                ua.activity_date 
            FROM 
                user_activity ua 
            LEFT JOIN 
                users u ON ua.user_id = u.id 
            LEFT JOIN 
                spots s ON ua.spot_id = s.id 
            WHERE 
                u.role != 'admin'
            ORDER BY 
                ua.activity_date DESC
            LIMIT 5";

    $result = $conn->query($sql);

    $logs = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
    }

    echo json_encode(['success' => true, 'data' => $logs]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
