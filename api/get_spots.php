<?php
header('Content-Type: application/json');
include '../db_connect.php';

$sql = "SELECT s.*, 
        COUNT(DISTINCT ua.id) as visitor_count,
        COALESCE(AVG(r.rating), 0) as average_rating,
        COUNT(DISTINCT r.id) as review_count
        FROM spots s 
        LEFT JOIN user_activity ua ON s.id = ua.spot_id 
        LEFT JOIN reviews r ON s.id = r.spot_id
        GROUP BY s.id
        ORDER BY average_rating DESC";
$result = $conn->query($sql);

$spots = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {

        $highlights = json_decode($row['highlights']);
        $row['highlights'] = $highlights ?: [];
        $spots[] = $row;
    }
}

echo json_encode($spots);

$conn->close();
?>
 