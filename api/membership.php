<?php
/**
 * Membership API - Private Hire
 * Page controller moved to /membership.php
 */

session_start();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}

if (empty($action)) {
    echo json_encode([
        'success' => false,
        'message' => 'Page controller moved to /membership.php.',
        'moved_to' => '/membership.php'
    ]);
    exit;
}

// TODO: Add membership API actions here
echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
?>