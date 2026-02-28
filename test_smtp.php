<?php
/**
 * Quick SMTP test — run: php test_smtp.php
 */
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$smtpHost = EnvLoader::get('SMTP_HOST', 'smtp.gmail.com');
$smtpPort = (int) EnvLoader::get('SMTP_PORT', 587);
$smtpUser = EnvLoader::get('SMTP_USERNAME', '');
$smtpPass = EnvLoader::get('SMTP_PASSWORD', '');
$fromEmail = EnvLoader::get('SMTP_FROM_EMAIL', $smtpUser);
$fromName = EnvLoader::get('SMTP_FROM_NAME', 'DriveNow');

echo "=== SMTP Configuration ===\n";
echo "Host: $smtpHost\n";
echo "Port: $smtpPort\n";
echo "User: $smtpUser\n";
echo "Pass: " . str_repeat('*', strlen($smtpPass)) . " (" . strlen($smtpPass) . " chars)\n";
echo "Pass raw: [$smtpPass]\n";
echo "From: $fromEmail ($fromName)\n";
echo "\n=== Attempting to send test email ===\n";

try {
    $mail = new PHPMailer(true);
    $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Full debug output
    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $smtpPort;

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($smtpUser); // Send to self for testing

    $mail->isHTML(true);
    $mail->Subject = 'DriveNow SMTP Test';
    $mail->Body    = '<h1>SMTP Test Successful!</h1><p>If you see this, Gmail SMTP is working.</p>';
    $mail->AltBody = 'SMTP Test Successful!';

    $mail->send();
    echo "\n✅ SUCCESS! Email sent!\n";
} catch (Exception $e) {
    echo "\n❌ FAILED: {$mail->ErrorInfo}\n";
    echo "Exception: {$e->getMessage()}\n";
}
