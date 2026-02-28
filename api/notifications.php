<?php
/**
 * Notifications API - DriveNow
 * Handles real-time notification CRUD:
 *   - list: Get user's notifications (with pagination)
 *   - unread-count: Get unread count
 *   - mark-read: Mark one notification as read
 *   - mark-all-read: Mark all as read
 *   - delete: Delete a notification
 *   - create: Internal helper (called from other APIs)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/notification-helpers.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Action is required.']);
    exit;
}

// Helper: require login
function requireAuth() {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Authentication required.']);
        exit;
    }
}

// ==========================================================
// LIST - Get notifications for current user
// ==========================================================
if ($action === 'list') {
    requireAuth();
    $userId = $_SESSION['user_id'];
    $limit = (int)($input['limit'] ?? $_GET['limit'] ?? 30);
    $offset = (int)($input['offset'] ?? $_GET['offset'] ?? 0);
    $limit = min($limit, 100);

    try {
        $stmt = $pdo->prepare("
            SELECT id, type, title, message, is_read, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format
        foreach ($notifications as &$n) {
            $n['is_read'] = ($n['is_read'] === true || $n['is_read'] === 't' || $n['is_read'] === '1');
            $n['time_ago'] = timeAgo($n['created_at']);
        }

        // Unread count
        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = false");
        $stmt2->execute([$userId]);
        $unreadCount = (int)$stmt2->fetchColumn();

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// UNREAD COUNT
// ==========================================================
if ($action === 'unread-count') {
    requireAuth();
    $userId = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = false");
        $stmt->execute([$userId]);
        $count = (int)$stmt->fetchColumn();

        echo json_encode(['success' => true, 'count' => $count]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'count' => 0]);
    }
    exit;
}

// ==========================================================
// MARK READ - Mark single notification as read
// ==========================================================
if ($action === 'mark-read') {
    requireAuth();
    $userId = $_SESSION['user_id'];
    $notifId = $input['notification_id'] ?? '';

    if (empty($notifId)) {
        echo json_encode(['success' => false, 'message' => 'Notification ID required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = true WHERE id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

// ==========================================================
// MARK ALL READ
// ==========================================================
if ($action === 'mark-all-read') {
    requireAuth();
    $userId = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = true WHERE user_id = ? AND is_read = false");
        $stmt->execute([$userId]);
        $count = $stmt->rowCount();

        echo json_encode(['success' => true, 'marked' => $count]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

// ==========================================================
// DELETE - Delete a notification
// ==========================================================
if ($action === 'delete') {
    requireAuth();
    $userId = $_SESSION['user_id'];
    $notifId = $input['notification_id'] ?? '';

    if (empty($notifId)) {
        echo json_encode(['success' => false, 'message' => 'Notification ID required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

// ==========================================================
// CLEAR ALL - Delete all notifications for user
// ==========================================================
if ($action === 'clear-all') {
    requireAuth();
    $userId = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$userId]);

        echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

// ==========================================================
// Helper: Human-readable time ago
// ==========================================================
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
