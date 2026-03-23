<?php
session_start();
header('Content-Type: application/json');

require_once '../Database/db.php';
require_once '../sql/AuthRepository.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $authRepo = new AuthRepository($pdo);

    if ($action === 'send-otp') {
        // This handles STEP 1 -> STEP 2: Validate form & send OTP
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $role = trim($_POST['role'] ?? 'user');

        // Validation
        if (empty($email) || empty($phone) || empty($password) || empty($username) || empty($dob)) {
            $response['message'] = 'All fields are required';
            echo json_encode($response);
            exit;
        }

        // Validate password
        if (!$authRepo->isValidPassword($password)) {
            $response['message'] = 'Password must be at least 6 characters';
            echo json_encode($response);
            exit;
        }

        // Validate email
        if (!$authRepo->isValidEmail($email)) {
            $response['message'] = 'Invalid email format';
            echo json_encode($response);
            exit;
        }

        if (empty($dob) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $response['message'] = 'Date of birth is required';
            echo json_encode($response);
            exit;
        }

        if (!$authRepo->isAdult($dob)) {
            $response['message'] = 'You must be at least 18 years old';
            echo json_encode($response);
            exit;
        }

        // Check if email already exists
        if ($authRepo->emailExists($email)) {
            $response['message'] = 'Email already registered';
            echo json_encode($response);
            exit;
        }

        if ($authRepo->phoneExists($phone)) {
            $response['message'] = 'Phone already registered';
            echo json_encode($response);
            exit;
        }

        // Check if username already exists
        if ($authRepo->usernameExists($username)) {
            $response['message'] = 'Username already taken';
            echo json_encode($response);
            exit;
        }

        // Map role
        $roleMap = [
            'renter' => 'user',
            'owner' => 'controlstaff',
            'user' => 'user',
            'driver' => 'driver',
            'staff' => 'controlstaff',
            'controlstaff' => 'controlstaff',
            'control_staff' => 'controlstaff',
            'callcenterstaff' => 'callcenterstaff',
            'call_center_staff' => 'callcenterstaff',
            'admin' => 'admin'
        ];
        $normalizedRole = $roleMap[strtolower($role)] ?? 'user';

        // Save pending registration info
        $_SESSION['pending_registration'] = [
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'dob' => $dob,
            'role' => $normalizedRole,
            'created_at' => time()
        ];

        // Generate and send OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $authRepo->storeOtp($email, $otp);

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = \EnvLoader::get('SMTP_HOST', 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = \EnvLoader::get('SMTP_USERNAME');
            $mail->Password = \EnvLoader::get('SMTP_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $fromEmail = \EnvLoader::get('SMTP_FROM_EMAIL');
            $fromName = \EnvLoader::get('SMTP_FROM_NAME', 'PrivateHire');
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($email);
            $mail->Subject = 'PrivateHire - Email Verification Code';
            
            $mail->isHTML(true);
            $mail->Body = "
                <h2>Email Verification</h2>
                <p>Your verification code is:</p>
                <h1 style='letter-spacing: 5px; font-family: monospace;'>$otp</h1>
                <p>This code will expire in 5 minutes.</p>
                <p>If you didn't request this, please ignore this email.</p>
            ";

            $mail->send();
            
            $response['success'] = true;
            $response['message'] = 'Verification code sent to ' . $email . '. Please check your email.';
            $response['status'] = 'pending_verification';

        } catch (Exception $e) {
            unset($_SESSION['pending_registration']);
            $response['message'] = 'Failed to send verification email. Please try again.';
            error_log('PHPMailer Error: ' . $mail->ErrorInfo);
        }

    } elseif ($action === 'check-username') {
        // Real-time validation: check if username exists
        $username = trim($_POST['username'] ?? '');

        if (empty($username)) {
            $response['message'] = 'Username is required';
            echo json_encode($response);
            exit;
        }

        if ($authRepo->usernameExists($username)) {
            $response['message'] = 'Username already taken';
            echo json_encode($response);
            exit;
        }

        $response['success'] = true;
        $response['message'] = 'Username available';

    } elseif ($action === 'check-email') {
        // Real-time validation: check if email exists
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (empty($email)) {
            $response['message'] = 'Email is required';
            echo json_encode($response);
            exit;
        }

        if (!$authRepo->isValidEmail($email)) {
            $response['message'] = 'Invalid email format';
            echo json_encode($response);
            exit;
        }

        if ($authRepo->emailExists($email)) {
            $response['message'] = 'Email already registered';
            echo json_encode($response);
            exit;
        }

        $response['success'] = true;
        $response['message'] = 'Email available';

    } elseif ($action === 'check-phone') {
        // Real-time validation: check if phone exists
        $phone = trim($_POST['phone'] ?? '');

        if (empty($phone)) {
            $response['message'] = 'Phone is required';
            echo json_encode($response);
            exit;
        }

        // Basic phone validation: at least 10 digits
        $digitsOnly = preg_replace('/\D/', '', $phone);
        if (strlen($digitsOnly) < 10) {
            $response['message'] = 'Phone number must have at least 10 digits';
            echo json_encode($response);
            exit;
        }

        if ($authRepo->phoneExists($phone)) {
            $response['message'] = 'Phone already registered';
            echo json_encode($response);
            exit;
        }

        $response['success'] = true;
        $response['message'] = 'Phone available';

    } elseif ($action === 'resend-otp') {
        // This handles RESEND OTP when user clicks "Resend" button
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (empty($email)) {
            $response['message'] = 'Email is required';
            echo json_encode($response);
            exit;
        }

        // Check if there's a pending registration for this email
        if (empty($_SESSION['pending_registration']) || $_SESSION['pending_registration']['email'] !== $email) {
            $response['message'] = 'No pending registration found. Please sign up first.';
            echo json_encode($response);
            exit;
        }

        // Generate new OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $authRepo->storeOtp($email, $otp);

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = \EnvLoader::get('SMTP_HOST', 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = \EnvLoader::get('SMTP_USERNAME');
            $mail->Password = \EnvLoader::get('SMTP_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $fromEmail = \EnvLoader::get('SMTP_FROM_EMAIL');
            $fromName = \EnvLoader::get('SMTP_FROM_NAME', 'PrivateHire');
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($email);
            $mail->Subject = 'PrivateHire - Email Verification Code (Resent)';
            
            $mail->isHTML(true);
            $mail->Body = "
                <h2>Email Verification</h2>
                <p>Your verification code is:</p>
                <h1 style='letter-spacing: 5px; font-family: monospace;'>$otp</h1>
                <p>This code will expire in 5 minutes.</p>
                <p>If you didn't request this, please ignore this email.</p>
            ";

            $mail->send();
            
            $response['success'] = true;
            $response['message'] = 'Verification code resent to ' . $email;

        } catch (Exception $e) {
            $response['message'] = 'Failed to resend verification email. Please try again.';
            error_log('PHPMailer Error: ' . $mail->ErrorInfo);
        }

    } elseif ($action === 'verify-otp') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $otp = trim($_POST['otp'] ?? '');

        if (empty($email) || empty($otp)) {
            $response['message'] = 'Email and OTP are required';
            echo json_encode($response);
            exit;
        }

        // Check if there's a pending registration for this email
        if (empty($_SESSION['pending_registration']) || $_SESSION['pending_registration']['email'] !== $email) {
            $response['message'] = 'No pending registration found. Please sign up first.';
            echo json_encode($response);
            exit;
        }

        // Check if registration is not expired (10 minutes timeout)
        $createdAt = $_SESSION['pending_registration']['created_at'] ?? 0;
        $expiryTime = $createdAt + (10 * 60); // 10 minutes
        if (time() > $expiryTime) {
            unset($_SESSION['pending_registration']);
            $response['message'] = 'Registration session expired. Please sign up again.';
            echo json_encode($response);
            exit;
        }

        // Verify OTP using AuthRepository
        $otpResult = $authRepo->verifyOtp($email, $otp);

        if (!$otpResult['valid']) {
            $response['message'] = $otpResult['message'];
            echo json_encode($response);
            exit;
        }

        // ===== OTP VERIFIED: Now create the actual user account =====
        $pendingData = $_SESSION['pending_registration'];

        // Re-check uniqueness to avoid race conditions between send-otp and verify-otp.
        if ($authRepo->usernameExists($pendingData['username'] ?? '')) {
            $response['message'] = 'Username already taken';
            echo json_encode($response);
            exit;
        }
        if ($authRepo->emailExists($pendingData['email'] ?? '')) {
            $response['message'] = 'Email already registered';
            echo json_encode($response);
            exit;
        }
        if ($authRepo->phoneExists($pendingData['phone'] ?? '')) {
            $response['message'] = 'Phone already registered';
            echo json_encode($response);
            exit;
        }
        
        try {
            $userId = $authRepo->createUser(
                $pendingData['username'],
                $pendingData['email'],
                $pendingData['phone'],
                $pendingData['password'],
                $pendingData['username'], // Use username as full_name
                $pendingData['dob'],
                $pendingData['role']
            );

            // Set session (user is now logged in)
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $pendingData['username'];
            $_SESSION['email'] = $pendingData['email'];
            $_SESSION['role'] = $pendingData['role'];
            $_SESSION['full_name'] = $pendingData['username']; // Store username as full_name

            // Clear pending registration
            unset($_SESSION['pending_registration']);

            $response['success'] = true;
            $response['message'] = 'Email verified! Account created successfully.';
            $response['user'] = [
                'id' => $userId,
                'username' => $pendingData['username'],
                'email' => $pendingData['email'],
                'role' => $pendingData['role']
            ];

        } catch (Exception $creationError) {
            $response['message'] = 'Failed to create account: ' . $creationError->getMessage();
            error_log('Account creation error: ' . $creationError->getMessage());
        }

    } elseif ($action === 'set-user-role') {
        // This handles STEP 3: Update role after user selection
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role = trim($_POST['role'] ?? 'user');

        if (empty($email)) {
            $response['message'] = 'Email is required';
            echo json_encode($response);
            exit;
        }

        // After OTP verification (Step 2), user should be logged in
        // If not logged in, they need to start from Step 1 again
        if (empty($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            $response['message'] = 'Session expired. Please sign up again.';
            echo json_encode($response);
            exit;
        }

        // Verify email matches the logged-in user
        if (empty($_SESSION['email']) || $_SESSION['email'] !== $email) {
            $response['message'] = 'Email mismatch. Please try again.';
            echo json_encode($response);
            exit;
        }

        // Map role
        $roleMap = [
            'renter' => 'user',
            'owner' => 'controlstaff',
            'user' => 'user',
            'driver' => 'driver',
            'staff' => 'controlstaff',
            'controlstaff' => 'controlstaff',
            'control_staff' => 'controlstaff',
            'callcenterstaff' => 'callcenterstaff',
            'call_center_staff' => 'callcenterstaff',
            'admin' => 'admin'
        ];
        $normalizedRole = $roleMap[strtolower($role)] ?? 'user';

        // Update role in session
        $_SESSION['role'] = $normalizedRole;
        
        // Update user role in database
        try {
            $authRepo->updateUserRoleByEmail($email, $normalizedRole);
            $response['success'] = true;
            $response['message'] = 'Role updated successfully.';
        } catch (Exception $e) {
            $response['message'] = 'Failed to update role: ' . $e->getMessage();
            error_log('Role update error: ' . $e->getMessage());
        }


    } else {
        $response['message'] = 'Invalid action';
    }

} catch (Exception $e) {
    $response['message'] = 'Server error: ' . $e->getMessage();
    error_log($e->getMessage());
}

echo json_encode($response);
?>
