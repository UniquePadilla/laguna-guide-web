<?php
require_once __DIR__ . '/SimpleSMTP.php';

class MailHelper {
    public static function sendEmail($to, $subject, $body) {
        $configPath = __DIR__ . '/../config/mail_config.php';
        if (!file_exists($configPath)) {
            error_log("Mail config not found.");
            return false;
        }
        
        $config = include($configPath);
        
        // Check if config is dummy or password is not set or is 'admin123' (common test password)
        if ($config['smtp_user'] === 'your_email@gmail.com' || 
            $config['smtp_pass'] === 'your_app_password' || 
            $config['smtp_pass'] === 'admin123') {
            $logMessage = "--------------------------------------------------\n";
            $logMessage .= "DATE: " . date('Y-m-d H:i:s') . "\n";
            $logMessage .= "TO: $to\n";
            $logMessage .= "SUBJECT: $subject\n";
            $logMessage .= "BODY:\n$body\n";
            $logMessage .= "--------------------------------------------------\n\n";
            
            // Log to file in project root
            $logFile = __DIR__ . '/../mock_emails.txt';
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            
            error_log("[MOCK MAIL] Email saved to mock_emails.txt");
            return true; // Pretend success
        }
        
        $smtp = new SimpleSMTP(
            $config['smtp_host'],
            $config['smtp_port'],
            $config['smtp_user'],
            $config['smtp_pass']
        );
        
        return $smtp->send(
            $to,
            $subject,
            $body,
            $config['from_email'],
            $config['from_name']
        );
    }
}
?>
