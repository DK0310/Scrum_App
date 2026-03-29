<?php
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/AuthRepository.php';

$authRepo = new AuthRepository($pdo);

$identifier = "milizelena0310@gmail.com";
$password = "password";

echo "=== LOGIN DEBUG ===\n";
echo "Email: $identifier\n";
echo "Password: $password\n\n";

// Step 1: Find user
$user = $authRepo->findUserByEmailOrUsername($identifier);

if (!$user) {
    echo "ERROR: User not found\n";
    exit;
}

echo "✓ User found\n";
echo "  ID: {$user['id']}\n";
echo "  Full Name: {$user['full_name']}\n";
echo "  Is Active: {$user['is_active']}\n";
echo "  Password Hash: " . substr($user['password_hash'], 0, 20) . "...\n\n";

// Step 2: Check if active
if (!$user['is_active']) {
    echo "ERROR: Account is inactive\n";
    exit;
}

echo "✓ Account is active\n\n";

// Step 3: Verify password
echo "Verifying password...\n";
$isValid = $authRepo->verifyPassword($password, $user['password_hash']);

if ($isValid) {
    echo "✓ Password is CORRECT\n";
} else {
    echo "✗ Password is WRONG\n";
    echo "\nTrying other common passwords...\n";
    $testPasswords = ["admin", "123456", "password123", "test", "test123"];
    foreach ($testPasswords as $testPass) {
        $result = $authRepo->verifyPassword($testPass, $user['password_hash']);
        echo "  '$testPass': " . ($result ? "✓ MATCH" : "✗") . "\n";
    }
}
?>
