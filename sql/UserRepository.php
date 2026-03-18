<?php

declare(strict_types=1);

final class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(string $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByGoogleIdOrEmail(string $googleId, string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE google_id = ? OR email = ? LIMIT 1');
        $stmt->execute([$googleId, $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function createGoogleUser(string $email, string $googleId, string $fullName, string $avatarUrl): ?array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (email, google_id, auth_provider, full_name, avatar_url, email_verified, last_login_at)\n             VALUES (?, ?, 'google', ?, ?, TRUE, NOW())\n             RETURNING *"
        );
        $stmt->execute([$email, $googleId, $fullName, $avatarUrl]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateGoogleIdAndSetProvider(string $userId, string $googleId): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET google_id = ?, auth_provider = 'google', last_login_at = NOW() WHERE id = ?");
        $stmt->execute([$googleId, $userId]);
    }

    public function touchLastLogin(string $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$userId]);
    }

    public function existsByField(string $field, string $value): bool
    {
        if (!in_array($field, ['email', 'phone'], true)) {
            throw new InvalidArgumentException('Unsupported field');
        }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE {$field} = ? LIMIT 1");
        $stmt->execute([$value]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByPhone(string $phone): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function createPhoneUser(string $phone): ?array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (phone, auth_provider, phone_verified, last_login_at)\n             VALUES (?, 'phone', TRUE, NOW())\n             RETURNING *"
        );
        $stmt->execute([$phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function markPhoneVerifiedAndTouchLastLogin(string $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET phone_verified = TRUE, last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$userId]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByEmailOrFullName(string $identifier): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? OR full_name = ? LIMIT 1');
        $stmt->execute([$identifier, $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return (bool) $stmt->fetchColumn();
    }

    public function phoneExists(string $phone): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function createLocalUser(
        string $fullName,
        string $email,
        string $phone,
        string $dateOfBirth,
        string $role,
        ?string $address,
        string $passwordHash
    ): ?array {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (full_name, email, phone, date_of_birth, role, address, auth_provider, password_hash, email_verified, profile_completed, last_login_at)\n             VALUES (?, ?, ?, ?::DATE, ?::user_role_v2, NULLIF(?, ''), 'email', ?, TRUE, TRUE, NOW())\n             RETURNING *"
        );
        $stmt->execute([$fullName, $email, $phone, $dateOfBirth, $role, $address ?? '', $passwordHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get all drivers with their assigned vehicles
     * @return array<int,array<string,mixed>>
     */
    public function getDriversWithVehicles(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id, u.full_name, u.phone, u.email, u.avatar_url,
                u.assigned_vehicle_id, u.assigned_vehicle_assigned_at,
                v.brand, v.model, v.license_plate
            FROM users u
            LEFT JOIN vehicles v ON u.assigned_vehicle_id = v.id
            WHERE u.role = 'driver'
            AND u.is_active = true
            ORDER BY u.full_name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Search users by query (name, email, or phone)
     * @return array<int,array<string,mixed>>
     */
    public function searchByQuery(string $query, string $role = 'user', int $limit = 20): array
    {
        $like = '%' . strtolower($query) . '%';
        $stmt = $this->pdo->prepare("
            SELECT id, full_name, email, phone
            FROM users
            WHERE role = ?
              AND is_active = true
              AND (LOWER(full_name) ILIKE ? OR LOWER(email) ILIKE ? OR LOWER(phone) ILIKE ?)
            ORDER BY full_name
            LIMIT ?
        ");
        $stmt->execute([$role, $like, $like, $like, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get user's public profile with community stats
     * @return array<string,mixed>|null
     */
    public function getUserPublicProfile(string $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, full_name, avatar_url, role, bio, city, country, membership, created_at
            FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        // Count posts
        $pStmt = $this->pdo->prepare("SELECT COUNT(*) FROM community_posts WHERE user_id = ?");
        $pStmt->execute([$userId]);
        $user['posts_count'] = (int)$pStmt->fetchColumn();

        // Count comments
        $cStmt = $this->pdo->prepare("SELECT COUNT(*) FROM community_comments WHERE user_id = ?");
        $cStmt->execute([$userId]);
        $user['comments_count'] = (int)$cStmt->fetchColumn();

        // Count total likes received
        $lStmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(p.likes_count), 0) 
            FROM community_posts p WHERE p.user_id = ?
        ");
        $lStmt->execute([$userId]);
        $user['total_likes_received'] = (int)$lStmt->fetchColumn();

        return $user;
    }

    /**
     * Update user profile with selected fields
     * @param array<string,mixed> $fields Associative array of fields to update (null values are preserved)
     * @return array<string,mixed> Updated user record
     */
    public function updateProfile(string $userId, array $fields): array
    {
        $updates = [];
        $params = [];

        // Allowed fields for update
        $allowedFields = [
            'full_name', 'date_of_birth', 'phone', 'email', 'role', 'address', 'city',
            'country', 'driving_license', 'license_expiry', 'id_card_number', 'bio', 'avatar_url',
            'profile_completed'
        ];

        foreach ($fields as $field => $value) {
            if (!in_array($field, $allowedFields, true)) {
                continue;
            }

            if ($field === 'role' && !in_array($value, ['user', 'driver', 'callcenterstaff', 'controlstaff'], true)) {
                continue;
            }

            $updates[] = "{$field} = ?";
            $params[] = $value;
        }

        if (empty($updates)) {
            return $this->findById($userId) ?? [];
        }

        $updates[] = "updated_at = NOW()";
        $params[] = $userId;

        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ? RETURNING *";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?? [];
    }

    /**
     * List all users (admin only)
     * @return array<int,array<string,mixed>>
     */
    public function listAllUsers(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, email, phone, auth_provider, role, full_name, date_of_birth,
                   avatar_url, city, country, driving_license, membership,
                   is_active, email_verified, phone_verified, faceid_enabled,
                   profile_completed, created_at, last_login_at
            FROM users
            ORDER BY created_at DESC
        ");
        $stmt->execute([]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update user fields (admin only)
     * @param string $userId
     * @param array<string,mixed> $fields
     * @return bool True if updated, false if not found
     */
    public function updateUserAdmin(string $userId, array $fields): bool
    {
        $updates = [];
        $params = [];

        // Allowed admin-editable fields
        $allowedFields = ['role', 'is_active'];

        foreach ($fields as $field => $value) {
            if (!in_array($field, $allowedFields, true)) {
                continue;
            }

            if ($field === 'role' && !in_array($value, ['user', 'driver', 'callcenterstaff', 'controlstaff', 'admin'], true)) {
                continue;
            }

            $updates[] = "{$field} = ?";
            $params[] = $value;
        }

        if (empty($updates)) {
            return false;
        }

        $updates[] = "updated_at = NOW()";
        $params[] = $userId;

        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a user (admin only)
     * @return bool True if deleted, false if not found
     */
    public function deleteUser(string $userId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Count active bookings for a user (as renter or owner)
     * @return int
     */
    public function countActiveBookings(string $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE (renter_id = ? OR owner_id = ?) AND status IN ('pending', 'confirmed', 'in_progress')");
        $stmt->execute([$userId, $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get user avatar info
     */
    public function getAvatarInfo(string $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT avatar_storage_path, avatar_url FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all users with FaceID enabled
     * @return array<int,array<string,mixed>>
     */
    public function getAllWithFaceId(): array
    {
        $stmt = $this->pdo->query("SELECT id, full_name, email, phone, role, face_descriptor, profile_completed, avatar_url FROM users WHERE faceid_enabled = TRUE AND face_descriptor IS NOT NULL");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(string $userId): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }

    /**
     * Get user basic info for session
     */
    public function getSessionInfo(string $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, full_name, email, phone, role, avatar_url, profile_completed, faceid_enabled, membership FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Update avatar info
     */
    public function updateAvatar(string $userId, string $storagePath, string $avatarUrl): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET avatar_storage_path = ?, avatar_url = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$storagePath, $avatarUrl, $userId]);
    }

    /**
     * Check if username exists
     * NOTE: No username column in schema; check email instead for compatibility
     */
    public function usernameExists(string $username): bool
    {
        // Kept for API compatibility but always returns false (no username column)
        return false;
    }

    /**
     * Check if username or email exists
     * NOTE: Schema has no username; checking email/phone only
     */
    public function usernameOrEmailExists(string $username, string $email): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return (bool)$stmt->fetch();
    }

    /**
     * Create user with face descriptor
     */
    public function createWithFaceId(string $username, string $email, string $faceDescriptorJson): ?string
    {
        // Note: username parameter kept for backward compatibility but not used
        // Face ID users created with email auth provider
        $stmt = $this->pdo->prepare("INSERT INTO users (email, full_name, auth_provider, face_descriptor, faceid_enabled, profile_completed, created_at) 
                                     VALUES (?, ?, 'faceid', ?, TRUE, FALSE, NOW()) 
                                     RETURNING id");
        $stmt->execute([$email, $username ?? $email, $faceDescriptorJson]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Get all users with face descriptors for matching
     * @return array<int,array<string,mixed>>
     */
    public function getAllWithFaceDescriptors(): array
    {
        $stmt = $this->pdo->prepare("SELECT id, full_name, email, face_descriptor FROM users WHERE face_descriptor IS NOT NULL");
        $stmt->execute([]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get user role
     */
    public function getUserRole(string $userId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }

    /**
     * Get all active user IDs
     * @return array<int,string>
     */
    public function getAllActiveUserIds(): array
    {
        $stmt = $this->pdo->query("SELECT id FROM users WHERE is_active = true");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Create notification
     */
    public function createNotification(string $userId, string $type, string $title, string $message): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $type, $title, $message]);
    }

    /**
     * Disable face ID for a user
     */
    public function disableFaceId(string $userId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET
                face_descriptor = NULL,
                faceid_enabled = FALSE,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }

    /**
     * Enable face ID for a user
     */
    public function enableFaceId(string $userId, string $faceDescriptorJson): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET
                face_descriptor = ?::JSONB,
                faceid_enabled = TRUE,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$faceDescriptorJson, $userId]);
    }

    /**
     * Get other users with face descriptors (for conflict checking)
     */
    public function getOtherUsersWithFaceId(string $excludeUserId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, full_name, face_descriptor FROM users 
            WHERE faceid_enabled = TRUE AND face_descriptor IS NOT NULL AND id != ?
        ");
        $stmt->execute([$excludeUserId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get full user profile with all fields
     */
    public function getFullProfile(string $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, email, phone, auth_provider, role, full_name, date_of_birth, avatar_url,
                   address, city, country, driving_license, license_expiry, id_card_number, bio,
                   faceid_enabled, profile_completed, membership, email_verified, phone_verified,
                   created_at, last_login_at
            FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
