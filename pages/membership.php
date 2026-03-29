<?php
session_start();
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$userRole = $_SESSION['role'] ?? 'user';
$userId = $_SESSION['user_id'] ?? null;
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') : null;
$title = 'Membership Plans - Private Hire';
$currentPage = 'membership';

$plans = [
    [
        'name' => 'Basic', 'price' => 9, 'popular' => false, 'slug' => 'basic',
        'features' => ['5% discount on all rentals', 'Priority email support', 'Free cancellation/modification (24h)', 'Monthly newsletter', 'Basic GPS tracking']
    ],
    [
        'name' => 'Premium', 'price' => 29, 'popular' => true, 'slug' => 'premium',
        'features' => ['15% discount on all rentals', '24/7 priority support', 'Free cancellation/modification (24h)', 'Free driver upgrade', 'Advanced GPS + route history', 'Airport lounge access']
    ],
    [
        'name' => 'Corporate', 'price' => 99, 'popular' => false, 'slug' => 'corporate',
        'features' => ['25% discount on all rentals', 'Dedicated account manager', 'Free cancellation/modification (24h)', 'Fleet management dashboard', 'Custom billing & invoicing', 'Long-term contract rates', 'Multi-user accounts']
    ],
];

require __DIR__ . '/../templates/membership.html.php';
