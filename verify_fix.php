<?php
// Verification script
echo "Testing setup_database.php syntax...\n";
try {
    include 'setup_database.php';
    echo "setup_database.php included successfully (no syntax errors).\n";
} catch (Throwable $e) {
    echo "Error including setup_database.php: " . $e->getMessage() . "\n";
}
?>
