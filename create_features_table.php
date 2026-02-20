<?php
include 'db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS features (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fas fa-star',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'features' created successfully\n";
    
    // Insert some default data if empty
    $check = $conn->query("SELECT count(*) as count FROM features");
    $row = $check->fetch_assoc();
    if ($row['count'] == 0) {
        $insert = "INSERT INTO features (title, description, icon, status) VALUES 
        ('Featured Spots', 'Manage the highlighted spots on the homepage.', 'fas fa-star', 'active'),
        ('Local Cuisine', 'Curated list of best local restaurants and delicacies.', 'fas fa-utensils', 'active'),
        ('Events & Festivals', 'Schedule of upcoming festivals and local events.', 'fas fa-calendar-alt', 'active'),
        ('Travel Tips', 'Helpful advice and guidelines for tourists.', 'fas fa-lightbulb', 'active')";
        
        if ($conn->query($insert) === TRUE) {
            echo "Default data inserted.\n";
        } else {
            echo "Error inserting default data: " . $conn->error . "\n";
        }
    }
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
?>