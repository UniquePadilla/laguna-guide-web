<?php
include 'db_connect.php';

// 1. Create bookings table
$sql = "CREATE TABLE IF NOT EXISTS bookings (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    spot_id INT(6) UNSIGNED NOT NULL,
    booking_date DATE NOT NULL,
    num_adults INT(3) DEFAULT 1,
    num_children INT(3) DEFAULT 0,
    total_price DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed', 'rejected') DEFAULT 'pending',
    special_request TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (spot_id) REFERENCES spots(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table bookings created successfully\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// 2. Add price column to spots
$check = $conn->query("SHOW COLUMNS FROM spots LIKE 'price'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE spots ADD COLUMN price DECIMAL(10, 2) DEFAULT 0.00 AFTER entranceFee";
    if ($conn->query($sql) === TRUE) {
        echo "Column price added to spots\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column price already exists\n";
}

// 3. Update prices based on known spots (Best effort)
$updates = [
    'Enchanted Kingdom' => 1200.00,
    'Rizal Shrine' => 0.00,
    'Pagsanjan Falls' => 1500.00,
    'Nuvali Park' => 0.00,
    'Seven Lakes of San Pablo' => 0.00,
    'Hulugan Falls' => 100.00, // Guess
    'Mount Makiling' => 50.00, // Guess
    'Hidden Valley Springs' => 2500.00, // Guess
    'Splash Island' => 800.00, // Guess
    'Vonwelt Nature Farm' => 150.00 // Guess
];

foreach ($updates as $name => $price) {
    $stmt = $conn->prepare("UPDATE spots SET price = ? WHERE name LIKE ?");
    $searchName = "%" . $name . "%";
    $stmt->bind_param("ds", $price, $searchName);
    $stmt->execute();
}
echo "Prices updated\n";

$conn->close();
?>