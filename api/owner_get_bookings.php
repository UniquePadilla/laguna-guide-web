<?php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business_owner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$owner_id = $_SESSION['user_id'];

try {
    $sql = "SELECT 
                b.id, 
                b.booking_date, 
                b.status, 
                b.total_price, 
                u.username AS user_name, 
                u.contact_number, 
                u.email,
                s.name AS spot_name 
            FROM bookings b
            JOIN spots s ON b.spot_id = s.id
            JOIN users u ON b.user_id = u.id
            WHERE s.user_id = ?
            ORDER BY b.booking_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }

    echo json_encode(['success' => true, 'bookings' => $bookings]);

} catch (Exception $e) {
    error_log("Error fetching bookings: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$conn->close();
?>
