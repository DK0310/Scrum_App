<?php
/**
 * My Vehicles API Placeholder - Private Hire
 * Page controller moved to /my-vehicles.php
 * Vehicle management data APIs are served by /api/vehicles.php
 */
require_once __DIR__ . '/bootstrap.php';

api_init(['allow_origin' => '*']);

api_json([
    'success' => false,
    'message' => 'Page controller moved to /my-vehicles.php. Use /api/vehicles.php for data actions.',
    'moved_to' => '/my-vehicles.php'
]);
?>