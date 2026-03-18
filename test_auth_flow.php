<?php
session_start();
echo "=== LOGIN FLOW DEBUG ===\n\n";

// Test 1: Check if session is working
echo "1. Session ID before: " . session_id() . "\n";

// Test 2: Simulate login API call
require_once __DIR__ . '/Database/db.php';
require_once __DIR__ . '/sql/AuthRepository.php';

$authRepo = new AuthRepository($pdo);

// Find test user
$user = $authRepo->findUserByEmailOrUsername('testuser@example.com');

if (!$user) {
    echo "ERROR: User not found\n";
    exit;
}

echo "2. User found: {$user['full_name']}\n";

// Verify password
if (!$authRepo->verifyPassword('TestPassword123', $user['password_hash'])) {
    echo "ERROR: Password verification failed\n";
    exit;
}

echo "3. Password verified ✓\n";

// Set session (same as API does)
$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['full_name'] ?: $user['email'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];
$_SESSION['full_name'] = $user['full_name'];

echo "4. Session variables set:\n";
echo "   - logged_in: " . ($_SESSION['logged_in'] ? 'true' : 'false') . "\n";
echo "   - user_id: " . $_SESSION['user_id'] . "\n";
echo "   - email: " . $_SESSION['email'] . "\n";
echo "   - full_name: " . $_SESSION['full_name'] . "\n";

// Test 3: Check if session persists
echo "\n5. Session ID after: " . session_id() . "\n";
echo "6. Session file: " . session_save_path() . "\n";

// Display all session data
echo "\n7. All session data:\n";
var_dump($_SESSION);

// Test 4: Verify session check works
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
echo "\n8. Session check result: " . ($isLoggedIn ? 'LOGGED IN ✓' : 'NOT LOGGED IN ✗') . "\n";
?>
