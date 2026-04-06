<?php
/**
 * Vehicles API - Private Hire
 * Handles vehicle CRUD operations with Supabase Storage for images
 * - Owners can add/edit/delete their vehicles
 * - Anyone can view/list vehicles
 * - Images stored in Supabase Storage bucket "DriveNow" under vehicles/ folder
 */

// Check if this is an image request — redirect to Supabase Storage public URL
$action = $_GET['action'] ?? '';
if ($action === 'get-image') {
    session_start();
    require_once __DIR__ . '/../Database/db.php';
    require_once __DIR__ . '/supabase-storage.php';
    require_once __DIR__ . '/../sql/VehicleImageRepository.php';

    $imageId = $_GET['id'] ?? '';
    if (empty($imageId)) {
        http_response_code(400);
        echo 'Image ID required';
        exit;
    }

    try {
        $imgRepo = new VehicleImageRepository($pdo);
        $img = $imgRepo->findById($imageId);

        if (!$img || empty($img['storage_path'])) {
            http_response_code(404);
            echo 'Image not found';
            exit;
        }

        // Redirect to Supabase Storage public URL
        $storage = new SupabaseStorage();
        $publicUrl = $storage->getPublicUrl($img['storage_path']);
        header('Location: ' . $publicUrl, true, 302);
        header('Cache-Control: public, max-age=86400');
    } catch (Exception $e) {
        http_response_code(500);
        echo 'Server error';
    }
    exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/bootstrap.php';
$input = api_init();
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/supabase-storage.php';
require_once __DIR__ . '/notification-helpers.php';
require_once __DIR__ . '/../sql/VehicleRepository.php';
require_once __DIR__ . '/../sql/VehicleImageRepository.php';

$vehicleRepo = new VehicleRepository($pdo);
$vehicleImageRepo = new VehicleImageRepository($pdo);

$action = api_action($input);

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Action is required.']);
    exit;
}

// Helper: check if user is logged in
function requireAuth() {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Authentication required. Please sign in.', 'require_login' => true]);
        exit;
    }
}

// Helper: check if user is staff or admin (vehicle management)
function requireStaffOrAdmin() {
    requireAuth();
    $role = strtolower(str_replace(['-', ' ', '_'], '', (string)($_SESSION['role'] ?? '')));
    if ($role === 'staff') {
        $role = 'controlstaff';
    }
    if (!in_array($role, ['controlstaff', 'admin'], true)) {
        echo json_encode(['success' => false, 'message' => 'Only control staff or administrators can perform this action.']);
        exit;
    }
}

// Helper: get image URLs for a vehicle (returns Supabase Storage public URLs)
function getVehicleImageUrls($pdo, $vehicleId) {
    $storage = new SupabaseStorage();
    $repo = new VehicleImageRepository($pdo);
    $rows = $repo->listByVehicleId((string)$vehicleId);

    $urls = [];
    foreach ($rows as $row) {
        $storagePath = trim((string)($row['storage_path'] ?? ''));
        if ($storagePath === '') {
            continue;
        }

        try {
            $urls[] = $storage->getPublicUrl($storagePath);
        } catch (Throwable $e) {
            // Skip broken image records and continue returning valid JSON response.
            continue;
        }
    }

    return $urls;
}

// Helper: get image IDs for a vehicle
function getVehicleImageIds($pdo, $vehicleId) {
    $repo = new VehicleImageRepository($pdo);
    return $repo->listIdsByVehicleId((string)$vehicleId);
}

function toOptionalCapacityLbs($value): ?int {
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    $capacity = (int)$value;
    return $capacity > 0 ? $capacity : null;
}

function appendVehicleCustomerFields(array &$vehicle): void {
    $vehicle['luggage_capacity_lbs'] = toOptionalCapacityLbs($vehicle['capacity'] ?? null);
}

/**
 * @param array<int,string> $vehicleIds
 * @return array<string,bool>
 */
function getOnServiceVehicleIdMap(PDO $pdo, array $vehicleIds): array {
    $vehicleIds = array_values(array_unique(array_filter(array_map(static function ($id) {
        return trim((string)$id);
    }, $vehicleIds), static function ($id) {
        return $id !== '';
    })));

    if (empty($vehicleIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($vehicleIds), '?'));
    $sql = "
        SELECT DISTINCT b.vehicle_id::text AS vehicle_id
        FROM bookings b
        WHERE b.vehicle_id IS NOT NULL
          AND b.vehicle_id::text IN ({$placeholders})
          AND REPLACE(LOWER(COALESCE(b.status::text, '')), '-', '_') = 'in_progress'
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($vehicleIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $row) {
        $id = trim((string)($row['vehicle_id'] ?? ''));
        if ($id !== '') {
            $map[$id] = true;
        }
    }

    return $map;
}

/**
 * Customer-facing normalization only (display status), DB data is unchanged.
 * @param array<int,array<string,mixed>> $vehicles
 */
function applyCustomerOnServiceAvailability(PDO $pdo, array &$vehicles): void {
    if (empty($vehicles)) {
        return;
    }

    $ids = [];
    foreach ($vehicles as $v) {
        $ids[] = (string)($v['id'] ?? '');
    }

    $onServiceMap = getOnServiceVehicleIdMap($pdo, $ids);

    foreach ($vehicles as &$vehicle) {
        $vehicleId = trim((string)($vehicle['id'] ?? ''));
        $isOnService = $vehicleId !== '' && isset($onServiceMap[$vehicleId]);
        $vehicle['is_on_service'] = $isOnService;

        if ($isOnService) {
            $rawStatus = strtolower(trim((string)($vehicle['status'] ?? 'available')));
            if ($rawStatus === '' || $rawStatus === 'available') {
                // Keep customer UX consistent: on-service vehicles are not available now.
                $vehicle['status'] = 'rented';
            }
        }
    }
    unset($vehicle);
}

function parseMinicabPickupDateTimeVehicles(?string $pickupDateRaw, ?string $pickupTimeRaw = null): ?DateTimeImmutable {
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

function estimateMinicabDurationHoursVehicles(?float $distanceKm, ?string $serviceType = null): int {
    if (strtolower(trim((string)($serviceType ?? ''))) === 'daily-hire') {
        return 24;
    }

    if ($distanceKm === null || $distanceKm <= 0) {
        return 1;
    }

    $distanceMiles = $distanceKm * 0.621371;
    return max(1, (int)ceil($distanceMiles / 20.0));
}

function getMinicabPreBufferHoursVehicles(?string $serviceType): int {
    return strtolower(trim((string)($serviceType ?? ''))) === 'daily-hire' ? 24 : 2;
}

/**
 * @return array{pickup_at:DateTimeImmutable,window_start:DateTimeImmutable,window_end:DateTimeImmutable,duration_hours:int}|null
 */
function buildMinicabWindowVehicles(
    ?string $pickupDateRaw,
    ?string $pickupTimeRaw = null,
    ?float $distanceKm = null,
    ?string $serviceType = null,
    int $bufferHours = 2
): ?array {
    $pickupAt = parseMinicabPickupDateTimeVehicles($pickupDateRaw, $pickupTimeRaw);
    if (!$pickupAt) {
        return null;
    }

    $bufferHours = max(0, getMinicabPreBufferHoursVehicles($serviceType));
    $durationHours = estimateMinicabDurationHoursVehicles($distanceKm, $serviceType);
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

// ==========================================================
// NOTIFY ME WHEN VEHICLE BECOMES AVAILABLE (auth required)
// ==========================================================
if ($action === 'notify-me') {
    requireAuth();

    $userId = (string)($_SESSION['user_id'] ?? '');
    $vehicleId = (string)($input['vehicle_id'] ?? $_GET['vehicle_id'] ?? '');

    if ($userId === '') {
        echo json_encode(['success' => false, 'message' => 'Invalid session. Please sign in again.']);
        exit;
    }

    if ($vehicleId === '') {
        echo json_encode(['success' => false, 'message' => 'Vehicle ID is required.']);
        exit;
    }

    try {
        $vehicle = $vehicleRepo->getById($vehicleId);
        if (!$vehicle) {
            echo json_encode(['success' => false, 'message' => 'Vehicle not found.']);
            exit;
        }

        $status = strtolower((string)($vehicle['status'] ?? 'available'));
        if ($status === 'available') {
            echo json_encode([
                'success' => true,
                'message' => 'This vehicle is already available for booking now.'
            ]);
            exit;
        }

        $ok = subscribeVehicleAvailability($pdo, $userId, $vehicleId);
        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'Could not subscribe for availability notifications.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Subscribed successfully. We will notify you when this vehicle becomes available.'
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to subscribe for availability updates.']);
    }
    exit;
}

// ==========================================================
// FILTER OPTIONS (public - get available brands & categories from DB)
// ==========================================================
if ($action === 'filter-options') {
    try {
        $brands = $vehicleRepo->getDistinctAvailableBrands();
        $categories = $vehicleRepo->getDistinctAvailableCategories();

        echo json_encode([
            'success' => true,
            'brands' => $brands,
            'categories' => $categories
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => true, 'brands' => [], 'categories' => []]);
    }
    exit;
}

// ==========================================================
// CHECK AVAILABLE TIERS (public - check which tiers have available vehicles)
// ==========================================================
if ($action === 'check-available-tiers') {
    $seatCapacity = (int)($input['seat_capacity'] ?? $input['passengers'] ?? 4);
    $seatCapacity = ($seatCapacity >= 7) ? 7 : 4;
    $pickupDateRaw = (string)($input['pickup_datetime'] ?? $input['pickup_date'] ?? $_GET['pickup_datetime'] ?? '');
    $pickupTimeRaw = (string)($input['pickup_time'] ?? $_GET['pickup_time'] ?? '');
    $serviceTypeRaw = strtolower(trim((string)($input['service_type'] ?? $_GET['service_type'] ?? 'local')));
    if (!in_array($serviceTypeRaw, ['local', 'long-distance', 'airport-transfer', 'hotel-transfer', 'daily-hire'], true)) {
        $serviceTypeRaw = 'local';
    }
    $distanceKmRaw = $input['distance_km'] ?? $_GET['distance_km'] ?? null;
    $distanceKm = is_numeric($distanceKmRaw) ? (float)$distanceKmRaw : null;
    $requestWindow = null;

    if ($pickupDateRaw !== '') {
        $requestWindow = buildMinicabWindowVehicles($pickupDateRaw, $pickupTimeRaw, $distanceKm, $serviceTypeRaw);
    }
    
    try {
        // Match seat class exactly for minicab options:
        // 4-seat option => vehicles with seats < 7, 7-seat option => vehicles with seats >= 7.
        $seatClause = $seatCapacity >= 7 ? 'seats >= ?' : 'seats < ?';
        $seatValue = 7;

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
        $isDailyHireRequest = $serviceTypeRaw === 'daily-hire';

        $conflictClause = '';
        $params = [$seatValue];
        if ($requestWindow) {
            if ($isDailyHireRequest) {
                $conflictClause = "
                  AND NOT EXISTS (
                        SELECT 1
                        FROM bookings b
                        WHERE b.vehicle_id = vehicles.id
                          AND b.booking_type = 'minicab'
                          AND b.status IN ('pending', 'in_progress')
                          AND (
                              {$existingWindowStartExpr} < ?::timestamptz
                              AND
                              {$existingWindowEndExpr} > ?::timestamptz
                          )
                    )
                ";
                $params[] = $requestWindow['window_end']->format('Y-m-d H:i:sP');
                $params[] = $requestWindow['window_start']->format('Y-m-d H:i:sP');
            } else {
                $conflictClause = "
                  AND NOT EXISTS (
                        SELECT 1
                        FROM bookings b
                        WHERE b.vehicle_id = vehicles.id
                          AND b.booking_type = 'minicab'
                          AND b.status IN ('pending', 'in_progress')
                          AND (
                              {$existingWindowStartExpr} < ?::timestamptz
                              AND
                              {$existingWindowEndExpr} > ?::timestamptz
                          )
                    )
                ";
                $params[] = $requestWindow['pickup_at']->format('Y-m-d H:i:sP');
                $params[] = $requestWindow['pickup_at']->format('Y-m-d H:i:sP');
            }
        }

        $query = "
            SELECT LOWER(CASE WHEN service_tier = 'premium' THEN 'luxury' ELSE service_tier END) AS normalized_tier, COUNT(*) AS total
            FROM vehicles
            WHERE status = 'available'
              AND {$seatClause}
              AND service_tier IN ('eco', 'standard', 'luxury', 'premium')
              {$conflictClause}
            GROUP BY normalized_tier
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        $availableTiers = [
            'eco' => false,
            'standard' => false,
            'luxury' => false,
        ];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tier = strtolower((string)($row['normalized_tier'] ?? ''));
            if (array_key_exists($tier, $availableTiers)) {
                $availableTiers[$tier] = ((int)($row['total'] ?? 0) > 0);
            }
        }
        
        echo json_encode([
            'success' => true,
            'available_tiers' => $availableTiers,
            'seat_capacity' => $seatCapacity
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error checking available tiers']);
    }
    exit;
}

// ==========================================================
// UNAVAILABLE TIME SLOTS (public)
// Returns blocked pickup time slots for selected date based on tier/seat and time-window rules.
// ==========================================================
if ($action === 'unavailable-time-slots') {
    $dateRaw = trim((string)($input['date'] ?? $_GET['date'] ?? ''));
    $seatCapacity = (int)($input['seat_capacity'] ?? $_GET['seat_capacity'] ?? 4);
    $seatCapacity = ($seatCapacity >= 7) ? 7 : 4;
    $tierRaw = strtolower(trim((string)($input['ride_tier'] ?? $_GET['ride_tier'] ?? '')));
    $serviceTypeRaw = strtolower(trim((string)($input['service_type'] ?? $_GET['service_type'] ?? 'local')));
    if (!in_array($serviceTypeRaw, ['local', 'long-distance', 'airport-transfer', 'hotel-transfer', 'daily-hire'], true)) {
        $serviceTypeRaw = 'local';
    }
    if ($tierRaw === 'premium') {
        $tierRaw = 'luxury';
    }
    $distanceKmRaw = $input['distance_km'] ?? $_GET['distance_km'] ?? null;
    $distanceKm = is_numeric($distanceKmRaw) ? (float)$distanceKmRaw : null;
    $slotMinutes = 30;

    if ($dateRaw === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw) !== 1) {
        echo json_encode(['success' => false, 'message' => 'Valid date is required.']);
        exit;
    }

    try {
        $seatClause = $seatCapacity >= 7 ? 'v.seats >= 7' : 'v.seats < 7';

        $tierClause = '';
        if (in_array($tierRaw, ['eco', 'standard', 'luxury'], true)) {
            if ($tierRaw === 'luxury') {
                $tierClause = " AND LOWER(COALESCE(v.service_tier, '')) IN ('luxury', 'premium')";
            } else {
                $tierClause = " AND LOWER(COALESCE(v.service_tier, '')) = :tier";
            }
        }

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
        $isDailyHireRequest = $serviceTypeRaw === 'daily-hire';
        $timeConflictCondition = $isDailyHireRequest
            ? "{$existingWindowStartExpr} < :request_window_end::timestamptz AND {$existingWindowEndExpr} > :request_window_start::timestamptz"
            : "{$existingWindowStartExpr} < :request_pickup_at::timestamptz AND {$existingWindowEndExpr} > :request_pickup_at::timestamptz";

        $query = "
            SELECT COUNT(*)
            FROM vehicles v
            WHERE v.status = 'available'
              AND {$seatClause}
              {$tierClause}
              AND NOT EXISTS (
                    SELECT 1
                    FROM bookings b
                    WHERE b.vehicle_id = v.id
                                            AND b.booking_type = 'minicab'
                      AND b.status IN ('pending', 'in_progress')
                                            AND ({$timeConflictCondition})
                )
        ";

        $stmt = $pdo->prepare($query);
        $allTimes = [];
        $unavailableTimes = [];

        for ($minutes = 0; $minutes < 24 * 60; $minutes += $slotMinutes) {
            $hour = intdiv($minutes, 60);
            $minute = $minutes % 60;
            $timeValue = sprintf('%02d:%02d', $hour, $minute);
            $candidateRaw = $dateRaw . 'T' . $timeValue;

            $requestWindow = buildMinicabWindowVehicles($candidateRaw, null, $distanceKm, $serviceTypeRaw);
            if (!$requestWindow) {
                continue;
            }

            $params = [];
            if ($isDailyHireRequest) {
                $params[':request_window_start'] = $requestWindow['window_start']->format('Y-m-d H:i:sP');
                $params[':request_window_end'] = $requestWindow['window_end']->format('Y-m-d H:i:sP');
            } else {
                $params[':request_pickup_at'] = $requestWindow['pickup_at']->format('Y-m-d H:i:sP');
            }
            if ($tierClause !== '' && $tierRaw !== 'luxury') {
                $params[':tier'] = $tierRaw;
            }

            $stmt->execute($params);
            $availableCount = (int)$stmt->fetchColumn();

            $allTimes[] = $timeValue;
            if ($availableCount <= 0) {
                $unavailableTimes[] = $timeValue;
            }
        }

        echo json_encode([
            'success' => true,
            'date' => $dateRaw,
            'slot_minutes' => $slotMinutes,
            'all_times' => $allTimes,
            'unavailable_times' => $unavailableTimes,
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to compute unavailable slots.']);
    }
    exit;
}

// ==========================================================
// TIER/SEAT AVAILABILITY MATRIX (public)
// Returns available vehicle counts for each tier by passenger count 1..7
// ==========================================================
if ($action === 'tier-seat-availability') {
    $pickupDateRaw = (string)($input['pickup_datetime'] ?? $input['pickup_date'] ?? $_GET['pickup_datetime'] ?? '');
    $pickupTimeRaw = (string)($input['pickup_time'] ?? $_GET['pickup_time'] ?? '');
    $serviceTypeRaw = strtolower(trim((string)($input['service_type'] ?? $_GET['service_type'] ?? 'local')));
    if (!in_array($serviceTypeRaw, ['local', 'long-distance', 'airport-transfer', 'hotel-transfer', 'daily-hire'], true)) {
        $serviceTypeRaw = 'local';
    }
    $distanceKmRaw = $input['distance_km'] ?? $_GET['distance_km'] ?? null;
    $distanceKm = is_numeric($distanceKmRaw) ? (float)$distanceKmRaw : null;
    $requestWindow = null;

    if ($pickupDateRaw !== '') {
        $requestWindow = buildMinicabWindowVehicles($pickupDateRaw, $pickupTimeRaw, $distanceKm, $serviceTypeRaw);
    }

    try {
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
        $isDailyHireRequest = $serviceTypeRaw === 'daily-hire';

        $conflictClause = '';
        $params = [];
        if ($requestWindow) {
            if ($isDailyHireRequest) {
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
                $params[] = $requestWindow['window_end']->format('Y-m-d H:i:sP');
                $params[] = $requestWindow['window_start']->format('Y-m-d H:i:sP');
            } else {
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
                $params[] = $requestWindow['pickup_at']->format('Y-m-d H:i:sP');
                $params[] = $requestWindow['pickup_at']->format('Y-m-d H:i:sP');
            }
        }

        $stmt = $pdo->prepare(
            "SELECT v.service_tier, v.seats, COUNT(*) AS total
             FROM vehicles v
             WHERE v.status = 'available'
               AND v.service_tier IN ('eco', 'standard', 'luxury', 'premium')
               {$conflictClause}
             GROUP BY v.service_tier, v.seats"
        );

        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tiers = ['eco', 'standard', 'luxury'];
        $availability = [];
        foreach ($tiers as $tier) {
            $availability[$tier] = [];
            for ($p = 1; $p <= 7; $p++) {
                $availability[$tier][(string)$p] = 0;
            }
        }

        foreach ($rows as $row) {
            $tier = strtolower((string)($row['service_tier'] ?? ''));
            if ($tier === 'premium') {
                $tier = 'luxury';
            }
            if (!isset($availability[$tier])) {
                continue;
            }

            $vehicleSeats = (int)($row['seats'] ?? 0);
            $count = (int)($row['total'] ?? 0);
            if ($vehicleSeats <= 0 || $count <= 0) {
                continue;
            }

            for ($p = 1; $p <= 7; $p++) {
                if ($vehicleSeats >= $p) {
                    $availability[$tier][(string)$p] += $count;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'availability' => $availability,
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to get availability.',
        ]);
    }
    exit;
}

// ==========================================================
// SEARCH SUGGESTIONS (public - brand-only autocomplete)
// ==========================================================
if ($action === 'search-suggestions') {
    $query = trim($_GET['q'] ?? $input['q'] ?? '');
    if (strlen($query) < 1) {
        echo json_encode(['success' => true, 'suggestions' => []]);
        exit;
    }

    try {
        // Get brand suggestions using repository
        $brands = $vehicleRepo->getAvailableBrandsSuggestions($query, 8);

        $suggestions = [];
        foreach ($brands as $b) {
            $suggestions[] = ['type' => 'brand', 'text' => $b, 'label' => $b];
        }

        echo json_encode(['success' => true, 'suggestions' => $suggestions]);
    } catch (PDOException $e) {
        echo json_encode(['success' => true, 'suggestions' => []]);
    }
    exit;
}

// ==========================================================
// LIST VEHICLES (public - minimal listing for homepage)
// Show published fleet vehicles including unavailable states
// ==========================================================
if ($action === 'public-list') {
    $brand  = $_GET['brand'] ?? $input['brand'] ?? '';
    $search = $_GET['search'] ?? $input['search'] ?? '';
    $limit  = min((int)($_GET['limit'] ?? $input['limit'] ?? 50), 100);

    try {
        $vehicles = $vehicleRepo->listPublic(['brand' => (string)$brand, 'search' => (string)$search, 'limit' => (int)$limit]);
        applyCustomerOnServiceAvailability($pdo, $vehicles);

        // Attach first image (thumbnail if present) as vehicle_image
        foreach ($vehicles as &$v) {
            $v['price_per_day'] = isset($v['price_per_day']) ? (float)$v['price_per_day'] : null;
            appendVehicleCustomerFields($v);
            try {
                $imgs = getVehicleImageUrls($pdo, $v['id']);
                $v['vehicle_image'] = $imgs[0] ?? null;
            } catch (Exception $ignore) {
                $v['vehicle_image'] = null;
            }
        }

        echo json_encode(['success' => true, 'vehicles' => $vehicles]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

// ==========================================================
// LIST VEHICLES (public - anyone can view)
// Includes unavailable states for "Not Available" customer UX
// ==========================================================
if ($action === 'list') {
    $filters = [
        'category' => $_GET['category'] ?? $input['category'] ?? '',
        'tier' => $_GET['tier'] ?? $input['tier'] ?? $_GET['service_tier'] ?? $input['service_tier'] ?? '',
        'seats' => $_GET['seats'] ?? $input['seats'] ?? '',
        'brand' => $_GET['brand'] ?? $input['brand'] ?? '',
        'fuel' => $_GET['fuel'] ?? $input['fuel'] ?? '',
        'transmission' => $_GET['transmission'] ?? $input['transmission'] ?? '',
        'max_price' => $_GET['max_price'] ?? $input['max_price'] ?? 9999,
        'location' => $_GET['location'] ?? $input['location'] ?? '',
        'search' => $_GET['search'] ?? $input['search'] ?? '',
        'limit' => $_GET['limit'] ?? $input['limit'] ?? 20,
        'offset' => $_GET['offset'] ?? $input['offset'] ?? 0,
    ];

    try {
        $res = $vehicleRepo->listAvailable($filters);
        $vehicles = $res['vehicles'];
        $total = $res['total'];
        applyCustomerOnServiceAvailability($pdo, $vehicles);

        foreach ($vehicles as &$v) {
            $v['images'] = getVehicleImageUrls($pdo, $v['id']);
            $v['image_ids'] = getVehicleImageIds($pdo, $v['id']);
            $rawFeatures = $v['features'] ?? '';
            $rawFeatures = is_string($rawFeatures) ? trim($rawFeatures, '{}') : '';
            $v['features'] = $rawFeatures !== '' ? explode(',', str_replace('"', '', $rawFeatures)) : [];
            $v['price_per_day'] = isset($v['price_per_day']) ? (float)$v['price_per_day'] : 0.0;
            $v['avg_rating'] = isset($v['avg_rating']) ? (float)$v['avg_rating'] : 0.0;
            appendVehicleCustomerFields($v);
            unset($v['thumbnail_id']);
        }

        $limit = min((int)($filters['limit'] ?? 20), 50);
        $offset = max((int)($filters['offset'] ?? 0), 0);

        echo json_encode([
            'success' => true,
            'vehicles' => $vehicles,
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// GET SINGLE VEHICLE (public)
// ==========================================================
if ($action === 'get') {
    $vehicleId = $input['vehicle_id'] ?? $_GET['vehicle_id'] ?? '';
    if (empty($vehicleId)) {
        echo json_encode(['success' => false, 'message' => 'Vehicle ID is required.']);
        exit;
    }

    try {
        $vehicle = $vehicleRepo->getById((string)$vehicleId);

        if (!$vehicle) {
            echo json_encode(['success' => false, 'message' => 'Vehicle not found.']);
            exit;
        }

        $singleVehicle = [$vehicle];
        applyCustomerOnServiceAvailability($pdo, $singleVehicle);
        $vehicle = $singleVehicle[0];

        $vehicle['images'] = getVehicleImageUrls($pdo, $vehicle['id']);
        $vehicle['image_ids'] = getVehicleImageIds($pdo, $vehicle['id']);
        $vehicle['features'] = $vehicle['features'] ? trim($vehicle['features'], '{}') : '';
        $vehicle['features'] = $vehicle['features'] ? explode(',', str_replace('"', '', $vehicle['features'])) : [];
        $vehicle['price_per_day'] = (float)$vehicle['price_per_day'];
        $vehicle['avg_rating'] = (float)$vehicle['avg_rating'];
        appendVehicleCustomerFields($vehicle);
        unset($vehicle['thumbnail_id']);

        echo json_encode(['success' => true, 'vehicle' => $vehicle]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// MY VEHICLES (staff/admin only - get all vehicles)
// ==========================================================
if ($action === 'my-vehicles') {
    requireStaffOrAdmin();

    try {
        // List all vehicles via repository
        $vehicles = $vehicleRepo->listAll();

        foreach ($vehicles as &$v) {
            $v['images'] = getVehicleImageUrls($pdo, $v['id']);
            $v['image_ids'] = getVehicleImageIds($pdo, $v['id']);
            $v['features'] = $v['features'] ? trim($v['features'], '{}') : '';
            $v['features'] = $v['features'] ? explode(',', str_replace('"', '', $v['features'])) : [];
            $v['price_per_day'] = (float)$v['price_per_day'];
            $v['avg_rating'] = (float)$v['avg_rating'];
            appendVehicleCustomerFields($v);
            unset($v['thumbnail_id']);
        }

        echo json_encode(['success' => true, 'vehicles' => $vehicles]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// ADD VEHICLE (staff/admin only)
// ==========================================================
if ($action === 'add') {
    requireStaffOrAdmin();
    $ownerId = $_SESSION['user_id'];

    // Verify user still exists in DB (may have been deleted after schema reset)
    if (!$vehicleRepo->userExists($ownerId)) {
        // User's session is stale — force re-login
        session_destroy();
        echo json_encode(['success' => false, 'message' => 'Your session is invalid. The database may have been reset. Please log out and register/login again.', 'force_logout' => true]);
        exit;
    }

    $brand        = trim($input['brand'] ?? '');
    $model        = trim($input['model'] ?? '');
    $year         = (int)($input['year'] ?? 0);
    $licensePlate = trim($input['license_plate'] ?? '');
    $category     = trim($input['category'] ?? 'sedan');
    $transmission = trim($input['transmission'] ?? 'automatic');
    $fuelType     = trim($input['fuel_type'] ?? 'petrol');
    $seats        = (int)($input['seats'] ?? 5);
    $color        = trim($input['color'] ?? '');
    $engineSize   = trim($input['engine_size'] ?? '');
    $consumption  = trim($input['consumption'] ?? '');
    $features     = $input['features'] ?? [];
    $luggageCapacityLbs = toOptionalCapacityLbs($input['luggage_capacity_lbs'] ?? ($input['capacity'] ?? null));
    $imageIds     = $input['image_ids'] ?? [];       // array of vehicle_images UUIDs
    $pricePerDay  = (float)($input['price_per_day'] ?? 0);
    $pricePerWeek = isset($input['price_per_week']) && $input['price_per_week'] ? (float)$input['price_per_week'] : null;
    $pricePerMonth= isset($input['price_per_month']) && $input['price_per_month'] ? (float)$input['price_per_month'] : null;
    $locationCity = trim($input['location_city'] ?? '');
    $locationAddr = trim($input['location_address'] ?? '');
    $description  = trim($input['description'] ?? '');

    // Validate required fields
    if (empty($brand) || empty($model) || $year < 1990 || empty($licensePlate) || $pricePerDay <= 0) {
        echo json_encode(['success' => false, 'message' => 'Brand, model, year (1990+), license plate and price per day are required.']);
        exit;
    }

    // Check for duplicate license plate BEFORE insert
    try {
        if ($vehicleRepo->licensePlateExists($licensePlate)) {
            echo json_encode(['success' => false, 'message' => 'A vehicle with license plate "' . $licensePlate . '" already exists. Each vehicle must have a unique license plate.']);
            exit;
        }
    } catch (PDOException $e) {
        // If check fails, let the insert handle the unique constraint
    }

    try {
        $pdo->beginTransaction();

        // Create vehicle via repository
        $vehicleId = $vehicleRepo->create([
            'owner_id' => $ownerId,
            'brand' => $brand,
            'model' => $model,
            'year' => $year,
            'license_plate' => $licensePlate,
            'category' => $category,
            'transmission' => $transmission,
            'fuel_type' => $fuelType,
            'seats' => $seats,
            'color' => $color,
            'engine_size' => $engineSize,
            'consumption' => $consumption,
            'features' => $features,
            'capacity' => $luggageCapacityLbs,
            'price_per_day' => $pricePerDay,
            'price_per_week' => $pricePerWeek,
            'price_per_month' => $pricePerMonth,
            'location_city' => $locationCity,
            'location_address' => $locationAddr
        ]);

        // Link uploaded images to this vehicle
        if (!empty($imageIds)) {
            $vehicleRepo->linkImagesToVehicle($vehicleId, $imageIds);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Vehicle added successfully!',
            'vehicle_id' => $vehicleId
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        if (strpos($e->getMessage(), 'vehicles_license_plate_key') !== false) {
            echo json_encode(['success' => false, 'message' => 'A vehicle with this license plate already exists.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
    exit;
}

// ==========================================================
// UPDATE VEHICLE (staff/admin only)
// ==========================================================
if ($action === 'update') {
    requireStaffOrAdmin();
    $ownerId   = $_SESSION['user_id'];
    $vehicleId = $input['vehicle_id'] ?? '';
    $currentVehicle = null;

    if (empty($vehicleId)) {
        echo json_encode(['success' => false, 'message' => 'Vehicle ID is required.']);
        exit;
    }

    // Verify ownership
    if (!$vehicleRepo->existsForUser($vehicleId, $ownerId)) {
        echo json_encode(['success' => false, 'message' => 'Vehicle not found or you do not own this vehicle.']);
        exit;
    }

    $currentVehicle = $vehicleRepo->getById((string)$vehicleId);

    // Check for duplicate license plate on update (exclude current vehicle)
    if (!empty($input['license_plate'])) {
        if ($vehicleRepo->licensePlateExists(trim($input['license_plate']), $vehicleId)) {
            echo json_encode(['success' => false, 'message' => 'A vehicle with license plate "' . trim($input['license_plate']) . '" already exists. Each vehicle must have a unique license plate.']);
            exit;
        }
    }

    // Build fields to update
    $updates = [];

    $allowedFields = [
        'brand', 'model', 'year', 'license_plate', 'category', 'transmission', 'fuel_type',
        'seats', 'capacity', 'color', 'engine_size', 'consumption',
        'price_per_day', 'price_per_week', 'price_per_month',
        'location_city', 'location_address', 'status', 'gps_enabled'
    ];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[$field] = $input[$field];
        }
    }

    // Handle arrays specially
    if (isset($input['features'])) {
        $updates['features'] = $input['features'];
    }

    if (array_key_exists('luggage_capacity_lbs', $input)) {
        $updates['capacity'] = toOptionalCapacityLbs($input['luggage_capacity_lbs']);
    }

    // Handle image_ids: update which images belong to this vehicle
    if (isset($input['image_ids'])) {
        $imageIds = $input['image_ids'];
        $vehicleRepo->updateVehicleImages($vehicleId, $imageIds);
        
        // Set thumbnail in updates
        if (!empty($imageIds)) {
            $updates['thumbnail_id'] = $imageIds[0];
        } else {
            $updates['thumbnail_id'] = null;
        }
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        exit;
    }

    try {
        // Update vehicle via repository
        $vehicle = $vehicleRepo->updateVehicle($vehicleId, $ownerId, $updates);
        $availabilitySubscribersNotified = 0;

        if (isset($updates['status'])) {
            $newStatus = strtolower((string)$updates['status']);
            $oldStatus = strtolower((string)($currentVehicle['status'] ?? ''));
            if ($newStatus === 'available' && $oldStatus !== 'available') {
                $availabilitySubscribersNotified = notifyVehicleAvailabilitySubscribers($pdo, (string)$vehicleId);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Vehicle updated successfully!',
            'vehicle' => $vehicle,
            'availability_subscribers_notified' => $availabilitySubscribersNotified
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// DELETE VEHICLE (staff/admin only)
// ==========================================================
if ($action === 'delete') {
    requireStaffOrAdmin();
    $ownerId   = $_SESSION['user_id'];
    $vehicleId = $input['vehicle_id'] ?? '';

    if (empty($vehicleId)) {
        echo json_encode(['success' => false, 'message' => 'Vehicle ID is required.']);
        exit;
    }

    try {
        // Check for active bookings
        $activeBookings = $vehicleRepo->countActiveBookings($vehicleId);
        if ($activeBookings > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete vehicle with active bookings. Please cancel or complete all bookings first.']);
            exit;
        }

        // Delete images from Supabase Storage first
        $storagePaths = $vehicleRepo->getImageStoragePaths($vehicleId);
        if (!empty($storagePaths)) {
            $storage = new SupabaseStorage();
            $storage->deleteMultiple($storagePaths);
        }

        // Delete vehicle via repository
        $deleted = $vehicleRepo->deleteVehicle($vehicleId, $ownerId);

        if (!$deleted) {
            echo json_encode(['success' => false, 'message' => 'Vehicle not found or you do not own this vehicle.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Vehicle deleted successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// UPLOAD IMAGE (staff/admin only - stores in Supabase Storage)
// ==========================================================
if ($action === 'upload-image') {
    requireStaffOrAdmin();

    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'message' => 'No image file provided.']);
        exit;
    }

    $file = $_FILES['image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, WebP and GIF are allowed.']);
        exit;
    }

    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
        exit;
    }

    $imageData = file_get_contents($file['tmp_name']);
    if ($imageData === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to read uploaded file.']);
        exit;
    }

    $vehicleId = $_POST['vehicle_id'] ?? null;

    try {
        // storage_path-only
        $storage = new SupabaseStorage();
        $uniqueName = SupabaseStorage::uniqueName($file['name']);
        $folder = $vehicleId ? 'vehicles/' . $vehicleId : 'vehicles/unlinked';
        $storagePath = $folder . '/' . $uniqueName;

        $uploadResult = $storage->upload($storagePath, $imageData, $file['type']);
        if (!$uploadResult['success']) {
            echo json_encode(['success' => false, 'message' => 'Storage upload failed: ' . ($uploadResult['message'] ?? 'Unknown error')]);
            exit;
        }

        $imageId = $vehicleRepo->insertImage($vehicleId, $storagePath, $file['type'], $file['name'], $file['size'], $imageData);

        echo json_encode([
            'success' => true,
            'message' => 'Image uploaded successfully!',
            'image_id' => $imageId,
            'storage_path' => $storagePath,
            'url' => $uploadResult['public_url']
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// DELETE IMAGE (staff/admin only)
// ==========================================================
if ($action === 'delete-image') {
    requireStaffOrAdmin();

    $imageId = $input['image_id'] ?? '';
    if (empty($imageId)) {
        echo json_encode(['success' => false, 'message' => 'Image ID is required.']);
        exit;
    }

    try {
        // Verify the image belongs to a vehicle owned by this user & get storage_path
        $img = $vehicleRepo->getImage($imageId);
        if (!$img || ($img['owner_id'] && $img['owner_id'] !== $_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Image not found or access denied.']);
            exit;
        }

        if (empty($img['storage_path'])) {
            echo json_encode(['success' => false, 'message' => 'Image storage path missing.']);
            exit;
        }

        // Delete from Supabase Storage
        $storage = new SupabaseStorage();
        $storage->delete($img['storage_path']);

        // Delete DB record
        $vehicleRepo->deleteImage($imageId);
        echo json_encode(['success' => true, 'message' => 'Image deleted.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// UNKNOWN ACTION
// ==========================================================
echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
