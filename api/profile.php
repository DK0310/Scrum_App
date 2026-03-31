<?php
/**
 * Profile Page & API - Private Hire
 * Route controller for profile page view + API endpoints
 * If no action param → render profile page
 * If action param → handle API request
 */

require_once __DIR__ . '/bootstrap.php';

$input = api_init();
$action = api_action($input);

// get-avatar remains a non-JSON file redirect endpoint.
$preAction = $_GET['action'] ?? '';
if (empty($action) && $preAction !== 'get-avatar') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Page controller moved to /profile.php.',
        'moved_to' => '/profile.php'
    ]);
    exit;
}

// ===== API MODE or AVATAR DOWNLOAD =====
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/UserRepository.php';
require_once __DIR__ . '/supabase-storage.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$userRepo = new UserRepository($pdo);

// Handle get-avatar special case (file download, not JSON API)
if ($preAction === 'get-avatar') {
    $targetUserId = $_GET['id'] ?? '';
    if (empty($targetUserId)) {
        http_response_code(400);
        echo 'User ID required';
        exit;
    }

    try {
        $row = $userRepo->getAvatarInfo($targetUserId);
        if (!$row) {
            http_response_code(404);
            echo 'Avatar not found';
            exit;
        }

        // Determine which URL to use for redirect
        $redirectUrl = null;
        
        // 1. Try avatar_storage_path first (Supabase Storage)
        if (!empty($row['avatar_storage_path'])) {
            try {
                $storage = new SupabaseStorage();
                $redirectUrl = $storage->getPublicUrl($row['avatar_storage_path']);
            } catch (Exception $e) {
                error_log("Supabase error: " . $e->getMessage());
                $redirectUrl = null;
            }
        }
        
        // 2. Fallback to avatar_url if storage path fails or doesn't exist
        if (empty($redirectUrl) && !empty($row['avatar_url'])) {
            $redirectUrl = $row['avatar_url'];
        }
        
        if (empty($redirectUrl)) {
            http_response_code(404);
            echo 'Avatar not found';
            exit;
        }

        // Redirect with cache busting
        header('Location: ' . $redirectUrl, true, 302);
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    } catch (Exception $e) {
        error_log("Get-avatar error: " . $e->getMessage());
        http_response_code(500);
        echo 'Server error';
    }
    exit;
}

// ===== JSON API RESPONSE MODE =====
require_once __DIR__ . '/notification-helpers.php';

// Re-parse input for API mode (handles file uploads)
if (isset($_FILES['avatar'])) {
    $input = $_POST;
    if (!isset($input['action'])) {
        $input['action'] = 'upload-avatar';
    }
} else {
    $input = is_array($input) ? $input : $_POST;
}
$action = api_action($input);

// Require authentication for API requests
if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

if ($action === 'get-profile') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    try {
        $user = $userRepo->getFullProfile($_SESSION['user_id']);
        if ($user) {
            $user['faceid_enabled'] = (bool) ($user['faceid_enabled'] ?? false);
            $user['profile_completed'] = (bool) ($user['profile_completed'] ?? false);
            $user['email_verified'] = (bool) ($user['email_verified'] ?? false);
            $user['phone_verified'] = (bool) ($user['phone_verified'] ?? false);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'update-profile') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $updates = [];
    $allowedFields = [
        'full_name', 'date_of_birth', 'phone', 'email', 'address', 'city',
        'country', 'driving_license', 'license_expiry', 'id_card_number', 'bio', 'avatar_url'
    ];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[$field] = trim((string) $input[$field]) ?: null;
        }
    }

    if (isset($input['role']) && in_array($input['role'], ['user', 'driver', 'callcenterstaff', 'controlstaff'], true)) {
        $updates['role'] = $input['role'];
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        exit;
    }

    try {
        $user = $userRepo->updateProfile($userId, $updates);

        $_SESSION['username'] = $user['full_name'] ?? ($_SESSION['username'] ?? null);
        $_SESSION['email'] = $user['email'] ?? ($_SESSION['email'] ?? null);
        $_SESSION['role'] = $user['role'] ?? ($_SESSION['role'] ?? null);

        $changedFields = [];
        if (isset($updates['full_name'])) $changedFields[] = 'name';
        if (isset($updates['phone'])) $changedFields[] = 'phone';
        if (isset($updates['address'])) $changedFields[] = 'address';
        if (isset($updates['role'])) $changedFields[] = 'role to ' . $updates['role'];
        if (isset($updates['bio'])) $changedFields[] = 'bio';
        $fieldsSummary = !empty($changedFields) ? implode(', ', $changedFields) : 'profile info';
        createNotification($pdo, $userId, 'system', '✏️ Profile Updated', "Your profile has been updated: {$fieldsSummary}.");

        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully!',
            'user' => [
                'id' => $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'role' => $user['role'],
                'avatar_url' => $user['avatar_url'],
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'upload-avatar') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No avatar file provided.']);
        exit;
    }

    $file = $_FILES['avatar'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize = 3 * 1024 * 1024;

    if (!in_array($file['type'], $allowedTypes, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, WebP, GIF allowed.']);
        exit;
    }
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'Image too large. Max 3MB.']);
        exit;
    }

    $imageData = file_get_contents($file['tmp_name']);
    if ($imageData === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to read uploaded file.']);
        exit;
    }

    try {
        $userId = $_SESSION['user_id'];
        $storage = new SupabaseStorage();
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
        $storagePath = 'avatars/' . $userId . '.' . $ext;

        $uploadResult = $storage->upload($storagePath, $imageData, $file['type'], true);
        if (!$uploadResult['success']) {
            echo json_encode(['success' => false, 'message' => 'Storage upload failed: ' . ($uploadResult['message'] ?? 'Unknown error')]);
            exit;
        }

        $publicUrl = $uploadResult['public_url'] . '?t=' . time();
        $avatarUrl = '/api/profile.php?action=get-avatar&id=' . $userId . '&t=' . time();
        
        // Update database with new avatar paths
        try {
            $userRepo->updateAvatar($userId, $storagePath, $avatarUrl);
        } catch (PDOException $dbError) {
            // Check if avatar_storage_path column exists (migration check)
            if (strpos($dbError->getMessage(), 'avatar_storage_path') !== false) {
                // Migration not run yet - just update avatar_url as fallback
                error_log("⚠️ avatar_storage_path column not found. Running migration: ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_storage_path TEXT;");
                $userRepo->updateAvatarUrlOnly($userId, $avatarUrl);
            } else {
                throw $dbError;
            }
        }

        createNotification($pdo, $userId, 'system', '📷 Avatar Updated', 'Your profile picture has been updated successfully.');

        echo json_encode([
            'success' => true,
            'message' => 'Avatar updated!',
            'avatar_url' => $avatarUrl,
            'public_url' => $publicUrl
        ]);
    } catch (Exception $e) {
        error_log("Avatar upload error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'email-change-send-otp') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $newEmail = trim($input['new_email'] ?? '');
    $oldEmail = trim($input['old_email'] ?? '');
    $currentEmail = trim((string)($_SESSION['email'] ?? ''));

    if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Valid new email is required.']);
        exit;
    }
    if (empty($oldEmail) || !filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Valid old email is required.']);
        exit;
    }
    if (!empty($currentEmail) && strcasecmp($oldEmail, $currentEmail) !== 0) {
        echo json_encode(['success' => false, 'message' => 'Current email does not match session account.']);
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
