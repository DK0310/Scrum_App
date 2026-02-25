<?php
/**
 * Reviews Page - Backend
 * Handles customer reviews and ratings
 */
session_start();
$title = "DriveNow - Customer Reviews";
$currentPage = 'reviews';

require_once '../config/env.php';
require_once '../Database/db.php';
require_once '../api/n8n.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? $_SESSION['username'] : null;
$userRole = $_SESSION['role'] ?? 'renter';

// Handle POST review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    
    // TODO: Save review to database
    echo json_encode(['success' => true, 'message' => 'Review submitted!']);
    exit;
}

// Sample reviews data (replace with DB queries later)
$reviews = [
    ['initials' => 'JD', 'name' => 'James Davis', 'trip' => 'BMW 5 Series • New York → Boston', 'stars' => 5, 'text' => 'Best car rental experience ever! The booking process was seamless, and the car was in perfect condition. Will definitely use DriveNow again.'],
    ['initials' => 'AL', 'name' => 'Anna Lee', 'trip' => 'Honda CR-V • Tokyo → Osaka', 'stars' => 5, 'text' => 'The face recognition login is super cool! No passwords to remember. And the AI chatbot helped me find the perfect car for my family trip.'],
    ['initials' => 'MP', 'name' => 'Marco Polo', 'trip' => 'Tesla Model Y • Milan → Rome', 'stars' => 4, 'text' => 'Great selection of electric vehicles. The GPS tracking feature gave me peace of mind when renting out my car. Very professional platform.'],
    ['initials' => 'SK', 'name' => 'Sophie Kim', 'trip' => 'Mercedes C-Class • Seoul → Busan', 'stars' => 5, 'text' => 'Love the membership discounts! Premium plan is totally worth it. Already saved over $200 in just 3 months of using DriveNow.'],
    ['initials' => 'RB', 'name' => 'Robert Brown', 'trip' => 'Ford Mustang • Miami → Key West', 'stars' => 5, 'text' => 'The sports car selection is amazing! Rented a Mustang for a road trip and it was an unforgettable experience. Highly recommend!'],
    ['initials' => 'LW', 'name' => 'Lisa Wang', 'trip' => 'Toyota Camry • Sydney → Melbourne', 'stars' => 4, 'text' => 'Very reliable service. The car was clean and well-maintained. Customer support was super helpful when I had questions about the route.'],
];

include '../templates/reviews.html.php';
?>
