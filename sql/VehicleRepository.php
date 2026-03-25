<?php

declare(strict_types=1);

final class VehicleRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function generateUuidV4(): string
    {
        if (class_exists('\\Ramsey\\Uuid\\Uuid')) {
            /** @var class-string $uuidClass */
            $uuidClass = '\\Ramsey\\Uuid\\Uuid';
            return $uuidClass::uuid4()->toString();
        }

        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * @return array<int,string>
     */
    public function getDistinctAvailableBrands(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT brand FROM vehicles WHERE status = 'available' AND brand IS NOT NULL AND brand != '' ORDER BY brand");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @return array<int,string>
     */
    public function getDistinctAvailableCategories(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT category FROM vehicles WHERE status = 'available' AND category IS NOT NULL AND category != '' ORDER BY category");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @param array{brand?:string,search?:string,limit?:int} $filters
     * @return array<int,array<string,mixed>>
     */
    public function listPublic(array $filters): array
    {
        $brand = trim((string)($filters['brand'] ?? ''));
        $search = trim((string)($filters['search'] ?? ''));
        $limit = (int)($filters['limit'] ?? 50);
        $limit = min(max($limit, 1), 100);

        $where = ["status = 'available'"];
        $params = [];

        $q = trim($search);
        if ($brand !== '') {
            $where[] = 'LOWER(brand) ILIKE ?';
            $params[] = '%' . strtolower($brand) . '%';
        } elseif ($q !== '') {
            $where[] = 'LOWER(brand) ILIKE ?';
            $params[] = '%' . strtolower($q) . '%';
        }

        $sql = "SELECT id, brand, model, service_tier, license_plate, seats\n                FROM vehicles\n                WHERE " . implode(' AND ', $where) . "\n                ORDER BY brand, model\n                LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{vehicles: array<int,array<string,mixed>>, total:int}
     */
    public function listAvailable(array $filters): array
    {
        $category = trim((string)($filters['category'] ?? ''));
        $brand = trim((string)($filters['brand'] ?? ''));
        $fuel = trim((string)($filters['fuel'] ?? ''));
        $transmission = trim((string)($filters['transmission'] ?? ''));
        $maxPrice = (float)($filters['max_price'] ?? 9999);
        $location = trim((string)($filters['location'] ?? ''));
        $search = trim((string)($filters['search'] ?? ''));
        $limit = min((int)($filters['limit'] ?? 20), 50);
        $offset = max((int)($filters['offset'] ?? 0), 0);

        $where = ["v.status = 'available'"];
        $params = [];

        if ($category !== '') {
            $where[] = 'LOWER(v.category) = LOWER(?)';
            $params[] = $category;
        }
        if ($brand !== '') {
            $where[] = 'LOWER(v.brand) ILIKE ?';
            $params[] = '%' . $brand . '%';
        }
        if ($fuel !== '') {
            $where[] = 'LOWER(v.fuel_type) = LOWER(?)';
            $params[] = $fuel;
        }
        if ($transmission !== '') {
            $where[] = 'LOWER(v.transmission) = LOWER(?)';
            $params[] = $transmission;
        }
        // Note: Price filtering removed - now filtering by service_tier instead
        if ($location !== '') {
            $where[] = '(LOWER(v.location_city) ILIKE ? OR LOWER(v.location_address) ILIKE ?)';
            $params[] = '%' . strtolower($location) . '%';
            $params[] = '%' . strtolower($location) . '%';
        }
        if ($search !== '') {
            $where[] = "(LOWER(v.brand) ILIKE ? OR LOWER(v.model) ILIKE ? OR LOWER(v.category) ILIKE ? OR LOWER(v.brand || ' ' || v.model) ILIKE ? OR LOWER(v.brand || ' ' || v.model || ' ' || CAST(v.year AS TEXT)) ILIKE ?)";
            $searchLike = '%' . strtolower($search) . '%';
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
        }

        $whereClause = implode(' AND ', $where);

        $listParams = $params;
        $listParams[] = $limit;
        $listParams[] = $offset;

        $sql = "SELECT v.*, u.full_name AS owner_name, u.avatar_url AS owner_avatar\n                FROM vehicles v\n                JOIN users u ON v.owner_id = u.id\n                WHERE {$whereClause}\n                ORDER BY v.created_at DESC\n                LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($listParams);
        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countSql = "SELECT COUNT(*) FROM vehicles v WHERE {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        return ['vehicles' => $vehicles, 'total' => $total];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getById(string $vehicleId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT v.*, u.full_name AS owner_name, u.avatar_url AS owner_avatar, u.phone AS owner_phone\n             FROM vehicles v\n             JOIN users u ON v.owner_id = u.id\n             WHERE v.id = ?"
        );
        $stmt->execute([$vehicleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get vehicle assigned to driver for today
     * @return array<string,mixed>|null
     */
    public function getAssignedVehicleForDriver(string $driverId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                v.id, v.brand, v.model, v.year, v.license_plate, v.color,
                v.transmission, v.fuel_type, v.seats,
                va.assigned_date
            FROM vehicle_assignments va
            JOIN vehicles v ON va.vehicle_id = v.id
            WHERE va.driver_id = ?
            AND va.assigned_date = CURRENT_DATE
            AND va.unassigned_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$driverId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Assign vehicle to driver for a specific date
     */
    public function assignToDriver(string $staffId, string $driverId, string $vehicleId, string $assignedDate): bool
    {
        // Unassign any previous vehicle for this driver on the same date
        $stmt = $this->pdo->prepare("
            UPDATE vehicle_assignments
            SET unassigned_at = NOW()
            WHERE driver_id = ? AND assigned_date = ? AND unassigned_at IS NULL
        ");
        $stmt->execute([$driverId, $assignedDate]);

        // Create new assignment
        $stmt = $this->pdo->prepare("
            INSERT INTO vehicle_assignments (staff_id, driver_id, vehicle_id, assigned_date, assigned_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$staffId, $driverId, $vehicleId, $assignedDate]);

        // Update user's assigned_vehicle_id
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET assigned_vehicle_id = ?, assigned_vehicle_assigned_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$vehicleId, $driverId]);

        return true;
    }

    /**
     * Unassign vehicle from driver for today
     */
    public function unassignFromDriver(string $driverId): bool
    {
        // Unassign vehicle
        $stmt = $this->pdo->prepare("
            UPDATE vehicle_assignments
            SET unassigned_at = NOW()
            WHERE driver_id = ? AND assigned_date = CURRENT_DATE AND unassigned_at IS NULL
        ");
        $stmt->execute([$driverId]);

        // Clear user's assigned_vehicle_id
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET assigned_vehicle_id = NULL
            WHERE id = ?
        ");
        $stmt->execute([$driverId]);

        return true;
    }

    /**
     * Create a new vehicle
     * @param array<string,mixed> $data
     * @return string Vehicle ID
     */
    public function create(array $data): string
    {
        // Find or create admin owner for fleet vehicles
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        $ownerId = $admin['id'] ?? null;

            if (!$ownerId) {
                $ownerId = $this->generateUuidV4();
            $stmt = $this->pdo->prepare("
                INSERT INTO users (id, full_name, email, role, auth_provider, is_active, created_at, updated_at)
                VALUES (?, 'System Fleet Owner', 'fleet@drivenow.local', 'admin', 'email', true, NOW(), NOW())
            ");
            $stmt->execute([$ownerId]);
        }

            $vehicleId = $data['id'] ?? $this->generateUuidV4();
        
        $stmt = $this->pdo->prepare("
            INSERT INTO vehicles
            (id, owner_id, brand, model, year, license_plate,
                         category, service_tier, transmission, fuel_type, seats, color,
             engine_size, consumption, features,
             location_city, location_address,
             status, created_at, updated_at)
            VALUES
            (?, ?, ?, ?, ?, ?,
                         ?, ?, ?, ?, ?, ?,
             ?, ?, ?,
             ?, ?,
             'available', NOW(), NOW())
        ");

        $stmt->execute([
            $vehicleId,
            $ownerId,
            $data['brand'],
            $data['model'],
            (int)$data['year'],
            $data['license_plate'],
            $data['category'] ?? 'sedan',
            $data['service_tier'] ?? 'standard',
            $data['transmission'] ?? 'automatic',
            $data['fuel_type'] ?? 'petrol',
            (int)($data['seats'] ?? 5),
            $data['color'] ?? null,
            $data['engine_size'] ?? null,
            $data['consumption'] ?? null,
            isset($data['features']) ? json_encode($data['features']) : null,
            $data['location_city'] ?? null,
            $data['location_address'] ?? null,
        ]);

        return $vehicleId;
    }

    /**
     * List all vehicles (for admin/staff only)
     * @return array<int,array<string,mixed>>
     */
    public function listAll(): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM vehicles ORDER BY created_at DESC");
        $stmt->execute([]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update vehicle fields dynamically
     * @param string $vehicleId
     * @param string $ownerId - Verify ownership
     * @param array<string,mixed> $fields - Fields to update
     * @return array<string,mixed> Updated vehicle record
     */
    public function updateVehicle(string $vehicleId, string $ownerId, array $fields): array
    {
        $updates = [];
        $params = [];

        // Allowed fields for update
        $allowedFields = [
            'brand', 'model', 'year', 'license_plate', 'category', 'transmission', 'fuel_type',
                'service_tier',
            'seats', 'color', 'engine_size', 'consumption',
            'price_per_day', 'price_per_week', 'price_per_month',
            'location_city', 'location_address', 'status', 'gps_enabled', 'thumbnail_id'
        ];

        foreach ($fields as $field => $value) {
            if (!in_array($field, $allowedFields, true)) {
                continue;
            }

            // Special handling for arrays (features)
            if ($field === 'features' && is_array($value)) {
                $updates[] = "features = ?::TEXT[]";
                $featuresStr = '{' . implode(',', array_map(fn($f) => '"' . str_replace('"', '\\"', $f) . '"', $value)) . '}';
                $params[] = $featuresStr;
            } else {
                $updates[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return $this->getById($vehicleId) ?? [];
        }

        $updates[] = "updated_at = NOW()";
        $params[] = $vehicleId;
        $params[] = $ownerId;

        $sql = "UPDATE vehicles SET " . implode(', ', $updates) . " WHERE id = ? AND owner_id = ? RETURNING *";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?? [];
    }

    /**
     * Delete a vehicle (owner verification required)
     * @return bool True if deleted, false if not found or not owned
     */
    public function deleteVehicle(string $vehicleId, string $ownerId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM vehicles WHERE id = ? AND owner_id = ?");
        $stmt->execute([$vehicleId, $ownerId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete vehicle admin (no ownership check)
     * @return bool
     */
    public function deleteVehicleAdmin(string $vehicleId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM vehicles WHERE id = ?");
        $stmt->execute([$vehicleId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Link images to vehicle and set thumbnail
     * @param string $vehicleId
     * @param array<int,string> $imageIds
     * @return void
     */
    public function linkImagesToVehicle(string $vehicleId, array $imageIds): void
    {
        $updateStmt = $this->pdo->prepare("UPDATE vehicle_images SET vehicle_id = ?, sort_order = ? WHERE id = ?");
        foreach ($imageIds as $i => $imgId) {
            $updateStmt->execute([$vehicleId, $i, $imgId]);
        }
        // Set first image as thumbnail
        $thumbStmt = $this->pdo->prepare("UPDATE vehicle_images SET is_thumbnail = TRUE WHERE id = ?");
        $thumbStmt->execute([$imageIds[0]]);
        $this->pdo->prepare("UPDATE vehicles SET thumbnail_id = ? WHERE id = ?")->execute([$imageIds[0], $vehicleId]);
    }

    /**
     * Update vehicle images for update operation (reorder, relink, thumbnail)
     * @param string $vehicleId
     * @param array<int,string> $imageIds - New image IDs in order
     * @return void
     */
    public function updateVehicleImages(string $vehicleId, array $imageIds): void
    {
        // Get current images
        $currentStmt = $this->pdo->prepare("SELECT id FROM vehicle_images WHERE vehicle_id = ? ORDER BY sort_order");
        $currentStmt->execute([$vehicleId]);
        $currentIds = $currentStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Find removed images
        $removedIds = array_diff($currentIds, $imageIds);
        
        // Delete removed images
        if (!empty($removedIds)) {
            $placeholders = implode(',', array_fill(0, count($removedIds), '?'));
            $this->pdo->prepare("DELETE FROM vehicle_images WHERE id IN ($placeholders)")->execute(array_values($removedIds));
        }
        
        // Reorder and relink remaining images
        $updateSort = $this->pdo->prepare("UPDATE vehicle_images SET sort_order = ?, vehicle_id = ? WHERE id = ?");
        foreach ($imageIds as $i => $imgId) {
            $updateSort->execute([$i, $vehicleId, $imgId]);
        }
        
        // Reset and set new thumbnail
        $this->pdo->prepare("UPDATE vehicle_images SET is_thumbnail = FALSE WHERE vehicle_id = ?")->execute([$vehicleId]);
        if (!empty($imageIds)) {
            $this->pdo->prepare("UPDATE vehicle_images SET is_thumbnail = TRUE WHERE id = ?")->execute([$imageIds[0]]);
        }
    }

    /**
     * Insert new vehicle image
     * @param string|null $vehicleId - null for unlinked uploads
     * @param string $storagePath
     * @param string $mimeType
     * @param string $fileName
     * @param int $fileSize
     * @return string Image ID
     */
    public function insertImage(?string $vehicleId, string $storagePath, string $mimeType, string $fileName, int $fileSize): string
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO vehicle_images (vehicle_id, storage_path, mime_type, file_name, file_size)
            VALUES (?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([$vehicleId, $storagePath, $mimeType, $fileName, $fileSize]);
        return (string)$stmt->fetchColumn();
    }

    /**
     * Get vehicle image for access check
     * @return array<string,mixed>|null
     */
    public function getImage(string $imageId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT vi.id, vi.storage_path, vi.vehicle_id, v.owner_id
            FROM vehicle_images vi
            LEFT JOIN vehicles v ON vi.vehicle_id = v.id
            WHERE vi.id = ?
        ");
        $stmt->execute([$imageId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Delete image by ID
     * @return bool
     */
    public function deleteImage(string $imageId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM vehicle_images WHERE id = ?");
        $stmt->execute([$imageId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get thumbnail image ID for a vehicle
     */
    public function getThumbnailImageId(string $vehicleId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT id FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_thumbnail DESC, sort_order ASC LIMIT 1");
        $stmt->execute([$vehicleId]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Get available vehicle brands matching search query for suggestions
     * @return array<int,string>
     */
    public function getAvailableBrandsSuggestions(string $searchQuery, int $limit = 8): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT brand 
            FROM vehicles
            WHERE status = 'available' AND brand IS NOT NULL AND brand != '' AND LOWER(brand) ILIKE ?
            ORDER BY brand
            LIMIT ?
        ");
        $stmt->execute(['%' . $searchQuery . '%', $limit]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get image storage paths for a vehicle (for deletion)
     * @return array<int,string>
     */
    public function getVehicleImageStoragePaths(string $vehicleId): array
    {
        $stmt = $this->pdo->prepare("SELECT storage_path FROM vehicle_images WHERE vehicle_id = ? AND storage_path IS NOT NULL");
        $stmt->execute([$vehicleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
