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
                l.id, 
                u.username, 
                l.ip_address, 
                l.user_agent, 
                l.login_time 
            FROM 
                login_logs l
            LEFT JOIN 
                users u ON l.user_id = u.id 
            ORDER BY 
                l.login_time DESC";

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
