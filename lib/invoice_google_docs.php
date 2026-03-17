<?php
/**
 * Google Docs Invoice Generator
 * - Copy Google Docs template
 * - Replace placeholders like {{customer_name}}
 * - Export to PDF
 */

require_once __DIR__ . '/../config/env.php';

// Composer autoload (google/apiclient)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    throw new Exception('Missing vendor/autoload.php. Run composer install.');
}
require_once $autoload;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Docs;

/**
 * @param array<string, mixed> $replacements
 */
function privatehire_generate_invoice_pdf(array $replacements): string {
    $saJson = \EnvLoader::get('GOOGLE_SERVICE_ACCOUNT_JSON', '');
    $templateId = \EnvLoader::get('GOOGLE_INVOICE_TEMPLATE_DOC_ID', '');
    $outputFolderId = \EnvLoader::get('GOOGLE_INVOICE_OUTPUT_FOLDER_ID', '');

    if (empty($saJson) || empty($templateId)) {
        throw new Exception('GOOGLE_SERVICE_ACCOUNT_JSON / GOOGLE_INVOICE_TEMPLATE_DOC_ID not configured.');
    }

    $client = new Client();
    $client->setAuthConfig($saJson);
    $client->addScope([Drive::DRIVE, Docs::DOCUMENTS]);

    $drive = new Drive($client);
    $docs = new Docs($client);

    $invoiceNo = (string)($replacements['invoice_number'] ?? $replacements['booking_id'] ?? time());

    // 1) Copy the template doc
    $copyMeta = new Drive\DriveFile();
    $copyMeta->setName('Invoice - ' . $invoiceNo);
    if (!empty($outputFolderId)) {
        $copyMeta->setParents([$outputFolderId]);
    }

    $copied = $drive->files->copy($templateId, $copyMeta);

    // 2) Replace placeholders
    $requests = [];
    foreach ($replacements as $key => $value) {
        $requests[] = [
            'replaceAllText' => [
                'containsText' => ['text' => '{{' . $key . '}}', 'matchCase' => true],
                'replaceText' => (string)($value ?? '')
            ]
        ];
    }

    if (!empty($requests)) {
        $batch = new Docs\BatchUpdateDocumentRequest(['requests' => $requests]);
        $docs->documents->batchUpdate($copied->id, $batch);
    }

    // 3) Export PDF
    $response = $drive->files->export($copied->id, 'application/pdf', ['alt' => 'media']);
    $pdf = $response->getBody()->getContents();

    // 4) Cleanup the copied doc (optional: if you prefer to keep it, comment out)
    $drive->files->delete($copied->id);

    return $pdf;
}

/**
 * Build invoice placeholders for your template.
 * Keeps "vehicle info" as a single name (vehicle_name) instead of brand/model.
 *
 * @param array<string, mixed> $booking
 * @return array<string, string>
 */
function privatehire_build_invoice_replacements(array $booking): array {
    $currency = \EnvLoader::get('INVOICE_CURRENCY', 'USD');
    $tz = \EnvLoader::get('INVOICE_TIMEZONE', 'Asia/Ho_Chi_Minh');

    try {
        $dt = new DateTime('now', new DateTimeZone($tz));
    } catch (Exception $e) {
        $dt = new DateTime('now');
    }

    $vehicleName = trim((string)($booking['vehicle_name'] ?? ''));
    if ($vehicleName === '') {
        $brand = trim((string)($booking['brand'] ?? ''));
        $model = trim((string)($booking['model'] ?? ''));
        $year  = trim((string)($booking['year'] ?? ''));
        $vehicleName = trim($brand . ' ' . $model . (empty($year) ? '' : (' ' . $year)));
    }

    $fmtMoney = function ($n) use ($currency): string {
        if ($n === null || $n === '') return '';
        $val = (float)$n;
        return ($currency === 'USD' ? '$' : '') . number_format($val, 2);
    };

    return [
        'invoice_number' => (string)($booking['id'] ?? ''),
        'invoice_date' => $dt->format('F j, Y'),

        'customer_name' => (string)($booking['customer_name'] ?? $booking['renter_name'] ?? ''),
        'customer_email' => (string)($booking['customer_email'] ?? $booking['renter_email'] ?? ''),
        'customer_phone' => (string)($booking['customer_phone'] ?? ''),
        'customer_address' => (string)($booking['customer_address'] ?? ''),

        // Basic line items as a single multiline placeholder (simple approach)
        // If your template uses a real Docs table, we can upgrade later.
        'line_items' => (string)($booking['line_items'] ?? ''),

        'subtotal' => $fmtMoney($booking['subtotal'] ?? $booking['sub_total'] ?? ''),
        'discount' => $fmtMoney($booking['discount_amount'] ?? $booking['discount'] ?? ''),
        'total_due' => $fmtMoney($booking['total_amount'] ?? $booking['total'] ?? ''),

        'payment_method' => (string)($booking['payment_method'] ?? ''),
        'payment_status' => (string)($booking['payment_status'] ?? ''),

        // Vehicle info (single name)
        'vehicle_name' => $vehicleName,
        'license_plate' => (string)($booking['license_plate'] ?? ''),
        'pickup_location' => (string)($booking['pickup_location'] ?? ''),
        'destination' => (string)($booking['return_location'] ?? $booking['destination'] ?? ''),

        // Company info
        'company_name' => (string)\EnvLoader::get('INVOICE_COMPANY_NAME', 'PrivateHire'),
        'support_email' => (string)\EnvLoader::get('INVOICE_SUPPORT_EMAIL', ''),
        'support_phone' => (string)\EnvLoader::get('INVOICE_SUPPORT_PHONE', ''),
    ];
}
