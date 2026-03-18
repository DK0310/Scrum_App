<?php
/**
 * Cars Page & API - Private Hire
 * Route controller for cars page view
 * Handles car listing, filtering, and search
 */
session_start();

// Parse action first to determine if this is a page view or API request
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = $input['action'] ?? $_GET['action'] ?? '';

// ===== PAGE VIEW MODE (no action) =====
if (empty($action)) {
    // Render cars page (not API)
    $title = "Private Hire - Browse Cars";
    $currentPage = 'cars';

    // Include dependencies
    require_once __DIR__ . '/../config/env.php';
    require_once __DIR__ . '/../Database/db.php';

    // Check login status
    $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
    $currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? null) : null;
    $userRole = $_SESSION['role'] ?? 'user';

    // Cars are loaded dynamically from database via vehicles API (JavaScript fetch)
    // No server-side car data needed — the template uses client-side API calls

    // Load template
    require __DIR__ . '/../templates/cars.html.php';
    exit;
}

// ===== API MODE (if any action endpoints needed in future)
// Currently cars.php only renders the page view
// All car data API calls go to /api/vehicles.php
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Unknown action']);
exit;