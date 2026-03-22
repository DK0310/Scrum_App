<?php
/**
 * Simple mail helper (PHPMailer) to send HTML emails with optional PDF attachment.
 */

require_once __DIR__ . '/../config/env.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    throw new Exception('Missing vendor/autoload.php. Run composer install.');
}
require_once $autoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * @param string $toEmail
 * @param string $subject
 * @param string $htmlBody
 * @param array{content:string, filename:string, mime:string}|null $attachment
 */
function privatehire_send_email(string $toEmail, string $subject, string $htmlBody, ?array $attachment = null): void {
    $smtpHost = \EnvLoader::get('SMTP_HOST', 'smtp.gmail.com');
    $smtpPort = (int) \EnvLoader::get('SMTP_PORT', 587);
    $smtpUser = \EnvLoader::get('SMTP_USERNAME', '');
    $smtpPass = \EnvLoader::get('SMTP_PASSWORD', '');

    if (empty($smtpUser) || empty($smtpPass)) {
        throw new Exception('SMTP is not configured (SMTP_USERNAME/SMTP_PASSWORD).');
    }

    $fromEmail = \EnvLoader::get('SMTP_FROM_EMAIL', $smtpUser);
    $fromName  = \EnvLoader::get('SMTP_FROM_NAME', 'PrivateHire');

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtpPort;

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($toEmail);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

    if ($attachment) {
        $mail->addStringAttachment(
            $attachment['content'],
            $attachment['filename'],
            'base64',
            $attachment['mime']
        );
    }

    $mail->send();
}
