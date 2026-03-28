<?php
/**
 * Auth API - Private Hire
 * Handles: Google OAuth, Email OTP, Phone OTP, Face ID, Email Change
 * 
 * Endpoints (via action parameter):
 *   POST google-login           - Login/Register with Google
 *   POST phone-send-otp         - Send OTP to phone number
 *   POST phone-verify-otp       - Verify phone OTP and login
 *   POST email-send-otp         - Send OTP to email (PHPMailer/Gmail SMTP)
 *   POST email-verify-otp       - Verify email OTP
 *   POST check-duplicate        - Check if email/phone exists
 *   POST enable-faceid          - Enable Face ID
 *   POST disable-faceid         - Disable Face ID
 *   POST faceid-login           - Login via Face ID
 *   POST email-change-send-otp  - Send OTP for email change (both old & new)
 *   POST email-change-verify    - Verify email change with both OTPs
 * 
 * Note: Login, register, session management, and profile management moved to:
 *   - login.php (login action)
 *   - register.php (register, send-otp, verify-otp actions)
 *   - session.php (check-session, logout actions)
 *   - profile.php (get-profile, update-profile, upload-avatar, get-avatar actions)
 */

// ===== JSON API =====
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/notification-helpers.php';
require_once __DIR__ . '/supabase-storage.php';
require_once __DIR__ . '/../lib/repositories/UserRepository.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Instantiate UserRepository
$userRepo = new UserRepository($pdo);

// Handle both JSON and multipart form data
if (isset($_FILES['avatar'])) {
    $input = $_POST;
    if (!isset($input['action'])) $input['action'] = 'upload-avatar';
} else {
    $input = json_decode(file_get_contents('php://input'), true);
}

if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request. Action required.']);
    exit;
}

$action = $input['action'];

// ==========================================================
// CHECK DUPLICATE (email / phone) - Realtime validation
// ==========================================================
if ($action === 'check-duplicate') {
    $field = trim($input['field'] ?? '');
    $value = trim($input['value'] ?? '');

    if (!in_array($field, ['email', 'phone'])) {
        echo json_encode(['success' => false, 'message' => 'Field must be email or phone.']);
        exit;
    }
    if (empty($value)) {
        echo json_encode(['success' => true, 'exists' => false]);
        exit;
    }

    try {
        $exists = $userRepo->existsByField($field, $value);
        echo json_encode(['success' => true, 'exists' => $exists]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

// ==========================================================
// GOOGLE LOGIN / REGISTER
// ==========================================================
if ($action === 'google-login') {
    $googleId    = trim($input['google_id'] ?? '');
    $email       = trim($input['email'] ?? '');
    $fullName    = trim($input['full_name'] ?? '');
    $avatarUrl   = trim($input['avatar_url'] ?? '');

    if (empty($googleId) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Google ID and email are required.']);
        exit;
    }

    try {
        // Check if user already exists by google_id or email
        $user = $userRepo->findByGoogleIdOrEmail($googleId, $email);

        $isNewUser = false;

        if ($user) {
            // Existing user - update google_id if not set, update last login
            if (empty($user['google_id'])) {
                $userRepo->updateGoogleIdAndSetProvider($user['id'], $googleId);
            } else {
                $userRepo->touchLastLogin($user['id']);
            }
        } else {
            // New user - register
            $isNewUser = true;
            $user = $userRepo->createGoogleUser($email, $googleId, $fullName, $avatarUrl);
            if (!$user) {
                throw new RuntimeException('Failed to create user');
            }
        }

        // Set session
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['full_name'] ?? $user['email'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['profile_completed'] = $user['profile_completed'];

        echo json_encode([
            'success' => true,
            'message' => $isNewUser ? 'Account created successfully!' : 'Welcome back!',
            'is_new_user' => $isNewUser,
            'profile_completed' => (bool) $user['profile_completed'],
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'avatar_url' => $user['avatar_url'],
                'role' => $user['role'],
                'faceid_enabled' => (bool) $user['faceid_enabled'],
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Server error.']);
    }
    exit;
}

// ==========================================================
// PHONE - SEND OTP
// ==========================================================
if ($action === 'phone-send-otp') {
    $phone = trim($input['phone'] ?? '');

    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
        exit;
    }

    // Generate 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // Store OTP in session (in production: use SMS service like Twilio)
    $_SESSION['otp_phone'] = $phone;
    $_SESSION['otp_code'] = $otp;
    $_SESSION['otp_expires'] = $expiresAt;

    // TODO: In production, send OTP via SMS (Twilio, Vonage, etc.)
    // For development, return the OTP in response
    $isDev = \EnvLoader::get('APP_ENV', 'development') === 'development';

    echo json_encode([
        'success' => true,
        'message' => 'OTP sent to ' . $phone . '. Valid for 5 minutes.',
        'dev_otp' => $isDev ? $otp : null,  // Only show OTP in dev mode
    ]);
    exit;
}

// ==========================================================
// PHONE - VERIFY OTP & LOGIN
// ==========================================================
if ($action === 'phone-verify-otp') {
    $phone = trim($input['phone'] ?? '');
    $otp   = trim($input['otp'] ?? '');

    if (empty($phone) || empty($otp)) {
        echo json_encode(['success' => false, 'message' => 'Phone and OTP are required.']);
        exit;
    }

    // Verify OTP
    if (
        !isset($_SESSION['otp_phone']) ||
        $_SESSION['otp_phone'] !== $phone ||
        $_SESSION['otp_code'] !== $otp
    ) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP code.']);
        exit;
    }

    // Check expiry
    if (isset($_SESSION['otp_expires']) && strtotime($_SESSION['otp_expires']) < time()) {
        unset($_SESSION['otp_phone'], $_SESSION['otp_code'], $_SESSION['otp_expires']);
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
        exit;
    }

    // Clear OTP from session
    unset($_SESSION['otp_phone'], $_SESSION['otp_code'], $_SESSION['otp_expires']);

    try {
        // Check if user exists by phone
        $user = $userRepo->findByPhone($phone);

        $isNewUser = false;

        if ($user) {
            // Existing user
            $userRepo->markPhoneVerifiedAndTouchLastLogin($user['id']);
        } else {
            // New user via phone
            $isNewUser = true;
            $user = $userRepo->createPhoneUser($phone);
            if (!$user) {
                throw new RuntimeException('Failed to create user');
            }
        }

        // Set session
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['full_name'] ?? $user['phone'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['phone'] = $user['phone'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['profile_completed'] = $user['profile_completed'];

        echo json_encode([
            'success' => true,
            'message' => $isNewUser ? 'Account created! Please complete your profile.' : 'Welcome back!',
            'is_new_user' => $isNewUser,
            'profile_completed' => (bool) $user['profile_completed'],
            'user' => [
                'id' => $user['id'],
                'phone' => $user['phone'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'faceid_enabled' => (bool) $user['faceid_enabled'],
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Server error.']);
    }
    exit;
}

// ==========================================================
// EMAIL - SEND OTP (via PHPMailer / Gmail SMTP)
// ==========================================================
if ($action === 'email-send-otp') {
    $email = trim($input['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
        exit;
    }

    // Generate 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // Store OTP in session
    $_SESSION['email_otp_address'] = $email;
    $_SESSION['email_otp_code'] = $otp;
    $_SESSION['email_otp_expires'] = $expiresAt;

    $isDev = \EnvLoader::get('APP_ENV', 'development') === 'development';

    // Send OTP via PHPMailer
    $smtpHost = \EnvLoader::get('SMTP_HOST', 'smtp.gmail.com');
    $smtpPort = (int) \EnvLoader::get('SMTP_PORT', 587);
    $smtpUser = \EnvLoader::get('SMTP_USERNAME', '');
    $smtpPass = \EnvLoader::get('SMTP_PASSWORD', '');
    $fromEmail = \EnvLoader::get('SMTP_FROM_EMAIL', $smtpUser);
    $fromName = \EnvLoader::get('SMTP_FROM_NAME', 'PrivateHire');

    $emailSent = false;

    if (!empty($smtpUser) && !empty($smtpPass) && $smtpUser !== 'your_gmail@gmail.com') {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtpPort;

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = "PrivateHire - Your Verification Code: $otp";
            $mail->Body = "
                <div style='font-family:Inter,Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px;'>
                    <div style='text-align:center;margin-bottom:24px;'>
                        <h1 style='color:#2563eb;font-size:1.5rem;'>🚗 PrivateHire</h1>
                    </div>
                    <div style='background:#f8fafc;border-radius:16px;padding:32px;text-align:center;'>
                        <h2 style='color:#1e293b;margin-bottom:8px;'>Email Verification</h2>
                        <p style='color:#64748b;margin-bottom:24px;'>Use this code to verify your email address. It expires in 5 minutes.</p>
                        <div style='background:#fff;border:2px solid #2563eb;border-radius:12px;padding:20px;font-size:2rem;font-weight:800;letter-spacing:8px;color:#2563eb;margin-bottom:24px;'>
                            $otp
                        </div>
                        <p style='color:#94a3b8;font-size:0.813rem;'>If you didn't request this code, please ignore this email.</p>
                    </div>
                    <p style='text-align:center;color:#94a3b8;font-size:0.75rem;margin-top:24px;'>© 2026 PrivateHire. All rights reserved.</p>
                </div>
            ";
            $mail->AltBody = "Your PrivateHire verification code is: $otp (expires in 5 minutes)";

            $mail->send();
            $emailSent = true;
        } catch (Exception $e) {
            // Email sending failed
            error_log("PHPMailer Error: " . $e->getMessage());
            error_log("PHPMailer Debug: SMTP Host=$smtpHost, Port=$smtpPort, User=$smtpUser");
        }
    }

    echo json_encode([
        'success' => $emailSent,
        'message' => $emailSent
            ? "Verification code sent to $email. Check your inbox (and spam folder)."
            : "Failed to send verification email. Please check SMTP configuration.",
        'email_sent' => $emailSent,
    ]);
    exit;
}

// ==========================================================
// EMAIL - VERIFY OTP
// ==========================================================
if ($action === 'email-verify-otp') {
    $email = trim($input['email'] ?? '');
    $otp   = trim($input['otp'] ?? '');

    if (empty($email) || empty($otp)) {
        echo json_encode(['success' => false, 'message' => 'Email and OTP are required.']);
        exit;
    }

    // Verify OTP
    if (
        !isset($_SESSION['email_otp_address']) ||
        $_SESSION['email_otp_address'] !== $email ||
        $_SESSION['email_otp_code'] !== $otp
    ) {
        echo json_encode(['success' => false, 'message' => 'Invalid verification code.']);
        exit;
    }

    // Check expiry
    if (isset($_SESSION['email_otp_expires']) && strtotime($_SESSION['email_otp_expires']) < time()) {
        unset($_SESSION['email_otp_address'], $_SESSION['email_otp_code'], $_SESSION['email_otp_expires']);
        echo json_encode(['success' => false, 'message' => 'Code has expired. Please request a new one.']);
        exit;
    }

    // Clear OTP from session
    unset($_SESSION['email_otp_address'], $_SESSION['email_otp_code'], $_SESSION['email_otp_expires']);

    echo json_encode([
        'success' => true,
        'message' => 'Email verified successfully!',
        'email' => $email,
        'verified' => true
    ]);
    exit;
}

// ==========================================================
// ENABLE FACE ID (optional, user chooses to enable)
// ==========================================================
if ($action === 'enable-faceid') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $faceDescriptor = $input['face_descriptor'] ?? null;

    if (!$faceDescriptor || !is_array($faceDescriptor)) {
        echo json_encode(['success' => false, 'message' => 'Face descriptor data is required.']);
        exit;
    }

    try {
        // Check if this face matches any OTHER user's face descriptor
        $existingUsers = $userRepo->getOtherUsersWithFaceId($userId);

        $duplicateThreshold = 0.45; // Stricter threshold for duplicate detection
        foreach ($existingUsers as $existing) {
            $storedDescriptor = json_decode($existing['face_descriptor'], true);
            if (!is_array($storedDescriptor)) continue;

            $distance = 0;
            for ($i = 0; $i < min(count($faceDescriptor), count($storedDescriptor)); $i++) {
                $distance += pow($faceDescriptor[$i] - $storedDescriptor[$i], 2);
            }
            $distance = sqrt($distance);

            if ($distance < $duplicateThreshold) {
                echo json_encode([
                    'success' => false,
                    'message' => 'This face is already registered to another account ("' . ($existing['full_name'] ?? 'Unknown') . '"). Each face can only be linked to one account.',
                    'duplicate' => true
                ]);
                exit;
            }
        }

        $userRepo->enableFaceId($userId, json_encode($faceDescriptor));

        // Notification
        createNotification($pdo, $userId, 'system', '🔐 Face ID Enabled', 'Face ID has been enabled on your account. You can now log in using facial recognition.');

        echo json_encode([
            'success' => true,
            'message' => 'Face ID enabled successfully! You can now log in with your face.'
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// DISABLE FACE ID
// ==========================================================
if ($action === 'disable-faceid') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $userId = $_SESSION['user_id'];

    try {
        $userRepo->disableFaceId($userId);

        // Notification
        createNotification($pdo, $userId, 'system', '🔓 Face ID Disabled', 'Face ID has been removed from your account. You can re-enable it anytime from your profile settings.');

        echo json_encode(['success' => true, 'message' => 'Face ID disabled.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// FACE ID LOGIN
// ==========================================================
if ($action === 'faceid-login') {
    $faceDescriptor = $input['face_descriptor'] ?? null;

    if (!$faceDescriptor || !is_array($faceDescriptor)) {
        echo json_encode(['success' => false, 'message' => 'Face descriptor data is required.']);
        exit;
    }

    try {
        // Get all users with Face ID enabled
        $users = $userRepo->getAllWithFaceId();

        if (empty($users)) {
            echo json_encode(['success' => false, 'message' => 'No users have Face ID enabled.']);
            exit;
        }

        $bestMatch = null;
        $bestScore = PHP_FLOAT_MAX;
        $threshold = 0.6;

        foreach ($users as $user) {
            $storedDescriptor = json_decode($user['face_descriptor'], true);
            if (!is_array($storedDescriptor)) continue;

            // Euclidean distance
            $distance = 0;
            for ($i = 0; $i < min(count($faceDescriptor), count($storedDescriptor)); $i++) {
                $distance += pow($faceDescriptor[$i] - $storedDescriptor[$i], 2);
            }
            $distance = sqrt($distance);

            if ($distance < $bestScore) {
                $bestScore = $distance;
                $bestMatch = $user;
            }
        }

        if (!$bestMatch || $bestScore > $threshold) {
            echo json_encode([
                'success' => false,
                'message' => 'Face not recognized. Please try again or use another login method.',
                'score' => round($bestScore, 4)
            ]);
            exit;
        }

        $matchPercent = round((1 - $bestScore) * 100, 1);

        // Set session
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $bestMatch['id'];
        $_SESSION['username'] = $bestMatch['full_name'] ?? $bestMatch['email'] ?? $bestMatch['phone'];
        $_SESSION['email'] = $bestMatch['email'];
        $_SESSION['role'] = $bestMatch['role'];
        $_SESSION['profile_completed'] = $bestMatch['profile_completed'];
        // Update last login
        $userRepo->updateLastLogin($bestMatch['id']);

        echo json_encode([
            'success' => true,
            'message' => "Welcome back, {$bestMatch['full_name']}!",
            'match_score' => $matchPercent . '%',
            'profile_completed' => (bool) $bestMatch['profile_completed'],
            'user' => [
                'id' => $bestMatch['id'],
                'full_name' => $bestMatch['full_name'],
                'email' => $bestMatch['email'],
                'role' => $bestMatch['role'],
                'avatar_url' => $bestMatch['avatar_url'],
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// UNKNOWN ACTION
// ==========================================================
echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
