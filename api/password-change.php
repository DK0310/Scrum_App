<?php
session_start();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
	$input = $_POST;
}

$action = $input['action'] ?? $_GET['action'] ?? '';

// Page view mode: render reset page when no API action is provided.
if (empty($action)) {
	$title = 'Reset Password - Private Hire';
	$currentPage = 'password-change';

	$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
	$userRole = $_SESSION['role'] ?? 'user';
	$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? 'User') : null;

	require __DIR__ . '/../templates/password-change.html.php';
	exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	exit(0);
}

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/AuthRepository.php';
require_once __DIR__ . '/notification-helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$authRepo = new AuthRepository($pdo);

function buildBaseUrl(): string {
	$configured = trim((string) \EnvLoader::get('APP_BASE_URL', ''));
	if ($configured !== '') {
		return rtrim($configured, '/');
	}

	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
	return $scheme . '://' . $host;
}

function sendResetEmail(string $toEmail, string $resetLink): bool {
	$smtpHost = \EnvLoader::get('SMTP_HOST', 'smtp.gmail.com');
	$smtpPort = (int) \EnvLoader::get('SMTP_PORT', 587);
	$smtpUser = \EnvLoader::get('SMTP_USERNAME', '');
	$smtpPass = \EnvLoader::get('SMTP_PASSWORD', '');
	$fromEmail = \EnvLoader::get('SMTP_FROM_EMAIL', $smtpUser);
	$fromName = \EnvLoader::get('SMTP_FROM_NAME', 'PrivateHire');

	if ($smtpUser === '' || $smtpPass === '' || $fromEmail === '') {
		return false;
	}

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
		$mail->Subject = 'PrivateHire - Reset Your Password';
		$mail->Body = "
			<div style='font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;'>
				<h2 style='color:#0f766e;margin-bottom:8px;'>Reset your password</h2>
				<p style='color:#374151;'>You requested a password reset for your account.</p>
				<p style='color:#374151;'>This link is valid for <strong>5 minutes</strong> and can be used only once after successful reset.</p>
				<p style='margin:24px 0;'>
					<a href='{$resetLink}' style='display:inline-block;background:#0f766e;color:white;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;'>Reset Password</a>
				</p>
				<p style='color:#6b7280;font-size:13px;'>If you did not request this, you can ignore this email.</p>
			</div>
		";
		$mail->AltBody = "Reset your password using this link (valid for 5 minutes): {$resetLink}";
		$mail->send();
		return true;
	} catch (Exception $e) {
		error_log('Password reset email error: ' . $e->getMessage());
		return false;
	}
}

if ($action === 'send-reset-link') {
	$email = strtolower(trim((string)($input['email'] ?? '')));

	// Generic response to avoid account enumeration.
	$genericResponse = [
		'success' => true,
		'message' => 'If an account exists for this email, a reset link has been sent.'
	];

	if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		echo json_encode($genericResponse);
		exit;
	}

	try {
		$user = $authRepo->findUserByEmail($email);
		if ($user && !empty($user['id']) && !empty($user['email'])) {
			$token = $authRepo->createPasswordResetToken($user['id'], 5);
			$resetLink = buildBaseUrl() . '/api/password-change.php?token=' . urlencode($token);
			sendResetEmail($user['email'], $resetLink);

			createNotification($pdo, $user['id'], 'system', '🔐 Password Reset Requested', 'A password reset link was requested for your account.');
		}
	} catch (Throwable $e) {
		// Keep generic response for privacy.
		error_log('send-reset-link error: ' . $e->getMessage());
	}

	echo json_encode($genericResponse);
	exit;
}

if ($action === 'send-reset-link-current-user') {
	if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || empty($_SESSION['user_id'])) {
		echo json_encode(['success' => false, 'message' => 'Please sign in first.']);
		exit;
	}

	$userId = $_SESSION['user_id'];
	$email = strtolower(trim((string)($_SESSION['email'] ?? '')));

	// Fallback: session may not have email yet, fetch from DB by current user id.
	if ($email === '') {
		try {
			$stmt = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
			$stmt->execute([':id' => $userId]);
			$email = strtolower(trim((string)($stmt->fetchColumn() ?: '')));
		} catch (Throwable $e) {
			error_log('send-reset-link-current-user lookup error: ' . $e->getMessage());
		}
	}

	if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		echo json_encode(['success' => false, 'message' => 'No valid email found for this account.']);
		exit;
	}

	try {
		$token = $authRepo->createPasswordResetToken($userId, 5);
		$resetLink = buildBaseUrl() . '/api/password-change.php?token=' . urlencode($token);
		$sent = sendResetEmail($email, $resetLink);

		if (!$sent) {
			echo json_encode(['success' => false, 'message' => 'Could not send reset email. Please check mail settings.']);
			exit;
		}

		createNotification($pdo, $userId, 'system', '🔐 Password Reset Requested', 'A password reset link was sent to your account email.');
		echo json_encode(['success' => true, 'message' => 'Reset link sent to your account email.']);
	} catch (Throwable $e) {
		error_log('send-reset-link-current-user error: ' . $e->getMessage());
		echo json_encode(['success' => false, 'message' => 'Unable to send reset link right now.']);
	}
	exit;
}

if ($action === 'verify-token') {
	$token = trim((string)($input['token'] ?? $_GET['token'] ?? ''));
	if ($token === '') {
		echo json_encode(['success' => false, 'valid' => false, 'message' => 'Reset token is required.']);
		exit;
	}

	try {
		$tokenRow = $authRepo->findValidPasswordResetToken($token);
		if (!$tokenRow) {
			echo json_encode(['success' => false, 'valid' => false, 'message' => 'This reset link is invalid or has expired.']);
			exit;
		}

		echo json_encode(['success' => true, 'valid' => true, 'message' => 'Reset link is valid.']);
	} catch (Throwable $e) {
		error_log('verify-token error: ' . $e->getMessage());
		echo json_encode(['success' => false, 'valid' => false, 'message' => 'Unable to verify reset token.']);
	}
	exit;
}

if ($action === 'reset-password') {
	$token = trim((string)($input['token'] ?? ''));
	$newPassword = (string)($input['new_password'] ?? '');
	$confirmPassword = (string)($input['confirm_password'] ?? '');

	if ($token === '') {
		echo json_encode(['success' => false, 'message' => 'Reset token is required.']);
		exit;
	}

	if ($newPassword === '' || $confirmPassword === '') {
		echo json_encode(['success' => false, 'message' => 'Please fill in both password fields.']);
		exit;
	}

	if (strlen($newPassword) < 6) {
		echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
		exit;
	}

	if ($newPassword !== $confirmPassword) {
		echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
		exit;
	}

	try {
		$tokenRow = $authRepo->findValidPasswordResetToken($token);
		if (!$tokenRow) {
			echo json_encode(['success' => false, 'message' => 'This reset link is invalid or has expired.']);
			exit;
		}

		if (!empty($tokenRow['password_hash']) && password_verify($newPassword, $tokenRow['password_hash'])) {
			echo json_encode(['success' => false, 'message' => 'New password must be different from current password.']);
			exit;
		}

		$updated = $authRepo->updatePasswordHash($tokenRow['user_id'], $newPassword);
		if (!$updated) {
			echo json_encode(['success' => false, 'message' => 'Could not update password. Please try again.']);
			exit;
		}

		// Consume the used token and revoke any other active tokens.
		$authRepo->consumePasswordResetToken($token);
		$authRepo->revokeActivePasswordResetTokens($tokenRow['user_id']);

		createNotification($pdo, $tokenRow['user_id'], 'system', '✅ Password Changed', 'Your account password was changed successfully.');

		echo json_encode([
			'success' => true,
			'message' => 'Password reset successful. You can now sign in with your new password.',
			'redirect' => '/'
		]);
	} catch (Throwable $e) {
		error_log('reset-password error: ' . $e->getMessage());
		echo json_encode(['success' => false, 'message' => 'Server error while resetting password.']);
	}
	exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);

