<?php
/**
 * Promotions API Placeholder - Private Hire
 * Page controller moved to /promotions.php
 * Promo validation and promo wallet APIs are served by /api/bookings.php
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
        ? 'Page controller moved to /promotions.php. Use /api/bookings.php for promo actions.'
        : 'Unknown action',
    'moved_to' => '/promotions.php'
]);
exit;