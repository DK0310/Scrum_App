<?php
/**
 * Community Page - Backend
 * Handles community posts, likes, and comments
 */
session_start();
$title = "DriveNow - Community";
$currentPage = 'community';

require_once '../config/env.php';
require_once '../Database/db.php';
require_once '../api/n8n.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? $_SESSION['username'] : null;
$userRole = $_SESSION['role'] ?? 'renter';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'create_post') {
        // TODO: Save post to database
        echo json_encode(['success' => true, 'message' => 'Post published!']);
        exit;
    }
    if ($action === 'like') {
        // TODO: Save like to database
        echo json_encode(['success' => true]);
        exit;
    }
}

// Sample community posts (replace with DB queries later)
$posts = [
    ['id' => 1, 'author' => 'Sarah Johnson', 'date' => 'Feb 20, 2026', 'emoji' => '🏖️', 'title' => 'Amazing Road Trip: LA to San Francisco', 'excerpt' => 'Rented a Tesla Model 3 for a weekend trip along the Pacific Coast Highway. The autopilot made the drive so relaxing...', 'likes' => 42, 'comments' => 12],
    ['id' => 2, 'author' => 'Mike Chen', 'date' => 'Feb 18, 2026', 'emoji' => '🏔️', 'title' => 'Best SUVs for Mountain Adventures', 'excerpt' => 'After trying 5 different SUVs on DriveNow, here\'s my honest review of each one for off-road adventures...', 'likes' => 89, 'comments' => 34],
    ['id' => 3, 'author' => 'Emma Wilson', 'date' => 'Feb 15, 2026', 'emoji' => '🌆', 'title' => 'Corporate Fleet Experience: 6 Month Review', 'excerpt' => 'Our company switched to DriveNow for our corporate fleet needs. Here\'s how it\'s been going after 6 months...', 'likes' => 156, 'comments' => 48],
];

include '../templates/community.html.php';
?>
