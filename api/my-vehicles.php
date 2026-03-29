<?php
/**
 * My Vehicles API Placeholder - Private Hire
 * Page controller moved to /my-vehicles.php
 * Vehicle management data APIs are served by /api/vehicles.php
 */
session_start();

header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'message' => 'Page controller moved to /my-vehicles.php. Use /api/vehicles.php for data actions.',
    'moved_to' => '/my-vehicles.php'
]);
?>