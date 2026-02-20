<?php
// Test Real Email Sending
header('Content-Type: text/plain');

echo "Loading config...\n";
$config = include 'config/mail_config.php';

require_once 'lib/SimpleSMTP.php';

echo "SMTP User: " . $config['smtp_user'] . "\n";
// Mask password for output
echo "SMTP Pass: " . substr($config['smtp_pass'], 0, 4) . "...\n";

try {
    echo "Initializing SMTP...\n";
    $smtp = new SimpleSMTP(
        $config['smtp_host'],
        $config['smtp_port'],
        $config['smtp_user'],
        $config['smtp_pass']
    );

    echo "Sending test email to " . $config['smtp_user'] . "...\n";
    $result = $smtp->send(
        $config['smtp_user'], // Send to self
        "Test Real Email",
        "This is a test email from the Tourist Guide System using the new App Password.",
        $config['from_email'],
        $config['from_name']
    );

    if ($result) {
        echo "[SUCCESS] Email sent successfully!\n";
    } else {
        echo "[FAILED] Email sending returned false.\n";
    }

} catch (Exception $e) {
    echo "[ERROR] Exception: " . $e->getMessage() . "\n";
}
?>