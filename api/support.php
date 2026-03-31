<?php
/**
 * Support API - Private Hire
 * Handles customer support and trip enquiries
 * Page controller moved to /support.php
 */

require_once __DIR__ . '/bootstrap.php';
$input = api_init();
$action = api_action($input);

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