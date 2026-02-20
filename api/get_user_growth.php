<?php
header('Content-Type: application/json');
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'week';

$response = [
    'labels' => [],
    'data' => []
];

if ($filter === 'year') {
    // THIS YEAR (Monthly data: Jan - Dec)
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $currentYear = date('Y');
    
    
    foreach ($months as $index => $month) {
        $response['labels'][] = $month;
        $response['data'][] = 0;
        $response['keys'][] = $index + 1; // 1 for Jan, 2 for Feb...
    }

    $sql = "SELECT MONTH(reg_date) as month_num, COUNT(*) as count 
            FROM users 
            WHERE YEAR(reg_date) = '$currentYear' 
            GROUP BY MONTH(reg_date)";

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $monthNum = (int)$row['month_num'];
            $count = (int)$row['count'];
            
            // Map month number (1-12) to array index (0-11)
            $index = $monthNum - 1;
            if (isset($response['data'][$index])) {
                $response['data'][$index] = $count;
            }
        }
    }
    unset($response['keys']);

} elseif ($filter === 'month') {
    
    
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayLabel = date('M d', strtotime("-$i days")); 
        $response['labels'][] = $dayLabel;
        $response['dates'][] = $date;
        $response['data'][] = 0;
    }

    $sql = "SELECT DATE(reg_date) as reg_date, COUNT(*) as count 
            FROM users 
            WHERE reg_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) 
            GROUP BY DATE(reg_date)";

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $regDate = $row['reg_date'];
            $count = (int)$row['count'];
            $index = array_search($regDate, $response['dates']);
            if ($index !== false) {
                $response['data'][$index] = $count;
            }
        }
    }
    unset($response['dates']);

} else {
    // THIS WEEK (Last 7 Days) - Default
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayName = date('D', strtotime("-$i days"));
        $response['labels'][] = $dayName;
        $response['dates'][] = $date;
        $response['data'][] = 0;
    }

    $sql = "SELECT DATE(reg_date) as reg_date, COUNT(*) as count 
            FROM users 
            WHERE reg_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
            GROUP BY DATE(reg_date)";

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $regDate = $row['reg_date'];
            $count = (int)$row['count'];
            $index = array_search($regDate, $response['dates']);
            if ($index !== false) {
                $response['data'][$index] = $count;
            }
        }
    }
    unset($response['dates']);
}

echo json_encode($response);
$conn->close();
?>