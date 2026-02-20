<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Mock session
$_SESSION['user_id'] = 7; // Enchanted Kingdom
$_SESSION['role'] = 'business_owner';

function test_api($file, $postData = [], $inputData = []) {
    echo "\n--- Testing $file ---\n";
    
    // Simulate POST data
    $_POST = $postData;
    
    // Simulate Input Stream for JSON data (tricky in CLI, but we can mock file_get_contents wrapper if we could, 
    // but here we just rely on the fact that if $inputData is set, we expect the script to read it.
    // Since we can't easily mock php://input in CLI without extensions, 
    // we will just skip input stream testing for delete_spot here or use a different approach if needed.
    // Actually, for delete_spot, we can't easily mock php://input in this simple script.
    // So we will just test the other files.
    
    ob_start();
    include $file;
    $output = ob_get_clean();

    echo "Output length: " . strlen($output) . "\n";
    $data = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "Valid JSON.\n";
        echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        if (!$data['success']) {
            echo "Message: " . $data['message'] . "\n";
        }
    } else {
        echo "Invalid JSON. Error: " . json_last_error_msg() . "\n";
        echo "Raw output start: " . substr($output, 0, 100) . "\n";
    }
}

// Test Add Spot (Invalid)
test_api('owner_add_spot.php', []);

// Test Add Spot (Valid Mock)
test_api('owner_add_spot.php', [
    'name' => 'Test Spot ' . time(),
    'location' => 'Test Location',
    'type' => 'Nature',
    'description' => 'Test Description'
]);

// Test Get Spots to see if it worked
test_api('owner_get_spots.php');

?>
