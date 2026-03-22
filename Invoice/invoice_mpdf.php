<?php
/**
 * Local invoice PDF generator (mPDF)
 * - Render a basic HTML invoice
 * - Convert to PDF bytes
 */

require_once __DIR__ . '/../config/env.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    throw new Exception('Missing vendor/autoload.php. Run composer install.');
}
require_once $autoload;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Build invoice placeholders (shared with Google Docs version).
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
    
    // Fallback if still empty - search using alternative keys
    if ($vehicleName === '') {
        $vehicleName = trim((string)($booking['vehicle'] ?? '')) ?: 'Vehicle';
    }

    $fmtMoney = function ($n) use ($currency): string {
        if ($n === null || $n === '') return '';
        $val = (float)$n;
        return ($currency === 'USD' ? '$' : '') . number_format($val, 2);
    };

    $fmtPickupTime = function ($dateStr) use ($tz): string {
        if (!$dateStr || $dateStr === '') return '';
        try {
            // Parse as UTC first (backend stores as UTC), then convert to target timezone
            $dt = new DateTime($dateStr, new DateTimeZone('UTC'));
            $dt->setTimeZone(new DateTimeZone($tz));
            return $dt->format('M d, Y \a\t h:i A');
        } catch (Exception $e) {
            return (string)$dateStr;
        }
    };

    $discountRaw = $booking['discount_amount'] ?? $booking['discount'] ?? $booking['discount_value'] ?? 0;

    return [
        'invoice_number' => (string)($booking['id'] ?? ''),
        'invoice_date' => $dt->format('F j, Y'),

        'customer_name' => (string)($booking['customer_name'] ?? $booking['renter_name'] ?? ''),
        'customer_email' => (string)($booking['customer_email'] ?? $booking['renter_email'] ?? ''),
        'customer_phone' => (string)($booking['customer_phone'] ?? ''),
        'customer_address' => (string)($booking['customer_address'] ?? ''),

        'line_items' => (string)($booking['line_items'] ?? ''),

        'subtotal' => $fmtMoney($booking['subtotal'] ?? $booking['sub_total'] ?? ''),
        'discount' => $fmtMoney($discountRaw),
        'total_due' => $fmtMoney($booking['total_amount'] ?? $booking['total'] ?? ''),

        'payment_method' => (string)($booking['payment_method'] ?? ''),
        'payment_status' => (string)($booking['payment_status'] ?? ''),

        'vehicle_name' => $vehicleName,
        'license_plate' => (string)($booking['license_plate'] ?? ''),
        'pickup_location' => (string)($booking['pickup_location'] ?? ''),
        'destination' => (string)($booking['return_location'] ?? $booking['destination'] ?? ''),
        'pickup_time' => $fmtPickupTime((string)($booking['pickup_date'] ?? '')),

        'company_name' => (string)\EnvLoader::get('INVOICE_COMPANY_NAME', 'PrivateHire'),
        'support_email' => (string)\EnvLoader::get('INVOICE_SUPPORT_EMAIL', ''),
        'support_phone' => (string)\EnvLoader::get('INVOICE_SUPPORT_PHONE', ''),
    ];
}

/**
 * Generate invoice PDF bytes.
 *
 * @param array<string, mixed> $bookingOrReplacements Either booking array or already-built replacements.
 */
function privatehire_generate_invoice_pdf_local(array $bookingOrReplacements): string {
    // Detect if caller passed booking array or replacements
    $repl = $bookingOrReplacements;
    if (!array_key_exists('invoice_number', $repl) || !array_key_exists('total_due', $repl)) {
        $repl = privatehire_build_invoice_replacements($bookingOrReplacements);
    }

    $html = privatehire_render_invoice_html($repl);

    $mpdf = new Mpdf([
        'tempDir' => sys_get_temp_dir(),
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 12,
        'margin_right' => 12,
        'margin_top' => 12,
        'margin_bottom' => 12,
    ]);

    $mpdf->SetTitle(($repl['company_name'] ?? 'PrivateHire') . ' Invoice ' . ($repl['invoice_number'] ?? ''));
    $mpdf->WriteHTML($html);

    return $mpdf->Output('', Destination::STRING_RETURN);
}

/**
 * @param array<string, string> $r
 */
function privatehire_render_invoice_html(array $r): string {
    $e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $company = $e((string)($r['company_name'] ?? 'PrivateHire'));
    $invoiceNo = $e((string)($r['invoice_number'] ?? ''));
    $invoiceDate = $e((string)($r['invoice_date'] ?? ''));

    $custName = $e((string)($r['customer_name'] ?? ''));
    $custEmail = $e((string)($r['customer_email'] ?? ''));
    $custPhone = $e((string)($r['customer_phone'] ?? ''));
    $custAddress = $e((string)($r['customer_address'] ?? ''));

    $vehicleName = $e((string)($r['vehicle_name'] ?? ''));
    $plate = $e((string)($r['license_plate'] ?? ''));
    $pickup = $e((string)($r['pickup_location'] ?? ''));
    $dest = $e((string)($r['destination'] ?? ''));
    $timepickup = $e((string)($r['pickup_time'] ?? ''));

    $subtotal = $e((string)($r['subtotal'] ?? ''));
    $discount = $e((string)($r['discount'] ?? ''));
    $totalDue = $e((string)($r['total_due'] ?? ''));

    $payMethod = $e((string)($r['payment_method'] ?? ''));
    $payStatus = $e((string)($r['payment_status'] ?? ''));

    $supportEmail = $e((string)($r['support_email'] ?? ''));
    $supportPhone = $e((string)($r['support_phone'] ?? ''));

    return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
    .muted { color: #6b7280; }
    .row { display: flex; justify-content: space-between; }
    .card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; margin: 10px 0; }
    h1 { font-size: 20px; margin: 0 0 6px; }
    h2 { font-size: 14px; margin: 0 0 8px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 8px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    th { text-align: left; background: #f9fafb; }
    .totals td { border-bottom: none; }
    .right { text-align: right; }
  </style>
</head>
<body>
  <div class="row">
    <div>
      <h1>{$company}</h1>
      <div class="muted">Invoice</div>
    </div>
    <div class="right">
      <div><strong>#{$invoiceNo}</strong></div>
      <div class="muted">{$invoiceDate}</div>
    </div>
  </div>

  <div class="card">
    <h2>Invoice To</h2>
    <div><strong>{$custName}</strong></div>
    <div class="muted">{$custEmail}</div>
    <div class="muted">{$custPhone}</div>
    <div class="muted">{$custAddress}</div>
  </div>

  <div class="card">
    <h2>Trip / Vehicle</h2>
    <table>
      <tr><th style="width: 30%">Vehicle</th><td>{$vehicleName} {$plate}</td></tr>
      <tr><th>Pickup</th><td>{$pickup}</td></tr>
      <tr><th>Destination</th><td>{$dest}</td></tr>
      <tr><th>Time</th><td>{$timepickup}</td></tr>
      <tr><th>Payment</th><td>{$payMethod} {$payStatus}</td></tr>
    </table>
  </div>

  <div class="card">
    <h2>Summary</h2>
    <table>
      <tr><th>Description</th><th class="right">Amount</th></tr>
      <tr><td>Subtotal</td><td class="right">{$subtotal}</td></tr>
      <tr><td>Discount</td><td class="right">{$discount}</td></tr>
      <tr><td><strong>Total</strong></td><td class="right"><strong>{$totalDue}</strong></td></tr>
    </table>
  </div>

  <div class="muted">
    Support: {$supportEmail} {$supportPhone}
  </div>
</body>
</html>
HTML;
}
