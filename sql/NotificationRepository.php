<?php

declare(strict_types=1);

final class NotificationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a notification
     * @param array<string,mixed> $data
     * @return string Notification ID
     */
    public function create(array $data): string
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (
                user_id, type, title, message, booking_id, data, read_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NULL, NOW())
            RETURNING id
        ");
        $stmt->execute([
            $data['user_id'],
            $data['type'],
            $data['title'],
            $data['message'],
            $data['booking_id'] ?? null,
            isset($data['data']) ? json_encode($data['data']) : null,
        ]);

        return (string) $stmt->fetchColumn();
    }

    /**
     * Get unread notifications for user
     * @return array<int,array<string,mixed>>
     */
    public function getUnread(string $userId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, type, title, message, booking_id, data, created_at
            FROM notifications
            WHERE user_id = ? AND read_at IS NULL
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            if (!empty($row['data'])) {
                $row['data'] = json_decode($row['data'], true);
            }
        }

        return $rows;
    }

    /**
     * Get user's notifications (all, paginated)
     * @return array<int,array<string,mixed>>
     */
    public function getUserNotifications(string $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, type, title, message, booking_id, data, read_at, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            if (!empty($row['data'])) {
                $row['data'] = json_decode($row['data'], true);
            }
        }

        return $rows;
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $notificationId, ?string $driverId = null): bool
    {
        $sql = "UPDATE notifications SET is_read = true WHERE id = ? AND is_read = false";
        $params = [$notificationId];
        
        if ($driverId) {
            // If driver specified, verify ownership
            $sql = "UPDATE notifications SET is_read = true WHERE id = ? AND is_read = false AND user_id = ?";
            $params = [$notificationId, $driverId];
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params) && $stmt->rowCount() > 0;
    }

    /**
     * Get user's notifications with pagination (for API list action)
     * @return array<int,array<string,mixed>>
     */
    public function listWithPagination(string $userId, int $limit = 30, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, type, title, message, is_read, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark all user notifications as read
     */
    public function markAllAsRead(string $userId): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE notifications
            SET is_read = true
            WHERE user_id = ? AND is_read = false
        ");
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }

    /**
     * Delete notification (with ownership check)
     */
    public function delete(string $notificationId, ?string $userId = null): bool
    {
        $sql = "DELETE FROM notifications WHERE id = ?";
        $params = [$notificationId];

        if ($userId) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params) && $stmt->rowCount() > 0;
    }

    /**
     * Delete all user notifications
     */
    public function deleteAll(string $userId): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCount(string $userId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id = ? AND is_read = false
        ");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get notification by ID
     * @return array<string,mixed>|null
     */
    public function getById(string $notificationId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, user_id, type, title, message, booking_id, data, read_at, created_at
            FROM notifications
            WHERE id = ?
        ");
        $stmt->execute([$notificationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['data'])) {
            $row['data'] = json_decode($row['data'], true);
        }

        return $row ?: null;
    }

    /**
     * Get notifications by type (e.g., 'booking-confirmed', 'payment-received', etc)
     * @return array<int,array<string,mixed>>
     */
    public function getByType(string $userId, string $type, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, type, title, message, booking_id, data, read_at, created_at
            FROM notifications
            WHERE user_id = ? AND type = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $type, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            if (!empty($row['data'])) {
                $row['data'] = json_decode($row['data'], true);
            }
        }

        return $rows;
    }

    /**
     * Get notifications for a booking (all users involved)
     * @return array<int,array<string,mixed>>
     */
    public function getByBooking(string $bookingId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, user_id, type, title, message, data, read_at, created_at
            FROM notifications
            WHERE booking_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$bookingId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            if (!empty($row['data'])) {
                $row['data'] = json_decode($row['data'], true);
            }
        }

        return $rows;
    }

    /**
     * Send notification to multiple users
     * @param string[] $userIds
     * @param array<string,mixed> $data
     * @return int Number of notifications created
     */
    public function sendBulk(array $userIds, array $data): int
    {
        $count = 0;
        foreach ($userIds as $userId) {
            try {
                $data['user_id'] = $userId;
                $this->create($data);
                $count++;
            } catch (PDOException $e) {
                // Continue with next user
            }
        }
        return $count;
    }

    /**
     * Delete old notifications (cleanup - older than X days)
     */
    public function deleteOldNotifications(int $daysOld = 30): int
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM notifications
            WHERE created_at < NOW() - INTERVAL '? days'
        ");
        $stmt->execute([$daysOld]);
        return $stmt->rowCount();
    }

    /**
     * Get driver notifications (from driver_notifications table)
     * @return array<int,array<string,mixed>>
     */
    public function getForDriver(string $driverId, bool $unreadOnly = false, int $limit = 50): array
    {
        $sql = "
            SELECT id, title, message, notification_type, is_read, created_at, booking_id
            FROM driver_notifications
            WHERE driver_id = ?
        ";

        $params = [$driverId];

        if ($unreadOnly) {
            $sql .= " AND is_read = false";
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a simple notification with just type, title, and message
     */
    public function createSimple(string $userId, string $type, string $title, string $message): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, created_at)
            VALUES (?, ?::notification_type, ?, ?, NOW())
        ");
        return $stmt->execute([$userId, $type, $title, $message]);
    }
}
