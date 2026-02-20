<?php

class SimpleSMTP {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $conn;
    private $debug = false;

    public function __construct($host, $port, $user, $pass) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function send($to, $subject, $body, $from, $fromName) {
        try {
            $this->connect();
            $this->auth();
            
            $this->sendCommand("MAIL FROM: <$from>");
            $this->sendCommand("RCPT TO: <$to>");
            $this->sendCommand("DATA");
            
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=utf-8\r\n";
            $headers .= "From: $fromName <$from>\r\n";
            $headers .= "To: $to\r\n";
            $headers .= "Subject: $subject\r\n";
            
            $data = $headers . "\r\n" . $body . "\r\n.";
            $this->sendCommand($data);
            
            $this->sendCommand("QUIT");
            fclose($this->conn);
            return true;
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        }
    }

    private function connect() {
        $this->conn = fsockopen($this->host, $this->port, $errno, $errstr, 30);
        if (!$this->conn) {
            throw new Exception("Could not connect to SMTP host: $errstr");
        }
        $this->getResponse();
        
        $this->sendCommand("EHLO " . gethostname());
        
        if ($this->port == 587) {
            $this->sendCommand("STARTTLS");
            stream_socket_enable_crypto($this->conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->sendCommand("EHLO " . gethostname());
        }
    }

    private function auth() {
        $this->sendCommand("AUTH LOGIN");
        $this->sendCommand(base64_encode($this->user));
        $this->sendCommand(base64_encode($this->pass));
    }

    private function sendCommand($cmd) {
        fputs($this->conn, $cmd . "\r\n");
        $response = $this->getResponse();
        // Basic error checking (simplified)
        if (substr($response, 0, 1) == '4' || substr($response, 0, 1) == '5') {
            throw new Exception("SMTP Command failed: $cmd | Response: $response");
        }
        return $response;
    }

    private function getResponse() {
        $response = "";
        while ($str = fgets($this->conn, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") {
                break;
            }
        }
        return $response;
    }
}
?>
