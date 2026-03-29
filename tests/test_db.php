<?php
require_once __DIR__ . '/../Database/db.php';

echo "=== USER TABLE ===\n";
$stmt = $pdo->query("SELECT id, email, phone, full_name, password_hash, is_active FROM users LIMIT 10");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "No users found in database\n";
} else {
    foreach ($users as $user) {
        echo "ID: {$user['id']}\n";
        echo "  Email: {$user['email']}\n";
        echo "  Phone: {$user['phone']}\n";
        echo "  Full Name: {$user['full_name']}\n";
        echo "  Has Password: " . (!empty($user['password_hash']) ? 'YES' : 'NO') . "\n";
        echo "  Is Active: {$user['is_active']}\n";
        echo "\n";
    }
}

echo "=== TEST LOGIN ===\n";

// Test with first user
if (!empty($users)) {
    $user = $users[0];
    $email = $user['email'];
    $phone = $user['phone'];
    
    echo "Testing with email: $email\n";
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$email, $phone]);
    $found = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($found) {
        echo "Found user: {$found['full_name']}\n";
        echo "Password hash exists: " . (!empty($found['password_hash']) ? 'YES' : 'NO') . "\n";
    }
}
?>
