<?php
include 'db_connect.php';

$result = $conn->query("SELECT COUNT(*) as count FROM spots");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Spots count: " . $row['count'] . "\n";
    
    // Check for NULL values in critical columns
    $null_check = $conn->query("SELECT id, name FROM spots WHERE name IS NULL OR location IS NULL");
    if ($null_check->num_rows > 0) {
        echo "Found spots with NULL name or location:\n";
        while($r = $null_check->fetch_assoc()) {
            print_r($r);
        }
    }
} else {
    echo "Error querying spots: " . $conn->error . "\n";
}

// Check api/get_spots.php logic
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
echo "API Query Result Rows: " . ($result ? $result->num_rows : "Error: " . $conn->error) . "\n";

$conn->close();
?>
