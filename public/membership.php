<?php
/**
 * Membership Page - Backend
 * Handles subscription plans
 */
session_start();
$title = "DriveNow - Membership Plans";
$currentPage = 'membership';

require_once '../config/env.php';
require_once '../Database/db.php';
require_once '../api/n8n.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? $_SESSION['username'] : null;
$userRole = $_SESSION['role'] ?? 'renter';

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

include '../templates/membership.html.php';
?>
