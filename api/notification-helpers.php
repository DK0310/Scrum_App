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
            if (class_exists('NotificationRepository') && $pdo instanceof NotificationRepository) {
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

            if (!($pdo instanceof PDO)) {
                return 0;
            }

            $stmt = $pdo->query("SELECT id FROM users WHERE is_active = TRUE");
            $users = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

            $count = 0;
            foreach ($users as $uid) {
                if (createNotification($pdo, (string)$uid, $type, $title, $message) !== false) {
                    $count++;
                }
            }
            return $count;
        } catch (PDOException $e) {
            error_log("Notification broadcast error: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('ensureVehicleAvailabilitySubscriptionTable')) {
    /**
     * Ensure subscription table exists for "notify me" feature.
     */
    function ensureVehicleAvailabilitySubscriptionTable(PDO $pdo): bool {
        try {
            $pdo->exec("\n                CREATE TABLE IF NOT EXISTS vehicle_availability_subscriptions (\n                    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),\n                    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,\n                    vehicle_id UUID NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,\n                    is_active BOOLEAN NOT NULL DEFAULT TRUE,\n                    notified_at TIMESTAMPTZ NULL,\n                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),\n                    UNIQUE(user_id, vehicle_id)\n                )\n            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_vehicle_availability_subscriptions_vehicle_active ON vehicle_availability_subscriptions(vehicle_id, is_active)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_vehicle_availability_subscriptions_user ON vehicle_availability_subscriptions(user_id)");
            return true;
        } catch (Throwable $e) {
            error_log('Ensure availability subscription table failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('subscribeVehicleAvailability')) {
    /**
     * Create or reactivate a user's availability subscription for a vehicle.
     */
    function subscribeVehicleAvailability(PDO $pdo, string $userId, string $vehicleId): bool {
        if (!ensureVehicleAvailabilitySubscriptionTable($pdo)) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("\n                INSERT INTO vehicle_availability_subscriptions (user_id, vehicle_id, is_active, notified_at, created_at)\n                VALUES (?, ?, TRUE, NULL, NOW())\n                ON CONFLICT (user_id, vehicle_id)\n                DO UPDATE SET\n                    is_active = TRUE,\n                    notified_at = NULL,\n                    created_at = NOW()\n            ");
            return $stmt->execute([$userId, $vehicleId]);
        } catch (Throwable $e) {
            error_log('Subscribe availability failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notifyVehicleAvailabilitySubscribers')) {
    /**
     * Notify active subscribers that vehicle is available and mark subscriptions as processed.
     *
     * @return int Number of notifications created.
     */
    function notifyVehicleAvailabilitySubscribers(PDO $pdo, string $vehicleId): int {
        if (!ensureVehicleAvailabilitySubscriptionTable($pdo)) {
            return 0;
        }

        try {
            $vehicleStmt = $pdo->prepare('SELECT brand, model FROM vehicles WHERE id = ? LIMIT 1');
            $vehicleStmt->execute([$vehicleId]);
            $vehicle = $vehicleStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $vehicleName = trim(((string)($vehicle['brand'] ?? '')) . ' ' . ((string)($vehicle['model'] ?? '')));
            if ($vehicleName === '') {
                $vehicleName = 'Your selected vehicle';
            }

            $stmt = $pdo->prepare("\n                SELECT user_id\n                FROM vehicle_availability_subscriptions\n                WHERE vehicle_id = ? AND is_active = TRUE\n            ");
            $stmt->execute([$vehicleId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return 0;
            }

            $count = 0;
            foreach ($rows as $row) {
                $userId = (string)($row['user_id'] ?? '');
                if ($userId === '') {
                    continue;
                }

                $created = createNotification(
                    $pdo,
                    $userId,
                    'alert',
                    '🚗 Vehicle Available Again',
                    $vehicleName . ' is now available for booking. Reserve it before it gets booked again.'
                );

                if ($created !== false) {
                    $count++;
                }
            }

            $updateStmt = $pdo->prepare("\n                UPDATE vehicle_availability_subscriptions\n                SET is_active = FALSE, notified_at = NOW()\n                WHERE vehicle_id = ? AND is_active = TRUE\n            ");
            $updateStmt->execute([$vehicleId]);

            return $count;
        } catch (Throwable $e) {
            error_log('Notify availability subscribers failed: ' . $e->getMessage());
            return 0;
        }
    }
}
