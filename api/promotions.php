<?php
/**
 * Promotions API Placeholder - Private Hire
 * Page controller moved to /promotions.php
 * Promo validation and promo wallet APIs are served by /api/bookings.php
 */
require_once __DIR__ . '/bootstrap.php';

$input = api_init(['allow_origin' => '*']);
$action = api_action($input);

api_json([
    'success' => false,
    'message' => $action === ''
        ? 'Page controller moved to /promotions.php. Use /api/bookings.php for promo actions.'
        : 'Unknown action',
    'moved_to' => '/promotions.php'
]);
exit;