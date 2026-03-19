<?php
/**
 * Notification Helper Functions - DriveNow
 * Shared functions for creating notifications from any API file.
 * Include this file where needed (bookings, auth, admin, etc.)
 */

if (!function_exists('createNotification')) {
    /**
     * Create a notification for a specific user
     * @param PDO $pdo Database connection (or NotificationRepository)
     * @param string $userId UUID of target user
     * @param string $type One of: booking, payment, promo, system, alert
     * @param string $title Notification title
     * @param string $message Notification body text
     * @return array|false The created notification row, or false on failure
     */
    function createNotification($pdo, $userId, $type, $title, $message) {
        try {
            // Support both legacy PDO and new NotificationRepository
            if ($pdo instanceof NotificationRepository) {
                // Use repository method
                $result = $pdo->createSimple($userId, $type, $title, $message);
                return $result ? ['user_id' => $userId, 'type' => $type, 'title' => $title, 'message' => $message] : false;
            } else {
                // Legacy: use PDO directly for backward compatibility
                $validTypes = ['booking', 'payment', 'promo', 'system', 'alert'];
                if (!in_array($type, $validTypes)) {
                    $type = 'system';
                }

                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message)
                    VALUES (?, ?::notification_type, ?, ?)
                    RETURNING id, created_at
                ");
                $stmt->execute([$userId, $type, $title, $message]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Notification create error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("Notification create error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('createNotificationForAll')) {
    /**
     * Create a notification for ALL active users (broadcast)
     * @param PDO $pdo Database connection
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification body text
     * @return int Number of notifications created
     */
    function createNotificationForAll($pdo, $type, $title, $message) {
        try {
            $validTypes = ['booking', 'payment', 'promo', 'system', 'alert'];
            if (!in_array($type, $validTypes)) {
                $type = 'system';
            }

            $users = $userRepo->getAllActiveUserIds();

            $count = 0;
            foreach ($users as $uid) {
                $userRepo->createNotification($uid, $type, $title, $message);
                $count++;
            }
            return $count;
        } catch (PDOException $e) {
            error_log("Notification broadcast error: " . $e->getMessage());
            return 0;
        }
    }
}
