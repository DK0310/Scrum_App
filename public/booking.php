<?php
/**
 * Booking Page - Backend
 * Handles booking form and payment processing
 */
session_start();
$title = "DriveNow - Book Your Ride";
$currentPage = 'booking';

require_once '../config/env.php';
require_once '../Database/db.php';
require_once '../api/n8n.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? $_SESSION['username'] : null;
$userRole = $_SESSION['role'] ?? 'renter';

// Require login to book
if (!$isLoggedIn) {
    header('Location: login.php?redirect=booking.php' . (isset($_GET['car_id']) ? '&car_id=' . urlencode($_GET['car_id']) : ''));
    exit;
}

// Get pre-filled data
$carId = $_GET['car_id'] ?? '';
$promoCode = $_GET['promo'] ?? '';

// Handle POST booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    
    // TODO: Save booking to database
    echo json_encode(['success' => true, 'message' => 'Booking submitted successfully!']);
    exit;
}

include '../templates/booking.html.php';
?>
