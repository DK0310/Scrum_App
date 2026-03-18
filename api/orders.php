<?php
/**
 * My Orders Page & API - Private Hire
 * Route controller for orders page view
 * View booking history and manage order status
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
    // Render orders page (not API)
    $title = "Private Hire - My Orders";
    $currentPage = 'orders';

    require_once __DIR__ . '/../config/env.php';
    require_once __DIR__ . '/../Database/db.php';

    $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
    $currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? null) : null;
    $userRole = $_SESSION['role'] ?? 'user';

    // Require login
    if (!$isLoggedIn) {
        $_SESSION['login_flash'] = [
            'type' => 'error',
            'message' => 'Please sign in to view your orders.'
        ];
        header('Location: /');
        exit;
    }

    require __DIR__ . '/../templates/orders.html.php';
    exit;
}

// ===== API MODE (if any action endpoints needed in future)
// Currently orders.php only renders the page view
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Unknown action']);
exit;