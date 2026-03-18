<?php
/**
 * Support API - Private Hire
 * Handles customer support and trip enquiries
 * - Page view: /api/support.php (renders template)
 * - API: /api/support.php?action=xxx (returns JSON)
 */

session_start();
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$userRole = $_SESSION['role'] ?? 'user';
$userId = $_SESSION['user_id'] ?? null;
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') : null;
$title = "Customer Support - Private Hire";
$currentPage = 'support';

// Determine if this is an API call or page view
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ===== JSON API ENDPOINTS =====
if (!empty($action)) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    // ==========================================================
    // SUBMIT ENQUIRY
    // ==========================================================
    if ($action === 'submit-enquiry') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['name'] ?? '';
        $email = $input['email'] ?? '';
        $details = $input['details'] ?? '';
        $contactMethod = $input['contact_method'] ?? 'email';

        if (empty($name) || empty($email) || empty($details)) {
            echo json_encode(['success' => false, 'message' => 'Name, email, and details required.']);
            exit;
        }

        try {
            // TODO: Save enquiry to database and send email
            // For now, just return success
            echo json_encode([
                'success' => true,
                'message' => 'Trip enquiry submitted! Our team will contact you within 2 hours.'
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
    exit;
}

// ===== PAGE VIEW =====
// Render support page template
include __DIR__ . '/../templates/support.html.php';
?>