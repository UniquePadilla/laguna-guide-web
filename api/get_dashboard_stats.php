<?php
header('Content-Type: application/json');
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Initialize response array
$response = [
    'total_users' => 0,
    'total_destinations' => 0,
    'active_events' => 0,
    'new_feedback' => 0,
    'by_category' => [
        'nature' => 0,
        'historical' => 0,
        'food' => 0,
        'theme_park' => 0
    ]
];

// Get total users
$sql = "SELECT COUNT(*) as count FROM users";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $response['total_users'] = $row['count'];
}

// Get total destinations (spots)
$sql = "SELECT COUNT(*) as count FROM spots";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $response['total_destinations'] = $row['count'];
}

// Get Active Events (Count spots that are events or theme parks, plus active bookings)
// Since we don't have a dedicated events table, we'll count 'Theme Park' spots and 'booking' activities
$active_events = 0;

// Count 'Event' or 'Theme Park' spots
$sql = "SELECT COUNT(*) as count FROM spots WHERE category IN ('Event', 'Theme Park')";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $active_events += $row['count'];
}

// Count recent bookings (last 30 days) from bookings table
$sql = "SELECT COUNT(*) as count FROM bookings WHERE booking_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status != 'cancelled'";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $active_events += $row['count'];
}

$response['active_events'] = $active_events;

// Get New Feedback (Count total reviews)
$sql = "SELECT COUNT(*) as count FROM reviews";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $response['new_feedback'] = $row['count'];
}


// Get counts by category
// Note: Categories in DB are Capitalized (e.g., 'Nature', 'Historical', 'Restaurant', 'Delicacy', 'Theme Park', 'Park')
// We map them to our display categories

$sql = "SELECT category, COUNT(*) as count FROM spots GROUP BY category";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $cat = strtolower($row['category']);
        $count = (int)$row['count'];
        
        if ($cat == 'nature') {
            $response['by_category']['nature'] += $count;
        } elseif ($cat == 'historical' || $cat == 'park') {
            // Mapping Park to Historical/Cultural or just counting them
            $response['by_category']['historical'] += $count;
        } elseif ($cat == 'restaurant' || $cat == 'delicacy' || $cat == 'shop') {
            $response['by_category']['food'] += $count;
        } elseif ($cat == 'theme park') {
            $response['by_category']['theme_park'] += $count;
        }
    }
}

echo json_encode($response);

$conn->close();
?>
