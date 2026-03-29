<?php
/**
 * Comprehensive login test - simulates browser request
 */

echo "=== COMPREHENSIVE LOGIN TEST ===\n\n";

// Step 1: Create a new session (simulating user coming to page)
session_start();
echo "1. Session started: " . session_id() . "\n";
echo "   Logged in before: " . (isset($_SESSION['logged_in']) ? 'YES' : 'NO') . "\n\n";

// Step 2: Make a login POST request
echo "2. Simulating POST request to /api/login.php\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => 'login',
    'identifier' => 'testuser@example.com',
    'password' => 'TestPassword123'
];

echo "   POST data:\n";
echo "   - action: " . $_POST['action'] . "\n";
echo "   - identifier: " . $_POST['identifier'] . "\n";
echo "   - password: " . $_POST['password'] . "\n\n";

// Include login API and capture output
ob_start();
include __DIR__ . '/../api/login.php';
$apiOutput = ob_get_clean();

echo "3. API Response:\n";
$response = json_decode($apiOutput, true);
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Step 3: Verify session
echo "4. Session after login:\n";
echo "   logged_in: " . (isset($_SESSION['logged_in']) ? ($_SESSION['logged_in'] ? 'true' : 'false') : 'NOT SET') . "\n";
echo "   user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "   email: " . ($_SESSION['email'] ?? 'NOT SET') . "\n";
echo "   full_name: " . ($_SESSION['full_name'] ?? 'NOT SET') . "\n\n";

// Step 4: Simulate page reload - check if session persists
echo "5. Page reload check:\n";
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
echo "   isLoggedIn: " . ($isLoggedIn ? 'YES ✓' : 'NO ✗') . "\n";

if ($isLoggedIn) {
    echo "\n✓ LOGIN FLOW WORKING CORRECTLY!\n";
} else {
    echo "\n✗ LOGIN FLOW BROKEN - Session not persisting\n";
}
?>
