<?php
// Test Script for AI Chat API

function test_question($question) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/Tourist Guide System/test_ai_questions.php';
    $baseDir = dirname($scriptPath);
    $url = "$protocol://$host" . ($baseDir === '/' ? '' : $baseDir) . "/api/chat_ai.php";
    $data = json_encode(['message' => $question]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "--------------------------------------------------\n";
    echo "Q: $question\n";
    if ($http_code == 200) {
        $json = json_decode($response, true);
        if (isset($json['reply'])) {
            echo "A: " . substr(str_replace("\n", " ", $json['reply']), 0, 150) . "...\n";
            echo "Confidence: " . ($json['confidence'] ?? 'N/A') . "\n";
        } else {
            echo "Error: Invalid JSON response\n$response\n";
        }
    } else {
        echo "Error: HTTP $http_code\n";
    }
    echo "\n";
}

$questions = [
    "tell me about laguna",
    "Who is the president of the Philippines?",
    "ask on chat gpt"
];

echo "Testing AI Chat API (Confident Mode)...\n";
foreach ($questions as $q) {
    test_question($q);
}
?>
