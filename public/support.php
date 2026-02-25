<?php
/**
 * Support Page - Backend
 * Handles customer support and trip enquiries
 */
session_start();
$title = "DriveNow - Customer Support";
$currentPage = 'support';

require_once '../config/env.php';
require_once '../Database/db.php';
require_once '../api/n8n.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? $_SESSION['username'] : null;
$userRole = $_SESSION['role'] ?? 'renter';

// Handle POST enquiry submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    
    // TODO: Save enquiry to database and send email
    echo json_encode(['success' => true, 'message' => 'Enquiry submitted!']);
    exit;
}

include '../templates/support.html.php';
?>
