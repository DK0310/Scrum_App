<?php
/**
 * Profile Page & API - Private Hire
 * Route controller for profile page view + API endpoints
 * If no action param → render profile page
 * If action param → handle API request
 */

session_start();

// Parse action first to determine if this is a page view or API request
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = $input['action'] ?? $_GET['action'] ?? '';

// ===== PAGE VIEW MODE (no action and not get-avatar) =====
$preAction = $_GET['action'] ?? '';
if (empty($action) && $preAction !== 'get-avatar') {
    // Render profile page (not API)
    $title = 'My Profile - Private Hire';
    $currentPage = 'profile';

    $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
    $userRole = $_SESSION['role'] ?? 'user';
    $currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? 'User') : null;

    if (!$isLoggedIn) {
        $_SESSION['login_flash'] = [
            'type' => 'error',
            'message' => 'Please sign in to view your profile.'
        ];
        $_SESSION['login_old_identifier'] = $_SESSION['login_old_identifier'] ?? '';
        header('Location: /');
        exit;
    }

    require __DIR__ . '/../templates/profile.html.php';
    exit;
}

// ===== API MODE or AVATAR DOWNLOAD =====
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/UserRepository.php';
require_once __DIR__ . '/supabase-storage.php';

$userRepo = new UserRepository($pdo);

// Handle get-avatar special case (file download, not JSON API)
if ($preAction === 'get-avatar') {
    $targetUserId = $_GET['id'] ?? '';
    if (empty($targetUserId)) {
        http_response_code(400);
        echo 'User ID required';
        exit;
    }

    try {
        $row = $userRepo->getAvatarInfo($targetUserId);
        if (!$row || empty($row['avatar_storage_path'])) {
            http_response_code(404);
            echo 'Avatar not found';
            exit;
        }

        $storage = new SupabaseStorage();
        $publicUrl = $storage->getPublicUrl($row['avatar_storage_path']);
        header('Location: ' . $publicUrl, true, 302);
        header('Cache-Control: public, max-age=3600');
    } catch (Exception $e) {
        http_response_code(500);
        echo 'Server error';
    }
    exit;
}

// ===== JSON API RESPONSE MODE =====
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/notification-helpers.php';

// Re-parse input for API mode (handles file uploads)
if (isset($_FILES['avatar'])) {
    $input = $_POST;
    if (!isset($input['action'])) {
        $input['action'] = 'upload-avatar';
    }
} else {
    // Already parsed at top of file
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }
}
$action = $input['action'] ?? $_GET['action'] ?? '';

// Require authentication for API requests
if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

if ($action === 'get-profile') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    try {
        $user = $userRepo->getFullProfile($_SESSION['user_id']);
        if ($user) {
            $user['faceid_enabled'] = (bool) ($user['faceid_enabled'] ?? false);
            $user['profile_completed'] = (bool) ($user['profile_completed'] ?? false);
            $user['email_verified'] = (bool) ($user['email_verified'] ?? false);
            $user['phone_verified'] = (bool) ($user['phone_verified'] ?? false);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'update-profile') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $updates = [];
    $allowedFields = [
        'full_name', 'date_of_birth', 'phone', 'email', 'address', 'city',
        'country', 'driving_license', 'license_expiry', 'id_card_number', 'bio', 'avatar_url'
    ];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[$field] = trim((string) $input[$field]) ?: null;
        }
    }

    if (isset($input['role']) && in_array($input['role'], ['user', 'driver', 'callcenterstaff', 'controlstaff'], true)) {
        $updates['role'] = $input['role'];
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        exit;
    }

    try {
        $user = $userRepo->updateProfile($userId, $updates);

        $_SESSION['username'] = $user['full_name'] ?? ($_SESSION['username'] ?? null);
        $_SESSION['email'] = $user['email'] ?? ($_SESSION['email'] ?? null);
        $_SESSION['role'] = $user['role'] ?? ($_SESSION['role'] ?? null);

        $changedFields = [];
        if (isset($updates['full_name'])) $changedFields[] = 'name';
        if (isset($updates['phone'])) $changedFields[] = 'phone';
        if (isset($updates['address'])) $changedFields[] = 'address';
        if (isset($updates['role'])) $changedFields[] = 'role to ' . $updates['role'];
        if (isset($updates['bio'])) $changedFields[] = 'bio';
        $fieldsSummary = !empty($changedFields) ? implode(', ', $changedFields) : 'profile info';
        createNotification($pdo, $userId, 'system', '✏️ Profile Updated', "Your profile has been updated: {$fieldsSummary}.");

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
    $maxSize = 3 * 1024 * 1024;

    if (!in_array($file['type'], $allowedTypes, true)) {
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
        $storage = new SupabaseStorage();
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
        $storagePath = 'avatars/' . $userId . '.' . $ext;

        $uploadResult = $storage->upload($storagePath, $imageData, $file['type'], true);
        if (!$uploadResult['success']) {
            echo json_encode(['success' => false, 'message' => 'Storage upload failed: ' . ($uploadResult['message'] ?? 'Unknown error')]);
            exit;
        }

        $publicUrl = $uploadResult['public_url'] . '?t=' . time();
        $avatarUrl = '/api/profile.php?action=get-avatar&id=' . $userId . '&t=' . time();
        $userRepo->updateAvatar($userId, $storagePath, $avatarUrl);

        createNotification($pdo, $userId, 'system', '📷 Avatar Updated', 'Your profile picture has been updated successfully.');

        echo json_encode([
            'success' => true,
            'message' => 'Avatar updated!',
            'avatar_url' => $avatarUrl,
            'public_url' => $publicUrl
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
