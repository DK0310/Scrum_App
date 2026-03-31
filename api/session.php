<?php
require_once __DIR__ . '/bootstrap.php';

$input = api_init(['allow_origin' => '*']);
$action = api_action($input);

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/UserRepository.php';

$userRepo = new UserRepository($pdo);

if ($action === 'check-session') {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
        try {
            $userId = (string) ($_SESSION['user_id'] ?? '');
            $user = $userId !== '' ? $userRepo->getSessionInfo($userId) : null;
            if ($user) {
                api_json([
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
                api_json([
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
            api_json([
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
        api_json(['success' => true, 'logged_in' => false]);
    }
    exit;
}

if ($action === 'logout') {
    session_destroy();
    api_json(['success' => true, 'message' => 'Logged out successfully.']);
    exit;
}

api_json(['success' => false, 'message' => 'Unknown action: ' . $action]);
