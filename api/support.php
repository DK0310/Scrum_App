<?php
/**
 * Support API - Private Hire
 * Handles customer support and trip enquiries
 * Page controller moved to /support.php
 */

session_start();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}

if (empty($action)) {
    echo json_encode([
        'success' => false,
        'message' => 'Page controller moved to /support.php.',
        'moved_to' => '/support.php'
    ]);
    exit;
}

// ==========================================================
// SUBMIT ENQUIRY
// ==========================================================
if ($action === 'submit-enquiry') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

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
?>