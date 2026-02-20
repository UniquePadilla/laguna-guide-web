<?php
header('Content-Type: application/json');
session_start();
include '../db_connect.php';

// Enforce Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'reply' => 'Please log in to use the AI assistant.']);
    exit;
} 

// Build User Context (Mirror System State)
$user_context_str = "";
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $u_stmt = $conn->prepare("SELECT username, role, email FROM users WHERE id = ?");
    if ($u_stmt) {
        $u_stmt->bind_param("i", $uid);
        $u_stmt->execute();
        $u_res = $u_stmt->get_result();
        if ($u_data = $u_res->fetch_assoc()) {
            // Privacy: Only expose current user's data to themselves
            $user_context_str = "CURRENT USER CONTEXT:\n";
            $user_context_str .= "- Username: " . htmlspecialchars($u_data['username']) . "\n";
            $user_context_str .= "- Role: " . htmlspecialchars($u_data['role']) . "\n";
            // Don't expose other sensitive fields
        }
        $u_stmt->close();
    }
}

// System Usage Guide (How-To)
$system_guide_str = <<<GUIDE
SYSTEM USAGE INSTRUCTIONS:
- Contact Admin: Go to the 'Contact' section (bottom of the page) to use the message form. Or call the Tourism Office at 0985 807 2562.
- Logout: Click your profile icon/avatar in the top right corner, then select 'Logout' from the dropdown menu.
- Login/Sign Up: If you are not logged in, click the 'Login' button in the top right. To create an account, click 'Login' then 'Sign up'.
- Navigation: Use the top menu bar to switch between Home, Destinations, Cultural, Cuisine, Events, Maps, and Tips.
- Dark Mode: Toggle the Sun/Moon switch in the top right navigation bar to change the theme.
- Rating Spots: Click on any tourist spot card to open the details popup. Scroll down to the 'Rate this Spot' section, click the stars (1-5), and submit.
- Favorites: Click the heart icon on any spot card to save it. View your favorites in your Profile summary on the Home page.
- Settings: Click your profile avatar -> Settings to change text size or language.
- Search: Use the search bar in the 'Destination' section to find specific places.
GUIDE;

$debug_trace = [];
function trace_log($msg) {
    global $debug_trace;
    $debug_trace[] = $msg;
    file_put_contents('trace_live.txt', date('H:i:s') . " - $msg\n", FILE_APPEND);
}

// Load API Config
if (file_exists('../config/api_config.php')) {
    include '../config/api_config.php';
} else {
    // Fallback constants if file missing
    if (!defined('GEMINI_API_KEY')) define('GEMINI_API_KEY', '');
    if (!defined('OPENAI_API_KEY')) define('OPENAI_API_KEY', '');
    if (!defined('GOOGLE_CSE_KEY')) define('GOOGLE_CSE_KEY', '');
    if (!defined('GOOGLE_CSE_CX')) define('GOOGLE_CSE_CX', '');
}

// --- External API Helpers ---

function google_search_summary($query, $key, $cx) {
    if (!$key || !$cx) return null;
    $url = "https://www.googleapis.com/customsearch/v1?q=" . urlencode($query) . "&key=" . urlencode($key) . "&cx=" . urlencode($cx) . "&num=3";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5s timeout
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$response) return null;
    $json = json_decode($response, true);
    if (empty($json['items'])) return null;
    $snippets = [];
    foreach (array_slice($json['items'], 0, 3) as $it) {
        $snippets[] = trim(($it['title'] ?? '') . ': ' . ($it['snippet'] ?? ''));
    }
    return implode("\n- ", $snippets);
}

function dictionary_lookup($word) {
    $word = trim(strtolower($word));
    if (strpos($word, ' ') !== false) return null;
    $url = "https://api.dictionaryapi.dev/api/v2/entries/en/" . urlencode($word);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5s timeout
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$response) return null;
    $json = json_decode($response, true);
    return $json[0]['meanings'][0]['definitions'][0]['definition'] ?? null;
}

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

function wikipedia_summary($query) {
    $searchUrl = "https://en.wikipedia.org/w/api.php?action=opensearch&search=" . urlencode($query) . "&limit=1&namespace=0&format=json";
    $ch = curl_init($searchUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5s timeout
    $res = curl_exec($ch);
    curl_close($ch);
    if (!$res) return null;
    $sjson = json_decode($res, true);
    if (empty($sjson[1][0])) return null;
    
    $title = $sjson[1][0];
    $sumUrl = "https://en.wikipedia.org/api/rest_v1/page/summary/" . urlencode($title);
    $ch2 = curl_init($sumUrl);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 5); // 5s timeout
    $res2 = curl_exec($ch2);
    curl_close($ch2);
    if (!$res2) return null;
    $json2 = json_decode($res2, true);
    return isset($json2['extract']) ? $json2['title'] . ': ' . $json2['extract'] : null;
}

// --- Parallel Search Helper ---
class MultiCurl {
    private $mh;
    private $requests = [];

    public function __construct() {
        $this->mh = curl_multi_init();
    }

    public function add($key, $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4); // Aggressive 4s timeout
        curl_setopt($ch, CURLOPT_USERAGENT, 'LagunaGuide/1.0');
        curl_multi_add_handle($this->mh, $ch);
        $this->requests[$key] = $ch;
    }

    public function execute() {
        $running = null;
        do {
            curl_multi_exec($this->mh, $running);
            curl_multi_select($this->mh);
        } while ($running > 0);

        $results = [];
        foreach ($this->requests as $key => $ch) {
            $results[$key] = [
                'content' => curl_multi_getcontent($ch),
                'info' => curl_getinfo($ch)
            ];
            curl_multi_remove_handle($this->mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($this->mh);
        return $results;
    }
}

function parallel_search_context($query, $google_key, $google_cx) {
    $mc = new MultiCurl();
    $sources = [];
    
    // 1. Google Custom Search
    if ($google_key && $google_cx) {
        $url = "https://www.googleapis.com/customsearch/v1?q=" . urlencode($query) . "&key=" . urlencode($google_key) . "&cx=" . urlencode($google_cx) . "&num=3";
        $mc->add('google', $url);
    }
    
    // 2. DuckDuckGo
    $ddg_url = "https://api.duckduckgo.com/?q=" . urlencode($query) . "&format=json&no_html=1&skip_disambig=1";
    $mc->add('duck', $ddg_url);
    
    // 3. Wikipedia (OpenSearch)
    $wiki_url = "https://en.wikipedia.org/w/api.php?action=opensearch&search=" . urlencode($query) . "&limit=1&namespace=0&format=json";
    $mc->add('wiki_search', $wiki_url);
    
    // 4. Dictionary
    if (strpos($query, ' ') === false) {
        $dict_url = "https://api.dictionaryapi.dev/api/v2/entries/en/" . urlencode($query);
        $mc->add('dict', $dict_url);
    }
    
    $results = $mc->execute();
    
    // Process Google
    if (isset($results['google']) && $results['google']['info']['http_code'] === 200) {
        $json = json_decode($results['google']['content'], true);
        if (!empty($json['items'])) {
            $snippets = [];
            foreach (array_slice($json['items'], 0, 3) as $it) {
                $snippets[] = trim(($it['title'] ?? '') . ': ' . ($it['snippet'] ?? ''));
            }
            if ($snippets) $sources[] = "[Google]: " . implode("\n- ", $snippets);
        }
    }
    
    // Process DuckDuckGo
    if (isset($results['duck']) && $results['duck']['info']['http_code'] === 200) {
        $json = json_decode($results['duck']['content'], true);
        $text = $json['AbstractText'] ?: ($json['RelatedTopics'][0]['Text'] ?? null);
        if ($text) $sources[] = "[DuckDuckGo]: " . $text;
    }
    
    // Process Wiki & Dictionary
    if (isset($results['wiki_search']) && $results['wiki_search']['info']['http_code'] === 200) {
        $sjson = json_decode($results['wiki_search']['content'], true);
        if (!empty($sjson[1][0])) {
            $title = $sjson[1][0];
            $desc = $sjson[2][0] ?? ''; // OpenSearch sometimes returns description
            if ($desc) {
                $sources[] = "[Wikipedia]: $title - $desc";
            } else {
                 // Fast fetch summary if we have a title but no desc (rare in opensearch but happens)
                 // We could do a second parallel batch here if needed, but for speed we might skip or do a quick blocking call
                 // Let's just note the topic exists
                 $sources[] = "[Wikipedia]: Found topic '$title'.";
            }
        }
    }

    if (isset($results['dict']) && $results['dict']['info']['http_code'] === 200) {
        $json = json_decode($results['dict']['content'], true);
        $def = $json[0]['meanings'][0]['definitions'][0]['definition'] ?? null;
        if ($def) $sources[] = "[Dictionary]: $def";
    }

    return implode("\n\n", $sources);
}

function blended_search_context($query, $google_key, $google_cx) {
    // Legacy wrapper calling the new parallel function
    return parallel_search_context($query, $google_key, $google_cx);
}

function loadWebsiteKnowledge() { 
    // Simple Session Caching
    if (isset($_SESSION['website_knowledge_cache']) && !empty($_SESSION['website_knowledge_cache'])) {
        return $_SESSION['website_knowledge_cache'];
    }

    $root = dirname(__DIR__);
    // Scan for all PHP files in the root directory
    $files = glob($root . '/*.php');
    // Also include specific HTML files if they exist
    $html_files = glob($root . '/*.html');
    if ($html_files) {
        $files = array_merge($files, $html_files);
    }

    $knowledge = ""; 

    foreach ($files as $file) { 
        if (file_exists($file)) { 
            // Skip large system files or irrelevant scripts to save tokens
            $filename = basename($file);
            if ($filename === 'db_connect.php' || $filename === 'logout.php') continue;

            $raw_content = file_get_contents($file);
            
            // Special handling for PHP files to avoid reading code logic
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                // Remove PHP tags and content within them to just get HTML text
                $raw_content = preg_replace('/<\?php.*?\?>/s', '', $raw_content);
            }
            
            $content = strip_tags($raw_content); 
            // Remove excessive whitespace
            $content = preg_replace('/\s+/', ' ', $content);
            $content = trim($content);
            
            if (!empty($content)) {
                $knowledge .= "\n[File: $filename] " . $content; 
            }
        } 
    } 

    $final_knowledge = substr($knowledge, 0, 8000); // Increased limit as per instruction
    $_SESSION['website_knowledge_cache'] = $final_knowledge;
    return $final_knowledge;
} 

function smartFallback($question) { 
    return <<<PROMPT
Answer this question using: 
- General public knowledge 
- Logical reasoning 
- Common trends 
- Local tourism context 

Question: 
$question 
PROMPT; 
} 

function lacksWebsiteAnswer($msg) { 
    return preg_match('/news|latest|today|current|price|event/i', $msg); 
} 

// --- Python Integration ---

function call_python_ai_helper($query, $session_id = "default", $history = [], $api_key = "", $google_key = "", $google_cx = "") {
    // Check if Python script exists
    $scriptPath = __DIR__ . '/ai_helper.py';
    if (!file_exists($scriptPath)) return null;

    // Portability: Try to find python executable
    $pythonPath = 'python'; // Default
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

    // Convert history to JSON for passing to Python
    $history_json = json_encode($history);

    // Build command
    $command = escapeshellarg($pythonPath) . " " . escapeshellarg($scriptPath) . " " . escapeshellarg($query) . " " . escapeshellarg($session_id) . " " . escapeshellarg($history_json) . " " . escapeshellarg($api_key) . " " . escapeshellarg($google_key) . " " . escapeshellarg($google_cx);
    
    $output = shell_exec($command);
    
    if ($output) {
        $data = json_decode($output, true);
        if ($data) {
            return $data; // Return full array (source, answer, intents)
        }
    }
    return null;
}

// --- Conversation Memory ---

function setConversationContext($key, $value) { 
    if (!isset($_SESSION['chat_ctx'])) $_SESSION['chat_ctx'] = [];
    $_SESSION['chat_ctx'][$key] = $value; 
} 

function getConversationContext($key) { 
    return $_SESSION['chat_ctx'][$key] ?? null; 
}

function buildReply($text, $confidence) { 
    global $debug_trace;
    return [ 
        'success' => true, 
        'reply' => $text, 
        'confidence' => $confidence,
        'debug' => $debug_trace
    ]; 
}

// --- Enhanced Conversation Memory (DB Integrated) ---

function getActiveConversationId() {
    if (isset($_SESSION['active_conversation_id'])) {
        return $_SESSION['active_conversation_id'];
    }
    
    // Auto-Resume: If logged in and NOT forced new chat, try to resume last conversation
    if (isset($_SESSION['user_id']) && !isset($_SESSION['force_new_chat'])) {
        global $conn;
        $uid = $_SESSION['user_id'];
        // Find last conversation from DB
        $stmt = $conn->prepare("SELECT id FROM chat_conversations WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                 $last_id = $row['id'];
                 $_SESSION['active_conversation_id'] = $last_id;
                 return $last_id;
            }
            $stmt->close();
        }
    }
    
    return null;
}

function startNewConversation($title = 'New Chat') {
    global $conn;
    if (!isset($_SESSION['user_id'])) return null;
    
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO chat_conversations (user_id, title) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $title);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    
    $_SESSION['active_conversation_id'] = $id;
    if (isset($_SESSION['force_new_chat'])) unset($_SESSION['force_new_chat']);
    return $id;
}

function addToChatHistory($role, $message) {
    global $conn;

    // Update Session (Keep for fast access/fallback)
    if (!isset($_SESSION['chat_history'])) {
        $_SESSION['chat_history'] = [];
    }
    
    $_SESSION['chat_history'][] = [
        "role" => $role,
        "parts" => [["text" => $message]]
    ];
    
    // Keep only last 10 messages (avoid token overload)
    $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -10);
    
    // Update Database
    if (isset($_SESSION['user_id'])) {
        $conv_id = getActiveConversationId();
        if (!$conv_id) {
            // First message becomes title
            $title = mb_substr($message, 0, 30);
            if (strlen($message) > 30) $title .= '...';
            $conv_id = startNewConversation($title);
        }
        
        if ($conv_id) {
            $stmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, role, content) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $conv_id, $role, $message);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function getChatHistory() {
    global $conn;
    
    // Prefer DB if available and logged in
    if (isset($_SESSION['user_id'])) {
        $conv_id = getActiveConversationId();
        if ($conv_id) {
            $stmt = $conn->prepare("SELECT role, content FROM chat_messages WHERE conversation_id = ? ORDER BY id ASC");
            $stmt->bind_param("i", $conv_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $history = [];
            while ($row = $res->fetch_assoc()) {
                $history[] = [
                    "role" => $row['role'],
                    "parts" => [["text" => $row['content']]]
                ];
            }
            $stmt->close();
            
            // Sync session
            // We return full history for display, but might slice for AI context elsewhere
            return $history; 
        }
    }
    
    return $_SESSION['chat_history'] ?? [];
}

function buildConversationContext($systemPrompt, $userMessage) {
    $history = getChatHistory();
    $conversationContext = $systemPrompt . "\n\nCurrent Conversation Context:";
    
    foreach ($history as $message) {
        $role = $message['role'];
        $text = $message['parts'][0]['text'];
        $conversationContext .= "\n$role: $text";
    }
    
    $conversationContext .= "\n\nCurrent User Message: $userMessage";
    return $conversationContext;
}

function needsWebSearch($message) { 
    $keywords = ['latest', 'current', 'today', 'news', 'price', 'who is', 'what is', 'search', 'online', 'internet', 'google', 'find']; 
    foreach ($keywords as $word) { 
        if (stripos($message, $word) !== false) { 
            return true; 
        } 
    } 
    return false; 
}

function isNewsQuestion($msg) { 
    return preg_match('/latest|news|update|current|today|recent/i', $msg); 
}

function isWebsiteQuestion($msg) { 
    return preg_match('/website|this site|laguna|your site|here/i', $msg); 
} 

function buildFallbackPrompt($question) { 
    return <<<PROMPT
Answer the following question using general public knowledge, 
current trends, and logical reasoning. 

Then adapt the answer to fit Laguna Tourism and this website. 

Question: 
$question 
PROMPT; 
} 

// --- Smart Intent Detection ---
// Logic moved to api/ai_helper.py as per user request to separate Python code.
// The PHP functions below are kept as a local fallback if Python is unavailable.

function detectIntent($message) {
    $message = strtolower($message);

    if (preg_match('/relax|chill|peaceful|rest|calm/i', $message)) {
        return "relaxation";
    }

    if (preg_match('/food|eat|restaurant|dish|cuisine/i', $message)) {
        return "food";
    }

    if (preg_match('/festival|event|celebration/i', $message)) {
        return "events";
    }

    if (preg_match('/how to go|transport|commute|directions/i', $message)) {
        return "transport";
    }

    return "general";
}

function buildIntentContext($intent, $spots_data) {
    $recommendations = [];
    
    // Simple mapping of intents to spot types/keywords
    $intent_map = [
        'relaxation' => ['resort', 'park', 'nature', 'lake', 'falls', 'spring'],
        'food' => ['cuisine', 'restaurant', 'cafe', 'delicacy', 'eat', 'dining'],
        'events' => ['festival', 'event', 'cultural', 'celebration'],
        'transport' => ['transport', 'direction', 'bus', 'car', 'jeepney']
    ];

    if (!isset($intent_map[$intent])) {
        return "Answer helpfully and naturally as Doquerainee AI.";
    }

    $keywords = $intent_map[$intent];
    foreach ($spots_data as $spot) {
        $text = strtolower($spot['name'] . ' ' . $spot['type'] . ' ' . $spot['description']);
        foreach ($keywords as $kw) {
            if (strpos($text, $kw) !== false) {
                $recommendations[] = "- **{$spot['name']}** ({$spot['type']}): {$spot['description']}";
                break;
            }
        }
        if (count($recommendations) >= 5) break;
    }

    if (!empty($recommendations)) {
        $places = implode("\n", $recommendations);
        return "User intent detected: $intent\n\nRecommended Laguna locations:\n$places\n\nAnswer naturally and suggest these when relevant.";
    } else {
        return "User intent detected: $intent. Answer helpfully and naturally as Doquerainee AI.";
    }
}

// --- Utility Classes ---

class TextNormalizer {
    public static function normalize(string $text): string {
        $text = mb_strtolower($text, 'UTF-8');
        // Collapse 3+ repeated characters to 1 (e.g. "tellll" -> "tel", "lagunaaaa" -> "laguna")
        $text = preg_replace('/(.)\1{2,}/u', '$1', $text);
        $text = preg_replace('/[^\p{L}\p{N}\s\-\'"]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return $text;
    }
}

class SpellAdapter {
    private $dictionary = [];
    private $stopwords = [
        'the', 'is', 'at', 'which', 'on', 'in', 'a', 'an', 'and', 'or', 'for', 'of', 'to', 
        'can', 'i', 'you', 'me', 'he', 'she', 'it', 'we', 'they', 'what', 'where', 'when', 
        'who', 'why', 'how', 'are', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
        'ask', 'tell', 'say', 'speak', 'talk', 'about', 'know'
    ];

    public function __construct(array $dictionary = []) {
        $this->dictionary = $dictionary;
    }

    public function correctPhrase(string $phrase): string {
        $words = explode(' ', $phrase);
        $out = [];
        foreach ($words as $w) {
            if (in_array(strtolower($w), $this->stopwords)) {
                $out[] = $w;
            } else {
                $out[] = $this->correctWord($w);
            }
        }
        return implode(' ', $out);
    }

    private function correctWord(string $word): string {
        if (mb_strlen($word) < 3) return $word; // Skip very short words (1-2 chars)
        if (empty($this->dictionary)) return $word;

        $best = $word;
        $shortest = -1;

        foreach ($this->dictionary as $dictWord) {
            $lev = levenshtein($word, $dictWord);
            if ($lev === 0) return $word; // Exact match

            if ($lev <= 2) { // Allow max 2 edits
                if ($shortest < 0 || $lev < $shortest) {
                    $shortest = $lev;
                    $best = $dictWord;
                }
            }
        }
        return $best;
    }
}

// --- Embedding Retriever (Semantic) ---
class EmbeddingRetriever {
    private array $kb;
    private string $apiKey;
    private array $cache = [];

    public function __construct(array $kb, string $apiKey) {
        $this->kb = $kb;
        $this->apiKey = $apiKey;
    }

    private function getEmbedding(string $text): ?array {
        if (isset($this->cache[$text])) return $this->cache[$text];

        // Clean text to avoid JSON errors
        $cleanText = str_replace(["\r", "\n"], " ", $text);
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/embedding-001:embedContent?key=" . $this->apiKey;
        $postData = [
            "model" => "models/embedding-001",
            "content" => ["parts" => [["text" => $cleanText]]]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 5, // 5s timeout
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) return null;
        $json = json_decode($response, true);
        $vec = $json['embedding']['values'] ?? null;
        
        if ($vec) $this->cache[$text] = $vec;
        return $vec;
    }

    private function cosineSimilarity(array $a, array $b): float {
        $dot = 0; $normA = 0; $normB = 0;
        foreach ($a as $i => $val) {
            $dot += $val * $b[$i];
            $normA += $val * $val;
            $normB += $b[$i] * $b[$i];
        }
        return ($normA * $normB) > 0 ? $dot / (sqrt($normA) * sqrt($normB)) : 0;
    }

    // Interface for HybridRetriever
    public function similarity(string $query, string $text): float {
        $qVec = $this->getEmbedding($query);
        $tVec = $this->getEmbedding($text);
        if ($qVec && $tVec) {
            return $this->cosineSimilarity($qVec, $tVec);
        }
        return 0.0;
    }

    public function findBest(string $query, int $limit = 3): array {
        $queryVec = $this->getEmbedding($query);
        if (!$queryVec) return [];
        
        $scores = [];
        foreach ($this->kb as $k => $v) {
            $kbVec = $this->getEmbedding($k);
            if ($kbVec) $scores[$k] = $this->cosineSimilarity($queryVec, $kbVec);
        }
        arsort($scores);
        $best = array_slice($scores, 0, $limit, true);
        
        $out = [];
        foreach ($best as $k => $s) {
            $out[] = ['question' => $k, 'answer' => $this->kb[$k], 'score' => $s];
        }
        return $out;
    }
}

// --- Hybrid Retriever (Fuzzy + Embedding Placeholder) ---
class HybridRetriever {
    private array $kb;
    private $embeddingModel;

    public function __construct(array $kb, $embeddingModel = null) {
        $this->kb = $kb;
        $this->embeddingModel = $embeddingModel;
    }

    public function findBest(string $query, int $limit = 3): array {
        $scores = [];
        foreach ($this->kb as $k => $v) {
            // Normalized Levenshtein score (0 to 1, where 1 is exact match)
            $len = max(strlen($query), strlen($k));
            $levScore = $len > 0 ? (1 - (levenshtein($query, $k) / $len)) : 0;
            
            if ($this->embeddingModel) {
                // Semantic score
                $semanticScore = $this->embeddingModel->similarity($query, $k);
                // Weighted score
                $scores[$k] = ($levScore * 0.4) + ($semanticScore * 0.6);
            } else {
                // Fallback: Pure Levenshtein
                $scores[$k] = $levScore;
            }
        }
        arsort($scores);
        $best = array_slice($scores, 0, $limit, true);
        
        // Format for consumption
        $out = [];
        foreach ($best as $k => $s) {
            $out[] = ['question' => $k, 'answer' => $this->kb[$k], 'score' => $s];
        }
        return $out;
    }
}

// --- AI Responder with Fallback ---
class AIResponder {
    private $apiKeyGemini;
    private $apiKeyOpenAI;

    public function __construct($geminiKey, $openaiKey) {
        $this->apiKeyGemini = $geminiKey;
        $this->apiKeyOpenAI = $openaiKey;
    }

    public function ask(string $prompt, string $systemContext = ''): ?string {
        $reply = null;
        
        // Debug Log
        file_put_contents('debug_log.txt', "Ask called with: $prompt\n", FILE_APPEND);
        
        // 1. Try Cloudflare First (User Preference)
        if (defined('CF_API_KEY') && defined('CF_ACCOUNT_ID') && !empty(CF_API_KEY)) {
            $reply = $this->askCloudflare($prompt, $systemContext);
            file_put_contents('debug_log.txt', "Cloudflare reply: " . ($reply ? "YES" : "NO") . "\n", FILE_APPEND);
        }

        // 2. Fallback to Gemini
        if (!$reply) {
            $reply = $this->askGemini($prompt, $systemContext);
            file_put_contents('debug_log.txt', "Gemini reply: " . ($reply ? "YES" : "NO") . "\n", FILE_APPEND);
        }
        
        // 3. Fallback to OpenAI
        if (!$reply && $this->apiKeyOpenAI) $reply = $this->askOpenAI($prompt, $systemContext);
        
        // Enhance short responses
        $responseText = trim($reply);
        
        $final = $responseText ?: "I’m not sure, but let’s explore further!";
        file_put_contents('debug_log.txt', "Final reply: $final\n", FILE_APPEND);
        return $final;
    }

    private function askCloudflare($prompt, $systemContext) {
        $accountId = defined('CF_ACCOUNT_ID') ? CF_ACCOUNT_ID : '';
        $apiKey = defined('CF_API_KEY') ? CF_API_KEY : '';
        $model = defined('CF_MODEL') ? CF_MODEL : '@cf/meta/llama-3-8b-instruct';
        
        trace_log("Asking Cloudflare: $model");
        
        if (!$accountId || !$apiKey) return null;

        $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}";
        
        // Prepare messages
        $messages = [];
        // Add system context
        $messages[] = ["role" => "system", "content" => $systemContext];
        
        // Add Chat History (limit to last 5 turns to avoid token limits)
        if (isset($_SESSION['chat_history']) && is_array($_SESSION['chat_history'])) {
            $history = array_slice($_SESSION['chat_history'], -5); 
            foreach ($history as $msg) {
                // Convert Gemini format to Cloudflare format
                if (isset($msg['role']) && isset($msg['parts'][0]['text'])) {
                    $role = ($msg['role'] == 'user') ? 'user' : 'assistant'; // Gemini uses 'model', CF uses 'assistant'
                    $messages[] = ["role" => $role, "content" => $msg['parts'][0]['text']];
                }
            }
        }
        
        // Add current prompt
        $messages[] = ["role" => "user", "content" => $prompt];

        $postData = [
            "messages" => $messages
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        trace_log("Cloudflare HTTP: $http_code Response: " . substr($response, 0, 100));

        if ($http_code === 200 && $response) {
            $json = json_decode($response, true);
            if (isset($json['success']) && $json['success']) {
                $content = $json['result']['response'] ?? null;
                return !empty($content) ? $content : null;
            }
        }
        return null;
    }

    private function askGemini($prompt, $systemContext) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $this->apiKeyGemini;
        
        // Check if web search is needed
        $enhancedSystemPrompt = $systemContext;
        
        // Check if we already have manual search results in the context
        $has_manual_search = strpos($systemContext, 'WEB SEARCH RESULTS') !== false;

        if (needsWebSearch($prompt) && !$has_manual_search) {
            $enhancedSystemPrompt .= "\nUse up-to-date information when answering.";
        }
        
        if (isNewsQuestion($prompt)) { 
            $enhancedSystemPrompt .= "\nWhen answering, summarize likely recent developments and trends instead of refusing."; 
        }
        
        // Prepare messages including current prompt
        $messages = $_SESSION['chat_history'] ?? [];
        $messages[] = [
            "role" => "user",
            "parts" => [["text" => $prompt]]
        ];

        $tools = [];
        // Only use Gemini's built-in search if we haven't already provided manual search results
        // This speeds up response time significantly by avoiding double-searching
        if (needsWebSearch($prompt) && !$has_manual_search) {
            $tools[] = ["googleSearchRetrieval" => (object)[]];
        }

        $payload = [ 
            "systemInstruction" => [ 
                "parts" => [ 
                    ["text" => $enhancedSystemPrompt] 
                ] 
            ], 
            "contents" => $messages, 
            "generationConfig" => [ 
                "temperature" => 0.7, 
                "topP" => 0.9, 
                "topK" => 40, 
                "maxOutputTokens" => 900 
            ]
        ];
        
        if (!empty($tools)) {
            $payload["tools"] = $tools;
        }

        $postData = $payload;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout to 60s
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $response) {
            $json = json_decode($response, true);
            return $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
        }
        return null;
    }

    private function askOpenAI($prompt, $systemContext) {
        $url = "https://api.openai.com/v1/chat/completions";
        $postData = [
            "model" => "gpt-4o-mini",
            "messages" => [
                ["role" => "system", "content" => $systemContext],
                ["role" => "user", "content" => $prompt]
            ]
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKeyOpenAI
            ],
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        if (!$response) return null;
        $json = json_decode($response, true);
        return $json['choices'][0]['message']['content'] ?? null;
    }
}

// --- Main Execution Flow ---

$data = json_decode(file_get_contents('php://input'), true);

// 1. Handle History Actions
if (isset($data['action'])) {
    if ($data['action'] === 'get_history') {
        $history = getChatHistory();
        echo json_encode(['success' => true, 'history' => $history]);
        exit;
    }
    
    if ($data['action'] === 'clear_history' || $data['action'] === 'new_chat') {
        unset($_SESSION['active_conversation_id']);
        $_SESSION['force_new_chat'] = true; // Signal to create new chat on next message
        $_SESSION['chat_history'] = [];
        $_SESSION['chat_ctx'] = [];
        echo json_encode(['success' => true, 'message' => 'Started new chat.']);
        exit;
    }

    if ($data['action'] === 'get_conversations') {
        if (!isset($_SESSION['user_id'])) {
             echo json_encode(['success' => false, 'message' => 'Not logged in']);
             exit;
        }
        $uid = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id, title, created_at FROM chat_conversations WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $list = [];
        while($row = $res->fetch_assoc()) {
            $list[] = $row;
        }
        echo json_encode(['success' => true, 'conversations' => $list]);
        exit;
    }

    if ($data['action'] === 'load_conversation') {
         if (!isset($data['id'])) {
             echo json_encode(['success' => false, 'message' => 'Missing ID']);
             exit;
         }
         $conv_id = $data['id'];
         
         // Verify ownership
         if (isset($_SESSION['user_id'])) {
             $uid = $_SESSION['user_id'];
             $check = $conn->prepare("SELECT id FROM chat_conversations WHERE id = ? AND user_id = ?");
             $check->bind_param("ii", $conv_id, $uid);
             $check->execute();
             if ($check->get_result()->num_rows === 0) {
                 echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                 exit;
             }
             $check->close();
             
             $_SESSION['active_conversation_id'] = $conv_id;
             $history = getChatHistory(); // Will fetch from DB
             echo json_encode(['success' => true, 'history' => $history]);
             exit;
         }
         echo json_encode(['success' => false, 'message' => 'Not logged in']);
         exit;
    }
}

$message = isset($data['message']) ? trim($data['message']) : '';

if (empty($message)) {
    echo json_encode(['success' => false, 'reply' => 'Please say something!']);
    exit;
}

// Enhanced System Prompt
$websiteKnowledge = loadWebsiteKnowledge();

$systemPrompt = <<<PROMPT
You are Doquerainee AI, a smart Laguna tourism assistant.

You have FULL knowledge of the website content below.
Use it as your PRIMARY source of truth.

$user_context_str

$system_guide_str

WEBSITE CONTENT:
$websiteKnowledge

RULES:
- Always answer confidently
- Never say you are offline
- If unsure, infer answers logically
- Base answers on the website whenever possible
- Only use external knowledge when website info is missing
- Never say you are unsure or "I don't know".
- Always give suggestions when possible.
- Be confident, friendly, and helpful.
- If a question asks for "latest" or "current" info:
  - Answer based on general trends and recent patterns
  - Never say you cannot access the internet
  - Phrase answers as "Based on recent trends" or "Typically"

CONTEXT & CONTINUITY:
- You are in a continuous conversation. REMEMBER what was just discussed.
- If the user gives a short statement like "I'm from X" or "What about Y?", interpret it based on the PREVIOUS message.
- Example: If the previous topic was "How to get to Laguna", and the user says "I'm from Visayas", explain how to get to Laguna FROM Visayas. Do NOT change the topic to "Food in Visayas" unless explicitly asked.
PROMPT;

// 3. Trigger Search Fallback
if (lacksWebsiteAnswer($message)) { 
    $fallbackPrompt = smartFallback($message);
    // Append fallback instruction to system prompt to guide the AI
    $systemPrompt .= "\n\n" . $fallbackPrompt;
}

// 1. Load Data & Build Knowledge Base
$spots_data = [];
$spot_names = [];
$kb_data = []; // Question -> Answer map

// Fetch Spots
$result = $conn->query("SELECT * FROM spots");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $spots_data[] = $row;
        $name_lower = strtolower($row['name']);
        $spot_names[] = $name_lower;
        
        // Add to KB
        $kb_data[$name_lower] = "Ah, **{$row['name']}**! It's a {$row['type']} spot located at {$row['location']}. Open: {$row['openTime']}-{$row['closeTime']}. {$row['description']}";
        $kb_data["where is $name_lower"] = "{$row['name']} is located at {$row['location']}.";
        $kb_data["what is $name_lower"] = "{$row['name']} is a {$row['type']} in Laguna. {$row['description']}";
    }
}

// Fetch Features (Events, Tips, etc.)
$feat_result = $conn->query("SELECT * FROM features WHERE status='active'");
if ($feat_result && $feat_result->num_rows > 0) {
    while($row = $feat_result->fetch_assoc()) {
        $title_lower = strtolower($row['title']);
        $kb_data[$title_lower] = "**{$row['title']}**: {$row['description']}";
        $spot_names[] = $title_lower;
        
        // Add keywords for events
        if (strpos($title_lower, 'event') !== false || strpos($title_lower, 'festival') !== false) {
             $kb_data['events'] = "**{$row['title']}**: {$row['description']}";
             $kb_data['next event'] = "**{$row['title']}**: {$row['description']}";
        }
    }
}

// 2. FINAL BRAIN: Call Python AI Helper with Session Persistence
$session_id = session_id() ?: 'guest_' . $_SERVER['REMOTE_ADDR'];
$current_history = getChatHistory();

// Pick API Key (Support both single and array of keys)
$api_key = '';
if (defined('GEMINI_API_KEYS') && is_array(GEMINI_API_KEYS) && !empty(GEMINI_API_KEYS)) {
    $api_key = GEMINI_API_KEYS[array_rand(GEMINI_API_KEYS)];
} elseif (defined('GEMINI_API_KEY')) {
    $api_key = GEMINI_API_KEY;
}

// Windows Limit: escapeshellarg() has a limit of 8192 bytes.
// Slice history to last 5 messages and truncate content to keep it safe.
$safe_history = array_slice($current_history, -5);
foreach ($safe_history as &$msg) {
    if (isset($msg['parts'][0]['text'])) {
        $msg['parts'][0]['text'] = mb_substr($msg['parts'][0]['text'], 0, 1000);
    }
}
unset($msg);

$googleKey = defined('GOOGLE_CSE_KEY') ? GOOGLE_CSE_KEY : '';
$googleCx = defined('GOOGLE_CSE_CX') ? GOOGLE_CSE_CX : '';

$pre_meta_reply = null;
if (preg_match('/^\s*(who\s+are\s+you|who\s+you|who\s+is\s+this|what\s+is\s+your\s+name)\s*$/i', $message)) {
    $pre_meta_reply = "I'm **Doquerainee**, the assistant for the **Laguna Tourist Guide System**. This site focuses only on **Laguna, Philippines**—ask me about Laguna spots, food, events, directions, or how to use the site.";
} elseif (preg_match('/(what.*can.*(do|offer)|features|capabilities|function|what.*is.*this.*system|what.*is.*this.*website|what.*does.*this.*website|purpose.*of.*this.*website|about.*this.*website|what.*this.*website.*for)/i', $message)) {
    $pre_meta_reply = "This website is the **Laguna Tourist Guide System**. It focuses only on **Laguna, Philippines** and helps you discover spots, food, events, directions, and how to use the site.";
}

if ($pre_meta_reply) {
    addToChatHistory('user', $message);
    addToChatHistory('model', $pre_meta_reply);
    echo json_encode([
        'success' => true,
        'reply' => $pre_meta_reply,
        'confidence' => 1.0,
        'debug' => array_merge($debug_trace, [
            'method' => 'pre_meta_reply'
        ])
    ]);
    exit;
}

$python_result = call_python_ai_helper($message, $session_id, $safe_history, $api_key, $googleKey, $googleCx);

if ($python_result && (isset($python_result['response']) || isset($python_result['answer']))) {
    $ai_response = $python_result['response'] ?? $python_result['answer'];
    
    // Universal History Update
    addToChatHistory('user', $message);
    addToChatHistory('model', $ai_response);

    echo json_encode([
        'success' => true,
        'reply' => $ai_response,
        'confidence' => 1.0,
        'debug' => array_merge($debug_trace, [
            'method' => 'python_smart_ai',
            'intents' => $python_result['intents'] ?? []
        ])
    ]);
    exit;
}

// --- Fallback to existing PHP logic if Python fails ---

// System QA & Website Knowledge
$system_qa = [
    // General Website Info
    'website name' => "This website is called the **Laguna Tourist Guide System** (or simply **Laguna Guide**).",
    'site name' => "This website is called the **Laguna Tourist Guide System** (or simply **Laguna Guide**).",
    'who made this' => "This website was created to help tourists explore the beautiful province of Laguna!",
    'what is this website' => "This is the **Laguna Tourist Guide System**, your one-stop platform for discovering the best spots, food, and culture in Laguna.",
    'what this website for' => "This is the **Laguna Tourist Guide System**, your one-stop platform for discovering the best spots, food, and culture in Laguna.",
    'purpose of this website' => "This is the **Laguna Tourist Guide System**, your one-stop platform for discovering the best spots, food, and culture in Laguna.",
    
    // Account & Auth
    'login' => "You can log in or sign up using the button in the top right corner of the navigation bar. If you're on mobile, check the menu!",
    'register' => "To create an account, click 'Login' in the top right, then select 'Sign Up' in the login form.",
    'logout' => "To log out, click the 'Logout' button in the top right (or in the settings menu if you're on mobile).",
    'password' => "If you forgot your password, please contact the admin or use the 'Forgot Password' link on the login page.",
    
    // Features & Settings
    'dark mode' => "You can toggle between Dark 🌙 and Light ☀️ mode using the switch in the navigation bar (look for the sun/moon icon).",
    'theme' => "You can customize the look of the site! Toggle Dark/Light mode using the switch in the header.",
    'text size' => "Trouble reading? Open the Settings ⚙️ (gear icon) and switch Text Size to 'Large'.",
    'settings' => "Click the gear icon ⚙️ in the top right to access Language settings and Text Size options.",
    'language' => "You can switch between English and Tagalog in the Settings menu (gear icon ⚙️).",
    
    // Navigation & Sections
    'home' => "The Home page features your user stats (if logged in), Featured Spots, and Top Rated destinations.",
    'destination' => "The Destinations page lists all the beautiful places in Laguna. You can filter them by name or check which ones are 'Open Now'.",
    'cultural' => "The Cultural section highlights Laguna's rich history, including heritage houses, churches, and museums.",
    'cuisine' => "Hungry? The Cuisine section lists the best local restaurants and delicacies like Buko Pie!",
    'events' => "The Events section showcases local festivals like the Anilag Festival and Pinya Festival. Check it for dates!",
    'maps' => "The Maps section shows a Google Map of Laguna and provides general directions for commuters and private cars.",
    'tips' => "The Tips section offers helpful advice like the best time to visit (Dec-May) and what to bring.",
    'about' => "The About section contains facts about Laguna's population, area, climate, and history.",
    'contact' => "Need help? Use the Contact Us button in the header or the Contact section at the bottom of the Home page. Messages go directly to the site admins.",
    
    // User Features
    'book' => "We don't support direct booking yet, but you can mark spots as 'Visited' ✅ or 'Favorites' ❤️ to track your trip!",
    'favorite' => "Click the heart icon ❤️ on any spot card to add it to your Favorites list.",
    'visit' => "Click the checkmark icon ✅ on a spot card to mark it as Visited.",
    'rate' => "To rate a spot, click on its card to open the details, then select a star rating (1-5) and write a comment.",
    'review' => "Share your experience! Click on a spot, give it some stars, and tell us what you think.",
    'search' => "You can search for specific spots using the search bar in the Destinations section.",
    'filter' => "You can filter spots by 'Open Now' or 'Closed' in the Destinations section.",
    
    // General Info (from About Section)
    'capital' => "The capital of Laguna is **Santa Cruz**.",
    'area' => "Laguna covers an area of approximately **1,917.85 km²**.",
    'population' => "Laguna has a population of around **3.4 Million** people.",
    'people' => "Laguna has a population of around **3.4 Million** people.",
    'how many people' => "Laguna has a population of around **3.4 Million** people.",
    'climate' => "Laguna has a tropical climate, with the dry season from November to May.",
    'industry' => "Laguna's main industries are Agriculture, Tourism, and Manufacturing.",
    'history' => "Laguna is the birthplace of **Jose Rizal**! It's named after Laguna de Bay and played a key role in the Philippine Revolution.",
    
    // Travel Tips (Static)
    'best time' => "The best time to visit Laguna is during the dry season, from **December to May**.",
    'clothes' => "Wear comfortable clothes and walking shoes, as there's a lot to explore!",
    'emergency' => "For emergencies, please consult official local hotlines. This site provides travel information only.",
    'directions' => "🚌 **How to get to Laguna**:\n\n" .
                    "**By Bus:**\n" .
                    "- **From Cubao/Buendia:** Take buses like **DLTB**, **JAC Liner**, **LLI**, or **HM Transport** bound for *Sta. Cruz* (for Pagsanjan/Cavinti/Lumban) or *Calamba/San Pablo*.\n" .
                    "- **Travel Time:** 2-4 hours depending on traffic.\n" .
                    "- **Fare:** Approx. ₱150 - ₱250.\n\n" .
                    "**By Private Car:**\n" .
                    "- Take **SLEX (South Luzon Expressway)**.\n" .
                    "- **Exits:**\n" .
                    "  • *Calamba Exit* (for hot springs/Rizal Shrine)\n" .
                    "  • *Sta. Rosa Exit* (for Enchanted Kingdom/Nuvali)\n" .
                    "  • *Sto. Tomas Exit* (for San Pablo/Quezon border)\n\n" .
                    "**Getting Around:**\n" .
                    "- Jeepneys and Tricycles are the main mode of transport within towns."
];

// Add Hardcoded Events from Website (Fallback)
$website_events = [
    'anilag' => "**Anilag Festival** (Mar-Apr): The 'Ani ng Laguna' festival showcases the province's bountiful harvest, culture, and arts.",
    'pinya' => "**Pinya Festival** (May 15): Held in Calauan, celebrating their sweet pineapples with street dancing and parades.",
    'turumba' => "**Turumba Festival** (Apr-May): A religious festival in Pakil honoring Our Lady of Sorrows.",
    'tsinelas' => "**Tsinelas Festival** (Sep): Celebrated in Liliw, showcasing their famous footwear industry.",
    'sampaguita' => "**Sampaguita Festival** (Feb): San Pedro City's celebration of the national flower.",
    'paskuhan' => "**Paskuhan sa Laguna** (Dec): Province-wide Christmas celebrations with giant lanterns."
];

foreach ($website_events as $k => $v) {
    if (!isset($kb_data[$k])) {
        $kb_data[$k] = $v;
        $spot_names[] = $k;
    }
}

foreach ($system_qa as $k => $v) {
    $kb_data[$k] = $v;
    $spot_names[] = $k; // Add keywords to dictionary
}

// Add Common Conversational Words to Dictionary for Spell Checking
$common_vocab = [
    'hi', 'hello', 'hey', 'greetings', 'good', 'morning', 'afternoon', 'evening',
    'who', 'what', 'where', 'when', 'why', 'how', 'is', 'are', 'you', 'your', 'name',
    'can', 'may', 'i', 'ask', 'help', 'guide', 'me',
    'thanks', 'thank', 'welcome', 'bye', 'goodbye',
    'summer', 'food', 'history', 'culture', 'nature', 'relax', 'swim', 'eat', 'best',
    'laguna', 'province', 'tourist', 'guide',
    'tell', 'about', // Added for "tell me about" corrections
    'top', 'spot', 'spots', 'rated', 'events', 'festival', 'festivals', 
    'transport', 'direction', 'directions', 'place', 'places', 'visit',
    'meaning', 'life', 'get', 'there', 'price', 'buko', 'pie', 'news', 'latest', 'update', 'updates' // Added missing words
];
$spot_names = array_merge($spot_names, $common_vocab);

// 2. Initialize Classes
$geminiKey = '';
if (defined('GEMINI_API_KEYS') && is_array(GEMINI_API_KEYS) && !empty(GEMINI_API_KEYS)) {
    // Pick a random key from the array
    $geminiKey = GEMINI_API_KEYS[array_rand(GEMINI_API_KEYS)];
} elseif (defined('GEMINI_API_KEY')) {
    $geminiKey = GEMINI_API_KEY;
}

$openaiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';

// Check if keys are configured
$is_ai_enabled = true;
if (empty($geminiKey) || stripos($geminiKey, 'YOUR_GEMINI_API_KEY') !== false) {
    $is_ai_enabled = false;
    // Don't exit here, we will try to answer from local KB
}
trace_log("AI Enabled Status: " . ($is_ai_enabled ? "TRUE" : "FALSE") . " Key: " . substr($geminiKey, 0, 5));

$norm = TextNormalizer::normalize($message);
$spell = new SpellAdapter($spot_names); // Use spot names & keywords as dictionary
$corrected = $spell->correctPhrase($norm);
trace_log("Corrected Query: $corrected");

// --- Enhanced Levenshtein Fallback for Spot Names ---
// If the corrected phrase didn't help, try to find the closest match in spot names directly
// This catches "llaguna" -> "laguna" even if "laguna" wasn't picked up by correctPhrase context
if (empty($reply)) {
    $best_lev_match = null;
    $best_lev_score = -1;
    $input_words = explode(' ', $norm);
    
    foreach ($spots_data as $spot) {
        $spot_name_lower = strtolower($spot['name']);
        
        // Check against full input
        $lev = levenshtein($norm, $spot_name_lower);
        $len = max(strlen($norm), strlen($spot_name_lower));
        $sim = (1 - ($lev / $len)) * 100;
        
        if ($sim > 80 && $sim > $best_lev_score) {
            $best_lev_score = $sim;
            $best_lev_match = $spot;
        }
        
        // Check against individual words (e.g. "llaguna" in "tell me about llaguna")
        foreach ($input_words as $word) {
            if (strlen($word) < 4) continue; // Skip short words
            $lev = levenshtein($word, $spot_name_lower);
            $len = max(strlen($word), strlen($spot_name_lower));
            $sim = (1 - ($lev / $len)) * 100;
             if ($sim > 80 && $sim > $best_lev_score) {
                $best_lev_score = $sim;
                $best_lev_match = $spot;
            }
        }
    }
    
    // Also check "Laguna" specifically since it's the main topic but not a "spot"
    $main_topic = "laguna";
    $lev = levenshtein($norm, $main_topic);
    $len = max(strlen($norm), strlen($main_topic));
    $sim = (1 - ($lev / $len)) * 100;
    if ($sim > 80) {
        $corrected = str_replace($norm, $main_topic, $norm); // Force correction to "laguna"
    } else {
        // Check words for "laguna" typo
        foreach ($input_words as $word) {
             $lev = levenshtein($word, $main_topic);
             $len = max(strlen($word), strlen($main_topic));
             $sim = (1 - ($lev / $len)) * 100;
             if ($sim > 80) {
                 $corrected = str_replace($word, $main_topic, $corrected);
             }
        }
    }
}
// ---------------------------------------------------

// Initialize Embedding Retriever
// Note: Real-time embedding for all KB items is slow. In production, cache these in DB.
$embeddingModel = null;
if ($is_ai_enabled) {
    // DISABLE Real-time embedding to fix "too long to answer" issue.
    // Iterating the entire KB with API calls is O(N) latency.
    // $embeddingModel = new EmbeddingRetriever($kb_data, $geminiKey);
    $embeddingModel = null; 
}

// Intent Mapping (Moved to global scope for access in multiple blocks)
$intents = [
    'summer' => ['resort', 'beach', 'pool', 'falls', 'lake', 'nature', 'swim'],
    'hot' => ['resort', 'pool', 'falls', 'cold'],
    'swim' => ['resort', 'pool', 'beach', 'falls'],
    'food' => ['cuisine', 'restaurant', 'cafe', 'delicacy', 'eat', 'dining'],
    'eat' => ['cuisine', 'restaurant', 'cafe', 'delicacy'],
    'history' => ['historical', 'shrine', 'church', 'monument', 'old', 'ancient'],
    'culture' => ['historical', 'shrine', 'museum', 'culture'],
    'nature' => ['park', 'garden', 'mountain', 'hill', 'lake', 'falls', 'nature'],
    'relax' => ['park', 'garden', 'resort', 'spa', 'relax'],
    'best' => ['popular', 'famous', 'top', 'best'],
    'recommend' => ['resort', 'restaurant', 'historical', 'park', 'falls'], // Generic mix
    'suggest' => ['resort', 'restaurant', 'historical', 'park', 'falls'],
    'spot' => ['resort', 'restaurant', 'historical', 'park', 'falls'],
    'place' => ['resort', 'restaurant', 'historical', 'park', 'falls']
];

// Use HybridRetriever with Semantic Capability
$retriever = new HybridRetriever($kb_data, $embeddingModel);
$best_matches = $retriever->findBest($corrected, 1);

$reply = "";
$confidence = 0.0;

// 3. Logic Chain

// A. Conversational & Meta-Check (Priority)
// Handle greetings and meta-questions locally to avoid expensive/irrelevant KB lookups.
$meta_reply = null;

// Quick actions
$q = strtolower(trim($corrected));
// Match variations including specific button texts
if (preg_match('/(top\s*(rated|tourist|best)?\s*spots?|what\s+are\s+the\s+top\s+tourist\s+spots)/i', $corrected)) {
    trace_log("Match: Top Spots Regex");
    $top_query = "SELECT s.name, s.type, s.description, s.location, AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
                  FROM spots s
                  LEFT JOIN reviews r ON s.id = r.spot_id
                  GROUP BY s.id
                  HAVING review_count > 0 AND avg_rating > 0
                  ORDER BY avg_rating DESC, review_count DESC, s.name ASC
                  LIMIT 5";
    $top_result = $conn->query($top_query);
    if ($top_result && $top_result->num_rows > 0) {
        $rec_text = "";
        while($row = $top_result->fetch_assoc()) {
            $stars = $row['avg_rating'] ? round($row['avg_rating'], 1) . "⭐" : "New (No ratings yet)";
            $reviews = isset($row['review_count']) ? " (" . intval($row['review_count']) . " reviews)" : "";
            $rec_text .= "- **{$row['name']}** ({$stars}{$reviews}): {$row['description']}\n";
        }
        $meta_reply = "Top rated spots in Laguna based on user reviews:\n\n" . $rec_text;
        $confidence = 0.95;
    } else {
        // Fallback: No rated spots yet. Show popular spots by visitor count.
        $pop_query = "SELECT s.name, s.type, s.description, s.location, COUNT(DISTINCT ua.id) as visitor_count
                      FROM spots s
                      LEFT JOIN user_activity ua ON s.id = ua.spot_id
                      GROUP BY s.id
                      ORDER BY visitor_count DESC, s.name ASC
                      LIMIT 5";
        $pop_result = $conn->query($pop_query);
        if ($pop_result && $pop_result->num_rows > 0) {
            $rec_text = "";
            while($row = $pop_result->fetch_assoc()) {
                $rec_text .= "- **{$row['name']}** ({$row['type']}): {$row['description']}\n";
            }
            $meta_reply = "There are no rated spots yet, but here are some popular destinations based on visits:\n\n" . $rec_text;
            $confidence = 0.9;
        } else {
            $meta_reply = "I couldn't find any top-rated spots or popular destinations at the moment. Please check the Destinations page for more info!";
            $confidence = 0.9;
        }
    }
} elseif (preg_match('/(best\s*food|top\s*food|best\s*cuisine|where\s+to\s+eat|recommend.*food|food\s*spots|places\s*to\s*eat)/i', $corrected)) {
    // Set Context
    setConversationContext('last_topic', 'food');

    $food_query = "SELECT s.name, s.type, s.description, s.location, AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
                   FROM spots s
                   LEFT JOIN reviews r ON s.id = r.spot_id
                   WHERE (s.type LIKE '%cuisine%' OR s.type LIKE '%restaurant%' OR s.category LIKE '%cuisine%' OR s.description LIKE '%cuisine%' OR s.description LIKE '%food%')
                   GROUP BY s.id
                   HAVING review_count > 0 AND avg_rating > 0
                   ORDER BY avg_rating DESC, review_count DESC, s.name ASC
                   LIMIT 5";
    $food_result = $conn->query($food_query);
    if ($food_result && $food_result->num_rows > 0) {
        $rec_text = "";
        while($row = $food_result->fetch_assoc()) {
            $stars = $row['avg_rating'] ? round($row['avg_rating'], 1) . "⭐" : "New (No ratings yet)";
            $reviews = isset($row['review_count']) ? " (" . intval($row['review_count']) . " reviews)" : "";
            $rec_text .= "- **{$row['name']}** ({$stars}{$reviews}): {$row['description']}\n";
        }
        $meta_reply = "Top rated cuisine and food spots:\n\n" . $rec_text;
        $confidence = 0.95;
    } else {
        // Fallback for food
        $pop_food_query = "SELECT s.name, s.type, s.description, s.location, COUNT(DISTINCT ua.id) as visitor_count
                           FROM spots s
                           LEFT JOIN user_activity ua ON s.id = ua.spot_id
                           WHERE (s.type LIKE '%cuisine%' OR s.type LIKE '%restaurant%' OR s.category LIKE '%cuisine%' OR s.description LIKE '%cuisine%' OR s.description LIKE '%food%')
                           GROUP BY s.id
                           ORDER BY visitor_count DESC, s.name ASC
                           LIMIT 5";
        $pop_food_result = $conn->query($pop_food_query);
        if ($pop_food_result && $pop_food_result->num_rows > 0) {
            $rec_text = "";
            while($row = $pop_food_result->fetch_assoc()) {
                $rec_text .= "- **{$row['name']}** ({$row['type']}): {$row['description']}\n";
            }
            $meta_reply = "Here are some popular food spots in Laguna:\n\n" . $rec_text;
            $confidence = 0.9;
        } else {
             $meta_reply = "I couldn't find any specific food spots right now. Try asking for 'Buko Pie spots' or 'local restaurants', or browse the Cuisine section!";
             $confidence = 0.9;
        }
    }
} elseif (preg_match('/(events|festivals|any\s+upcoming\s+festivals|next\s*event|upcoming\s*event|celebration|schedule)/i', $corrected)) {
    // Set Context
    setConversationContext('last_topic', 'events');

    $event_spots = array_filter($spots_data, function($s) {
        return stripos($s['type'], 'cultural') !== false || stripos($s['description'], 'festival') !== false || stripos($s['name'], 'festival') !== false;
    });
    if (!empty($event_spots)) {
        $rec_text = "";
        foreach (array_slice($event_spots, 0, 3) as $spot) {
            $rec_text .= "- **{$spot['name']}** ({$spot['location']}): {$spot['description']}\n";
        }
        if (isset($kb_data['events'])) {
            $rec_text .= "\n" . $kb_data['events'];
        }
        $meta_reply = "Events and cultural highlights:\n\n" . $rec_text;
        $confidence = 0.9;
    } elseif (isset($kb_data['events'])) {
        $meta_reply = $kb_data['events'];
        $confidence = 0.9;
    }
} elseif (preg_match('/(transport|how\s+to\s+get\s+to\s+laguna)/i', $corrected)) {
    if (isset($system_qa['directions'])) {
        $meta_reply = $system_qa['directions'];
        $confidence = 0.95;
        // Set Context for Follow-ups
        setConversationContext('last_topic', 'directions');
    }
}

// Check for generic recommendation requests FIRST
    // If specific event/festival query
    if (false) { // Redundant event check removed
        // Merged into main event intent above
    }

    if ($meta_reply) {
        // handled above
    } elseif (preg_match('/(recommend|suggestion|places to visit|tourist spot|where to go)/i', $corrected)) {
    // If no specific keyword found later, we will force a "Top Picks" result
    // But for now, let's just flag it. The keyword search below will handle specific spots.
    // If the keyword search fails, we want to catch this intent.
} elseif (preg_match('/^(hi|hello|hey|greetings|good\s*(morning|afternoon|evening))/i', $corrected)) {
    $greetings = [
        "Hello there! 👋 I'm **Doquerainee**, your friendly guide! Ready to explore Laguna? 🌴",
        "Hi! 🌟 Welcome to Laguna! I'm Doquerainee, and I can't wait to help you find the best spots! Where should we start? 🗺️",
        "Hey! 👋 Looking for an adventure in Laguna? You've come to the right place! I'm Doquerainee, your personal guide! 🚗"
    ];
    $meta_reply = $greetings[array_rand($greetings)];
    $confidence = 1.0;
} elseif (preg_match('/^(who|what)\s+(are|is)\s+(you|your name)$/i', $corrected)) { 
    $identities = [
        "I'm **Doquerainee**! 💁‍♀️ I'm here to help you find the best spots, food, and fun in Laguna! Ask me anything! ✨",
        "They call me **Doquerainee**! 🌸 I'm a digital guide who loves everything about this province. Let's plan your trip! 📅",
        "I'm **Doquerainee**, your AI travel buddy! 🤖💖 I know all the hidden gems and popular spots here."
    ];
    $meta_reply = $identities[array_rand($identities)];
    $confidence = 1.0;
} elseif (preg_match('/^(can|may)\s+i\s+ask/i', $corrected)) {
    $meta_reply = "Of course! 🤩 Ask away! You can ask about waterfalls, historical sites, or where to get the best Buko Pie! 🥧";
    $confidence = 1.0;
} elseif (preg_match('/^(help|guide\s+me)/i', $corrected)) {
    $meta_reply = "I'd love to help! 💖 You can ask me things like 'Where is Rizal Shrine?' or 'Recommend a swimming spot'.";
    $confidence = 1.0;
} elseif (preg_match('/^(thank|thanks)/i', $corrected)) {
    $thanks_replies = [
        "You're so welcome! 🤗 Enjoy your trip to Laguna! 🚗💨",
        "No problem at all! Happy to help! ✨ Let me know if you need anything else.",
        "Anytime! 😉 Have a fantastic time exploring!"
    ];
    $meta_reply = $thanks_replies[array_rand($thanks_replies)];
    $confidence = 1.0;
} elseif (preg_match('/(how are you|how r u)/i', $corrected)) {
    $status_replies = [
        "I'm doing great, thanks for asking! 💃 Just dreaming about Laguna's hot springs! How can I help you today?",
        "I'm fantastic! 🌟 Ready to guide you to the best places in Laguna! What's on your mind?",
        "Feeling super! 🚀 Just updated my map of Laguna's best food spots. Want a recommendation?"
    ];
    $meta_reply = $status_replies[array_rand($status_replies)];
    $confidence = 1.0;
} elseif (preg_match('/(what.*can.*(do|offer)|features|capabilities|function|what.*is.*this.*system|what.*is.*this.*website|what.*does.*this.*website|purpose.*of.*this.*website|about.*this.*website|what.*this.*website.*for)/i', $corrected)) {
    $meta_reply = "This website is the **Laguna Tourist Guide System**! 🌴\n\nIt is designed to help you:\n" .
        "✅ **Discover Spots**: Find the best tourist destinations in Laguna.\n" .
        "✅ **Check Events**: See upcoming festivals and celebrations.\n" .
        "✅ **Get Directions**: View maps and travel guides.\n" .
        "✅ **Local Cuisine**: Explore famous food spots.\n" .
        "✅ **User Dashboard**: Track your visits and favorites (if logged in)!";
    $confidence = 1.0;
} elseif (preg_match('/(how\s+to\s+use|how\s+does\s+this\s+work)/i', $corrected)) {
    $meta_reply = "It's easy! 🌟 You can ask me for **recommendations** (e.g., 'Top rated spots'), **directions**, or specific **places** like 'Nuvali Park'. I'm here to be your virtual tour guide! 🗺️";
    $confidence = 1.0;
} elseif (preg_match('/(meaning\s+of\s+life)/i', $corrected)) {
    $meta_reply = "The answer is **42**. 🌌 But if you're looking for the meaning of a great vacation, it's definitely in **Laguna**! 🌴";
    $confidence = 1.0;
} elseif (preg_match('/(price|cost|how\s+much|entrance\s+fee|rates)/i', $corrected)) {
    $last_spot = getConversationContext('last_spot_name');
    if ($last_spot) {
        $meta_reply = "The entrance fee for **$last_spot** varies or may be free. \n" .
                      "💡 Please check their official page or visit the spot for the most accurate rates!\n\n" .
                      "(Generally, resorts in Laguna range from ₱150 - ₱500, and museums are often donation-based.)";
        $confidence = 0.95;
    } else {
        $meta_reply = "Prices vary by establishment:\n" .
                      "🥧 **Buko Pie**: Usually ₱250 - ₱350 per box.\n" .
                      "🏊 **Resorts**: Entrance fees range from ₱150 - ₱500+.\n" .
                      "💡 For specific rates, please check the details of the spot you're interested in!";
        $confidence = 1.0;
    }
} elseif (preg_match('/(news|update|latest)/i', $corrected)) {
    $meta_reply = "For the latest news and updates, please check the **Events** section! 📅\n" .
                  "We constantly update our listings with upcoming festivals and announcements.";
    $confidence = 1.0;
} elseif (preg_match('/(weather|rain|sunny)/i', $corrected)) {
    $meta_reply = "I can't check the real-time weather yet, but Laguna is generally sunny with occasional rain showers! ☀️☔\n" .
                  "Don't forget to bring an umbrella just in case!";
    $confidence = 1.0;
} elseif (preg_match('/(contact|admin|support|email|phone|call)/i', $corrected)) {
    $meta_reply = "For assistance related to the Laguna Guide System:\n\n" .
                  "📝 Use the **Contact Us** button in the header or the **Contact** section at the bottom of the Home page.\n" .
                  "� Messages submitted there go directly to the site administrators.\n" .
                  "ℹ️ Phone numbers and emails are not provided within the chat to avoid incorrect information.";
    $confidence = 1.0;
} elseif (preg_match('/(logout|sign\s*out|log\s*off)/i', $corrected)) {
    $meta_reply = "To **Logout**:\n" .
                  "1. Click your **Profile Picture/Avatar** in the top right corner.\n" .
                  "2. Select **'Logout'** from the dropdown menu.";
    $confidence = 1.0;
} elseif (preg_match('/(login|sign\s*in|sign\s*up|register|create\s*account)/i', $corrected)) {
    $meta_reply = "To **Login** or **Sign Up**:\n" .
                  "1. Click the **'Login'** button in the top right corner.\n" .
                  "2. If you don't have an account, click **'Login'** then select **'Sign up'**.";
    $confidence = 1.0;
} elseif (preg_match('/(rate|rating|star|stars|favorite|heart|like)/i', $corrected)) {
    $meta_reply = "**How to Rate & Favorite:**\n" .
                  "⭐ **Rate**: Click on any spot card -> Scroll down in the popup -> Click the stars (1-5) -> Submit.\n" .
                  "❤️ **Favorite**: Click the heart icon on any spot card to save it to your Profile.";
    $confidence = 1.0;
}

if ($meta_reply) {
        $reply = $meta_reply;
    } else {
        trace_log("Entering Main Logic");
        if (!empty($best_matches) && $best_matches[0]['score'] >= 0.60) {
            trace_log("Match: KB (Score: {$best_matches[0]['score']})");
            $reply = $best_matches[0]['answer'];
            $confidence = $best_matches[0]['score'];
        setConversationContext('last_topic', 'kb_match');
        setConversationContext('last_recommendation', $reply);
        setConversationContext('confidence', $confidence);

        // Attempt to set context if the match corresponds to a spot
        $match_key = $best_matches[0]['question'];
        foreach ($spots_data as $s) {
             if (strtolower($s['name']) === $match_key) {
                 setConversationContext('last_spot_name', $s['name']);
                 break;
             }
        }
    } elseif (preg_match('/(more\s+info|tell\s+me\s+about|details\s+about|details\s+for|what\s+is|describe)\s+(.+)/i', $corrected, $matches)) {
            trace_log("Match: Info Request");
            // Specific Information Request Pattern
        // $matches[2] contains the potential spot name
        $target_spot = trim($matches[2]);
        // Remove common stopwords from the target name to improve matching
        $target_spot_clean = str_replace(['the ', 'a ', 'an '], '', strtolower($target_spot));
        
        // SPECIAL CASE: Handle "Laguna" generic queries
        if ($target_spot_clean === 'laguna' || $target_spot_clean === 'laguna province') {
             $reply = "The **Province of Laguna** is known as the birthplace of Jose Rizal and is famous for its waterfalls, hot springs, and historical sites! 🏞️\n\n" . 
                      "You can ask me about:\n" .
                      "- **History** (e.g. 'History of Laguna')\n" .
                      "- **Cuisine** (e.g. 'Best food in Laguna')\n" .
                      "- **Resorts** (e.g. 'Where to swim?')\n" .
                      "- **Festivals** (e.g. 'Events in Laguna')";
             $confidence = 1.0;
             setConversationContext('last_topic', 'laguna_info');
        } else {
            $found_info = null;
            $max_sim = 0;
            
            foreach ($spots_data as $spot) {
            $spot_name_lower = strtolower($spot['name']);
            // Check for exact substring match or high similarity
            if (stripos($spot_name_lower, $target_spot_clean) !== false || 
                stripos($target_spot_clean, $spot_name_lower) !== false) {
                
                $sim = 100; // Perfect substring match
                if ($sim > $max_sim) {
                    $max_sim = $sim;
                    $found_info = $spot;
                }
            } else {
                // Levenshtein check for typos
                $lev = levenshtein($target_spot_clean, $spot_name_lower);
                $len = max(strlen($target_spot_clean), strlen($spot_name_lower));
                $sim = (1 - ($lev / $len)) * 100;
                
                if ($sim > 80 && $sim > $max_sim) {
                    $max_sim = $sim;
                    $found_info = $spot;
                }
            }
        }
        
        if ($found_info) {
             $reply = "**{$found_info['name']}**\n" .
                      "📍 Location: {$found_info['location']}\n" .
                      "🏷️ Type: {$found_info['type']}\n" .
                      "📝 Description: {$found_info['description']}\n";
             
             // Check if we have additional details in KB
             foreach ($kb_data as $k => $v) {
                 if (stripos($k, $found_info['name']) !== false) {
                     $reply .= "\n💡 " . $v;
                     break;
                 }
             }
             
             $confidence = 0.95;
             setConversationContext('last_topic', 'spot_details');
             setConversationContext('last_spot_name', $found_info['name']);
        }
        }
    } elseif (preg_match('/(near|nearby|closest|near me|around here)/i', $corrected)) {
            trace_log("Match: Nearby");
            // Since we don't have geolocation, we show top spots as fallback
        $top_query = "SELECT s.name, s.type, s.description, s.category, AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
                      FROM spots s
                      LEFT JOIN reviews r ON s.id = r.spot_id
                      GROUP BY s.id
                      HAVING review_count > 0 AND avg_rating > 0
                      ORDER BY avg_rating DESC, review_count DESC, s.name ASC
                      LIMIT 5";
        $top_result = $conn->query($top_query);
        if ($top_result && $top_result->num_rows > 0) {
            $rec_text = "";
            while($row = $top_result->fetch_assoc()) {
                $stars = $row['avg_rating'] ? round($row['avg_rating'], 1) . "⭐" : "New (No ratings yet)";
                $reviews = isset($row['review_count']) ? " (" . intval($row['review_count']) . " reviews)" : "";
                $rec_text .= "- **{$row['name']}** ({$stars}{$reviews}): {$row['description']}\n";
            }
            $reply = "I currently don't have access to your GPS location, but here are some of the most popular spots in Laguna that visitors love! 🗺️\n\n" . $rec_text;
            $confidence = 0.9;
            setConversationContext('last_recommendation', $rec_text);
        } else {
             $reply = "I'm not sure where you are, but you can browse the **Destinations** page to see spots near you!";
             $confidence = 0.9;
        }
    } elseif (preg_match('/(top rated|best rated|highest rated|most popular|best spot|star|stars|rating|ratings|recommendation|recommendations|\\btop\\b|\\bbest\\b)/i', $corrected)) {
            trace_log("Match: Top Rated");
            // Detect if specific categories are mentioned alongside "best" (e.g., "best food", "top resorts")
        $filter_sql = "";
        $detected_intent = "";
        
        foreach ($intents as $intent => $keywords) {
            if ($intent === 'best' || $intent === 'recommend' || $intent === 'suggest' || $intent === 'spot' || $intent === 'place') continue; // Skip generic
            
            if (stripos($corrected, $intent) !== false) {
                $detected_intent = $intent;
                $kw_conditions = [];
                foreach ($keywords as $kw) {
                    $kw_conditions[] = "type LIKE '%$kw%'";
                    $kw_conditions[] = "description LIKE '%$kw%'";
                }
                if (!empty($kw_conditions)) {
                    $filter_sql = " AND (" . implode(' OR ', $kw_conditions) . ")";
                }
                break; // Use the first specific intent found
            }
        }

        $top_query = "SELECT s.name, s.type, s.description, s.category, AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
                      FROM spots s
                      LEFT JOIN reviews r ON s.id = r.spot_id
                      WHERE 1=1 $filter_sql
                      GROUP BY s.id
                      HAVING review_count > 0 AND avg_rating > 0
                      ORDER BY avg_rating DESC, review_count DESC, s.name ASC
                      LIMIT 5";
        $top_result = $conn->query($top_query);
        if ($top_result && $top_result->num_rows > 0) {
            $rec_text = "";
            while($row = $top_result->fetch_assoc()) {
                $stars = $row['avg_rating'] ? round($row['avg_rating'], 1) . "⭐" : "New (No ratings yet)";
                $reviews = isset($row['review_count']) ? " (" . intval($row['review_count']) . " reviews)" : "";
                $rec_text .= "- **{$row['name']}** ({$stars}{$reviews}): {$row['description']}\n";
            }
            
            $intro = "Here are the top-rated spots in Laguna based on user reviews: 🌟";
            if ($detected_intent) {
                $intro = "Here are the top-rated **" . ucfirst($detected_intent) . "** spots in Laguna based on user reviews: 🌟";
            }
            
            $reply = $intro . "\n\n" . $rec_text;
            $confidence = 0.9;
            setConversationContext('last_recommendation', $rec_text);
        } else {
             // Fallback if filtering yielded no results (e.g. "best skating rinks" -> none found)
             // We don't set reply here, allowing it to fall through to AI/Keywords
        }
    } else {
        // Fallback Strategy
        
        trace_log("Entering Fallback Strategy. AI Enabled: " . ($is_ai_enabled ? "YES" : "NO"));

        // 1. Try AI First if enabled (it has the smartest context)
        if ($is_ai_enabled) {
            trace_log("AI Enabled");
            // Keys initialized above
        
            // Build Context
                $context_str = "Known Tourist Spots (Database):\n";
                foreach ($spots_data as $spot) {
                    $context_str .= "- {$spot['name']} ({$spot['type']}): {$spot['description']} Location: {$spot['location']}.\n";
                }
                
                // Add Features to Context
                if (isset($feat_result) && $feat_result->num_rows > 0) {
                     $context_str .= "\nSystem Features & Highlights:\n";
                     foreach ($kb_data as $k => $v) {
                         if (strpos($v, '**') === 0) { 
                             $context_str .= "- $v\n";
                         }
                     }
                }

                $context_str .= "\nSystem Info:\n" . implode("\n", $system_qa);

                // D. Fetch External Search Results (Blended)
                $external_context = "";
                $google_key = defined('GOOGLE_CSE_KEY') ? GOOGLE_CSE_KEY : '';
                $google_cx = defined('GOOGLE_CSE_CX') ? GOOGLE_CSE_CX : '';
                
                $search_results = "";
                
                $is_system_query = preg_match('/(contact|admin|support|email|phone|call|login|logout|sign up|register|password|account|settings|dark mode|rate|star|favorite)/i', $corrected);
                
                // ENABLED Parallel Web Search
                if (!$is_system_query && needsWebSearch($corrected)) {
                     trace_log("Performing Parallel Web Search...");
                     $search_results = parallel_search_context($corrected, $google_key, $google_cx);
                     trace_log("Search Results: " . substr($search_results, 0, 100));
                }
                
                if ($search_results) {
                    $external_context = "\n\nWEB SEARCH RESULTS (Use these to answer if relevant):\n" . $search_results;
                    $context_str .= $external_context;
                }
                
                if (strlen($context_str) > 4000) {
                    $context_str = substr($context_str, 0, 4000) . "... [Truncated]";
                }
                
                trace_log("Calling AI with Context (Length: " . strlen($context_str) . ")");

                // Build enhanced context with conversation history
                $enhancedContext = buildConversationContext($systemPrompt, $context_str);
                
                $ai = new AIResponder($geminiKey, $openaiKey);
            $reply = $ai->ask($corrected, $enhancedContext);
            trace_log("AI Reply: " . substr($reply, 0, 50));
            $confidence = 0.85; 
                
                if ($reply) {
                    // Chat history update moved to end of script
                }
            }
        }

        // 2. If AI failed or disabled, check for spot name matching
    if (empty($reply)) {
        // Try Python AI Helper First (as requested by user for "smarter" logic)
        $python_reply = call_python_ai_helper($corrected);
        if ($python_reply && stripos($python_reply, 'sorry') === false) {
             $reply = $python_reply;
             $confidence = 0.85;
             trace_log("Python Helper Reply: " . substr($reply, 0, 50));
        }
    }

    if (empty($reply)) {
        $fallback_spot = null;
        foreach ($spots_data as $spot) {
                if (stripos($corrected, $spot['name']) !== false) {
                    $fallback_spot = $spot;
                    break;
                }
            }
            
            if ($fallback_spot) {
                 $reply = "**{$fallback_spot['name']}**\n" .
                          "📍 Location: {$fallback_spot['location']}\n" .
                          "🏷️ Type: {$fallback_spot['type']}\n" .
                          "📝 Description: {$fallback_spot['description']}\n";
                 $confidence = 0.8;
            }
        }

        
        setConversationContext('last_recommendation', $reply);
        
        if (empty($reply)) {
            $memory_hit = false;
        
        // Check for "news" or "latest" queries in Offline Mode
        if (preg_match('/(news|latest|update|current|today)/i', $corrected)) {
            $reply = "I am currently in **Offline Mode**, so I cannot browse the live internet for the latest news.\n\nHowever, you can check our **Events** page or the official Laguna Tourism website for updates.";
            $confidence = 1.0;
            $memory_hit = true;
        }

        // SYSTEM/GUIDE INTERCEPTION (Prevents Hallucinations)
        // If the user asks about system features (contact, login, logout, etc.), strictly use the guide.
        if (preg_match('/(contact|admin|support|email|phone|call|login|logout|sign up|register|password|account|settings|dark mode|rate|star|favorite)/i', $corrected)) {
             // Force usage of system prompt instructions and disable external search for this turn if possible
             // We do this by appending a strong instruction to the prompt context for this specific turn.
             $enhancedSystemPrompt .= "\n\nCRITICAL INSTRUCTION: The user is asking about a SYSTEM FEATURE. You MUST use the 'SYSTEM USAGE INSTRUCTIONS' provided above. DO NOT search the internet. DO NOT invent email addresses or phone numbers. If the info is not in the guide, say you don't know.";
        }
        
        // 0. Check Memory for Follow-ups
        // Triggers: "again", "repeat", "previous", "what was that", "earlier"
        if (preg_match('/(again|repeat|previous|what\s+was|remind|earlier)/i', $corrected)) {
            $last_rec = getConversationContext('last_recommendation');
            if ($last_rec) {
                $reply = "Here is what I suggested earlier:\n\n" . $last_rec;
                $confidence = 0.9;
                $memory_hit = true;
            }
        }

        // 0.5 Check for Affirmation/Confirmation
        // Triggers: "yes", "sure", "okay", "please", "yeah"
        // Prevents searching the web for "yes"
        if (!$memory_hit && preg_match('/^(yes|sure|okay|ok|yup|please|yeah|of course|go ahead)/i', $corrected)) {
             $last_rec = getConversationContext('last_recommendation');
             if ($last_rec) {
                 $reply = "Great! Which specific spot from the list would you like to know more about? 🧐\n\nPlease type the name (e.g., 'Rizal Shrine') so I can give you the details! 👇";
                 $confidence = 0.9;
                 $memory_hit = true;
             } else {
                 $reply = "I'm ready to help! What would you like to know about Laguna? 🌴";
                 $confidence = 0.9;
                 $memory_hit = true;
             }
        }

        // 0.6 Check for Negation
        // Triggers: "no", "nope", "nah"
        if (!$memory_hit && preg_match('/^(no|nope|nah|not now|later|cancel)/i', $corrected)) {
             $reply = "No problem! Let me know if you need any other recommendations. Enjoy Laguna! 🚗";
             $confidence = 0.9;
             $memory_hit = true;
        }



        // 0.7 Check for Contextual Directions (Fix for "I'm from X")
        if (!$memory_hit) {
            $last_topic = getConversationContext('last_topic');
            
            // If the user was talking about directions, and now mentions a place (e.g. "I'm from Visayas")
            // We should catch it before it falls to keyword search.
            if ($last_topic === 'directions' && preg_match('/(from|in|at)\s+([a-zA-Z\s]+)/i', $corrected, $matches)) {
                $origin = trim($matches[2]);
                // Construct a specific prompt for the AI
                $reply = "To get to Laguna from **$origin**, you usually have a few options depending on where exactly you are coming from. \n\n" . 
                         "Typically, you would need to travel to Manila first (by plane or boat if from Visayas/Mindanao), and then take a bus to Laguna (Buendia/Cubao terminals).";
                $confidence = 0.9;
                $memory_hit = true;
            }
        }

        if (!$memory_hit) {
            
            if (preg_match('/(top rated|best rated|highest rated|most popular|best spot|star|stars|rating|ratings|recommendation|recommendations|\\btop\\b|\\bbest\\b)/i', $corrected)) {
                $top_query = "SELECT s.name, s.type, s.description, s.category, AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
                              FROM spots s
                              LEFT JOIN reviews r ON s.id = r.spot_id
                              GROUP BY s.id
                              HAVING review_count > 0 AND avg_rating > 0
                              ORDER BY avg_rating DESC, review_count DESC, s.name ASC
                              LIMIT 5";
                $top_result = $conn->query($top_query);
                
                if ($top_result && $top_result->num_rows > 0) {
                    $rec_text = "";
                    while($row = $top_result->fetch_assoc()) {
                        $stars = $row['avg_rating'] ? round($row['avg_rating'], 1) . "⭐" : "New (No ratings yet)";
                        $reviews = isset($row['review_count']) ? " (" . intval($row['review_count']) . " reviews)" : "";
                        $rec_text .= "- **{$row['name']}** ({$stars}{$reviews}): {$row['description']}\n";
                    }
                    $reply = "Here are the top-rated spots in Laguna based on user reviews: 🌟\n\n" . $rec_text;
                    $confidence = 0.9;
                    $memory_hit = true;
                    setConversationContext('last_recommendation', $rec_text);
                } else {
                     // Fallback: No rated spots yet. Show popular spots by visitor count.
                     $pop_query = "SELECT s.name, s.type, s.description, s.location, COUNT(DISTINCT ua.id) as visitor_count
                                   FROM spots s
                                   LEFT JOIN user_activity ua ON s.id = ua.spot_id
                                   GROUP BY s.id
                                   ORDER BY visitor_count DESC, s.name ASC
                                   LIMIT 5";
                     $pop_result = $conn->query($pop_query);
                     if ($pop_result && $pop_result->num_rows > 0) {
                         $rec_text = "";
                         while($row = $pop_result->fetch_assoc()) {
                             $rec_text .= "- **{$row['name']}** ({$row['type']}): {$row['description']}\n";
                         }
                         $reply = "There are no rated spots yet, but here are some popular destinations based on visits: 🌟\n\n" . $rec_text;
                         $confidence = 0.9;
                         $memory_hit = true;
                         setConversationContext('last_recommendation', $rec_text);
                     }
                }
            }
            
            // 0.8 Check for Weather-Based Recommendations
            if (!$memory_hit) {
                $weather_context = '';
                $weather_type = '';
                
                if (preg_match('/(rain|storm|wet|pour)/i', $corrected)) {
                    $weather_context = "Since it's raining, I recommend indoor spots like museums, shrines, or cafes! ☔";
                    $weather_type = "indoor";
                } elseif (preg_match('/(hot|sunny|warm|summer|dry)/i', $corrected)) {
                    $weather_context = "Since it's hot/sunny, it's perfect for swimming or nature trips! ☀️";
                    $weather_type = "outdoor";
                }
                
                if ($weather_type) {
                    $weather_query = "";
                    if ($weather_type == 'indoor') {
                        $weather_query = "SELECT * FROM spots WHERE type IN ('Historical', 'Museum', 'Cafe', 'Restaurant') OR description LIKE '%indoor%' LIMIT 3";
                    } else {
                        $weather_query = "SELECT * FROM spots WHERE type IN ('Resort', 'Falls', 'Nature', 'Park') OR description LIKE '%pool%' LIMIT 3";
                    }
                    
                    $w_result = $conn->query($weather_query);
                    if ($w_result && $w_result->num_rows > 0) {
                        $rec_text = "";
                        while($row = $w_result->fetch_assoc()) {
                            $rec_text .= "- **{$row['name']}** ({$row['type']}): {$row['description']}\n";
                        }
                        $reply = $weather_context . "\n\n" . $rec_text;
                        $confidence = 0.9;
                        $memory_hit = true;
                        setConversationContext('last_recommendation', $rec_text);
                    }
                }
            }

            // 1. Keyword Extraction
            $msg_lower = strtolower($corrected); // Use corrected text
            $found_spots = [];
            
            // Intent Mapping
            $intents = [
                'summer' => ['resort', 'beach', 'pool', 'falls', 'lake', 'nature', 'swim'],
                'hot' => ['resort', 'pool', 'falls', 'cold'],
                'swim' => ['resort', 'pool', 'beach', 'falls'],
                'food' => ['cuisine', 'restaurant', 'cafe', 'delicacy', 'eat', 'dining'],
                'eat' => ['cuisine', 'restaurant', 'cafe', 'delicacy'],
                'history' => ['historical', 'shrine', 'church', 'monument', 'old', 'ancient'],
                'culture' => ['historical', 'shrine', 'museum', 'culture'],
                'nature' => ['park', 'garden', 'mountain', 'hill', 'lake', 'falls', 'nature'],
                'relax' => ['park', 'garden', 'resort', 'spa', 'relax'],
                'best' => ['popular', 'famous', 'top', 'best'],
                'recommend' => ['resort', 'restaurant', 'historical', 'park', 'falls'], // Generic mix
                'suggest' => ['resort', 'restaurant', 'historical', 'park', 'falls'],
                'spot' => ['resort', 'restaurant', 'historical', 'park', 'falls'],
                'place' => ['resort', 'restaurant', 'historical', 'park', 'falls']
            ];
            
            $search_terms = [];
            
            // Check for intents in user message
        foreach ($intents as $intent => $keywords) {
            if (strpos($msg_lower, $intent) !== false) {
                $search_terms = array_merge($search_terms, $keywords);
            }
        }

        // IMPROVED: Fallback to local KB lookup if no specific intent found
        // This is crucial for "Offline Mode" when specific patterns missed but keywords exist
        if (empty($search_terms)) {
             // Try to find ANY match in KB keys
             foreach ($kb_data as $k => $v) {
                 if (stripos($msg_lower, $k) !== false) {
                     $reply = $v;
                     $confidence = 0.85;
                     $memory_hit = true;
                     break;
                 }
             }
        }
        
        // If no specific intent found, use the message words themselves (excluding stopwords)
        if (empty($search_terms) && !$memory_hit) {
            $words = explode(' ', $msg_lower);
                $stopwords = ['the', 'is', 'at', 'which', 'on', 'in', 'a', 'an', 'and', 'or', 'for', 'of', 'to', 'can', 'i', 'you', 'what', 'where', 'me', 'my'];
                foreach ($words as $w) {
                    if (!in_array($w, $stopwords) && strlen($w) > 3) {
                        $search_terms[] = $w;
                    }
                }
            }

            // Force at least one search term if the user is asking for recommendations but gave no keywords
            if (empty($search_terms) && preg_match('/(recommend|suggestion|places|spots)/i', $msg_lower)) {
                 $search_terms = ['resort', 'restaurant', 'historical'];
            }
            
            // Search in Spots Data
            foreach ($spots_data as $spot) {
                $score = 0;
                $spot_text = strtolower($spot['name'] . ' ' . $spot['type'] . ' ' . $spot['description'] . ' ' . $spot['location']);
                
                foreach ($search_terms as $term) {
                    if (strpos($spot_text, $term) !== false) {
                        $score++;
                    }
                }
                
                if ($score > 0) {
                    $found_spots[] = ['spot' => $spot, 'score' => $score];
                }
            }
            
            // Sort by relevance (score)
            usort($found_spots, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            
            // Generate Reply
            if (!empty($found_spots)) {
                $top_picks = array_slice($found_spots, 0, 3);
                $rec_text = "";
                foreach ($top_picks as $item) {
                    $s = $item['spot'];
                    $rec_text .= "- **{$s['name']}** ({$s['type']}): {$s['description']}\n";
                }
                
                $reply = "Since you're asking about that, here are some recommendations:\n" . $rec_text;
                $reply .= "\nWould you like to know more about any of these?";
                $confidence = 0.7; // Moderate confidence for recommendations
                
                // Save to memory
                setConversationContext('last_recommendation', $rec_text);
            } else {
                 $reply = "I can answer questions related to the **Laguna Guide System** only.\n\nTry asking about specific tourist spots, cultural sites, local cuisine, events, maps, tips, or how to use the website (login, favorites, ratings). You can also browse the **Destinations** page or use the search bar.";
                 $confidence = 0.9;
            }
        }
    }
}

if (empty($reply)) {
    trace_log("Reply was empty at end. Using safety fallback.");
    $reply = "I'm having a bit of trouble connecting to my brain right now. 🧠\n\nCould you try asking that in a slightly different way? Or check out the **Top Spots** on our homepage! 🏠";
    $confidence = 0.0;
}

// --- Universal History Update ---
// Save the conversation regardless of how the reply was generated (AI, Regex, or DB)
if (!empty($reply)) {
    addToChatHistory('user', $message);
    addToChatHistory('model', $reply);
}

$response = buildReply($reply, $confidence);
$response['corrected'] = $corrected;
echo json_encode($response);
$conn->close();
?>
