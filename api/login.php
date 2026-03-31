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

function redirectBackWithFlash(string $type, string $message): void {
    $_SESSION['login_flash'] = [
        'type' => $type,
        'message' => $message
    ];

    // Force session data to be written before redirecting.
    session_write_close();

    $returnTo = getReturnTo();
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

        if (!$user) {
            $response['message'] = 'Invalid email/phone or password';
            $_SESSION['login_old_identifier'] = $identifier;
            if ($wantsJson) {
                echo json_encode($response);
            } else {
                redirectBackWithFlash('error', $response['message']);
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
            $response['message'] = 'Invalid email/phone or password';
            $_SESSION['login_old_identifier'] = $identifier;
            if ($wantsJson) {
                echo json_encode($response);
            } else {
                redirectBackWithFlash('error', $response['message']);
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
