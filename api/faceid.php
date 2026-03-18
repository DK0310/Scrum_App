<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/UserRepository.php';
require_once __DIR__ . '/notification-helpers.php';

$userRepo = new UserRepository($pdo);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

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
        $existingUsers = $userRepo->getOtherUsersWithFaceId($userId);
        $duplicateThreshold = 0.45;

        foreach ($existingUsers as $existing) {
            $storedDescriptor = json_decode($existing['face_descriptor'], true);
            if (!is_array($storedDescriptor)) {
                continue;
            }

            $distance = 0.0;
            for ($i = 0; $i < min(count($faceDescriptor), count($storedDescriptor)); $i++) {
                $distance += pow($faceDescriptor[$i] - $storedDescriptor[$i], 2);
            }
            $distance = sqrt($distance);

            if ($distance < $duplicateThreshold) {
                echo json_encode([
                    'success' => false,
                    'message' => 'This face is already registered to another account ("' . ($existing['full_name'] ?? 'Unknown') . '"). Each face can only be linked to one account.',
                    'duplicate' => true
                ]);
                exit;
            }
        }

        $userRepo->enableFaceId($userId, json_encode($faceDescriptor));
        createNotification($pdo, $userId, 'system', '🔐 Face ID Enabled', 'Face ID has been enabled on your account. You can now log in using facial recognition.');

        echo json_encode(['success' => true, 'message' => 'Face ID enabled successfully! You can now log in with your face.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'disable-faceid') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $userId = $_SESSION['user_id'];

    try {
        $userRepo->disableFaceId($userId);
        createNotification($pdo, $userId, 'system', '🔓 Face ID Disabled', 'Face ID has been removed from your account. You can re-enable it anytime from your profile settings.');
        echo json_encode(['success' => true, 'message' => 'Face ID disabled.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'faceid-login') {
    $faceDescriptor = $input['face_descriptor'] ?? null;

    if (!$faceDescriptor || !is_array($faceDescriptor)) {
        echo json_encode(['success' => false, 'message' => 'Face descriptor data is required.']);
        exit;
    }

    try {
        $users = $userRepo->getAllWithFaceId();
        if (empty($users)) {
            echo json_encode(['success' => false, 'message' => 'No users have Face ID enabled.']);
            exit;
        }

        $bestMatch = null;
        $bestScore = PHP_FLOAT_MAX;
        $threshold = 0.6;

        foreach ($users as $user) {
            $storedDescriptor = json_decode($user['face_descriptor'], true);
            if (!is_array($storedDescriptor)) {
                continue;
            }

            $distance = 0.0;
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

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $bestMatch['id'];
        $_SESSION['username'] = $bestMatch['full_name'] ?? $bestMatch['email'] ?? $bestMatch['phone'];
        $_SESSION['email'] = $bestMatch['email'];
        $_SESSION['role'] = $bestMatch['role'];
        $_SESSION['profile_completed'] = $bestMatch['profile_completed'];

        $userRepo->updateLastLogin($bestMatch['id']);

        echo json_encode([
            'success' => true,
            'message' => 'Welcome back, ' . ($bestMatch['full_name'] ?? 'User') . '!',
            'match_score' => $matchPercent . '%',
            'profile_completed' => (bool) ($bestMatch['profile_completed'] ?? false),
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

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
