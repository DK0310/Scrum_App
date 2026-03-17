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

    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');

        // Validation
        if (empty($username) || empty($email) || empty($phone) || empty($password) || empty($full_name)) {
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

        // Check if email already exists
        if ($authRepo->emailExists($email)) {
            $response['message'] = 'Email already registered';
            echo json_encode($response);
            exit;
        }

        // Check if username already exists
        if ($authRepo->usernameExists($username)) {
            $response['message'] = 'Username already taken';
            echo json_encode($response);
            exit;
        }

        // Create user
        $userId = $authRepo->createUser($username, $email, $phone, $password, $full_name);

        // Set session
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'user';
        $_SESSION['full_name'] = $full_name;

        $response['success'] = true;
        $response['message'] = 'Account created successfully';
        $response['user'] = [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
            'role' => 'user',
            'full_name' => $full_name
        ];

    } elseif ($action === 'send-otp') {
        $email = trim($_POST['email'] ?? '');

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

        // Generate OTP (6 digits)
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in session
        $authRepo->storeOtp($email, $otp);

        // Send email with PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = \EnvLoader::get('SMTP_HOST', 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = \EnvLoader::get('SMTP_USER');
            $mail->Password = \EnvLoader::get('SMTP_PASS');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom(\EnvLoader::get('SMTP_USER'), 'PrivateHire');
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
            $response['message'] = 'OTP sent to ' . $email;

        } catch (Exception $e) {
            $response['message'] = 'Failed to send OTP: ' . $mail->ErrorInfo;
            error_log('PHPMailer Error: ' . $mail->ErrorInfo);
        }

    } elseif ($action === 'verify-otp') {
        $email = trim($_POST['email'] ?? '');
        $otp = trim($_POST['otp'] ?? '');

        if (empty($email) || empty($otp)) {
            $response['message'] = 'Email and OTP are required';
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

        $response['success'] = true;
        $response['message'] = 'OTP verified successfully';

    } else {
        $response['message'] = 'Invalid action';
    }

} catch (Exception $e) {
    $response['message'] = 'Server error: ' . $e->getMessage();
    error_log($e->getMessage());
}

echo json_encode($response);
?>
