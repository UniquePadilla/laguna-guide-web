<?php
// Read original file
$originalFile = 'api/chat_ai.php';
$code = file_get_contents($originalFile);

// Modify to be testable
// 1. Comment out session_start() and mock persistence
$mockSessionCode = '
/* session_start(); */ 
$sessionFile = __DIR__ . "/mock_session.json";
$_SESSION = file_exists($sessionFile) ? json_decode(file_get_contents($sessionFile), true) : ["user_id" => 2];
register_shutdown_function(function() use ($sessionFile) {
    file_put_contents($sessionFile, json_encode($_SESSION));
});
';
$code = str_replace('session_start();', $mockSessionCode, $code);

// 2. Mock $_SESSION['user_id'] - REMOVED redundant replacement
// $code = str_replace('// session_start();', '// session_start(); $_SESSION["user_id"] = 2;', $code);

// 3. Replace input reading
// The line is: $data = json_decode(file_get_contents('php://input'), true);
$code = str_replace(
    "\$data = json_decode(file_get_contents('php://input'), true);",
    "\$data = json_decode(\$argv[1], true);",
    $code
);

// Save to temp file
$tempFile = 'api/temp_chat_ai.php';
file_put_contents($tempFile, $code);

// Clear mock session
if (file_exists('api/mock_session.json')) {
    unlink('api/mock_session.json');
}

// Test Cases
$testCases = [
    // 1. Context Test Sequence
    'Tell me about Rizal Shrine',   // Sets context to Rizal Shrine
    'How much is the entrance fee?', // Should infer Rizal Shrine from context
    'How to get there?',            // Should infer Rizal Shrine from context
    
    // 2. Fuzzy Match Context Test
    'Rizal Shrine',     // Fuzzy match (KB)
    'How much?',        // Should use context
    
    // 3. Fuzzy Matching / Typos
    'lguana',       // Should correct to Laguna
    'top sputs',    // Should correct to Top Spots
    'buko pi price', // Typo + Price query

    // 4. Recommendations
    'Suggest a place in Laguna',
    'Where should I go?',
    
    // 5. Directions (Specific)
    'How to get to Pagsanjan Falls',
    
    // 6. Weather
    'Weather in Laguna',
    
    // 7. Chit-chat / Identity
    'Who are you?',
    'Hello there',
    
    // 8. Unknown / Gibberish
    'sfdsfdsf'
];

// Detect PHP executable path
$phpPath = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'php.exe' : 'php';

// Check common XAMPP path if default php fails
if ($phpPath === 'php.exe') {
    // Portability: Try to find PHP executable
    $xamppPath = 'php'; // Default to PATH
    $possiblePaths = [
        'C:\xampp\php\php.exe',
        'C:\php\php.exe',
        '/usr/bin/php'
    ];
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $xamppPath = $path;
            break;
        }
    }
    if (file_exists($xamppPath)) {
        $phpPath = $xamppPath;
    }
}

$results = [];

foreach ($testCases as $msg) {
    echo "\n--- Testing: '$msg' ---\n";
    $json = json_encode(['message' => $msg]);
    // Escape for shell
    $jsonEscaped = escapeshellarg($json);
    
    // Windows specific: escapeshellarg doesn't work well with cmd.exe
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $jsonEscaped = '"' . str_replace('"', '\"', $json) . '"';
    }
    
    $cmd = "cd api && \"$phpPath\" \"temp_chat_ai.php\" $jsonEscaped";
    $output = shell_exec($cmd);
    
    // Parse JSON output
    $res = json_decode($output, true);
    if ($res) {
        $reply = $res['reply'] ?? 'No reply field';
        $confidence = $res['confidence'] ?? 'N/A';
        $truncatedReply = substr(str_replace("\n", " ", $reply), 0, 100) . "...";
        echo "Reply: $truncatedReply\n";
        echo "Confidence: $confidence\n";
        $results[] = "Input: $msg | Reply: $truncatedReply | Confidence: $confidence";
    } else {
        echo "Raw Output: " . substr($output, 0, 200) . "...\n";
        $results[] = "Input: $msg | Raw Output: " . substr($output, 0, 100);
    }
}

// Write full results to file for analysis
file_put_contents('test_results_expanded.txt', implode("\n", $results));

// Cleanup
// unlink($tempFile);
?>