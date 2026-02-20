<?php
include __DIR__ . '/../db_connect.php';
session_start();

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

    return $knowledge;
}

$knowledge = loadWebsiteKnowledge();
echo "KNOWLEDGE CHECK:\n";
if (strpos($knowledge, "Palo Alto") !== false) {
    echo "SUCCESS: Manual knowledge found!\n";
    echo "SNIPPET: " . substr($knowledge, strpos($knowledge, "[Manual Knowledge]"), 200) . "\n";
} else {
    echo "FAILURE: Manual knowledge NOT found!\n";
}
?>
