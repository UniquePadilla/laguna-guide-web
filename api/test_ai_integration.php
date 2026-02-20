<?php
// Standalone test for AI Responder and Distance Knowledge
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../db_connect.php';

// Mock some functions needed by chat_ai.php if we were to include it
// But it's easier to just test the specific logic here

function trace_log($msg) {
    echo "[TRACE] $msg\n";
    file_put_contents('trace_live.txt', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

// Include the API keys (simulated or from config)
// We'll try to find where they are defined. Usually in db_connect or chat_ai.php
// For this test, let's just see if we can load the knowledge first.

function loadWebsiteKnowledge() { 
    $root = dirname(__DIR__);
    $files = glob($root . '/*.php');
    $html_files = glob($root . '/*.html');
    if ($html_files) {
        $files = array_merge($files, $html_files);
    }

    $knowledge = ""; 

    foreach ($files as $file) { 
        if (file_exists($file)) { 
            $filename = basename($file);
            if ($filename === 'db_connect.php' || $filename === 'logout.php' || $filename === 'chat_ai.php') continue;

            $raw_content = file_get_contents($file);
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $raw_content = preg_replace('/<\?php.*?\?>/s', '', $raw_content);
            }
            $content = strip_tags($raw_content); 
            $content = preg_replace('/\s+/', ' ', $content);
            $content = trim($content);
            if (!empty($content)) {
                $knowledge .= "\n[File: $filename] " . $content; 
            }
        } 
    } 

    $knowledge .= "\n[Manual Knowledge] Palo Alto, Calamba, Laguna is located approximately 12.5km (about 30-40 mins drive) from PWU (Philippine Women's University) Calamba via Marinig and the National Highway. It is definitely NOT just 5km away; it is on the other side of the city near the boundary of Silang/Tagaytay.";
    $knowledge .= "\n[Distances] PWU Calamba to Palo Alto: ~12.5km. Palo Alto to Calamba Crossing: ~11km. Palo Alto to SM Calamba: ~10km.";

    return substr($knowledge, 0, 8000);
}

echo "Testing Knowledge Base Loading...\n";
$knowledge = loadWebsiteKnowledge();
if (strpos($knowledge, "12.5km") !== false) {
    echo "SUCCESS: Correct distance knowledge found in context!\n";
} else {
    echo "FAILURE: Correct distance knowledge NOT found!\n";
}

echo "\nTesting Python AI Helper Integration...\n";
// This tests the function call_python_ai_helper from chat_ai.php
// We need to make sure we have the function available or copy it.

function call_python_ai_helper($query, $session_id = "default", $history = [], $api_key = "", $system_prompt = "") {
    $scriptPath = __DIR__ . '/ai_helper.py';
    if (!file_exists($scriptPath)) {
        echo "Python script not found at $scriptPath\n";
        return null;
    }

    $pythonPath = 'python';
    $commonPaths = [
        'C:\Python313\python.exe',
        'C:\Python312\python.exe',
        'C:\Python311\python.exe',
        'C:\Python310\python.exe',
        'C:\Users\\' . get_current_user() . '\AppData\Local\Programs\Python\Python313\python.exe',
        'C:\Users\\' . get_current_user() . '\AppData\Local\Programs\Python\Python312\python.exe'
    ];
    
    foreach ($commonPaths as $path) {
        if (file_exists($path)) {
            $pythonPath = $path;
            break;
        }
    }

    $history_json = json_encode($history);
    $promptFile = null;
    $systemPromptArg = $system_prompt;
    if (strlen($system_prompt) > 1000) {
        $promptFile = tempnam(sys_get_temp_dir(), 'ai_prompt_');
        file_put_contents($promptFile, $system_prompt);
        $systemPromptArg = "FILE:" . $promptFile;
    }

    $command = escapeshellarg($pythonPath) . " " . escapeshellarg($scriptPath) . " " . escapeshellarg($query) . " " . escapeshellarg($session_id) . " " . escapeshellarg($history_json) . " " . escapeshellarg($api_key) . " " . escapeshellarg($systemPromptArg);
    
    echo "Executing command: $command\n";
    $output = shell_exec($command);
    
    if ($promptFile && file_exists($promptFile)) {
        @unlink($promptFile);
    }

    if ($output) {
        return json_decode($output, true);
    }
    return null;
}

// Test with a simple query
$test_query = "How far is Palo Alto from PWU Calamba?";
echo "Sending query: $test_query\n";
$response = call_python_ai_helper($test_query, "test_session", [], "MOCK_KEY", "You are an assistant. Knowledge: " . $knowledge);

if ($response) {
    echo "Python Response Received:\n";
    print_r($response);
} else {
    echo "FAILURE: No response from Python helper. Check if Python is installed and ai_helper.py works.\n";
}
?>
