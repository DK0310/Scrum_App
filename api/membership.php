<?php
/**
 * Membership Page & API - Private Hire
 * - Page view: /api/membership.php (renders template)
 * - API: /api/membership.php?action=xxx (returns JSON)
 */

session_start();
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$userRole = $_SESSION['role'] ?? 'user';
$userId = $_SESSION['user_id'] ?? null;
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') : null;
$title = "Membership Plans - Private Hire";
$currentPage = 'membership';

// Membership plans data
$plans = [
    [
        'name' => 'Basic', 'price' => 9, 'popular' => false, 'slug' => 'basic',
        'features' => ['5% discount on all rentals', 'Priority email support', 'Free cancellation (48h)', 'Monthly newsletter', 'Basic GPS tracking']
    ],
    [
        'name' => 'Premium', 'price' => 29, 'popular' => true, 'slug' => 'premium',
        'features' => ['15% discount on all rentals', '24/7 priority support', 'Free cancellation (24h)', 'Free driver upgrade', 'Advanced GPS + route history', 'Airport lounge access']
    ],
    [
        'name' => 'Corporate', 'price' => 99, 'popular' => false, 'slug' => 'corporate',
        'features' => ['25% discount on all rentals', 'Dedicated account manager', 'Unlimited free cancellation', 'Fleet management dashboard', 'Custom billing & invoicing', 'Long-term contract rates', 'Multi-user accounts']
    ],
];

// Determine action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ===== JSON API =====
if (!empty($action)) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    // TODO: Add membership API actions here

    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
    exit;
}

// ===== PAGE VIEW =====
include __DIR__ . '/../templates/membership.html.php';
?>