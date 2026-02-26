<?php
/**
 * Booking Page - Backend
 * Multi-step booking: Step 1 = trip details, Step 2 = payment
 */
session_start();
$title = "DriveNow - Book Your Ride";
$currentPage = 'booking';

require_once '../config/env.php';
require_once '../Database/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? null) : null;
$currentEmail = $isLoggedIn ? ($_SESSION['email'] ?? '') : '';
$userRole = $_SESSION['role'] ?? 'renter';

// Require login to book
if (!$isLoggedIn) {
    header('Location: login.php?redirect=booking.php' . (isset($_GET['car_id']) ? '&car_id=' . urlencode($_GET['car_id']) : ''));
    exit;
}

// Get car_id from URL
$carId = $_GET['car_id'] ?? '';
$promoCode = $_GET['promo'] ?? '';

include '../templates/booking.html.php';
?>
