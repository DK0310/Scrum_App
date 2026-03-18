<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/UserRepository.php';

$userRepo = new UserRepository($pdo);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

if ($action === 'check-session') {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
        try {
            $userId = (string) ($_SESSION['user_id'] ?? '');
            $user = $userId !== '' ? $userRepo->getSessionInfo($userId) : null;
            if ($user) {
                echo json_encode([
                    'success' => true,
                    'logged_in' => true,
                    'profile_completed' => (bool) ($user['profile_completed'] ?? false),
                    'user' => [
                        'id' => $user['id'],
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'phone' => $user['phone'],
                        'role' => $user['role'],
                        'avatar_url' => $user['avatar_url'],
                        'faceid_enabled' => (bool) ($user['faceid_enabled'] ?? false),
                        'membership' => $user['membership'] ?? null,
                    ]
                ]);
            } else {
                // Keep session intact and fall back to session fields when DB lookup fails.
                echo json_encode([
                    'success' => true,
                    'logged_in' => true,
                    'profile_completed' => false,
                    'stale_user' => true,
                    'user' => [
                        'id' => $_SESSION['user_id'] ?? null,
                        'full_name' => $_SESSION['full_name'] ?? ($_SESSION['username'] ?? null),
                        'email' => $_SESSION['email'] ?? null,
                        'phone' => null,
                        'role' => $_SESSION['role'] ?? 'user',
                        'avatar_url' => null,
                        'faceid_enabled' => false,
                        'membership' => null,
                    ]
                ]);
            }
        } catch (PDOException $e) {
            echo json_encode([
                'success' => true,
                'logged_in' => true,
                'profile_completed' => false,
                'stale_user' => true,
                'user' => [
                    'id' => $_SESSION['user_id'] ?? null,
                    'full_name' => $_SESSION['full_name'] ?? ($_SESSION['username'] ?? null),
                    'email' => $_SESSION['email'] ?? null,
                    'phone' => null,
                    'role' => $_SESSION['role'] ?? 'user',
                    'avatar_url' => null,
                    'faceid_enabled' => false,
                    'membership' => null,
                ]
            ]);
        }
    } else {
        echo json_encode(['success' => true, 'logged_in' => false]);
    }
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
