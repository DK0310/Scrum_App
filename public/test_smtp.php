<?php
/**
 * Quick SMTP test via browser
 */
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../vendor/autoload.php';

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
echo "Pass length: " . strlen($smtpPass) . " chars\n";
echo "Pass value: [$smtpPass]\n";
echo "From: $fromEmail ($fromName)\n";
echo "\n=== Attempting SMTP connection ===\n";
ob_flush(); flush();

try {
    $mail = new PHPMailer(true);
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->Debugoutput = function($str, $level) {
        echo "DEBUG[$level]: $str";
        ob_flush(); flush();
    };
    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $smtpPort;
    $mail->Timeout    = 15;

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($smtpUser);

    $mail->isHTML(true);
    $mail->Subject = 'DriveNow SMTP Test';
    $mail->Body    = '<h1>Test OK!</h1>';
    $mail->AltBody = 'Test OK!';

    $mail->send();
    echo "\n\n=== RESULT: SUCCESS! Email sent! ===\n";
} catch (Exception $e) {
    echo "\n\n=== RESULT: FAILED ===\n";
    echo "Error: {$mail->ErrorInfo}\n";
    echo "Exception: {$e->getMessage()}\n";
}
