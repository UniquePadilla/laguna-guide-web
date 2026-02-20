<?php
// Load configuration if available
if (file_exists(__DIR__ . '/db_config.php')) {
    require __DIR__ . '/db_config.php';
} else {
    // Default XAMPP settings
    $servername = "127.0.0.1";
    $username = "root";
    $password = "";
    $dbname = "tourist_guide_db";
}

// Create connection
try {
    if (!class_exists('mysqli')) {
        die(json_encode(['success' => false, 'message' => 'MySQLi extension missing']));
    }
    $conn = new mysqli($servername, $username, $password);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]));
}

// Check connection
if ($conn->connect_error) {
    header('Content-Type: application/json');
    die(json_encode([
        'success' => false, 
        'message' => 'Database connection failed. Please ensure MySQL is running in your XAMPP Control Panel (usually on port 3306).',
        'details' => [
            'error' => $conn->connect_error,
            'host' => $servername,
            'user' => $username,
            'hint' => 'Check if another service is using port 3306 or if your MySQL credentials have changed.'
        ]
    ]));
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    $conn->select_db($dbname);
} else {
    die(json_encode(['success' => false, 'message' => 'Error creating database: ' . $conn->error]));
}

// --- AUTO-INITIALIZATION ---
// Check for critical tables. If any are missing, run the master setup.
$critical_tables = ['users', 'spots', 'chat_conversations', 'chat_messages'];
$missing = false;

foreach ($critical_tables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows == 0) {
        $missing = true;
        break;
    }
}

if ($missing) {
    include_once __DIR__ . '/setup_database.php';
}
