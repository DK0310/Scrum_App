<?php
require_once __DIR__ . '/bootstrap.php';

$input = api_init(['allow_origin' => '*']);
if (empty($_POST) && is_array($input)) {
    $_POST = $input;
}

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/AuthRepository.php';

function isAjaxRequest(): bool {
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return strtolower($requestedWith) === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

function getReturnTo(): string {
    $returnTo = trim($_POST['return_to'] ?? '');
    if ($returnTo === '' || strpos($returnTo, 'http://') === 0 || strpos($returnTo, 'https://') === 0) {
        return '/';
    }
    if ($returnTo[0] !== '/') {
        $returnTo = '/' . $returnTo;
    }
    return $returnTo;
}

function formatLockDuration(int $seconds): string {
    $seconds = max(0, $seconds);
    $units = [
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second',
    ];

    foreach ($units as $unitSeconds => $unitName) {
        if ($seconds >= $unitSeconds) {
            $value = (int)ceil($seconds / $unitSeconds);
            return $value . ' ' . $unitName . ($value === 1 ? '' : 's');
        }
    }

    return '0 seconds';
}

function redirectBackWithFlash(string $type, string $message, array $extra = []): void {
    $_SESSION['login_flash'] = array_merge([
        'type' => $type,
        'message' => $message
    ], $extra);

    // Force session data to be written before redirecting.
    session_write_close();

    $returnTo = getReturnTo();
    if ($type === 'success' && (($_SESSION['role'] ?? '') === 'driver')) {
        $returnTo = '/driver.php';
    }
    header('Location: ' . $returnTo);
    exit;
}

$response = ['success' => false, 'message' => ''];
$forcedBrowserMode = ($_POST['mode'] ?? '') === 'browser';
$wantsJson = !$forcedBrowserMode && isAjaxRequest();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    if ($wantsJson) {
        echo json_encode($response);
    } else {
        redirectBackWithFlash('error', $response['message']);
    }
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $authRepo = new AuthRepository($pdo);

    if ($action === 'login') {
        $identifier = trim($_POST['identifier'] ?? ''); // email or phone
        $password = $_POST['password'] ?? '';

        // Validation
        if (empty($identifier) || empty($password)) {
            $response['message'] = 'Email/Phone and password are required';
            $_SESSION['login_old_identifier'] = $identifier;
            if ($wantsJson) {
                echo json_encode($response);
            } else {
                redirectBackWithFlash('error', $response['message']);
            }
            exit;
        }

        // Find user by email or phone
        $user = $authRepo->findUserByEmailOrUsername($identifier);
        $throttleKey = $authRepo->buildLoginThrottleKey($identifier, $user ?: null);

        $throttleState = $authRepo->getLoginThrottleState($throttleKey);
        if (!empty($throttleState['is_locked'])) {
            $remainingSeconds = (int)($throttleState['remaining_seconds'] ?? 0);
            $response['message'] = 'Too many failed login attempts. Please try again in ' . formatLockDuration($remainingSeconds) . '.';
            $_SESSION['login_old_identifier'] = $identifier;
            $response['locked'] = true;
            $response['retry_after_seconds'] = $remainingSeconds;
            $response['lock_expires_at'] = time() + $remainingSeconds;
            if ($wantsJson) {
                echo json_encode($response);
            } else {
                redirectBackWithFlash('error', $response['message'], [
                    'locked' => true,
                    'retry_after_seconds' => $remainingSeconds,
                    'lock_expires_at' => time() + $remainingSeconds,
                ]);
            }
            exit;
        }

        if (!$user) {
            $failedState = $authRepo->recordFailedLoginAttempt($throttleKey);
            if (!empty($failedState['is_locked'])) {
                $lockSeconds = (int)($failedState['lock_applied_seconds'] ?? 0);
                $response['message'] = 'Too many failed login attempts. Login is locked for ' . formatLockDuration($lockSeconds) . '.';
                $response['locked'] = true;
                $response['retry_after_seconds'] = (int)($failedState['remaining_seconds'] ?? $lockSeconds);
                $response['lock_expires_at'] = time() + (int)$response['retry_after_seconds'];
            } else {
                $remainingAttempts = (int)($failedState['remaining_attempts_before_lock'] ?? 0);
                $response['message'] = 'Invalid email/phone or password. ' . $remainingAttempts . ' attempt(s) left before temporary lock.';
                $response['locked'] = false;
            }
            $_SESSION['login_old_identifier'] = $identifier;
            if ($wantsJson) {
                echo json_encode($response);
            } else {
                redirectBackWithFlash('error', $response['message'], [
                    'locked' => (bool)($response['locked'] ?? false),
                    'retry_after_seconds' => (int)($response['retry_after_seconds'] ?? 0),
                    'lock_expires_at' => (int)($response['lock_expires_at'] ?? 0),
                ]);
            }
            exit;
        }

        if (isset($user['is_active']) && !$user['is_active']) {
            $response['message'] = 'Account is inactive';
            $_SESSION['login_old_identifier'] = $identifier;
            if ($wantsJson) {
                echo json_encode($response);
            } else {
                redirectBackWithFlash('error', $response['message']);
            }
            exit;
        }

        // Verify password
        if (!$authRepo->verifyPassword($password, $user['password_hash'])) {
            $failedState = $authRepo->recordFailedLoginAttempt($throttleKey);
            if (!empty($failedState['is_locked'])) {
                $lockSeconds = (int)($failedState['lock_applied_seconds'] ?? 0);
                $response['message'] = 'Too many failed login attempts. Login is locked for ' . formatLockDuration($lockSeconds) . '.';
                $response['locked'] = true;
                $response['retry_after_seconds'] = (int)($failedState['remaining_seconds'] ?? $lockSeconds);
                $response['lock_expires_at'] = time() + (int)$response['retry_after_seconds'];
            } else {
                $remainingAttempts = (int)($failedState['remaining_attempts_before_lock'] ?? 0);
                $response['message'] = 'Invalid email/phone or password. ' . $remainingAttempts . ' attempt(s) left before temporary lock.';
                $response['locked'] = false;
            }
            $_SESSION['login_old_identifier'] = $identifier;
            if ($wantsJson) {
                echo json_encode($response);
            } else {
                redirectBackWithFlash('error', $response['message'], [
                    'locked' => (bool)($response['locked'] ?? false),
                    'retry_after_seconds' => (int)($response['retry_after_seconds'] ?? 0),
                    'lock_expires_at' => (int)($response['lock_expires_at'] ?? 0),
                ]);
            }
            exit;
        }

        // Set session
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['full_name'] ?: $user['email'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'] ?: $user['email'];
        unset($_SESSION['login_old_identifier'], $_SESSION['login_flash']);

        // Successful login resets progressive lockout sequence.
        $authRepo->resetLoginThrottle($throttleKey);

        // Update last login
        $authRepo->updateLastLogin($user['id']);

        $response['success'] = true;
        $response['message'] = 'Login successful';
        $response['user'] = [
            'id' => $user['id'],
            'username' => $user['full_name'] ?: $user['email'],
            'email' => $user['email'],
            'role' => $user['role'],
            'full_name' => $user['full_name']
        ];
        $response['redirect_to'] = (($user['role'] ?? '') === 'driver') ? '/driver.php' : getReturnTo();

        if (!$wantsJson) {
            redirectBackWithFlash('success', $response['message']);
        }

    } elseif ($action === 'faceid-login') {
        // Face ID login - placeholder for future implementation
        $response['message'] = 'Face ID login feature coming soon';
        
    } else {
        $response['message'] = 'Invalid action';
    }

} catch (Exception $e) {
    $response['message'] = 'Server error: ' . $e->getMessage();
    error_log($e->getMessage());
}

if ($wantsJson) {
    echo json_encode($response);
} else {
    redirectBackWithFlash('error', $response['message'] ?: 'Login failed');
}
?>
