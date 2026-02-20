<?php
// create_owners.php
include 'db_connect.php';

echo "Starting owner account creation process...\n";

// 1. Ensure columns exist
$columns_to_check = [
    'users' => ['status', 'business_name', 'business_address', 'permit_number', 'contact_number'],
    'spots' => ['user_id']
];

foreach ($columns_to_check as $table => $cols) {
    foreach ($cols as $col) {
        $check = $conn->query("SHOW COLUMNS FROM $table LIKE '$col'");
        if ($check->num_rows == 0) {
            echo "Adding column $col to $table...\n";
            $type = ($col == 'user_id') ? "INT(6) UNSIGNED" : "VARCHAR(255)";
            if ($col == 'status') $type = "VARCHAR(20) DEFAULT 'active'";
            $conn->query("ALTER TABLE $table ADD COLUMN $col $type");
        }
    }
}

// 2. Get all spots
$sql = "SELECT * FROM spots";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($spot = $result->fetch_assoc()) {
        $name = $spot['name'];
        $type = $spot['type'];
        $category = $spot['category'];
        
        // 3. Filter waterfalls
        if (stripos($name, 'Falls') !== false || 
            stripos($type, 'Waterfalls') !== false || 
            stripos($category, 'Waterfalls') !== false) {
            echo "Skipping waterfall spot: $name\n";
            continue;
        }
        
        // 4. Generate user details
        $clean_name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        $username = $clean_name;
        $password_plain = $clean_name . '123';
        $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);
        $email = $clean_name . '@example.com';
        
        echo "Processing spot: $name (User: $username)\n";
        
        // 5. Create/Get User
        $user_id = 0;
        $check_user = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_user->bind_param("s", $username);
        $check_user->execute();
        $res = $check_user->get_result();
        
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $user_id = $row['id'];
            echo "  User already exists (ID: $user_id). Updating password and role...\n";
            $role = 'business_owner';
            $update_pass = $conn->prepare("UPDATE users SET password = ?, role = ? WHERE id = ?");
            $update_pass->bind_param("ssi", $password_hash, $role, $user_id);
            $update_pass->execute();
            echo "  Password reset to: $password_plain\n";
        } else {
            // Create user
            // We use prepared statement but need to be careful with number of params
            // 'business_owner', 'active', 'PERMIT-123', '09123456789' are hardcoded in SQL for simplicity or bound
            
            $role = 'business_owner';
            $status = 'active';
            $permit = 'PERMIT-123';
            $contact = '09123456789';
            $biz_addr = $spot['location'];
            $biz_name = $name;

            $insert = $conn->prepare("INSERT INTO users (username, email, password, role, status, business_name, business_address, permit_number, contact_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param("sssssssss", $username, $email, $password_hash, $role, $status, $biz_name, $biz_addr, $permit, $contact);
            
            if ($insert->execute()) {
                $user_id = $insert->insert_id;
                echo "  Created user (ID: $user_id, Pass: $password_plain).\n";
            } else {
                echo "  Error creating user: " . $conn->error . "\n";
                continue;
            }
        }
        
        // 6. Update Spot
        if ($user_id > 0) {
            $update = $conn->prepare("UPDATE spots SET user_id = ? WHERE id = ?");
            $update->bind_param("ii", $user_id, $spot['id']);
            if ($update->execute()) {
                echo "  Linked spot '$name' to user ID $user_id.\n";
            } else {
                echo "  Error linking spot: " . $conn->error . "\n";
            }
        }
    }
} else {
    echo "No spots found.\n";
}

$conn->close();
?>