<?php
$q = "Why is the sky blue?";
$log = "result.txt";
file_put_contents($log, "Starting test...\n");

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/Tourist Guide System/test_single.php';
$baseDir = dirname($scriptPath);
$apiUrl = "$protocol://$host" . ($baseDir === '/' ? '' : $baseDir) . "/api/chat_ai.php";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['message' => $q]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 90); // Increased timeout

$response = curl_exec($ch);
$info = curl_getinfo($ch);
$error = curl_error($ch);
curl_close($ch);

file_put_contents($log, "HTTP: " . $info['http_code'] . "\n", FILE_APPEND);
if ($error) {
    file_put_contents($log, "ERROR: $error\n", FILE_APPEND);
}

$json = json_decode($response, true);
if (isset($json['debug'])) {
    file_put_contents($log, "DEBUG:\n" . implode("\n", $json['debug']) . "\n", FILE_APPEND);
}
file_put_contents($log, "REPLY: " . ($json['reply'] ?? 'NULL') . "\n", FILE_APPEND);
file_put_contents($log, "RAW: " . substr($response, 0, 500) . "\n", FILE_APPEND);
?>
