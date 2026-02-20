<?php
// Test Web Search Functions independently

include 'api/chat_ai.php'; // Includes the functions

$query = "Laguna Philippines";

echo "Testing Search for: '$query'\n\n";

// 1. Test DuckDuckGo
echo "--- DuckDuckGo ---\n";
$duck = duckduckgo_search($query);
if ($duck) {
    echo "Success: " . substr($duck, 0, 100) . "...\n";
} else {
    echo "Failed (Empty or Error)\n";
}

// 2. Test Wikipedia
echo "\n--- Wikipedia ---\n";
$wiki = wikipedia_summary($query);
if ($wiki) {
    echo "Success: " . substr($wiki, 0, 100) . "...\n";
} else {
    echo "Failed (Empty or Error)\n";
}

// 3. Test Dictionary
echo "\n--- Dictionary ---\n";
$dict = dictionary_lookup("tourist");
if ($dict) {
    echo "Success: " . substr($dict, 0, 100) . "...\n";
} else {
    echo "Failed\n";
}

// 4. Test Blended
echo "\n--- Blended ---\n";
$blended = blended_search_context($query, '', '');
echo $blended ? $blended : "No results";
?>
