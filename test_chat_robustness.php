<?php
// Simple Robustness Tester
$questions = [
    "Test 1: Greeting" => "Hello, who are you?",
    "Test 2: Specific Spot (DB)" => "Tell me about Rizal Shrine",
    "Test 3: Web Search (News)" => "What is the latest news in Laguna today?",
    "Test 4: General Knowledge" => "Why is the sky blue?",
    "Test 5: Nonsense/Garbage" => "asdfghjkl",
    "Test 6: Complex Query" => "I want to swim but also eat good food, where should I go?"
];

echo "Starting Tests...\n";

foreach ($questions as $label => $q) {
    echo "[$label] Question: $q\n";
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/Tourist Guide System/test_chat_robustness.php';
    $baseDir = dirname($scriptPath);
    $apiUrl = "$protocol://$host" . ($baseDir === '/' ? '' : $baseDir) . "/api/chat_ai.php";
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['message' => $q]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90); // 90s timeout to be safe
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: " . $info['http_code'] . "\n";
    if ($error) {
        echo "CURL Error: $error\n";
    }
    
    $json = json_decode($response, true);
    if ($json) {
         if (isset($json['debug'])) {
             echo "DEBUG:\n" . implode("\n", $json['debug']) . "\n";
         }
         echo "REPLY: " . ($json['reply'] ?? 'NULL') . "\n";
    } else {
         echo "RAW RESPONSE: " . substr($response, 0, 500) . "...\n";
    }
    echo "\n--------------------------------------------------\n";
    
    // Small delay to be nice to the server
    sleep(1);
}
?>
