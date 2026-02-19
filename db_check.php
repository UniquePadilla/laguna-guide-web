<?php
header('Content-Type: text/plain');

// Load configuration if available
if (file_exists(__DIR__ . '/db_config.php')) {
    require __DIR__ . '/db_config.php';
} else {
    $servername = "127.0.0.1";
    $username = "root";
    $password = "";
    $dbname = "tourist_guide_db";
}

// Ensure port is set if not from config
if (!isset($port)) {
    $port = 3306;
}

echo "--- Database Diagnostic ---\n";
echo "Checking connection to $servername:$port with user '$username'...\n";

// 1. Check Connection
try {
    $conn = new mysqli($servername, $username, $password, null, $port);
    if ($conn->connect_error) {
        die("Connection Failed: " . $conn->connect_error . "\n");
    }
    echo "Connection Successful!\n";
    echo "Server Info: " . $conn->server_info . "\n";
} catch (Exception $e) {
    die("Connection Exception: " . $e->getMessage() . "\n");
}

// 2. Check Database Existence
echo "\nChecking database '$dbname'...\n";
$db_check = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
if ($db_check->num_rows > 0) {
    echo "Database '$dbname' exists.\n";
    $conn->select_db($dbname);
} else {
    die("Database '$dbname' DOES NOT EXIST.\n");
}

// 3. Check Tables
echo "\nChecking tables...\n";
$tables = ['users', 'spots', 'reviews', 'user_activity', 'login_logs', 'messages', 'features', 'chat_conversations', 'chat_messages', 'chat_history', 'user_preferences'];
$existing_tables = [];

$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $existing_tables[] = $row[0];
}

foreach ($tables as $table) {
    if (in_array($table, $existing_tables)) {
        echo "[OK] Table '$table' exists.\n";
        // Optional: Check row count
        $count = $conn->query("SELECT COUNT(*) FROM $table")->fetch_row()[0];
        echo "     -> Rows: $count\n";
    } else {
        echo "[MISSING] Table '$table' is MISSING!\n";
    }
}

// 4. Check specific columns in users (for business owner check)
echo "\nChecking 'users' table structure...\n";
if (in_array('users', $existing_tables)) {
    $columns = [];
    $res = $conn->query("DESCRIBE users");
    while ($row = $res->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    echo "Columns: " . implode(", ", $columns) . "\n";
}

$conn->close();
echo "\nDiagnostic Complete.\n";
?>
