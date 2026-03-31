<?php
/**
 * Notifications API - Private Hire
 * Handles real-time notification CRUD:
 *   - list: Get user's notifications (with pagination)
 *   - unread-count: Get unread count
 *   - mark-read: Mark one notification as read
 *   - mark-all-read: Mark all as read
 *   - delete: Delete a notification
 *   - create: Internal helper (called from other APIs)
 */

require_once __DIR__ . '/bootstrap.php';
$input = api_init();
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/notification-helpers.php';
require_once __DIR__ . '/../sql/NotificationRepository.php';

$notificationRepo = new NotificationRepository($pdo);

$action = api_action($input);

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Action is required.']);
    exit;
}

// Helper: require login
function requireAuth() {
    api_require_auth();
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
        $notifications = $notificationRepo->listWithPagination($userId, $limit, $offset);

        // Format
        foreach ($notifications as &$n) {
            $n['is_read'] = ($n['is_read'] === true || $n['is_read'] === 't' || $n['is_read'] === '1');
            $n['time_ago'] = timeAgo($n['created_at']);
        }

        // Unread count
        $unreadCount = $notificationRepo->getUnreadCount($userId);

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
        $count = $notificationRepo->getUnreadCount($userId);
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
        $notificationRepo->markAsRead($notifId, $userId);
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
        $count = $notificationRepo->markAllAsRead($userId);
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
        $notificationRepo->delete($notifId, $userId);
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
        $count = $notificationRepo->deleteAll($userId);
        echo json_encode(['success' => true, 'deleted' => $count]);
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
