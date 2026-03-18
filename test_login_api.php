<?php
/**
 * Test login API end-to-end
 */

echo "=== LOGIN API SIMULATION TEST ===\n\n";

// Simulate a real POST request to login.php
// Start fresh session
session_start();
session_destroy();
session_start();

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => 'login',
    'identifier' => 'testuser@example.com',
    'password' => 'TestPassword123'
];

// Capture output
ob_start();

// Include the login API
require_once __DIR__ . '/api/login.php';

$output = ob_get_clean();

echo "API Response:\n";
echo $output . "\n\n";

// Parse JSON response
$response = json_decode($output, true);

if ($response['success']) {
    echo "✓ LOGIN SUCCESSFUL\n";
    echo "\nSession Variables:\n";
    var_dump($_SESSION);
} else {
    echo "✗ LOGIN FAILED\n";
    echo "Message: " . $response['message'] . "\n";
}
?>
