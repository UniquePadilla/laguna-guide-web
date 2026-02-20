<?php
header('Content-Type: application/json');
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Fetch recent bookings from bookings table
    $sql = "SELECT 
                b.id, 
                u.username, 
                u.email,
                s.name AS spot_name, 
                b.booking_date,
                b.status,
                b.num_adults,
                b.num_children,
                b.total_price,
                b.special_request,
                b.created_at
            FROM 
                bookings b
            LEFT JOIN 
                users u ON b.user_id = u.id 
            LEFT JOIN 
                spots s ON b.spot_id = s.id 
            ORDER BY 
                b.created_at DESC
            LIMIT 10";

    $result = $conn->query($sql);

    $bookings = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Default status if null
            if (empty($row['status'])) {
                $row['status'] = 'Pending';
            } else {
                // Capitalize first letter
                $row['status'] = ucfirst($row['status']);
            }
            
            // Format date for display
            $row['display_date'] = date('M d, Y', strtotime($row['booking_date']));
            
            // Add avatar helper
            $row['avatar_url'] = "https://ui-avatars.com/api/?name=" . urlencode($row['username']) . "&background=random";
            
            $bookings[] = $row;
        }
    }

    echo json_encode(['success' => true, 'data' => $bookings]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>