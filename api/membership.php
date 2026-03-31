<?php
/**
 * Membership API - Private Hire
 * Page controller moved to /membership.php
 */

require_once __DIR__ . '/bootstrap.php';

$input = api_init(['allow_origin' => '*']);
$action = api_action($input);

if ($action === '') {
    api_json([
        'success' => false,
        'message' => 'Page controller moved to /membership.php.',
        'moved_to' => '/membership.php'
    ]);
    exit;
}

// TODO: Add membership API actions here
api_json(['success' => false, 'message' => 'Unknown action: ' . $action]);
?>