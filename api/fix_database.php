<?php
header('Content-Type: application/json');
include __DIR__ . '/../db_connect.php';

$response = [];

// 1. Create bookings table
$sql = "CREATE TABLE IF NOT EXISTS bookings (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    spot_id INT(6) UNSIGNED NOT NULL,
    booking_date DATE NOT NULL,
    num_adults INT(3) DEFAULT 1,
    num_children INT(3) DEFAULT 0,
    total_price DECIMAL(10, 2) DEFAULT 0.00,
    special_request TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (spot_id) REFERENCES spots(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    $response['bookings_table'] = "Table 'bookings' created or already exists.";
} else {
    $response['bookings_table'] = "Error creating table: " . $conn->error;
}

// 2. Add status column to user_activity if not exists
$check = $conn->query("SHOW COLUMNS FROM user_activity LIKE 'status'");
if ($check->num_rows == 0) {
    if ($conn->query("ALTER TABLE user_activity ADD COLUMN status VARCHAR(20) DEFAULT 'pending'")) {
        $response['user_activity_status'] = "Column 'status' added to 'user_activity'.";
    } else {
        $response['user_activity_status'] = "Error adding column: " . $conn->error;
    }
} else {
    $response['user_activity_status'] = "Column 'status' already exists.";
}

// Check if 'price' column exists in 'spots' table
$check = $conn->query("SHOW COLUMNS FROM spots LIKE 'price'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE spots ADD COLUMN price DECIMAL(10, 2) DEFAULT 0.00 AFTER entranceFee");
    $response['spots_price'] = "Column 'price' added to spots table.";
    
    // Update price from entranceFee
    $result = $conn->query("SELECT id, entranceFee FROM spots");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $price = 0;
            $fee = $row['entranceFee'];
            // Remove '₱' and ',' then find number
            $clean_fee = str_replace([',', '₱'], '', $fee);
            if (preg_match('/(\d+)/', $clean_fee, $matches)) {
                $price = floatval($matches[1]);
            }
            $conn->query("UPDATE spots SET price = $price WHERE id = " . $row['id']);
        }
        $response['spots_price_update'] = "Updated existing spots prices.";
    }
} else {
    $response['spots_price'] = "Column 'price' already exists.";
}

echo json_encode($response);
?>
