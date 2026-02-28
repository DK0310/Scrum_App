<?php
/**
 * Auth API - DriveNow
 * Handles: Google OAuth, Email OTP, Phone OTP, Registration, Profile, Face ID (optional)
 * 
 * Endpoints (via action parameter):
 *   POST google-login      - Login/Register with Google
 *   POST phone-send-otp    - Send OTP to phone number
 *   POST phone-verify-otp  - Verify phone OTP and login
 *   POST email-send-otp    - Send OTP to email (PHPMailer/Gmail SMTP)
 *   POST email-verify-otp  - Verify email OTP
 *   POST register           - Create account (after email verification, with password)
 *   POST login              - Login with email/username + password
 *   POST complete-profile   - Complete user profile after first login
 *   POST enable-faceid      - Enable Face ID (optional)
 *   POST disable-faceid     - Disable Face ID
 *   POST faceid-login       - Login via Face ID
 *   POST check-session      - Check if user is logged in
 *   POST get-profile        - Get user profile
 *   POST update-profile     - Update user profile
 *   POST logout             - Logout
 */

// ===== SERVE AVATAR IMAGE (before JSON headers) =====
$preAction = $_GET['action'] ?? '';
if ($preAction === 'get-avatar') {
    session_start();
    require_once __DIR__ . '/../Database/db.php';

    $targetUserId = $_GET['id'] ?? '';
    if (empty($targetUserId)) {
        http_response_code(400);
        echo 'User ID required';
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT avatar_data, avatar_mime FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !$row['avatar_data']) {
            http_response_code(404);
            echo 'Avatar not found';
            exit;
        }

        $imageData = $row['avatar_data'];
        if (is_resource($imageData)) {
            $imageData = stream_get_contents($imageData);
        }
        if (is_string($imageData) && substr($imageData, 0, 2) === '\\x') {
            $imageData = hex2bin(substr($imageData, 2));
        }

        header('Content-Type: ' . ($row['avatar_mime'] ?? 'image/jpeg'));
        header('Content-Length: ' . strlen($imageData));
        header('Cache-Control: public, max-age=3600');
        echo $imageData;
    } catch (PDOException $e) {
        http_response_code(500);
        echo 'Server error';
    }
    exit;
}

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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Run migration: add avatar_data/avatar_mime if not exist
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_data BYTEA");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_mime VARCHAR(50)");
} catch (PDOException $e) { /* ignore */ }

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
        $stmt = $pdo->prepare("SELECT id FROM users WHERE $field = ? LIMIT 1");
        $stmt->execute([$value]);
        $exists = (bool)$stmt->fetch();
        echo json_encode(['success' => true, 'exists' => $exists]);
    } catch (PDOException $e) {
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
        $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR email = ? LIMIT 1");
        $stmt->execute([$googleId, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $isNewUser = false;

        if ($user) {
            // Existing user - update google_id if not set, update last login
            if (empty($user['google_id'])) {
                $upd = $pdo->prepare("UPDATE users SET google_id = ?, auth_provider = 'google', last_login_at = NOW() WHERE id = ?");
                $upd->execute([$googleId, $user['id']]);
            } else {
                $upd = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                $upd->execute([$user['id']]);
            }
        } else {
            // New user - register
            $isNewUser = true;
            $stmt = $pdo->prepare("
                INSERT INTO users (email, google_id, auth_provider, full_name, avatar_url, email_verified, last_login_at)
                VALUES (?, ?, 'google', ?, ?, TRUE, NOW())
                RETURNING *
            ");
            $stmt->execute([$email, $googleId, $fullName, $avatarUrl]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
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
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $isNewUser = false;

        if ($user) {
            // Existing user
            $upd = $pdo->prepare("UPDATE users SET phone_verified = TRUE, last_login_at = NOW() WHERE id = ?");
            $upd->execute([$user['id']]);
        } else {
            // New user via phone
            $isNewUser = true;
            $stmt = $pdo->prepare("
                INSERT INTO users (phone, auth_provider, phone_verified, last_login_at)
                VALUES (?, 'phone', TRUE, NOW())
                RETURNING *
            ");
            $stmt->execute([$phone]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
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
    $fromName = \EnvLoader::get('SMTP_FROM_NAME', 'DriveNow');

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
            $mail->Subject = "DriveNow - Your Verification Code: $otp";
            $mail->Body = "
                <div style='font-family:Inter,Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px;'>
                    <div style='text-align:center;margin-bottom:24px;'>
                        <h1 style='color:#2563eb;font-size:1.5rem;'>🚗 DriveNow</h1>
                    </div>
                    <div style='background:#f8fafc;border-radius:16px;padding:32px;text-align:center;'>
                        <h2 style='color:#1e293b;margin-bottom:8px;'>Email Verification</h2>
                        <p style='color:#64748b;margin-bottom:24px;'>Use this code to verify your email address. It expires in 5 minutes.</p>
                        <div style='background:#fff;border:2px solid #2563eb;border-radius:12px;padding:20px;font-size:2rem;font-weight:800;letter-spacing:8px;color:#2563eb;margin-bottom:24px;'>
                            $otp
                        </div>
                        <p style='color:#94a3b8;font-size:0.813rem;'>If you didn't request this code, please ignore this email.</p>
                    </div>
                    <p style='text-align:center;color:#94a3b8;font-size:0.75rem;margin-top:24px;'>© 2026 DriveNow. All rights reserved.</p>
                </div>
            ";
            $mail->AltBody = "Your DriveNow verification code is: $otp (expires in 5 minutes)";

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
// REGISTER - Create account after email verification
// ==========================================================
if ($action === 'register') {
    $fullName  = trim($input['full_name'] ?? '');
    $email     = trim($input['email'] ?? '');
    $phone     = trim($input['phone'] ?? '');
    $dob       = trim($input['date_of_birth'] ?? '');
    $role      = trim($input['role'] ?? 'renter');
    $address   = trim($input['address'] ?? '');
    $password  = $input['password'] ?? '';

    if (empty($fullName) || empty($email) || empty($phone) || empty($dob)) {
        echo json_encode(['success' => false, 'message' => 'Full name, email, phone and date of birth are required.']);
        exit;
    }

    if (empty($password) || strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit;
    }

    // Admin role can only be set directly in the database
    if (!in_array($role, ['renter', 'owner']) || $role === 'admin') {
        echo json_encode(['success' => false, 'message' => 'Role must be "renter" or "owner".']);
        exit;
    }

    try {
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'An account with this email already exists. Please sign in.']);
            exit;
        }

        // Check phone
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'An account with this phone number already exists.']);
            exit;
        }

        // Create user
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, email, phone, date_of_birth, role, address, auth_provider, password_hash, email_verified, profile_completed, last_login_at)
            VALUES (?, ?, ?, ?::DATE, ?::user_role, NULLIF(?, ''), 'email', ?, TRUE, TRUE, NOW())
            RETURNING *
        ");
        $stmt->execute([$fullName, $email, $phone, $dob, $role, $address, $hashedPassword]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Set session
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['profile_completed'] = true;

        // Welcome notification for new user
        createNotification($pdo, $user['id'], 'system', '🎉 Welcome to DriveNow!', 'Your account has been created successfully. Start exploring vehicles to rent or list your own cars!');

        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully! Welcome to DriveNow!',
            'user' => [
                'id' => $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'role' => $user['role'],
                'date_of_birth' => $user['date_of_birth'],
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// LOGIN - Email/Username + Password
// ==========================================================
if ($action === 'login') {
    $identifier = trim($input['identifier'] ?? '');
    $password   = $input['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email/username and password are required.']);
        exit;
    }

    try {
        // Search by email or full_name
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR full_name = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'No account found. Please check your credentials or register.']);
            exit;
        }

        if (empty($user['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'This account uses a different login method (Google/Phone). Please use that method or reset your password.']);
            exit;
        }

        if (!password_verify($password, $user['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Incorrect password. Please try again.']);
            exit;
        }

        // Update last login
        $upd = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        $upd->execute([$user['id']]);

        // Set session
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['full_name'] ?? $user['email'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['profile_completed'] = $user['profile_completed'];

        echo json_encode([
            'success' => true,
            'message' => 'Welcome back, ' . ($user['full_name'] ?? $user['email']) . '!',
            'user' => [
                'id' => $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'role' => $user['role'],
                'avatar_url' => $user['avatar_url'] ?? null,
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// COMPLETE PROFILE (after first login)
// ==========================================================
if ($action === 'complete-profile') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $userId = $_SESSION['user_id'];

    // Required fields
    $fullName = trim($input['full_name'] ?? '');
    $dob      = trim($input['date_of_birth'] ?? '');
    $phone    = trim($input['phone'] ?? '');
    $email    = trim($input['email'] ?? '');
    $role     = trim($input['role'] ?? 'renter'); // 'renter' or 'owner'

    if (empty($fullName) || empty($dob) || (empty($phone) && empty($email))) {
        echo json_encode(['success' => false, 'message' => 'Full name, date of birth, and phone or email are required.']);
        exit;
    }

    // Admin role can only be set directly in the database
    if (!in_array($role, ['renter', 'owner']) || $role === 'admin') {
        echo json_encode(['success' => false, 'message' => 'Role must be "renter" or "owner".']);
        exit;
    }

    // Optional fields
    $address        = trim($input['address'] ?? '');
    $city           = trim($input['city'] ?? '');
    $country        = trim($input['country'] ?? '');
    $drivingLicense = trim($input['driving_license'] ?? '');
    $licenseExpiry  = trim($input['license_expiry'] ?? '');
    $idCardNumber   = trim($input['id_card_number'] ?? '');
    $bio            = trim($input['bio'] ?? '');
    $avatarUrl      = trim($input['avatar_url'] ?? '');

    try {
        $stmt = $pdo->prepare("
            UPDATE users SET
                full_name = ?,
                date_of_birth = ?,
                phone = COALESCE(NULLIF(?, ''), phone),
                email = COALESCE(NULLIF(?, ''), email),
                role = ?::user_role,
                address = NULLIF(?, ''),
                city = NULLIF(?, ''),
                country = NULLIF(?, ''),
                driving_license = NULLIF(?, ''),
                license_expiry = NULLIF(?, '')::DATE,
                id_card_number = NULLIF(?, ''),
                bio = NULLIF(?, ''),
                avatar_url = COALESCE(NULLIF(?, ''), avatar_url),
                profile_completed = TRUE,
                updated_at = NOW()
            WHERE id = ?
            RETURNING *
        ");
        $stmt->execute([
            $fullName, $dob, $phone, $email, $role,
            $address, $city, $country,
            $drivingLicense, $licenseExpiry, $idCardNumber,
            $bio, $avatarUrl,
            $userId
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Update session
        $_SESSION['username'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['profile_completed'] = true;

        echo json_encode([
            'success' => true,
            'message' => 'Profile completed successfully!',
            'user' => [
                'id' => $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'role' => $user['role'],
                'date_of_birth' => $user['date_of_birth'],
                'avatar_url' => $user['avatar_url'],
                'profile_completed' => true,
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
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
        $stmt = $pdo->prepare("
            UPDATE users SET
                face_descriptor = ?::JSONB,
                faceid_enabled = TRUE,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([json_encode($faceDescriptor), $userId]);

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
        $stmt = $pdo->prepare("
            UPDATE users SET
                face_descriptor = NULL,
                faceid_enabled = FALSE,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId]);

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
        $stmt = $pdo->query("SELECT id, full_name, email, phone, role, face_descriptor, profile_completed, avatar_url FROM users WHERE faceid_enabled = TRUE AND face_descriptor IS NOT NULL");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $upd = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        $upd->execute([$bestMatch['id']]);

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
// CHECK SESSION
// ==========================================================
if ($action === 'check-session') {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
        try {
            $stmt = $pdo->prepare("SELECT id, full_name, email, phone, role, avatar_url, profile_completed, faceid_enabled, membership FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                echo json_encode([
                    'success' => true,
                    'logged_in' => true,
                    'profile_completed' => (bool) $user['profile_completed'],
                    'user' => [
                        'id' => $user['id'],
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'phone' => $user['phone'],
                        'role' => $user['role'],
                        'avatar_url' => $user['avatar_url'],
                        'faceid_enabled' => (bool) $user['faceid_enabled'],
                        'membership' => $user['membership'],
                    ]
                ]);
            } else {
                // User not found in DB, clear session
                session_destroy();
                echo json_encode(['success' => true, 'logged_in' => false]);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => true, 'logged_in' => false]);
    }
    exit;
}

// ==========================================================
// GET PROFILE
// ==========================================================
if ($action === 'get-profile') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, email, phone, auth_provider, role, full_name, date_of_birth, avatar_url,
                   address, city, country, driving_license, license_expiry, id_card_number, bio,
                   faceid_enabled, profile_completed, membership, email_verified, phone_verified,
                   created_at, last_login_at
            FROM users WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Cast booleans
            $user['faceid_enabled'] = (bool) $user['faceid_enabled'];
            $user['profile_completed'] = (bool) $user['profile_completed'];
            $user['email_verified'] = (bool) $user['email_verified'];
            $user['phone_verified'] = (bool) $user['phone_verified'];

            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// UPLOAD AVATAR (BLOB storage)
// ==========================================================
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
    $maxSize = 3 * 1024 * 1024; // 3MB

    if (!in_array($file['type'], $allowedTypes)) {
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
        $avatarUrl = '/api/auth.php?action=get-avatar&id=' . $userId . '&t=' . time();

        $stmt = $pdo->prepare("UPDATE users SET avatar_data = :imgdata, avatar_mime = :imgmime, avatar_url = :url, updated_at = NOW() WHERE id = :uid");
        $stmt->bindParam(':imgdata', $imageData, PDO::PARAM_LOB);
        $stmt->bindParam(':imgmime', $file['type']);
        $stmt->bindParam(':url', $avatarUrl);
        $stmt->bindParam(':uid', $userId);
        $stmt->execute();

        // Notification
        createNotification($pdo, $userId, 'system', '📷 Avatar Updated', 'Your profile picture has been updated successfully.');

        echo json_encode([
            'success' => true,
            'message' => 'Avatar updated!',
            'avatar_url' => $avatarUrl
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// EMAIL CHANGE - SEND OTP TO BOTH OLD & NEW EMAIL
// ==========================================================
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
    if ($newEmail === $oldEmail) {
        echo json_encode(['success' => false, 'message' => 'New email must be different from current email.']);
        exit;
    }

    // Check if new email already exists
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $checkStmt->execute([$newEmail, $_SESSION['user_id']]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This email is already registered to another account.']);
        exit;
    }

    // Generate OTPs for both
    $otpOld = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpNew = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // Store in session
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
    $fromName = \EnvLoader::get('SMTP_FROM_NAME', 'DriveNow');

    $sentOld = false;
    $sentNew = false;

    // Helper to send OTP email
    $sendOtpMail = function($toEmail, $otp, $purpose) use ($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromEmail, $fromName) {
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
            $mail->Subject = "DriveNow - Email Change Verification: $otp";
            $mail->Body = "
                <div style='font-family:Inter,Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px;'>
                    <div style='text-align:center;margin-bottom:24px;'>
                        <h1 style='color:#2563eb;font-size:1.5rem;'>🚗 DriveNow</h1>
                    </div>
                    <div style='background:#f8fafc;border-radius:16px;padding:32px;text-align:center;'>
                        <h2 style='color:#1e293b;margin-bottom:8px;'>$purpose</h2>
                        <p style='color:#64748b;margin-bottom:24px;'>Use this code to confirm the email change. It expires in 5 minutes.</p>
                        <div style='background:#fff;border:2px solid #2563eb;border-radius:12px;padding:20px;font-size:2rem;font-weight:800;letter-spacing:8px;color:#2563eb;margin-bottom:24px;'>
                            $otp
                        </div>
                        <p style='color:#94a3b8;font-size:0.813rem;'>If you didn't request this, please ignore this email or contact support.</p>
                    </div>
                </div>
            ";
            $mail->AltBody = "Your DriveNow email change verification code is: $otp (expires in 5 minutes)";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
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

// ==========================================================
// EMAIL CHANGE - VERIFY BOTH OTPS
// ==========================================================
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

    // Check session data exists
    if (!isset($_SESSION['email_change_otp_old']) || !isset($_SESSION['email_change_otp_new'])) {
        echo json_encode(['success' => false, 'message' => 'No pending email change. Please request again.']);
        exit;
    }

    // Check expiry
    if (isset($_SESSION['email_change_expires']) && strtotime($_SESSION['email_change_expires']) < time()) {
        unset($_SESSION['email_change_old_email'], $_SESSION['email_change_new_email'], $_SESSION['email_change_otp_old'], $_SESSION['email_change_otp_new'], $_SESSION['email_change_expires']);
        echo json_encode(['success' => false, 'message' => 'Codes have expired. Please request new ones.']);
        exit;
    }

    // Verify both OTPs
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
        $stmt = $pdo->prepare("UPDATE users SET email = ?, email_verified = true, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newEmail, $userId]);

        // Update session
        $_SESSION['email'] = $newEmail;

        // Clear change session
        unset($_SESSION['email_change_old_email'], $_SESSION['email_change_new_email'], $_SESSION['email_change_otp_old'], $_SESSION['email_change_otp_new'], $_SESSION['email_change_expires']);

        // --- Notification: email changed ---
        createNotification($pdo, $userId, 'system',
            '📧 Email Changed',
            "Your email has been changed to {$newEmail}."
        );

        echo json_encode(['success' => true, 'message' => 'Email changed successfully!', 'new_email' => $newEmail]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// UPDATE PROFILE
// ==========================================================
if ($action === 'update-profile') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $userId = $_SESSION['user_id'];

    // Only update provided fields
    $updates = [];
    $params = [];

    $allowedFields = [
        'full_name', 'date_of_birth', 'phone', 'email', 'address', 'city',
        'country', 'driving_license', 'license_expiry', 'id_card_number', 'bio', 'avatar_url'
    ];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = trim($input[$field]) ?: null;
        }
    }

    // Role change
    if (isset($input['role']) && in_array($input['role'], ['renter', 'owner'])) {
        $updates[] = "role = ?::user_role";
        $params[] = $input['role'];
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        exit;
    }

    $updates[] = "updated_at = NOW()";
    $params[] = $userId;

    try {
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ? RETURNING *";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Update session
        $_SESSION['username'] = $user['full_name'] ?? $_SESSION['username'];
        $_SESSION['full_name'] = $user['full_name'] ?? $_SESSION['full_name'] ?? $_SESSION['username'];
        $_SESSION['email'] = $user['email'] ?? $_SESSION['email'];
        $_SESSION['role'] = $user['role'];

        // --- Notification: profile updated ---
        $changedFields = [];
        if (isset($input['full_name'])) $changedFields[] = 'name';
        if (isset($input['phone'])) $changedFields[] = 'phone';
        if (isset($input['address'])) $changedFields[] = 'address';
        if (isset($input['role'])) $changedFields[] = 'role to ' . $input['role'];
        if (isset($input['bio'])) $changedFields[] = 'bio';
        $fieldsSummary = !empty($changedFields) ? implode(', ', $changedFields) : 'profile info';
        createNotification($pdo, $userId, 'system',
            '✏️ Profile Updated',
            "Your profile has been updated: {$fieldsSummary}."
        );

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

// ==========================================================
// LOGOUT
// ==========================================================
if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
    exit;
}

// ==========================================================
// UNKNOWN ACTION
// ==========================================================
echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
