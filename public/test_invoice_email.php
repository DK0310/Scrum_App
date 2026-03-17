<?php
// Manual test endpoint: generate invoice PDF locally (mPDF) and email it to a target address.

session_start();
require_once __DIR__ . '/../config/env.php';

header('Content-Type: text/plain; charset=utf-8');

$token = $_GET['token'] ?? '';
$expected = \EnvLoader::get('INVOICE_TEST_TOKEN', '');
if (empty($expected) || !hash_equals($expected, $token)) {
    http_response_code(403);
    echo "Forbidden. Configure INVOICE_TEST_TOKEN in .env and call with ?token=...";
    exit;
}

$to = $_GET['to'] ?? \EnvLoader::get('SMTP_USERNAME', '');
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo "Invalid recipient. Provide ?to=email@example.com";
    exit;
}

require_once __DIR__ . '/../lib/invoice_mpdf.php';
require_once __DIR__ . '/../lib/mailer.php';

$fakeBooking = [
    'id' => 'TEST-' . date('Ymd-His'),
    'subtotal' => 515,
    'discount_amount' => 77.25,
    'total_amount' => 437.75,
    'payment_method' => 'credit_card',
    'payment_status' => 'paid',

    'vehicle_name' => 'Toyota Camry 2024',
    'license_plate' => '51A-12345',
    'pickup_location' => '789 Creative Lane, Design City',
    'return_location' => 'Airport Terminal 1',

    'customer_name' => 'Sophie Turner',
    'customer_email' => $to,
    'customer_phone' => '789-456-1230',
    'customer_address' => '789 Creative Lane, Design City'
];

try {
    $pdf = privatehire_generate_invoice_pdf_local($fakeBooking);

    privatehire_send_email(
        $to,
        'PrivateHire - Invoice Test ' . $fakeBooking['id'],
        '<p>This is a test invoice email. PDF is attached.</p>',
        [
            'content' => $pdf,
            'filename' => 'invoice_' . $fakeBooking['id'] . '.pdf',
            'mime' => 'application/pdf'
        ]
    );

    echo "OK: invoice email sent to {$to}\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "FAIL: " . $e->getMessage() . "\n";
}
