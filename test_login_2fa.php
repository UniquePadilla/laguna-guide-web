<?php
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/Tourist Guide System/test_login_2fa.php';
$baseDir = dirname($scriptPath);
$url = "$protocol://$host" . ($baseDir === '/' ? '' : $baseDir) . "/api/login.php";
$data = ['username' => 'admin', 'password' => 'admin123'];

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
    ],
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

if ($result === FALSE) {
    echo "Error making request\n";
} else {
    echo "Response: " . $result . "\n";
}
?>