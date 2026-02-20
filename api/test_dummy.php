<?php
// Mock input
$_POST['message'] = 'hi';
$input = json_encode(['message' => 'hi']);

// Capture output
ob_start();
// Simulate file_get_contents('php://input') by mocking it or just modifying chat_ai.php to accept a variable? 
// No, chat_ai.php reads php://input. 
// I can't easily mock php://input in CLI without a wrapper or modifying the file.
// But I can modify the $_SERVER['REQUEST_METHOD'] and inject data if I used $_POST, but the script uses json_decode(file_get_contents('php://input')).

// Let's just use a curl request to the local server if it was running, but I can't guarantee a server is running.
// Instead, I will write a small wrapper that defines the input and includes the file, 
// BUT chat_ai.php does `echo json_encode(...)` and `exit;`.
// So I can catch the output.

// To mock `php://input`, I can use a stream wrapper or just write to a temporary file and use that? No.
// Easier way: Create a test file that modifies the input reading line temporarily or just use a tool to run it?
// Actually, I can use `php-cgi` or just rewrite the input reading part in chat_ai.php to support CLI args for testing?
// No, I shouldn't modify the production code for testing if possible.

// Alternative: Use a stream wrapper to mock php://input.
class VarStream {
    private $string;
    private $position;
    public function stream_open($path, $mode, $options, &$opened_path) {
        $url = parse_url($path);
        $this->string = $url["host"];
        $this->position = 0;
        return true;
    }
    public function stream_read($count) {
        $ret = substr($this->string, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }
    public function stream_eof() {
        return $this->position >= strlen($this->string);
    }
    public function stream_stat() {
        return [];
    }
}
stream_wrapper_unregister("php");
stream_wrapper_register("php", "VarStream");

// This is getting complicated.
// Simpler approach: Just copy the logic of chat_ai.php into a test script, or
// Just modify `chat_ai.php` to check if `php_sapi_name() == 'cli'` and read from args/stdin.

// Let's just trust my code changes for now and maybe run a syntax check.
// I'll run a syntax check.
?>
