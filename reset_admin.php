<?php
include 'db_connect.php';


$new_username = "admin"; 
$new_email = "admin@gmail.com";
$new_password = "admin123"; 


echo "Attempting to update admin account credentials...\n";


$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);


$sql = "UPDATE users SET username = ?, email = ?, password = ? WHERE role = 'admin'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $new_username, $new_email, $hashed_password);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo "------------------------------------------------\n";
        echo "SUCCESS! Admin account has been updated.\n";
        echo "------------------------------------------------\n";
        echo "New Username: " . $new_username . "\n";
        echo "New Email:    " . $new_email . "\n";
        echo "New Password: " . $new_password . "\n";
        echo "------------------------------------------------\n";
    } else {
        echo "No changes made. Either no admin account exists or the values are the same.\n";
        
        $check = $conn->query("SELECT * FROM users WHERE role='admin'");
        if ($check->num_rows == 0) {
            echo "Reason: No user with role 'admin' was found.\n";
            echo "Tip: Use the signup form or a create script to make one first.\n";
        }
    }
} else {
    echo "Error updating account: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();
?>