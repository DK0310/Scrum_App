<?php

declare(strict_types=1);

final class BookingRepository
{
    private PDO $pdo;
    /** @var array<string,bool>|null */
    private ?array $bookingColumnCache = null;
    private bool $historySchemaEnsured = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function bookingHasColumn(string $columnName): bool
    {
        if ($this->bookingColumnCache === null) {
            $this->bookingColumnCache = [];
            try {
                $stmt = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'bookings'");
                $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
                foreach ($cols as $col) {
                    $this->bookingColumnCache[(string)$col] = true;
                }
            } catch (PDOException $e) {
                $this->bookingColumnCache = [];
            }
        }

        return isset($this->bookingColumnCache[$columnName]);
    }

    private function ensureVehicleServiceTierColumnExists(): void
    {
        try {
            $this->pdo->exec("ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS service_tier VARCHAR(20) DEFAULT 'standard'");
        } catch (Throwable $e) {
            // Ignore in environments without ALTER permissions.
        }
    }

    private function parseMinicabPickupDateTime(?string $pickupDateRaw, ?string $pickupTimeRaw = null): ?DateTimeImmutable
    {
        $pickupDateRaw = trim((string)$pickupDateRaw);
        if ($pickupDateRaw === '') {
            return null;
        }

        $timezone = new DateTimeZone('UTC');

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $pickupDateRaw) === 1) {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $pickupDateRaw, $timezone);
            if ($dt instanceof DateTimeImmutable) {
                return $dt;
            }
        }

        try {
            $pickupAt = new DateTimeImmutable($pickupDateRaw, $timezone);
        } catch (Exception $e) {
            return null;
        }

        $pickupTimeRaw = strtoupper(str_replace(' ', '', trim((string)$pickupTimeRaw)));
        if ($pickupTimeRaw === '') {
            return $pickupAt;
        }

        $datePart = $pickupAt->format('Y-m-d');
        $formats = ['Y-m-d h:iA', 'Y-m-d H:i:s', 'Y-m-d H:i'];
        foreach ($formats as $format) {
            $candidate = DateTimeImmutable::createFromFormat($format, $datePart . ' ' . $pickupTimeRaw, $timezone);
            if ($candidate instanceof DateTimeImmutable) {
                return $candidate;
            }
        }

        return $pickupAt;
    }

    private function estimateMinicabDurationHours(?float $distanceKm, ?string $serviceType = null): int
    {
        if (strtolower(trim((string)($serviceType ?? ''))) === 'daily-hire') {
            return 24;
        }

        if ($distanceKm === null || $distanceKm <= 0) {
            return 1;
        }

        $distanceMiles = $distanceKm * 0.621371;
        return max(1, (int)ceil($distanceMiles / 20.0));
    }

    private function getMinicabPreBufferHours(?string $serviceType): int
    {
        return strtolower(trim((string)($serviceType ?? ''))) === 'daily-hire' ? 24 : 2;
    }

    /**
     * @return array{pickup_at:DateTimeImmutable,window_start:DateTimeImmutable,window_end:DateTimeImmutable,duration_hours:int}|null
     */
    public function buildMinicabRequestWindow(
        ?string $pickupDateRaw,
        ?string $pickupTimeRaw = null,
        ?float $distanceKm = null,
        ?string $serviceType = null,
        int $bufferHours = 2
    ): ?array
    {
        $pickupAt = $this->parseMinicabPickupDateTime($pickupDateRaw, $pickupTimeRaw);
        if (!$pickupAt) {
            return null;
        }

        $bufferHours = max(0, $this->getMinicabPreBufferHours($serviceType));
        $durationHours = $this->estimateMinicabDurationHours($distanceKm, $serviceType);
        $postBufferHours = max(0, $bufferHours > 2 ? 2 : $bufferHours);

        $windowStart = $pickupAt->sub(new DateInterval('PT' . $bufferHours . 'H'));
        $windowEnd = $pickupAt->add(new DateInterval('PT' . ($durationHours + $postBufferHours) . 'H'));

        return [
            'pickup_at' => $pickupAt,
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'duration_hours' => $durationHours,
        ];
    }

    /**
     * Ensure schema columns exist for minicab bookings
     */
    public function ensureBookingColumnsExist(): void
    {
        $columns = [
            'pickup_time VARCHAR(20) DEFAULT NULL',
            'service_type VARCHAR(50) DEFAULT \'local\'',
            'distance_km DECIMAL(10,2) DEFAULT NULL',
            'transfer_cost DECIMAL(10,2) DEFAULT NULL',
            'number_of_passengers INT DEFAULT 1',
            'ride_tier VARCHAR(50) DEFAULT NULL',
            'payment_method VARCHAR(50) DEFAULT NULL',
        ];

        foreach ($columns as $col) {
            try {
                $this->pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS " . $col);
            } catch (PDOException $e) {
                // Column may already exist
            }
        }
    }

    public function ensureBookingHistorySchema(): void
    {
        if ($this->historySchemaEnsured) {
            return;
        }

        $ddl = [
            "CREATE TABLE IF NOT EXISTS booking_regions (
                id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
                name VARCHAR(120) NOT NULL,
                normalized_key VARCHAR(160) NOT NULL UNIQUE,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )",
            "CREATE TABLE IF NOT EXISTS booking_archive (
                id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
                booking_id UUID NOT NULL UNIQUE,
                status booking_status NOT NULL,
                archived_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                completed_at TIMESTAMPTZ,
                cancelled_at TIMESTAMPTZ,
                archive_reason VARCHAR(80) NOT NULL DEFAULT 'status_transition',
                renter_id UUID,
                owner_id UUID,
                vehicle_id UUID,
                driver_id UUID,
                customer_name VARCHAR(255),
                customer_email VARCHAR(255),
                customer_phone VARCHAR(30),
                pickup_date DATE,
                pickup_location TEXT,
                return_location TEXT,
                total_amount DECIMAL(10,2),
                payment_method VARCHAR(50),
                booking_payload JSONB NOT NULL DEFAULT '{}'::jsonb,
                region_id UUID REFERENCES booking_regions(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS booking_deletion_audit (
                id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
                booking_id UUID NOT NULL,
                deleted_by UUID REFERENCES users(id) ON DELETE SET NULL,
                deleted_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                delete_reason TEXT,
                booking_snapshot JSONB NOT NULL DEFAULT '{}'::jsonb
            )",
            "CREATE INDEX IF NOT EXISTS idx_booking_archive_archived_at ON booking_archive(archived_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_booking_archive_status_archived ON booking_archive(status, archived_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_booking_archive_region_archived ON booking_archive(region_id, archived_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_booking_archive_customer_email ON booking_archive(customer_email)",
            "CREATE INDEX IF NOT EXISTS idx_booking_archive_customer_phone ON booking_archive(customer_phone)",
            "CREATE INDEX IF NOT EXISTS idx_booking_archive_completed_at ON booking_archive(completed_at)",
            "CREATE INDEX IF NOT EXISTS idx_booking_archive_cancelled_at ON booking_archive(cancelled_at)",
            "CREATE INDEX IF NOT EXISTS idx_booking_deletion_deleted_at ON booking_deletion_audit(deleted_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_booking_deletion_booking_id ON booking_deletion_audit(booking_id)",
            "CREATE OR REPLACE FUNCTION archive_booking_on_status_change()
            RETURNS TRIGGER AS $$
            DECLARE
                region_name TEXT;
                region_key TEXT;
                resolved_region_id UUID;
                payload JSONB;
            BEGIN
                IF TG_OP <> 'UPDATE' THEN
                    RETURN NEW;
                END IF;

                IF NEW.status NOT IN ('completed'::booking_status, 'cancelled'::booking_status) THEN
                    RETURN NEW;
                END IF;

                region_name := COALESCE(NULLIF(TRIM(split_part(COALESCE(NEW.pickup_location, ''), ',', 1)), ''), 'Unknown');
                region_key := LOWER(REGEXP_REPLACE(region_name, '[^a-z0-9]+', '-', 'g'));
                region_key := TRIM(BOTH '-' FROM region_key);
                IF region_key = '' THEN
                    region_key := 'region-' || SUBSTR(MD5(region_name), 1, 10);
                END IF;

                INSERT INTO booking_regions (name, normalized_key)
                VALUES (region_name, region_key)
                ON CONFLICT (normalized_key)
                DO UPDATE SET name = EXCLUDED.name
                RETURNING id INTO resolved_region_id;

                payload := to_jsonb(NEW);

                INSERT INTO booking_archive (
                    booking_id, status, archived_at,
                    completed_at, cancelled_at,
                    archive_reason,
                    renter_id, owner_id, vehicle_id, driver_id,
                    customer_name, customer_email, customer_phone,
                    pickup_date, pickup_location, return_location,
                    total_amount, payment_method,
                    booking_payload, region_id
                )
                SELECT
                    NEW.id,
                    NEW.status,
                    NOW(),
                    NEW.completed_at,
                    NEW.cancelled_at,
                    'status_transition',
                    NEW.renter_id,
                    NEW.owner_id,
                    NEW.vehicle_id,
                    NEW.driver_id,
                    u.full_name,
                    u.email,
                    u.phone,
                    NEW.pickup_date,
                    NEW.pickup_location,
                    NEW.return_location,
                    NEW.total_amount,
                    NEW.payment_method::text,
                    payload,
                    resolved_region_id
                FROM users u
                WHERE u.id = NEW.renter_id
                ON CONFLICT (booking_id) DO UPDATE SET
                    status = EXCLUDED.status,
                    archived_at = EXCLUDED.archived_at,
                    completed_at = EXCLUDED.completed_at,
                    cancelled_at = EXCLUDED.cancelled_at,
                    archive_reason = EXCLUDED.archive_reason,
                    renter_id = EXCLUDED.renter_id,
                    owner_id = EXCLUDED.owner_id,
                    vehicle_id = EXCLUDED.vehicle_id,
                    driver_id = EXCLUDED.driver_id,
                    customer_name = EXCLUDED.customer_name,
                    customer_email = EXCLUDED.customer_email,
                    customer_phone = EXCLUDED.customer_phone,
                    pickup_date = EXCLUDED.pickup_date,
                    pickup_location = EXCLUDED.pickup_location,
                    return_location = EXCLUDED.return_location,
                    total_amount = EXCLUDED.total_amount,
                    payment_method = EXCLUDED.payment_method,
                    booking_payload = EXCLUDED.booking_payload,
                    region_id = EXCLUDED.region_id;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql",
            "DROP TRIGGER IF EXISTS trg_bookings_archive_on_final_status ON bookings",
            "CREATE TRIGGER trg_bookings_archive_on_final_status
                AFTER UPDATE OF status, completed_at, cancelled_at ON bookings
                FOR EACH ROW EXECUTE FUNCTION archive_booking_on_status_change()",
        ];

        foreach ($ddl as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (Throwable $e) {
                // Ignore environments without migration permissions.
            }
        }

        $this->historySchemaEnsured = true;
    }

    private function normalizeRegionName(?string $pickupLocation): ?string
    {
        $raw = trim((string)($pickupLocation ?? ''));
        if ($raw === '') {
            return null;
        }

        $parts = preg_split('/[,|\-]/', $raw);
        $name = trim((string)($parts[0] ?? $raw));
        if ($name === '') {
            $name = $raw;
        }

        if (strlen($name) > 120) {
            $name = substr($name, 0, 120);
        }

        return ucwords(strtolower($name));
    }

    private function normalizeRegionKey(string $name): string
    {
        $key = strtolower(trim($name));
        $key = preg_replace('/[^a-z0-9]+/', '-', $key) ?? '';
        $key = trim($key, '-');
        if ($key === '') {
            $key = 'region-' . substr(md5($name), 0, 10);
        }
        return $key;
    }

    private function findOrCreateRegionId(?string $pickupLocation): ?string
    {
        $name = $this->normalizeRegionName($pickupLocation);
        if ($name === null) {
            return null;
        }

        $key = $this->normalizeRegionKey($name);
        $stmt = $this->pdo->prepare("\n            INSERT INTO booking_regions (name, normalized_key)\n            VALUES (?, ?)\n            ON CONFLICT (normalized_key)\n            DO UPDATE SET name = EXCLUDED.name\n            RETURNING id\n        ");
        $stmt->execute([$name, $key]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (string)$id;
    }

    public function archiveBookingSnapshotIfEligible(string $bookingId): bool
    {
        $this->ensureBookingHistorySchema();

        $stmt = $this->pdo->prepare("\n            SELECT\n                b.*,\n                u.full_name AS customer_name,\n                u.email AS customer_email,\n                u.phone AS customer_phone\n            FROM bookings b\n            LEFT JOIN users u ON u.id = b.renter_id\n            WHERE b.id = ?\n              AND b.status IN ('completed'::booking_status, 'cancelled'::booking_status)\n            LIMIT 1\n        ");
        $stmt->execute([$bookingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        $regionId = $this->findOrCreateRegionId((string)($row['pickup_location'] ?? ''));
        $payload = json_encode($row);

        $insert = $this->pdo->prepare("\n            INSERT INTO booking_archive (\n                booking_id, status, archived_at,\n                completed_at, cancelled_at,\n                archive_reason,\n                renter_id, owner_id, vehicle_id, driver_id,\n                customer_name, customer_email, customer_phone,\n                pickup_date, pickup_location, return_location,\n                total_amount, payment_method,\n                booking_payload, region_id\n            ) VALUES (\n                :booking_id, :status, NOW(),\n                :completed_at, :cancelled_at,\n                'status_transition',\n                :renter_id, :owner_id, :vehicle_id, :driver_id,\n                :customer_name, :customer_email, :customer_phone,\n                :pickup_date, :pickup_location, :return_location,\n                :total_amount, :payment_method,\n                :booking_payload::jsonb, :region_id\n            )\n            ON CONFLICT (booking_id) DO UPDATE SET\n                status = EXCLUDED.status,\n                archived_at = EXCLUDED.archived_at,\n                completed_at = EXCLUDED.completed_at,\n                cancelled_at = EXCLUDED.cancelled_at,\n                archive_reason = EXCLUDED.archive_reason,\n                renter_id = EXCLUDED.renter_id,\n                owner_id = EXCLUDED.owner_id,\n                vehicle_id = EXCLUDED.vehicle_id,\n                driver_id = EXCLUDED.driver_id,\n                customer_name = EXCLUDED.customer_name,\n                customer_email = EXCLUDED.customer_email,\n                customer_phone = EXCLUDED.customer_phone,\n                pickup_date = EXCLUDED.pickup_date,\n                pickup_location = EXCLUDED.pickup_location,\n                return_location = EXCLUDED.return_location,\n                total_amount = EXCLUDED.total_amount,\n                payment_method = EXCLUDED.payment_method,\n                booking_payload = EXCLUDED.booking_payload,\n                region_id = EXCLUDED.region_id\n        ");

        return $insert->execute([
            ':booking_id' => $row['id'],
            ':status' => $row['status'],
            ':completed_at' => $row['completed_at'] ?? null,
            ':cancelled_at' => $row['cancelled_at'] ?? null,
            ':renter_id' => $row['renter_id'] ?? null,
            ':owner_id' => $row['owner_id'] ?? null,
            ':vehicle_id' => $row['vehicle_id'] ?? null,
            ':driver_id' => $row['driver_id'] ?? null,
            ':customer_name' => $row['customer_name'] ?? null,
            ':customer_email' => $row['customer_email'] ?? null,
            ':customer_phone' => $row['customer_phone'] ?? null,
            ':pickup_date' => $row['pickup_date'] ?? null,
            ':pickup_location' => $row['pickup_location'] ?? null,
            ':return_location' => $row['return_location'] ?? null,
            ':total_amount' => $row['total_amount'] ?? null,
            ':payment_method' => $row['payment_method'] ?? null,
            ':booking_payload' => $payload !== false ? $payload : '{}',
            ':region_id' => $regionId,
        ]);
    }

    public function backfillBookingHistory(int $limit = 200): int
    {
        $this->ensureBookingHistorySchema();

        $limit = max(1, min(2000, $limit));
        $stmt = $this->pdo->prepare("\n            SELECT b.id\n            FROM bookings b\n            LEFT JOIN booking_archive ba ON ba.booking_id = b.id\n            WHERE b.status IN ('completed'::booking_status, 'cancelled'::booking_status)\n              AND ba.booking_id IS NULL\n            ORDER BY COALESCE(b.completed_at, b.cancelled_at, b.updated_at, b.created_at) DESC\n            LIMIT ?\n        ");
        $stmt->execute([$limit]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $archived = 0;
        foreach ($ids as $id) {
            if ($this->archiveBookingSnapshotIfEligible((string)$id)) {
                $archived++;
            }
        }

        return $archived;
    }

    private function buildHistoryWhereClause(array $filters, array &$params): string
    {
        $clauses = ["a.archived_at >= (NOW() - INTERVAL '5 years')"];

        $status = strtolower(trim((string)($filters['status'] ?? '')));
        if (in_array($status, ['completed', 'cancelled'], true)) {
            $clauses[] = 'a.status = ?::booking_status';
            $params[] = $status;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $clauses[] = "(COALESCE(a.completed_at, a.cancelled_at, a.archived_at) AT TIME ZONE 'UTC')::date >= ?::date";
            $params[] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $clauses[] = "(COALESCE(a.completed_at, a.cancelled_at, a.archived_at) AT TIME ZONE 'UTC')::date <= ?::date";
            $params[] = $dateTo;
        }

        $regionId = trim((string)($filters['region_id'] ?? ''));
        if ($regionId !== '') {
            $clauses[] = 'a.region_id = ?';
            $params[] = $regionId;
        }

        $search = strtolower(trim((string)($filters['search'] ?? '')));
        if ($search !== '') {
            $like = '%' . $search . '%';
            if (preg_match('/^[0-9a-f-]{36}$/i', $search)) {
                $clauses[] = "(a.renter_id::text = ? OR a.booking_id::text = ? OR LOWER(COALESCE(a.customer_name, '')) LIKE ? OR LOWER(COALESCE(a.customer_email, '')) LIKE ? OR LOWER(COALESCE(a.customer_phone, '')) LIKE ?)";
                $params[] = $search;
                $params[] = $search;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            } else {
                $clauses[] = "(LOWER(COALESCE(a.customer_name, '')) LIKE ? OR LOWER(COALESCE(a.customer_email, '')) LIKE ? OR LOWER(COALESCE(a.customer_phone, '')) LIKE ? OR a.booking_id::text LIKE ?)";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
        }

        return implode(' AND ', $clauses);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int,limit:int,offset:int}
     */
    public function listBookingHistory(array $filters, int $limit = 50, int $offset = 0): array
    {
        $this->ensureBookingHistorySchema();
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $params = [];
        $where = $this->buildHistoryWhereClause($filters, $params);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM booking_archive a WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $listParams = $params;
        $listParams[] = $limit;
        $listParams[] = $offset;

        $stmt = $this->pdo->prepare("\n            SELECT\n                a.id,\n                a.booking_id,\n                a.status,\n                a.archived_at,\n                a.completed_at,\n                a.cancelled_at,\n                a.renter_id,\n                a.owner_id,\n                a.vehicle_id,\n                a.driver_id,\n                a.customer_name,\n                a.customer_email,\n                a.customer_phone,\n                a.pickup_date,\n                a.pickup_location,\n                a.return_location,\n                a.total_amount,\n                a.payment_method,\n                a.archive_reason,\n                r.name AS region_name\n            FROM booking_archive a\n            LEFT JOIN booking_regions r ON r.id = a.region_id\n            WHERE {$where}\n            ORDER BY a.archived_at DESC\n            LIMIT ? OFFSET ?\n        ");
        $stmt->execute($listParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'rows' => $rows,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function getBookingHistorySummary(array $filters): array
    {
        $this->ensureBookingHistorySchema();

        $params = [];
        $where = $this->buildHistoryWhereClause($filters, $params);

        $stmt = $this->pdo->prepare("\n            SELECT\n                COUNT(*)::int AS total_bookings,\n                SUM(CASE WHEN a.status = 'completed'::booking_status THEN 1 ELSE 0 END)::int AS completed_bookings,\n                SUM(CASE WHEN a.status = 'cancelled'::booking_status THEN 1 ELSE 0 END)::int AS cancelled_bookings,\n                COALESCE(SUM(a.total_amount), 0)::numeric(12,2) AS total_revenue\n            FROM booking_archive a\n            WHERE {$where}\n        ");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_bookings' => (int)($row['total_bookings'] ?? 0),
            'completed_bookings' => (int)($row['completed_bookings'] ?? 0),
            'cancelled_bookings' => (int)($row['cancelled_bookings'] ?? 0),
            'total_revenue' => (float)($row['total_revenue'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function getBookingHistoryAggregation(array $filters, string $granularity = 'daily'): array
    {
        $this->ensureBookingHistorySchema();

        $bucket = strtolower($granularity) === 'quarterly' ? 'quarter' : 'day';
        $label = $bucket === 'quarter' ? 'YYYY-"Q"Q' : 'YYYY-MM-DD';
        $limit = $bucket === 'quarter' ? 40 : 180;

        $params = [];
        $where = $this->buildHistoryWhereClause($filters, $params);

        $sql = "\n            SELECT\n                TO_CHAR(DATE_TRUNC('{$bucket}', (COALESCE(a.completed_at, a.cancelled_at, a.archived_at) AT TIME ZONE 'UTC')), '{$label}') AS period_label,\n                DATE_TRUNC('{$bucket}', (COALESCE(a.completed_at, a.cancelled_at, a.archived_at) AT TIME ZONE 'UTC')) AS period_start,\n                COUNT(*)::int AS total_bookings,\n                SUM(CASE WHEN a.status = 'completed'::booking_status THEN 1 ELSE 0 END)::int AS completed_bookings,\n                SUM(CASE WHEN a.status = 'cancelled'::booking_status THEN 1 ELSE 0 END)::int AS cancelled_bookings,\n                COALESCE(SUM(a.total_amount), 0)::numeric(12,2) AS total_revenue\n            FROM booking_archive a\n            WHERE {$where}\n            GROUP BY period_label, period_start\n            ORDER BY period_start DESC\n            LIMIT {$limit}\n        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listBookingHistoryRegions(): array
    {
        $this->ensureBookingHistorySchema();
        $stmt = $this->pdo->query("SELECT id, name, normalized_key FROM booking_regions ORDER BY name ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function deleteBookingWithAudit(string $bookingId, string $adminUserId, ?string $deleteReason = null): bool
    {
        $this->ensureBookingHistorySchema();

        try {
            $this->pdo->beginTransaction();

            $bookingStmt = $this->pdo->prepare("SELECT * FROM bookings WHERE id = ? FOR UPDATE");
            $bookingStmt->execute([$bookingId]);
            $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
            if (!$booking) {
                $this->pdo->rollBack();
                return false;
            }

            $this->archiveBookingSnapshotIfEligible($bookingId);

            $snapshot = json_encode($booking);
            $auditStmt = $this->pdo->prepare("\n                INSERT INTO booking_deletion_audit (booking_id, deleted_by, delete_reason, booking_snapshot)\n                VALUES (?, ?, ?, ?::jsonb)\n            ");
            $auditStmt->execute([
                $bookingId,
                $adminUserId,
                $deleteReason,
                $snapshot !== false ? $snapshot : '{}',
            ]);

            $deleteStmt = $this->pdo->prepare("DELETE FROM bookings WHERE id = ?");
            $deleteStmt->execute([$bookingId]);
            $deleted = $deleteStmt->rowCount() > 0;

            if (!$deleted) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Find a vehicle matching the specified tier
     * @return array<string,mixed>|null
     */
    public function findVehicleForTier(
        string $rideTier,
        string $excludeRenterId,
        int $seatCapacity = 4,
        ?string $requestWindowStart = null,
        ?string $requestWindowEnd = null,
        ?string $requestPickupAt = null,
        ?string $requestServiceType = null
    ): ?array
    {
        $this->ensureVehicleServiceTierColumnExists();

        $tierConditions = '';
        match ($rideTier) {
            'eco' => $tierConditions = "LOWER(COALESCE(v.service_tier, '')) = 'eco'",
            'standard' => $tierConditions = "LOWER(COALESCE(v.service_tier, '')) = 'standard'",
            'luxury' => $tierConditions = "LOWER(COALESCE(v.service_tier, '')) IN ('luxury', 'premium')",
            default => throw new Exception('Invalid ride tier')
        };

        $normalizedSeatCapacity = $seatCapacity >= 7 ? 7 : 4;
        $seatCondition = $normalizedSeatCapacity >= 7 ? 'v.seats >= 7' : 'v.seats < 7';

        $bookingPickupExpr = "COALESCE(
            CASE
                WHEN NULLIF(TRIM(COALESCE(b.pickup_time, '')), '') IS NOT NULL
                     AND UPPER(REPLACE(TRIM(COALESCE(b.pickup_time, '')), ' ', '')) ~ '^[0-9]{1,2}:[0-9]{2}(AM|PM)$'
                    THEN to_timestamp(
                        to_char(b.pickup_date, 'YYYY-MM-DD') || ' ' || UPPER(REPLACE(TRIM(b.pickup_time), ' ', '')),
                        'YYYY-MM-DD HH12:MIAM'
                    )
                WHEN NULLIF(TRIM(COALESCE(b.pickup_time, '')), '') IS NOT NULL
                     AND TRIM(COALESCE(b.pickup_time, '')) ~ '^[0-9]{1,2}:[0-9]{2}(:[0-9]{2})?$'
                    THEN (to_char(b.pickup_date, 'YYYY-MM-DD') || ' ' || TRIM(b.pickup_time))::timestamp
                ELSE b.pickup_date::timestamp
            END,
            b.pickup_date::timestamp
        )";

        if (($requestPickupAt === null || trim($requestPickupAt) === '') && !empty($requestWindowStart)) {
            try {
                $requestPickupAt = (new DateTimeImmutable($requestWindowStart))
                    ->add(new DateInterval('PT2H'))
                    ->format('Y-m-d H:i:sP');
            } catch (Exception $e) {
                $requestPickupAt = null;
            }
        }

        $existingPreBufferExpr = "CASE
            WHEN LOWER(COALESCE(b.service_type, 'local')) = 'daily-hire' THEN 24
            ELSE 2
        END";
        $existingDurationExpr = "CASE
            WHEN LOWER(COALESCE(b.service_type, 'local')) = 'daily-hire' THEN 24
            ELSE GREATEST(1, CEIL((COALESCE(b.distance_km, 0) * 0.621371) / 20.0))::int
        END";
        $existingWindowStartExpr = "({$bookingPickupExpr} - ({$existingPreBufferExpr} * INTERVAL '1 hour'))";
        $existingWindowEndExpr = "({$bookingPickupExpr} + (({$existingDurationExpr} + 2) * INTERVAL '1 hour'))";

        $isDailyHireRequest = strtolower(trim((string)($requestServiceType ?? ''))) === 'daily-hire';

        $conflictClause = '';
        $params = [$excludeRenterId];
        if ($isDailyHireRequest && !empty($requestWindowStart) && !empty($requestWindowEnd)) {
            $conflictClause = "
              AND NOT EXISTS (
                    SELECT 1
                    FROM bookings b
                    WHERE b.vehicle_id = v.id
                                            AND b.booking_type = 'minicab'
                      AND b.status IN ('pending', 'in_progress')
                      AND (
                          {$existingWindowStartExpr} < ?::timestamptz
                          AND
                          {$existingWindowEndExpr} > ?::timestamptz
                      )
                )
            ";
            $params[] = $requestWindowEnd;
            $params[] = $requestWindowStart;
        } elseif (!empty($requestPickupAt)) {
            $conflictClause = "
              AND NOT EXISTS (
                    SELECT 1
                    FROM bookings b
                    WHERE b.vehicle_id = v.id
                                            AND b.booking_type = 'minicab'
                      AND b.status IN ('pending', 'in_progress')
                      AND (
                          {$existingWindowStartExpr} < ?::timestamptz
                          AND
                          {$existingWindowEndExpr} > ?::timestamptz
                      )
                )
            ";
            $params[] = $requestPickupAt;
            $params[] = $requestPickupAt;
        }

        $stmt = $this->pdo->prepare("
            SELECT v.id, v.owner_id, v.service_tier,
                   v.category, v.status, v.brand, v.model, v.seats, v.owner_id as owner_id
            FROM vehicles v
            WHERE v.status = 'available'
              AND {$tierConditions}
              AND {$seatCondition}
              AND v.owner_id != ?
              {$conflictClause}
            ORDER BY RANDOM()
            LIMIT 1
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get vehicle details for booking creation
     * @return array<string,mixed>|null
     */
    public function getVehicleForBooking(string $vehicleId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, owner_id, service_tier, 
                   category, status, brand, model
            FROM vehicles
            WHERE id = ?
        ");
        $stmt->execute([$vehicleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create a booking record
     * @param array<string,mixed> $data
     * @return array<string,mixed> Returns booking with id and created_at
     */
    public function createBooking(array $data): array
    {
        $columns = [
            'renter_id',
            'vehicle_id',
            'owner_id',
            'booking_type',
            'pickup_date',
            'pickup_location',
            'total_days',
            'subtotal',
            'discount_amount',
            'total_amount',
            'status',
        ];
        $values = [
            $data['renter_id'],
            $data['vehicle_id'],
            $data['owner_id'],
            $data['booking_type'],
            $data['pickup_date'],
            $data['pickup_location'],
            $data['total_days'],
            $data['subtotal'],
            $data['discount_amount'],
            $data['total_amount'],
            'pending',
        ];

        $optionalMap = [
            'price_per_day' => isset($data['price_per_day'])
                ? (float)$data['price_per_day']
                : round(((float)($data['subtotal'] ?? 0)) / max(1, (int)($data['total_days'] ?? 1)), 2),
            'pickup_time' => $data['pickup_time'] ?? null,
            'return_date' => $data['return_date'] ?? null,
            'return_location' => $data['return_location'] ?? null,
            'promo_code' => $data['promo_code'] ?? null,
            'special_requests' => $data['special_requests'] ?? '',
            'driver_requested' => $data['driver_requested'] ?? 't',
            'distance_km' => $data['distance_km'] ?? null,
            'transfer_cost' => $data['transfer_cost'] ?? null,
            'service_type' => $data['service_type'] ?? null,
            'number_of_passengers' => $data['number_of_passengers'] ?? 1,
            'ride_tier' => $data['ride_tier'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
        ];

        foreach ($optionalMap as $column => $value) {
            if ($this->bookingHasColumn($column)) {
                $columns[] = $column;
                $values[] = $value;
            }
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $returningCols = $this->bookingHasColumn('created_at') ? 'id, created_at' : 'id';
        $sql = 'INSERT INTO bookings (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ') RETURNING ' . $returningCols;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['id' => '', 'created_at' => null];
        }
        if (!array_key_exists('created_at', $row)) {
            $row['created_at'] = null;
        }

        return $row;
    }

    /**
     * Create active trip record for minicab bookings
     */
    public function createActiveTrip(
        string $bookingId,
        string $userId,
        string $vehicleId,
        ?float $pickupLat,
        ?float $pickupLng,
        ?float $destLat,
        ?float $destLng
    ): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO active_trips (
                    booking_id,
                    user_id,
                    vehicle_id,
                    pickup_lat,
                    pickup_lng,
                    destination_lat,
                    destination_lng,
                    status,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'searching_driver', NOW(), NOW())
            ");
            $stmt->execute([
                $bookingId,
                $userId,
                $vehicleId,
                $pickupLat,
                $pickupLng,
                $destLat,
                $destLng,
            ]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get booking details by ID
     * @return array<string,mixed>|null
     */
    public function getById(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM bookings WHERE id = ?
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get user's bookings (renter)
     * @return array<int,array<string,mixed>>
     */
    public function getUserBookings(string $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM bookings
            WHERE renter_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get owner's bookings (vehicle owner)
     * @return array<int,array<string,mixed>>
     */
    public function getOwnerBookings(string $ownerId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM bookings
            WHERE owner_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$ownerId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Confirm booking (move from pending to confirmed)
     */
    public function confirmBooking(string $bookingId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE bookings
            SET status = 'confirmed'
            WHERE id = ? AND status = 'pending'
        ");
        return $stmt->execute([$bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Mark booking as completed
     */
    public function completeBooking(string $bookingId): bool
    {
        $stmt = $this->pdo->prepare("\n            UPDATE bookings\n            SET status = 'completed'\n            WHERE id = ?\n        ");
        $ok = $stmt->execute([$bookingId]) && $stmt->rowCount() > 0;
        if ($ok) {
            $this->archiveBookingSnapshotIfEligible($bookingId);
        }
        return $ok;
    }

    /**
     * Cancel booking
     */
    public function cancelBooking(string $bookingId, ?string $reason = null): bool
    {
        $stmt = $this->pdo->prepare("\n            UPDATE bookings\n            SET status = 'cancelled', cancellation_reason = ?\n            WHERE id = ?\n        ");
        $ok = $stmt->execute([$reason, $bookingId]) && $stmt->rowCount() > 0;
        if ($ok) {
            $this->archiveBookingSnapshotIfEligible($bookingId);
        }
        return $ok;
    }

    /**
     * Update booking status
     */
    public function updateStatus(string $bookingId, string $status): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE bookings
            SET status = ?
            WHERE id = ?
        ");
        $ok = $stmt->execute([$status, $bookingId]) && $stmt->rowCount() > 0;
        if ($ok && in_array($status, ['completed', 'cancelled'], true)) {
            $this->archiveBookingSnapshotIfEligible($bookingId);
        }
        return $ok;
    }

    /**
     * Assign vehicle and owner for a booking.
     */
    public function assignVehicleToBooking(string $bookingId, string $vehicleId, string $ownerId, float $pricePerDay): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE bookings
            SET vehicle_id = ?, owner_id = ?, price_per_day = ?
            WHERE id = ?
        ");
        return $stmt->execute([$vehicleId, $ownerId, $pricePerDay, $bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Get bookings by status
     * @return array<int,array<string,mixed>>
     */
    public function getByStatus(string $status, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM bookings
            WHERE status = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$status, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if user owns or rented the booking
     */
    public function userHasAccessToBooking(string $bookingId, string $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM bookings
            WHERE id = ? AND (renter_id = ? OR owner_id = ?)
        ");
        $stmt->execute([$bookingId, $userId, $userId]);
        return $stmt->fetch() !== false;
    }

    /**
     * Get booking invoice data
     * @return array<string,mixed>|null
     */
    public function getInvoiceData(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, 
                   u.full_name as renter_name, u.email as renter_email,
                   v.brand, v.model, v.license_plate, v.year
            FROM bookings b
            JOIN users u ON b.renter_id = u.id
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Mark vehicle as rented when booking is confirmed/created
     */
    public function markVehicleRented(string $vehicleId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE vehicles
            SET status = 'rented'
            WHERE id = ? AND status = 'available'
        ");
        return $stmt->execute([$vehicleId]) && $stmt->rowCount() > 0;
    }

    /**
     * Mark vehicle as available when booking is completed/cancelled
     */
    public function markVehicleAvailable(string $vehicleId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE vehicles
            SET status = 'available'
            WHERE id = ?
        ");
        return $stmt->execute([$vehicleId]) && $stmt->rowCount() > 0;
    }

    /**
     * Get booking with related vehicle and user info
     * @return array<string,mixed>|null
     */
    public function getWithDetails(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, 
                   u.full_name as renter_name, u.email as renter_email, u.phone as renter_phone,
                   v.brand, v.model, v.license_plate, v.year, v.thumbnail_url,
                   owner.full_name as owner_name, owner.email as owner_email
            FROM bookings b
            JOIN users u ON b.renter_id = u.id
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            LEFT JOIN users owner ON b.owner_id = owner.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * List all bookings with limited detail
     * @return array<int,array<string,mixed>>
     */
    public function listAll(int $limit = 50, int $offset = 0): array
    {
        $driverIdExpr = $this->bookingHasColumn('driver_id') ? 'b.driver_id' : 'NULL::uuid AS driver_id';
        $serviceTypeExpr = $this->bookingHasColumn('service_type') ? 'b.service_type' : 'NULL::text AS service_type';
        $pickupTimeExpr = $this->bookingHasColumn('pickup_time') ? 'b.pickup_time' : 'NULL::text AS pickup_time';
        $passengerExpr = $this->bookingHasColumn('number_of_passengers') ? 'b.number_of_passengers' : 'NULL::int AS number_of_passengers';
        $rideTierExpr = $this->bookingHasColumn('ride_tier') ? 'b.ride_tier' : 'NULL::text AS ride_tier';
        $acceptedExpr = $this->bookingHasColumn('accepted_by_driver_at') ? 'b.accepted_by_driver_at' : 'NULL::timestamptz AS accepted_by_driver_at';

        $driverJoin = $this->bookingHasColumn('driver_id')
            ? 'LEFT JOIN users d ON b.driver_id = d.id'
            : '';

        $driverInfoExpr = $this->bookingHasColumn('driver_id')
            ? 'd.full_name as driver_name, d.phone as driver_phone'
            : 'NULL::text AS driver_name, NULL::text AS driver_phone';

        $stmt = $this->pdo->prepare("
            SELECT 
                b.id, b.renter_id, {$driverIdExpr}, b.status, b.booking_type,
                b.pickup_location, b.pickup_date, {$pickupTimeExpr}, {$serviceTypeExpr},
                b.total_amount, {$passengerExpr}, {$rideTierExpr},
                b.created_at, {$acceptedExpr},
                u.full_name as user_name, u.phone as user_phone,
                {$driverInfoExpr}
            FROM bookings b
            JOIN users u ON b.renter_id = u.id
            {$driverJoin}
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get booking with user and driver details
     * @return array<string,mixed>|null
     */
    public function getBookingWithUserAndDriver(string $bookingId): ?array
    {
        $driverIdExpr = $this->bookingHasColumn('driver_id') ? 'b.driver_id' : 'NULL::uuid AS driver_id';
        $serviceTypeExpr = $this->bookingHasColumn('service_type') ? 'b.service_type' : 'NULL::text AS service_type';
        $passengerExpr = $this->bookingHasColumn('number_of_passengers') ? 'b.number_of_passengers' : 'NULL::int AS number_of_passengers';
        $rideTierExpr = $this->bookingHasColumn('ride_tier') ? 'b.ride_tier' : 'NULL::text AS ride_tier';
        $pickupTimeExpr = $this->bookingHasColumn('pickup_time') ? 'b.pickup_time' : 'NULL::text AS pickup_time';
        $acceptedExpr = $this->bookingHasColumn('accepted_by_driver_at') ? 'b.accepted_by_driver_at' : 'NULL::timestamptz AS accepted_by_driver_at';
        $completedExpr = $this->bookingHasColumn('ride_completed_at') ? 'b.ride_completed_at' : 'NULL::timestamptz AS ride_completed_at';

        $this->ensureVehicleServiceTierColumnExists();
        $vehicleCols = [];
        try {
            $vehicleStmt = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'vehicles'");
            $vehicleCols = $vehicleStmt ? $vehicleStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        } catch (Throwable $e) {
            $vehicleCols = [];
        }
        $vehicleColsMap = [];
        foreach ($vehicleCols as $col) {
            $vehicleColsMap[(string)$col] = true;
        }
        $vehicleTierExpr = isset($vehicleColsMap['service_tier']) ? 'v.service_tier' : 'NULL::text AS service_tier';
        $vehicleSeatsExpr = isset($vehicleColsMap['seats']) ? 'v.seats' : 'NULL::int AS seats';

        $driverJoin = $this->bookingHasColumn('driver_id')
            ? 'LEFT JOIN users d ON b.driver_id = d.id'
            : '';

        $driverInfoExpr = $this->bookingHasColumn('driver_id')
            ? 'd.full_name as driver_name, d.phone as driver_phone'
            : 'NULL::text AS driver_name, NULL::text AS driver_phone';

        $stmt = $this->pdo->prepare("
            SELECT 
                b.id, b.renter_id, b.owner_id, {$driverIdExpr}, b.vehicle_id, b.status, b.booking_type,
                b.pickup_location, b.return_location, b.pickup_date, b.return_date,
                {$serviceTypeExpr}, {$pickupTimeExpr}, b.total_amount, {$passengerExpr},
                {$rideTierExpr}, b.special_requests,
                b.created_at, {$acceptedExpr}, {$completedExpr},
                u.full_name as user_name, u.phone as user_phone, u.email,
                {$driverInfoExpr},
                v.brand, v.model, v.license_plate, v.year,
                {$vehicleTierExpr}, {$vehicleSeatsExpr}
            FROM bookings b
            JOIN users u ON b.renter_id = u.id
            {$driverJoin}
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Load booking data needed for customer-side modify/cancel checks.
     * @return array<string,mixed>|null
     */
    public function getBookingForCustomerEdit(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("\n            SELECT id, renter_id, owner_id, status, booking_type,\n                   pickup_date, pickup_time,\n                   pickup_location, return_location,\n                   service_type, ride_tier, number_of_passengers,\n                   distance_km, subtotal, total_amount, price_per_day, payment_method\n            FROM bookings\n            WHERE id = ?\n            LIMIT 1\n        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Update customer-editable booking fields only.
     * Allowed keys include service_type, pickup_date, ride_tier, seats and recalculated fare fields.
     */
    public function updateCustomerEditableFields(string $bookingId, array $updates): bool
    {
        $allowedFields = [
            'pickup_location',
            'return_location',
            'service_type',
            'pickup_date',
            'pickup_time',
            'ride_tier',
            'number_of_passengers',
            'distance_km',
            'subtotal',
            'total_amount',
            'transfer_cost',
            'payment_method'
        ];
        $setParts = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $updates)) {
                continue;
            }

            if ($field === 'number_of_passengers') {
                $setParts[] = $field . ' = ?';
                $params[] = (int)$updates[$field];
            } elseif ($field === 'pickup_date') {
                $setParts[] = $field . ' = ?::date';
                $params[] = trim((string)$updates[$field]);
            } elseif ($field === 'pickup_time') {
                $setParts[] = $field . ' = ?';
                $params[] = trim((string)$updates[$field]);
            } elseif (in_array($field, ['distance_km', 'subtotal', 'total_amount', 'transfer_cost'], true)) {
                $setParts[] = $field . ' = ?';
                $params[] = $updates[$field] === null ? null : (float)$updates[$field];
            } else {
                $setParts[] = $field . ' = ?';
                $params[] = $updates[$field] === null ? null : trim((string)$updates[$field]);
            }
        }

        if (empty($setParts)) {
            return false;
        }

        $sql = 'UPDATE bookings SET ' . implode(', ', $setParts) . ', updated_at = NOW() WHERE id = ?';
        $params[] = $bookingId;

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params) && $stmt->rowCount() > 0;
    }

    /**
     * List bookings for a user (as renter or owner)
     * @return array<int,array<string,mixed>>
     */
    public function listUserBookings(string $userId): array
    {
        $bookingPaymentExpr = $this->bookingHasColumn('payment_method')
            ? 'COALESCE(b.payment_method::text, \'\')'
            : "''";
        $stmt = $this->pdo->prepare("
            SELECT b.id, b.renter_id, b.owner_id, b.vehicle_id, b.booking_type,
                   b.pickup_date, b.pickup_time, b.return_date, b.pickup_location, b.return_location,
                   b.airport_name, b.total_days, b.price_per_day, b.subtotal,
                   b.discount_amount, b.total_amount, b.promo_code, b.status,
                   b.special_requests, b.driver_requested, b.created_at,
                   b.confirmed_at, b.completed_at, b.cancelled_at,
                   b.distance_km, b.transfer_cost, b.service_type,
                   b.number_of_passengers, b.ride_tier,
                   v.brand, v.model, v.year, v.category, v.license_plate,
                   u_renter.full_name AS renter_name,
                   u_renter.email AS renter_email,
                   CASE
                       WHEN {$bookingPaymentExpr} <> ''
                       THEN {$bookingPaymentExpr}
                       WHEN p.method::text = 'bank_transfer'
                            AND COALESCE(p.payment_details->>'original_method', '') = 'account_balance'
                       THEN 'account_balance'
                       ELSE p.method::text
                   END AS payment_method,
                   p.status AS payment_status,
                   rev.id AS review_id,
                   rev.rating AS review_rating
            FROM bookings b
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            LEFT JOIN users u_renter ON b.renter_id = u_renter.id
            LEFT JOIN payments p ON p.booking_id = b.id
            LEFT JOIN reviews rev ON rev.booking_id = b.id AND rev.user_id = ?
            WHERE b.renter_id = ? OR b.owner_id = ?
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Clear assigned vehicle from a booking (used when pending booking is cancelled/rejected)
     */
    public function unassignVehicleFromBooking(string $bookingId): bool
    {
        // Some environments still keep strict NOT NULL constraints on these columns.
        // Reject/cancel workflows need to clear assignment, so relax constraints defensively.
        try {
            $this->pdo->exec("ALTER TABLE bookings ALTER COLUMN vehicle_id DROP NOT NULL");
        } catch (Throwable $e) {
            // Ignore if already nullable or no permission.
        }
        try {
            $this->pdo->exec("ALTER TABLE bookings ALTER COLUMN owner_id DROP NOT NULL");
        } catch (Throwable $e) {
            // Ignore if already nullable or no permission.
        }
        try {
            $this->pdo->exec("ALTER TABLE bookings ALTER COLUMN price_per_day DROP NOT NULL");
        } catch (Throwable $e) {
            // Ignore if already nullable or no permission.
        }

        $stmt = $this->pdo->prepare(" 
            UPDATE bookings
            SET vehicle_id = NULL, owner_id = NULL, price_per_day = NULL
            WHERE id = ?
        ");
        return $stmt->execute([$bookingId]);
    }

    /**
     * Update booking status
     */
    public function updateBookingStatus(string $bookingId, string $newStatus): bool
    {
        $extraSql = '';
        if ($newStatus === 'confirmed') $extraSql = ', confirmed_at = NOW()';
        if ($newStatus === 'completed') $extraSql = ', completed_at = NOW()';
        if ($newStatus === 'cancelled') $extraSql = ', cancelled_at = NOW()';

        $stmt = $this->pdo->prepare("UPDATE bookings SET status = ?::booking_status" . $extraSql . " WHERE id = ?");
        $ok = $stmt->execute([$newStatus, $bookingId]) && $stmt->rowCount() > 0;
        if ($ok && in_array($newStatus, ['completed', 'cancelled'], true)) {
            $this->archiveBookingSnapshotIfEligible($bookingId);
        }
        return $ok;
    }

    /**
     * Mark payment as paid
     */
    public function markPaymentAsPaid(string $bookingId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE payments SET status = 'paid'::payment_status, paid_at = NOW() WHERE booking_id = ?");
        return $stmt->execute([$bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Update vehicle status
     */
    public function updateVehicleStatus(string $vehicleId, string $status): bool
    {
        $statusCast = match ($status) {
            'available', 'rented', 'assigned' => true,
            default => false
        };

        if (!$statusCast) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE vehicles SET status = ?::vehicle_status WHERE id = ?");
        return $stmt->execute([$status, $vehicleId]);
    }

    /**
     * Increment vehicle stats
     */
    public function incrementVehicleStats(string $vehicleId, string $stat): bool
    {
        if ($stat === 'bookings') {
            $stmt = $this->pdo->prepare("UPDATE vehicles SET total_bookings = total_bookings + 1 WHERE id = ?");
        } else {
            return false;
        }

        return $stmt->execute([$vehicleId]);
    }

    /**
     * Update vehicle rating stats
     */
    public function updateVehicleRating(string $vehicleId, float $avgRating, int $totalReviews): bool
    {
        $stmt = $this->pdo->prepare("UPDATE vehicles SET avg_rating = ?, total_reviews = ? WHERE id = ?");
        return $stmt->execute([$avgRating, $totalReviews, $vehicleId]);
    }

    /**
     * Delete a booking (admin only)
     * @return bool True if deleted, false if not found
     */
    public function deleteBooking(string $bookingId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * List all bookings with user and vehicle details (admin only)
     * @return array<int,array<string,mixed>>
     */
    public function listAllBookingsForAdmin(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.id, b.renter_id, b.vehicle_id, b.owner_id, b.booking_type,
                   b.pickup_date, b.return_date, b.pickup_location, b.return_location,
                   b.total_days, b.price_per_day, b.subtotal, b.discount_amount,
                   b.total_amount, b.promo_code, b.status, b.special_requests,
                   b.created_at,
                   u.full_name AS renter_name, u.email AS renter_email,
                   v.brand, v.model, v.year, v.license_plate,
                   ow.full_name AS owner_name, ow.email AS owner_email
            FROM bookings b
            LEFT JOIN users u ON b.renter_id = u.id
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            LEFT JOIN users ow ON b.owner_id = ow.id
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if user already reviewed this booking
     */
    public function userHasReviewed(string $bookingId, string $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM reviews WHERE booking_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$bookingId, $userId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Get review stats for a vehicle
     */
    public function getVehicleReviewStats(string $vehicleId): array
    {
        $stmt = $this->pdo->prepare("SELECT ROUND(AVG(rating)::numeric, 1) as avg_rating, COUNT(*) as total FROM reviews WHERE vehicle_id = ?");
        $stmt->execute([$vehicleId]);
        return (array)$stmt->fetch(PDO::FETCH_ASSOC) ?? ['avg_rating' => null, 'total' => 0];
    }

    /**
     * Insert a review
     */
    public function insertReview(string $bookingId, string $vehicleId, string $userId, int $rating, string $comment): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO reviews (booking_id, vehicle_id, user_id, rating, comment)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$bookingId, $vehicleId, $userId, $rating, $comment]);
    }

    /**
     * Check if user can access booking (is renter or owner)
     */
    public function canUserAccessBooking(string $bookingId, string $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM bookings WHERE id = ? AND (renter_id = ? OR owner_id = ?) LIMIT 1");
        $stmt->execute([$bookingId, $userId, $userId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Get booking basic info by ID
     */
    public function getBookingInfo(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, renter_id, vehicle_id, status FROM bookings WHERE id = ? LIMIT 1");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get user info by ID
     */
    public function getUserInfo(string $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT email, full_name, phone, address FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get vehicle status by ID
     */
    public function getVehicleStatus(string $vehicleId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT status FROM vehicles WHERE id = ? LIMIT 1");
        $stmt->execute([$vehicleId]);
        return $stmt->fetchColumn();
    }

    /**
     * Get vehicle full info by ID
     */
    public function getVehicleInfo(string $vehicleId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT brand, model, year, license_plate FROM vehicles WHERE id = ? LIMIT 1");
        $stmt->execute([$vehicleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get payment method for booking
     */
    public function getPaymentMethod(string $bookingId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT method FROM payments WHERE booking_id = ? LIMIT 1");
        $stmt->execute([$bookingId]);
        return $stmt->fetchColumn();
    }

    /**
     * Get booking full info
     */
    public function getBookingFullInfo(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT subtotal, discount_amount, total_amount, pickup_location, return_location, pickup_date, pickup_time, vehicle_id FROM bookings WHERE id = ? LIMIT 1");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get user address by ID
     */
    public function getUserAddress(string $userId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT address FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string)($row['address'] ?? '') : null;
    }

    /**
     * Create payment record
     */
    public function createPayment(string $bookingId, string $userId, float $amount, string $method): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payments (booking_id, user_id, amount, method, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        return $stmt->execute([$bookingId, $userId, $amount, $method]);
    }

    /**
     * Persist gateway transaction data for an existing booking payment row
     */
    public function attachPaymentTransaction(string $bookingId, string $transactionId, array $paymentDetails = []): bool
    {
        $detailsJson = json_encode($paymentDetails);
        $stmt = $this->pdo->prepare("
            UPDATE payments
            SET transaction_id = ?, payment_details = ?::jsonb
            WHERE booking_id = ?
        ");
        return $stmt->execute([$transactionId, $detailsJson ?: '{}', $bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Load payment row by PayPal order/transaction ID
     * @return array<string,mixed>|null
     */
    public function getPaymentByTransactionId(string $transactionId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE transaction_id = ? LIMIT 1");
        $stmt->execute([$transactionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Load payment row by booking ID
     * @return array<string,mixed>|null
     */
    public function getPaymentByBookingId(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE booking_id = ? LIMIT 1");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Mark payment as paid/failed and optionally persist gateway response
     */
    public function updatePaymentByTransactionId(string $transactionId, string $status, array $paymentDetails = [], bool $setPaidAt = false): bool
    {
        if (!in_array($status, ['pending', 'paid', 'refunded', 'failed'], true)) {
            return false;
        }

        $detailsJson = json_encode($paymentDetails);
        $sql = "UPDATE payments SET status = ?::payment_status, payment_details = ?::jsonb";
        if ($setPaidAt) {
            $sql .= ", paid_at = NOW()";
        }
        $sql .= " WHERE transaction_id = ?";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $detailsJson ?: '{}', $transactionId]) && $stmt->rowCount() > 0;
    }

    /**
     * Update payment by booking ID and optionally persist gateway response.
     */
    public function updatePaymentByBookingId(string $bookingId, string $status, array $paymentDetails = [], bool $setPaidAt = false): bool
    {
        if (!in_array($status, ['pending', 'paid', 'refunded', 'failed'], true)) {
            return false;
        }

        $detailsJson = json_encode($paymentDetails);
        $sql = "UPDATE payments SET status = ?::payment_status, payment_details = ?::jsonb";
        if ($setPaidAt) {
            $sql .= ", paid_at = NOW()";
        }
        $sql .= " WHERE booking_id = ?";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $detailsJson ?: '{}', $bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Remove an unpaid PayPal booking after checkout cancellation.
     * Only allows deleting renter-owned, pending bookings with non-paid PayPal payment state.
     */
    public function removeUnpaidPaypalBooking(string $bookingId, string $userId): bool
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("SELECT id, status, renter_id FROM bookings WHERE id = ? FOR UPDATE");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$booking) {
                $this->pdo->rollBack();
                return false;
            }

            if ((string)($booking['renter_id'] ?? '') !== $userId) {
                $this->pdo->rollBack();
                return false;
            }

            if ((string)($booking['status'] ?? '') !== 'pending') {
                $this->pdo->rollBack();
                return false;
            }

            $stmt = $this->pdo->prepare("SELECT id, method, status FROM payments WHERE booking_id = ? FOR UPDATE");
            $stmt->execute([$bookingId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment || (string)($payment['method'] ?? '') !== 'paypal') {
                $this->pdo->rollBack();
                return false;
            }

            if ((string)($payment['status'] ?? '') === 'paid') {
                $this->pdo->rollBack();
                return false;
            }

            try {
                $stmt = $this->pdo->prepare("DELETE FROM active_trips WHERE booking_id = ?");
                $stmt->execute([$bookingId]);
            } catch (Throwable $e) {
                // Optional table in some environments.
            }

            try {
                $stmt = $this->pdo->prepare("DELETE FROM driver_notifications WHERE booking_id = ?");
                $stmt->execute([$bookingId]);
            } catch (Throwable $e) {
                // Optional table in some environments.
            }

            $stmt = $this->pdo->prepare("DELETE FROM payments WHERE booking_id = ?");
            $stmt->execute([$bookingId]);

            $stmt = $this->pdo->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->execute([$bookingId]);

            if ($stmt->rowCount() < 1) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    /**
     * Get active drivers for minicab
     * @return array<int,array<string,mixed>>
     */
    public function getActiveDrivers(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE role = 'driver' AND is_active = true LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create driver notification
     */
    public function createDriverNotification(string $driverId, string $bookingId, string $title, string $message, string $notificationType = 'minicab_request'): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO driver_notifications (driver_id, booking_id, title, message, notification_type, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, false, NOW())
        ");
        return $stmt->execute([$driverId, $bookingId, $title, $message, $notificationType]);
    }

    /**
     * Check how many active bookings exist for a vehicle
     */
    public function countActiveBookingsByVehicleId(string $vehicleId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE vehicle_id = ? AND status IN ('pending', 'confirmed', 'in_progress')");
        $stmt->execute([$vehicleId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Check how many active bookings exist for a renter
     */
    public function countActiveBookingsByRenterId(string $renterId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE renter_id = ? AND status IN ('pending', 'confirmed', 'in_progress')");
        $stmt->execute([$renterId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get reviews for a vehicle with user details and stats
     * @return array<int,array<string,mixed>>
     */
    public function getReviewsWithDetailsAndStats(string $vehicleId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                r.id, r.rating, r.content, r.created_at,
                u.full_name, u.avatar_url,
                v.brand, v.model, v.year,
                b.pickup_location, b.return_location, b.booking_type
            FROM reviews r
            JOIN users u ON r.user_id = u.id
            JOIN vehicles v ON r.vehicle_id = v.id
            LEFT JOIN bookings b ON r.booking_id = b.id
            WHERE r.vehicle_id = ?
            ORDER BY r.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$vehicleId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get complete review statistics for a vehicle
     * @return array<string,mixed>
     */
    public function getReviewStatsComplete(string $vehicleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total,
                ROUND(AVG(rating)::numeric, 1) as avg_rating,
                COUNT(*) FILTER (WHERE rating = 5) as stars_5,
                COUNT(*) FILTER (WHERE rating = 4) as stars_4,
                COUNT(*) FILTER (WHERE rating = 3) as stars_3,
                COUNT(*) FILTER (WHERE rating = 2) as stars_2,
                COUNT(*) FILTER (WHERE rating = 1) as stars_1
            FROM reviews
            WHERE vehicle_id = ?
        ");
        $stmt->execute([$vehicleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
