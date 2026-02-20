<?php
// Standalone Test for Search Functions

function duckduckgo_search($query) {
    $url = "https://api.duckduckgo.com/?q=" . urlencode($query) . "&format=json&no_html=1&skip_disambig=1";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5s timeout
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code !== 200 || !$response) return null;
    $json = json_decode($response, true);
    return $json['AbstractText'] ?: ($json['RelatedTopics'][0]['Text'] ?? null);
}

$queries = [
    "chat gpt"
];

foreach ($queries as $q) {
    echo "--------------------------------------------------\n";
    echo "Testing: '$q'\n";
    $d = duckduckgo_search($q);
    echo "DuckDuckGo: " . ($d ? substr($d, 0, 100) . "..." : "FAIL") . "\n";
}
?>
