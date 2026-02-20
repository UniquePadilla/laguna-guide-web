<?php

if (php_sapi_name() === 'cli') {
    $baseUrl = "http://127.0.0.1/Tourist%20Guide%20System/api";
} else {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/Tourist Guide System/test_ai_runner.php';
    $baseDir = dirname($scriptPath);
    $baseUrl = "$protocol://$host" . ($baseDir === '/' ? '' : $baseDir) . "/api";
}

$loginUrl = "$baseUrl/login.php";
$chatUrl = "$baseUrl/chat_ai.php";

$username = "test_ai_user";
$password = "password123";

$cookieFile = tempnam(sys_get_temp_dir(), 'cookie');

function makeRequest($url, $data) {
    global $cookieFile;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "Error: HTTP $httpCode - $curlError\n";
        if ($response) echo "Response: " . substr($response, 0, 500) . "\n";
        return null;
    }
    
    $decoded = json_decode($response, true);
    if ($decoded === null && !empty($response)) {
        echo "Error: Invalid JSON response\n";
        echo "Raw Response: " . substr($response, 0, 500) . "\n";
    }
    return $decoded;
}

// Login
echo "Logging in as $username...\n";
$loginData = ['username' => $username, 'password' => $password];
$loginRes = makeRequest($loginUrl, $loginData);

if (!$loginRes || !$loginRes['success']) {
    echo "Login failed: " . ($loginRes['message'] ?? 'Unknown error') . "\n";
    exit(1);
}
echo "Login successful.\n";

$testCases = [
    "Hello",
    "Who are you?",
    "What is this website?",
    "Top rated spots",
    "Where to eat?",
    "Tell me about Laguna",
    "How to get there?",
    "Events in Laguna",
    "Is there a festival?",
    "Where is Rizal Shrine?",
    "Rizal Shrine",
    "tell me about rizal shrine",
    "telll me abouut rizaaal shrin", // Typo test
    "swimming pools",
    "resorts",
    "contact admin",
    "logout",
    "login",
    "how to rate",
    "latest news about laguna",
    "price of buko pie",
    "meaning of life"
];

$results = [];

foreach ($testCases as $msg) {
    echo "\n--- Testing: '$msg' ---\n";
    $res = makeRequest($chatUrl, ['message' => $msg]);
    
    if ($res) {
        $reply = $res['reply'] ?? 'No reply field';
        $confidence = $res['confidence'] ?? 'N/A';
        echo "Reply: " . substr(str_replace("\n", " ", $reply), 0, 100) . "...\n";
        echo "Confidence: $confidence\n";
        
        $results[] = [
            'input' => $msg,
            'reply' => $reply,
            'success' => $res['success'] ?? false
        ];
    } else {
        echo "No response.\n";
        $results[] = [
            'input' => $msg,
            'reply' => 'ERROR',
            'success' => false
        ];
    }
}

echo "\n\n=== Test Summary ===\n";
foreach ($results as $r) {
    $status = $r['success'] ? "PASS" : "FAIL";
    $shortReply = substr(str_replace("\n", " ", $r['reply']), 0, 50);
    echo "[$status] '{$r['input']}' -> $shortReply...\n";
}

// Clean up
unlink($cookieFile);
?>