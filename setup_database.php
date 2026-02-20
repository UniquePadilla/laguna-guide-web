<?php
// MASTER DATABASE SETUP SCRIPT
// This script initializes all tables and default data for the Tourist Guide System.
// It is automatically called by db_connect.php if the database is empty.

// Check if we already have a connection from db_connect.php
if (!isset($conn)) {
    include_once 'db_connect.php';
}

// 1. SPOTS TABLE (with Featured column)
$sql = "CREATE TABLE IF NOT EXISTS spots (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    location VARCHAR(255),
    openTime VARCHAR(20),
    closeTime VARCHAR(20),
    image VARCHAR(255),
    entranceFee VARCHAR(100),
    price DECIMAL(10, 2) DEFAULT 0.00,
    contact VARCHAR(50),
    highlights JSON,
    featured TINYINT(1) DEFAULT 0,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)";
$conn->query($sql);

// Ensure featured column exists (for migrations where table exists but column doesn't)
$check = $conn->query("SHOW COLUMNS FROM spots LIKE 'featured'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE spots ADD COLUMN featured TINYINT(1) DEFAULT 0");
}

// Ensure price column exists
$check = $conn->query("SHOW COLUMNS FROM spots LIKE 'price'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE spots ADD COLUMN price DECIMAL(10, 2) DEFAULT 0.00 AFTER entranceFee");
}

// Ensure user_id column exists
$check = $conn->query("SHOW COLUMNS FROM spots LIKE 'user_id'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE spots ADD COLUMN user_id INT(6) UNSIGNED DEFAULT NULL AFTER id");
    $conn->query("ALTER TABLE spots ADD CONSTRAINT fk_spots_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
}

// 2. USERS TABLE (with 2FA columns)
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    status VARCHAR(20) DEFAULT 'active',
    business_name VARCHAR(100) NULL,
    business_address TEXT NULL,
    permit_number VARCHAR(50) NULL,
    contact_number VARCHAR(20) NULL,
    two_factor_secret VARCHAR(255) DEFAULT NULL,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Ensure all columns exist (for migrations)
$user_columns = [
    'status' => "VARCHAR(20) DEFAULT 'active'",
    'business_name' => "VARCHAR(100) NULL",
    'business_address' => "TEXT NULL",
    'permit_number' => "VARCHAR(50) NULL",
    'contact_number' => "VARCHAR(20) NULL",
    'two_factor_secret' => "VARCHAR(255) DEFAULT NULL",
    'two_factor_enabled' => "TINYINT(1) DEFAULT 0"
];

foreach ($user_columns as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN $col $def");
    }
}

// 3. USER ACTIVITY TABLE
$sql = "CREATE TABLE IF NOT EXISTS user_activity (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    spot_id INT(6) UNSIGNED NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    activity_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (spot_id) REFERENCES spots(id) ON DELETE CASCADE
)";
$conn->query($sql);

// 3.5. BOOKINGS TABLE
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
$conn->query($sql);

// 4. REVIEWS TABLE
$sql = "CREATE TABLE IF NOT EXISTS reviews (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    spot_id INT(6) UNSIGNED NOT NULL,
    rating INT(1) NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (spot_id) REFERENCES spots(id) ON DELETE CASCADE
)";
$conn->query($sql);

// 5. LOGIN LOGS TABLE
$sql = "CREATE TABLE IF NOT EXISTS login_logs (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($sql);

// 6. MESSAGES TABLE
$sql = "CREATE TABLE IF NOT EXISTS messages (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// 7. FEATURES TABLE
$sql = "CREATE TABLE IF NOT EXISTS features (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fas fa-star',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($sql);

// 8. CHAT CONVERSATIONS (Metadata for Chat History)
$sql = "CREATE TABLE IF NOT EXISTS chat_conversations (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    title VARCHAR(255) DEFAULT 'New Chat',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($sql);

// 9. CHAT MESSAGES (The actual messages)
$sql = "CREATE TABLE IF NOT EXISTS chat_messages (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT(11) NOT NULL,
    role ENUM('user', 'model') NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
)";
$conn->query($sql);

// 10. CHAT HISTORY TABLE (Legacy/Memory fallback)
$sql = "CREATE TABLE IF NOT EXISTS chat_history (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    role ENUM('user', 'model') NOT NULL,
    message TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (session_id)
)";
$conn->query($sql);

// 11. USER PREFERENCES TABLE (For Personalization)
$sql = "CREATE TABLE IF NOT EXISTS user_preferences (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    budget ENUM('low', 'medium', 'high') DEFAULT 'medium',
    preference_tags TEXT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($sql);

// --- INITIAL DATA INSERTION ---

// Insert default spots if empty
$result = $conn->query("SELECT COUNT(*) as count FROM spots");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $spots = [
        [
            "name" => "Enchanted Kingdom",
            "type" => "destination",
            "category" => "Theme Park",
            "description" => "The Philippines' first and only world-class theme park, offering a variety of rides and attractions for all ages.",
            "location" => "Santa Rosa, Laguna",
            "openTime" => "11:00",
            "closeTime" => "20:00",
            "image" => "laguna-photos/EK.jpg",
            "entranceFee" => "Starts at ₱1,200",
            "price" => 1200.00,
            "contact" => "09858072562",
            "highlights" => ["World-class Rides", "Fireworks Display", "Thematic Zones"],
            "featured" => 1
        ],
        [
            "name" => "Rizal Shrine",
            "type" => "cultural",
            "category" => "Historical",
            "description" => "A reproduction of the original two-story, Spanish-Colonial style house where José Rizal was born.",
            "location" => "Calamba, Laguna",
            "openTime" => "08:00",
            "closeTime" => "16:00",
            "image" => "laguna-photos/rizalshrine.webp",
            "entranceFee" => "Free (Donation)",
            "price" => 0.00,
            "contact" => "09858072562",
            "highlights" => ["History Museum", "Spanish Architecture", "Rizal's Memorabilia"],
            "featured" => 0
        ],
        [
            "name" => "Pagsanjan Falls",
            "type" => "destination",
            "category" => "Nature",
            "description" => "One of the most famous waterfalls in the Philippines. Accessible via a thrilling boat ride.",
            "location" => "Cavinti/Pagsanjan, Laguna",
            "openTime" => "08:00",
            "closeTime" => "15:00",
            "image" => "laguna-photos/falls.png",
            "entranceFee" => "₱1,500 - ₱2,000 (Boat Ride)",
            "price" => 1500.00,
            "contact" => "Local Tourism Office",
            "highlights" => ["Boat Ride", "Cave Entry", "Rafting"],
            "featured" => 1
        ]
    ];

    $stmt = $conn->prepare("INSERT INTO spots (name, type, category, description, location, openTime, closeTime, image, entranceFee, price, contact, highlights, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($spots as $spot) {
        $highlights = json_encode($spot['highlights']);
        $stmt->bind_param("sssssssssdssi", 
            $spot['name'], $spot['type'], $spot['category'], $spot['description'], 
            $spot['location'], $spot['openTime'], $spot['closeTime'], $spot['image'], 
            $spot['entranceFee'], $spot['price'], $spot['contact'], $highlights, $spot['featured']
        );
        $stmt->execute();
    }
}

// Insert default admin if missing
$check_admin = $conn->query("SELECT * FROM users WHERE username = 'admin'");
if ($check_admin->num_rows == 0) {
    $admin_pass = password_hash("admin123", PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, email, password, role) VALUES ('admin', 'admin@tourist.com', '$admin_pass', 'admin')");
}

// Insert default features if missing
$check_features = $conn->query("SELECT COUNT(*) as count FROM features");
$row = $check_features->fetch_assoc();
if ($row['count'] == 0) {
    $conn->query("INSERT INTO features (title, description, icon, status) VALUES 
        ('Featured Spots', 'Manage the highlighted spots on the homepage.', 'fas fa-star', 'active'),
        ('Local Cuisine', 'Curated list of best local restaurants and delicacies.', 'fas fa-utensils', 'active'),
        ('Events & Festivals', 'Schedule of upcoming festivals and local events.', 'fas fa-calendar-alt', 'active'),
        ('Travel Tips', 'Helpful advice and guidelines for tourists.', 'fas fa-lightbulb', 'active')");
}
