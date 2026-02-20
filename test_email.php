<?php

header('Content-Type: text/plain');

echo "Checking environment...\n";
echo "PHP Version: " . phpversion() . "\n";
echo "OpenSSL Enabled: " . (extension_loaded('openssl') ? 'Yes' : 'No') . "\n";

echo "\nChecking configuration...\n";
$config = include 'config/mail_config.php';
echo "SMTP Host: " . $config['smtp_host'] . "\n";
echo "SMTP Port: " . $config['smtp_port'] . "\n";
echo "SMTP User: " . $config['smtp_user'] . "\n";

if ($config['smtp_user'] === 'your_email@gmail.com') {
    echo "\n[ERROR] Configuration is still using placeholders!\n";
    echo "Please edit 'config/mail_config.php' and set your actual Gmail address and App Password.\n";
    exit(1);
}

echo "\nAttempting to send test email...\n";

require_once 'lib/SimpleSMTP.php';

try {
    $smtp = new SimpleSMTP(
        $config['smtp_host'], 
        $config['smtp_port'],
        $config['smtp_user'],
        $config['smtp_pass']
    );
    
    
    
    echo "Connecting...\n";
    
    
    echo "Sending...\n";
    $smtp->send(
        $config['from_email'], 
        $config['smtp_user'], // Send to self for testing
        "Test Email from Tourist Guide System",
        "If you are reading this, your email configuration is working correctly!",
        $config['from_name']
    );
    
    echo "\n[SUCCESS] Test email sent successfully to " . $config['smtp_user'] . "\n";
    
} catch (Exception $e) {
    echo "\n[FAILED] Error sending email:\n";
    echo $e->getMessage() . "\n";
    echo "\nTroubleshooting Tips:\n";
    echo "1. Ensure 'smtp_user' is your full Gmail address.\n";
    echo "2. Ensure 'smtp_pass' is a valid App Password (16 characters), NOT your login password.\n";
    echo "3. Ensure '2-Step Verification' is enabled on your Google Account.\n";
}
?>
