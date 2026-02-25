<?php
/**
 * Promotions Page - Backend
 * Handles promotional offers and promo codes
 */
session_start();
$title = "DriveNow - Promotions & Deals";
$currentPage = 'promotions';

require_once '../config/env.php';
require_once '../Database/db.php';
require_once '../api/n8n.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? $_SESSION['username'] : null;
$userRole = $_SESSION['role'] ?? 'renter';

// Sample promotions data (replace with DB queries later)
$promotions = [
    ['code' => 'WEEKEND20', 'discount' => '20% OFF', 'title' => 'Weekend Special', 'description' => 'Book any car for the weekend and save 20%. Valid until March 31.', 'style' => ''],
    ['code' => 'FIRST50', 'discount' => '$50 OFF', 'title' => 'First Ride Bonus', 'description' => 'New users get $50 off their first booking. Sign up today!', 'style' => 'accent'],
    ['code' => 'LONGTERM30', 'discount' => '30% OFF', 'title' => 'Long-term Rental', 'description' => 'Book for 30+ days and get 30% discount. Perfect for corporate use.', 'style' => 'dark'],
    ['code' => 'SUMMER25', 'discount' => '25% OFF', 'title' => 'Summer Road Trip', 'description' => 'Hit the road this summer with 25% off any SUV rental. Limited time.', 'style' => ''],
    ['code' => 'EV15', 'discount' => '15% OFF', 'title' => 'Go Electric', 'description' => 'Get 15% off all electric vehicle rentals. Save the planet, save money.', 'style' => 'accent'],
    ['code' => 'REFER20', 'discount' => '$20 OFF', 'title' => 'Refer a Friend', 'description' => 'Refer a friend and both get $20 off your next booking.', 'style' => 'dark'],
];

include '../templates/promotions.html.php';
?>
