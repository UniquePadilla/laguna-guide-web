<?php
include 'db_connect.php';
$conn->select_db($dbname);

echo "Tables:\n";
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $table = $row[0];
    echo "- $table\n";
    $cols = $conn->query("SHOW COLUMNS FROM $table");
    while ($col = $cols->fetch_assoc()) {
        echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
}
?>