<?php
/**
 * Cars API Placeholder - Private Hire
 * Page controller moved to /cars.php
 * Vehicle data API is served by /api/vehicles.php
 */
session_start();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = $input['action'] ?? $_GET['action'] ?? '';

header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'message' => empty($action)
        ? 'Page controller moved to /cars.php. Use /api/vehicles.php for data actions.'
        : 'Unknown action',
    'moved_to' => '/cars.php'
]);
exit;