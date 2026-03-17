<?php
session_start();
header('Content-Type: application/json');

require_once '../Database/db.php';
require_once '../sql/AuthRepository.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $authRepo = new AuthRepository($pdo);

    if ($action === 'login') {
        $identifier = trim($_POST['identifier'] ?? ''); // email or username
        $password = $_POST['password'] ?? '';

        // Validation
        if (empty($identifier) || empty($password)) {
            $response['message'] = 'Email/Username and password are required';
            echo json_encode($response);
            exit;
        }

        // Find user by email or username
        $user = $authRepo->findUserByEmailOrUsername($identifier);

        if (!$user) {
            $response['message'] = 'Invalid email/username or password';
            echo json_encode($response);
            exit;
        }

        // Verify password
        if (!$authRepo->verifyPassword($password, $user['password'])) {
            $response['message'] = 'Invalid email/username or password';
            echo json_encode($response);
            exit;
        }

        // Set session
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

        // Update last login
        $authRepo->updateLastLogin($user['id']);

        $response['success'] = true;
        $response['message'] = 'Login successful';
        $response['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'full_name' => $user['full_name']
        ];

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

echo json_encode($response);
?>
