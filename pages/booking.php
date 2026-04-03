<?php
session_start();
$title = 'Private Hire - Book Your Ride';
$currentPage = 'booking';

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../config/env.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? null) : null;
$currentEmail = $isLoggedIn ? ($_SESSION['email'] ?? '') : '';
$userRole = $_SESSION['role'] ?? 'user';

if (!$isLoggedIn) {
    $_SESSION['login_flash'] = [
        'type' => 'error',
        'message' => 'Please sign in to continue booking.'
    ];
    header('Location: /');
    exit;
}

$carId = $_GET['car_id'] ?? '';
$promoCode = $_GET['promo'] ?? '';
$bookingMode = $_GET['mode'] ?? 'minicab';

require __DIR__ . '/../templates/booking.html.php';
