<?php
/**
 * Cars API Placeholder - Private Hire
 * Page controller moved to /cars.php
 * Vehicle data API is served by /api/vehicles.php
 */
require_once __DIR__ . '/bootstrap.php';

$input = api_init(['allow_origin' => '*']);
$action = api_action($input);

api_json([
    'success' => false,
    'message' => $action === ''
        ? 'Page controller moved to /cars.php. Use /api/vehicles.php for data actions.'
        : 'Unknown action',
    'moved_to' => '/cars.php'
]);
exit;