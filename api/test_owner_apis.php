<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Mock session
$_SESSION['user_id'] = 7; // Enchanted Kingdom
$_SESSION['role'] = 'business_owner';

function test_api($file) {
    echo "\n--- Testing $file ---\n";
    ob_start();
    include $file;
    $output = ob_get_clean();

    echo "Output length: " . strlen($output) . "\n";
    $data = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "Valid JSON.\n";
        if (isset($data['success'])) {
            echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
            if (!$data['success']) {
                echo "Message: " . $data['message'] . "\n";
            } else {
                if (isset($data['stats'])) {
                    print_r($data['stats']);
                }
                if (isset($data['spots'])) {
                    echo "Spots count: " . count($data['spots']) . "\n";
                }
            }
        }
    } else {
        echo "Invalid JSON. Error: " . json_last_error_msg() . "\n";
        echo "Raw output start: " . substr($output, 0, 100) . "\n";
    }
}

test_api('owner_get_stats.php');
test_api('owner_get_spots.php');
?>
