<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/UserRepository.php';
require_once __DIR__ . '/notification-helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$userRepo = new UserRepository($pdo);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

if ($action === 'email-change-send-otp') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $newEmail = trim($input['new_email'] ?? '');
    $oldEmail = trim($input['old_email'] ?? '');

    if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Valid new email is required.']);
        exit;
    }
    if (empty($oldEmail) || !filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Valid old email is required.']);
        exit;
    }
    if (strcasecmp($newEmail, $oldEmail) === 0) {
        echo json_encode(['success' => false, 'message' => 'New email must be different from current email.']);
        exit;
    }

    if ($userRepo->emailExists($newEmail)) {
        echo json_encode(['success' => false, 'message' => 'This email is already registered to another account.']);
        exit;
    }

    $otpOld = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpNew = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    $_SESSION['email_change_old_email'] = $oldEmail;
    $_SESSION['email_change_new_email'] = $newEmail;
    $_SESSION['email_change_otp_old'] = $otpOld;
    $_SESSION['email_change_otp_new'] = $otpNew;
    $_SESSION['email_change_expires'] = $expiresAt;

    $smtpHost = \EnvLoader::get('SMTP_HOST', 'smtp.gmail.com');
    $smtpPort = (int) \EnvLoader::get('SMTP_PORT', 587);
    $smtpUser = \EnvLoader::get('SMTP_USERNAME', '');
    $smtpPass = \EnvLoader::get('SMTP_PASSWORD', '');
    $fromEmail = \EnvLoader::get('SMTP_FROM_EMAIL', $smtpUser);
    $fromName = \EnvLoader::get('SMTP_FROM_NAME', 'PrivateHire');

    $sendOtpMail = function(string $toEmail, string $otp, string $purpose) use ($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromEmail, $fromName): bool {
        try {
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
            $mail->Subject = "DriveNow - Email Change Verification: {$otp}";
            $mail->Body = "<p>{$purpose}</p><p>Your code is: <strong>{$otp}</strong></p><p>Expires in 5 minutes.</p>";
            $mail->AltBody = "{$purpose}. Your code is {$otp}. Expires in 5 minutes.";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('PHPMailer Error: ' . $e->getMessage());
            return false;
        }
    };

    $sentOld = $sendOtpMail($oldEmail, $otpOld, 'Confirm Email Change (Current Email)');
    $sentNew = $sendOtpMail($newEmail, $otpNew, 'Verify New Email Address');

    if ($sentOld && $sentNew) {
        echo json_encode(['success' => true, 'message' => 'Verification codes sent to both emails.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send verification emails. Please check SMTP configuration.']);
    }
    exit;
}

if ($action === 'email-change-verify') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $otpOld = trim($input['otp_old'] ?? '');
    $otpNew = trim($input['otp_new'] ?? '');

    if (empty($otpOld) || empty($otpNew)) {
        echo json_encode(['success' => false, 'message' => 'Both OTP codes are required.']);
        exit;
    }

    if (!isset($_SESSION['email_change_otp_old'], $_SESSION['email_change_otp_new'])) {
        echo json_encode(['success' => false, 'message' => 'No pending email change. Please request again.']);
        exit;
    }

    if (isset($_SESSION['email_change_expires']) && strtotime($_SESSION['email_change_expires']) < time()) {
        unset($_SESSION['email_change_old_email'], $_SESSION['email_change_new_email'], $_SESSION['email_change_otp_old'], $_SESSION['email_change_otp_new'], $_SESSION['email_change_expires']);
        echo json_encode(['success' => false, 'message' => 'Codes have expired. Please request new ones.']);
        exit;
    }

    if ($_SESSION['email_change_otp_old'] !== $otpOld) {
        echo json_encode(['success' => false, 'message' => 'Invalid code for current email.']);
        exit;
    }
    if ($_SESSION['email_change_otp_new'] !== $otpNew) {
        echo json_encode(['success' => false, 'message' => 'Invalid code for new email.']);
        exit;
    }

    $newEmail = $_SESSION['email_change_new_email'];
    $userId = $_SESSION['user_id'];

    try {
        $userRepo->updateProfile($userId, ['email' => $newEmail]);
        $_SESSION['email'] = $newEmail;

        unset($_SESSION['email_change_old_email'], $_SESSION['email_change_new_email'], $_SESSION['email_change_otp_old'], $_SESSION['email_change_otp_new'], $_SESSION['email_change_expires']);

        createNotification($pdo, $userId, 'system', '📧 Email Changed', "Your email has been changed to {$newEmail}.");

        echo json_encode(['success' => true, 'message' => 'Email changed successfully!', 'new_email' => $newEmail]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
