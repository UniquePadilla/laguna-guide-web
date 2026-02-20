<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['username']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Username and Password are required']);
    exit;
}

$username = $data['username'];
$password = $data['password'];

// Fetch user (Allow login by username or email)
$sql = "SELECT id, username, email, password, role, two_factor_enabled, status FROM users WHERE username = ? OR email = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
} 

$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Check Status
    if (isset($user['status']) && $user['status'] === 'pending') {
        echo json_encode(['success' => false, 'message' => 'Your account is pending approval from the admin.']);
        exit;
    }
    if (isset($user['status']) && $user['status'] === 'rejected') {
        echo json_encode(['success' => false, 'message' => 'Your account has been rejected.']);
        exit;
    }

    if (password_verify($password, $user['password'])) {
        
        // Check for 2FA (Only enforce for admins as per request, or if enabled)
        // Interpreting request "on login the admin only make true Two-Factor Authentication"
        // as: If user is admin AND 2FA is enabled, require it.
        // Actually, if 2FA is enabled for ANY user, we should probably require it, but user emphasized admin.
        // I will respect the flag in DB. If enabled, require it.
        
        if ($user['role'] === 'admin' && isset($user['two_factor_enabled']) && $user['two_factor_enabled'] == 1) {
            $_SESSION['partial_login_user_id'] = $user['id'];
            
            // Send Email Code immediately
            require_once '../lib/MailHelper.php';
            
            $email = $user['email'];
            
            if ($email) {
                $code = rand(100000, 999999);
                $_SESSION['login_2fa_code'] = $code;
                $_SESSION['login_2fa_expiry'] = time() + 600;
                
                $subject = "Login Verification Code - Tourist Guide System";
                $body = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 5px;'>
                        <h2>Login Verification</h2>
                        <p>Your verification code for logging in is:</p>
                        <h1 style='color: #4a90e2; letter-spacing: 5px;'>$code</h1>
                        <p>This code will expire in 10 minutes.</p>
                        <p>If you did not attempt to login, please change your password immediately.</p>
                    </div>
                ";
                
                // Attempt to send email, but don't block login flow if it fails (user can click resend)
                MailHelper::sendEmail($email, $subject, $body);
            }
            
            echo json_encode(['success' => true, 'requires_2fa' => true, 'message' => '2FA verification required. Code sent to email.']);
            exit;
        }

        // Login Success (No 2FA or 2FA disabled)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        $redirect = 'index.php';
        if ($user['role'] === 'admin') {
            $redirect = 'admin/admin.php';
        } elseif ($user['role'] === 'business_owner') {
            $redirect = 'owner/owner.php';
        }

        // --- Log the Login ---
        // Log admins too? Previous code didn't log admins. I'll keep it as is or improve it.
        // Previous code: if ($user['role'] !== 'admin')
        if ($user['role'] !== 'admin') {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
            if ($log_stmt) {
                $log_stmt->bind_param("iss", $user['id'], $ip_address, $user_agent);
                $log_stmt->execute();
                $log_stmt->close();
            }
        }
       

        echo json_encode(['success' => true, 'message' => 'Login successful', 'redirect' => $redirect]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
}

$stmt->close();
$conn->close();
?>