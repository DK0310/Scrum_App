<?php
/**
 * Vehicles API - DriveNow
 * Handles vehicle CRUD operations with BLOB image storage
 * - Owners can add/edit/delete their vehicles
 * - Anyone can view/list vehicles
 * - Images stored as BYTEA in vehicle_images table
 */

// Check if this is an image request BEFORE setting JSON content-type
$action = $_GET['action'] ?? '';
if ($action === 'get-image') {
    // Handle image serving without JSON headers
    session_start();
    require_once __DIR__ . '/../Database/db.php';

    $imageId = $_GET['id'] ?? '';
    if (empty($imageId)) {
        http_response_code(400);
        echo 'Image ID required';
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT image_data, mime_type, file_name FROM vehicle_images WHERE id = ?");
        $stmt->execute([$imageId]);
        $img = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$img) {
            http_response_code(404);
            echo 'Image not found';
            exit;
        }

        // Handle PostgreSQL bytea format
        $imageData = $img['image_data'];
        if (is_resource($imageData)) {
            $imageData = stream_get_contents($imageData);
        }
        // If PDO returns bytea as hex-escaped string (e.g. \x89504e47...)
        if (is_string($imageData) && substr($imageData, 0, 2) === '\\x') {
            $imageData = hex2bin(substr($imageData, 2));
        }

        header('Content-Type: ' . $img['mime_type']);
        header('Content-Length: ' . strlen($imageData));
        header('Cache-Control: public, max-age=86400'); // cache 24h
        header('Content-Disposition: inline; filename="' . ($img['file_name'] ?? 'image') . '"');
        echo $imageData;
    } catch (PDOException $e) {
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

session_start();
require_once __DIR__ . '/../Database/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

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

// Helper: check if user is owner
function requireOwner() {
    requireAuth();
    if (($_SESSION['role'] ?? '') !== 'owner') {
        echo json_encode(['success' => false, 'message' => 'Only car owners can perform this action. Please upgrade your account to owner.']);
        exit;
    }
}

// Helper: get image URLs for a vehicle (returns array of API endpoint URLs)
function getVehicleImageUrls($pdo, $vehicleId) {
    $stmt = $pdo->prepare("SELECT id FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_thumbnail DESC, sort_order ASC, created_at ASC");
    $stmt->execute([$vehicleId]);
    $imageIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return array_map(fn($id) => '/api/vehicles.php?action=get-image&id=' . $id, $imageIds);
}

// Helper: get image IDs for a vehicle
function getVehicleImageIds($pdo, $vehicleId) {
    $stmt = $pdo->prepare("SELECT id FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_thumbnail DESC, sort_order ASC, created_at ASC");
    $stmt->execute([$vehicleId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ==========================================================
// FILTER OPTIONS (public - get available brands & categories from DB)
// ==========================================================
if ($action === 'filter-options') {
    try {
        // Get distinct brands from available vehicles
        $stmt = $pdo->query("SELECT DISTINCT brand FROM vehicles WHERE status = 'available' AND brand IS NOT NULL AND brand != '' ORDER BY brand");
        $brands = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Get distinct categories from available vehicles
        $stmt2 = $pdo->query("SELECT DISTINCT category FROM vehicles WHERE status = 'available' AND category IS NOT NULL AND category != '' ORDER BY category");
        $categories = $stmt2->fetchAll(PDO::FETCH_COLUMN);

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
// SEARCH SUGGESTIONS (public - autocomplete)
// ==========================================================
if ($action === 'search-suggestions') {
    $query = trim($_GET['q'] ?? $input['q'] ?? '');
    if (strlen($query) < 1) {
        echo json_encode(['success' => true, 'suggestions' => []]);
        exit;
    }

    try {
        $q = '%' . strtolower($query) . '%';

        // Get matching brands
        $stmt = $pdo->prepare("
            SELECT DISTINCT brand FROM vehicles 
            WHERE status = 'available' AND LOWER(brand) ILIKE ? 
            ORDER BY brand LIMIT 5
        ");
        $stmt->execute([$q]);
        $brands = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Get matching brand + model combos
        $stmt2 = $pdo->prepare("
            SELECT DISTINCT brand, model, category, year, price_per_day, id FROM vehicles 
            WHERE status = 'available' AND (LOWER(brand) ILIKE ? OR LOWER(model) ILIKE ? OR LOWER(brand || ' ' || model) ILIKE ?)
            ORDER BY brand, model LIMIT 8
        ");
        $stmt2->execute([$q, $q, $q]);
        $vehicles = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $suggestions = [];

        // Brand suggestions
        foreach ($brands as $b) {
            $suggestions[] = ['type' => 'brand', 'text' => $b, 'label' => $b . ' (Brand)'];
        }

        // Vehicle suggestions
        foreach ($vehicles as $v) {
            $suggestions[] = [
                'type' => 'vehicle',
                'text' => $v['brand'] . ' ' . $v['model'],
                'label' => $v['brand'] . ' ' . $v['model'] . ' ' . $v['year'],
                'sub' => ucfirst($v['category']) . ' • $' . number_format($v['price_per_day']) . '/day',
                'id' => $v['id']
            ];
        }

        echo json_encode(['success' => true, 'suggestions' => $suggestions]);
    } catch (PDOException $e) {
        echo json_encode(['success' => true, 'suggestions' => []]);
    }
    exit;
}

// ==========================================================
// LIST VEHICLES (public - anyone can view)
// ==========================================================
if ($action === 'list') {
    $category     = $_GET['category'] ?? $input['category'] ?? '';
    $brand        = $_GET['brand'] ?? $input['brand'] ?? '';
    $fuel         = $_GET['fuel'] ?? $input['fuel'] ?? '';
    $transmission = $_GET['transmission'] ?? $input['transmission'] ?? '';
    $maxPrice     = $_GET['max_price'] ?? $input['max_price'] ?? 9999;
    $location     = $_GET['location'] ?? $input['location'] ?? '';
    $search       = $_GET['search'] ?? $input['search'] ?? '';
    $limit        = min((int)($_GET['limit'] ?? $input['limit'] ?? 20), 50);
    $offset       = max((int)($_GET['offset'] ?? $input['offset'] ?? 0), 0);

    try {
        $where = ["v.status = 'available'"];
        $params = [];

        if ($category) {
            $where[] = "LOWER(v.category) = LOWER(?)";
            $params[] = $category;
        }
        if ($brand) {
            $where[] = "LOWER(v.brand) ILIKE ?";
            $params[] = '%' . $brand . '%';
        }
        if ($fuel) {
            $where[] = "LOWER(v.fuel_type) = LOWER(?)";
            $params[] = $fuel;
        }
        if ($transmission) {
            $where[] = "LOWER(v.transmission) = LOWER(?)";
            $params[] = $transmission;
        }
        if ($maxPrice && $maxPrice < 9999) {
            $where[] = "v.price_per_day <= ?";
            $params[] = $maxPrice;
        }
        if ($location) {
            $where[] = "(LOWER(v.location_city) ILIKE ? OR LOWER(v.location_address) ILIKE ?)";
            $params[] = '%' . strtolower($location) . '%';
            $params[] = '%' . strtolower($location) . '%';
        }
        if ($search) {
            $where[] = "(LOWER(v.brand) ILIKE ? OR LOWER(v.model) ILIKE ? OR LOWER(v.category) ILIKE ?)";
            $params[] = '%' . strtolower($search) . '%';
            $params[] = '%' . strtolower($search) . '%';
            $params[] = '%' . strtolower($search) . '%';
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $sql = "
            SELECT v.*, u.full_name AS owner_name, u.avatar_url AS owner_avatar
            FROM vehicles v
            JOIN users u ON v.owner_id = u.id
            WHERE {$whereClause}
            ORDER BY v.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count
        $countSql = "SELECT COUNT(*) FROM vehicles v WHERE {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute(array_slice($params, 0, -2));
        $total = $countStmt->fetchColumn();

        // Process each vehicle: get images from vehicle_images table
        foreach ($vehicles as &$v) {
            $v['images'] = getVehicleImageUrls($pdo, $v['id']);
            $v['image_ids'] = getVehicleImageIds($pdo, $v['id']);
            $v['features'] = $v['features'] ? trim($v['features'], '{}') : '';
            $v['features'] = $v['features'] ? explode(',', str_replace('"', '', $v['features'])) : [];
            $v['price_per_day'] = (float)$v['price_per_day'];
            $v['avg_rating'] = (float)$v['avg_rating'];
            unset($v['thumbnail_id']); // don't expose internal ID
        }

        echo json_encode([
            'success' => true,
            'vehicles' => $vehicles,
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    } catch (PDOException $e) {
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
        $stmt = $pdo->prepare("
            SELECT v.*, u.full_name AS owner_name, u.avatar_url AS owner_avatar, u.phone AS owner_phone
            FROM vehicles v
            JOIN users u ON v.owner_id = u.id
            WHERE v.id = ?
        ");
        $stmt->execute([$vehicleId]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            echo json_encode(['success' => false, 'message' => 'Vehicle not found.']);
            exit;
        }

        $vehicle['images'] = getVehicleImageUrls($pdo, $vehicle['id']);
        $vehicle['image_ids'] = getVehicleImageIds($pdo, $vehicle['id']);
        $vehicle['features'] = $vehicle['features'] ? trim($vehicle['features'], '{}') : '';
        $vehicle['features'] = $vehicle['features'] ? explode(',', str_replace('"', '', $vehicle['features'])) : [];
        $vehicle['price_per_day'] = (float)$vehicle['price_per_day'];
        $vehicle['avg_rating'] = (float)$vehicle['avg_rating'];
        unset($vehicle['thumbnail_id']);

        echo json_encode(['success' => true, 'vehicle' => $vehicle]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// MY VEHICLES (owner only - get their own vehicles)
// ==========================================================
if ($action === 'my-vehicles') {
    requireOwner();
    $ownerId = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT * FROM vehicles
            WHERE owner_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$ownerId]);
        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($vehicles as &$v) {
            $v['images'] = getVehicleImageUrls($pdo, $v['id']);
            $v['image_ids'] = getVehicleImageIds($pdo, $v['id']);
            $v['features'] = $v['features'] ? trim($v['features'], '{}') : '';
            $v['features'] = $v['features'] ? explode(',', str_replace('"', '', $v['features'])) : [];
            $v['price_per_day'] = (float)$v['price_per_day'];
            $v['avg_rating'] = (float)$v['avg_rating'];
            unset($v['thumbnail_id']);
        }

        echo json_encode(['success' => true, 'vehicles' => $vehicles]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// ADD VEHICLE (owner only)
// ==========================================================
if ($action === 'add') {
    requireOwner();
    $ownerId = $_SESSION['user_id'];

    // Verify user still exists in DB (may have been deleted after schema reset)
    $checkUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $checkUser->execute([$ownerId]);
    if (!$checkUser->fetch()) {
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
        $checkPlate = $pdo->prepare("SELECT id FROM vehicles WHERE LOWER(license_plate) = LOWER(?)");
        $checkPlate->execute([$licensePlate]);
        if ($checkPlate->fetch()) {
            echo json_encode(['success' => false, 'message' => 'A vehicle with license plate "' . $licensePlate . '" already exists. Each vehicle must have a unique license plate.']);
            exit;
        }
    } catch (PDOException $e) {
        // If check fails, let the insert handle the unique constraint
    }

    // Convert PHP arrays to PostgreSQL TEXT[] format
    $featuresStr = '{' . implode(',', array_map(fn($f) => '"' . str_replace('"', '\\"', $f) . '"', $features)) . '}';

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO vehicles (
                owner_id, brand, model, year, license_plate, category, transmission, fuel_type,
                seats, color, engine_size, consumption, features,
                price_per_day, price_per_week, price_per_month,
                location_city, location_address
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?::TEXT[],
                ?, ?, ?,
                NULLIF(?, ''), NULLIF(?, '')
            ) RETURNING id
        ");
        $stmt->execute([
            $ownerId, $brand, $model, $year, $licensePlate, $category, $transmission, $fuelType,
            $seats, $color, $engineSize, $consumption, $featuresStr,
            $pricePerDay, $pricePerWeek, $pricePerMonth,
            $locationCity, $locationAddr
        ]);
        $vehicleId = $stmt->fetchColumn();

        // Link uploaded images to this vehicle
        if (!empty($imageIds)) {
            $updateStmt = $pdo->prepare("UPDATE vehicle_images SET vehicle_id = ?, sort_order = ? WHERE id = ?");
            foreach ($imageIds as $i => $imgId) {
                $updateStmt->execute([$vehicleId, $i, $imgId]);
            }
            // Set first image as thumbnail
            $thumbStmt = $pdo->prepare("UPDATE vehicle_images SET is_thumbnail = TRUE WHERE id = ?");
            $thumbStmt->execute([$imageIds[0]]);
            $pdo->prepare("UPDATE vehicles SET thumbnail_id = ? WHERE id = ?")->execute([$imageIds[0], $vehicleId]);
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
// UPDATE VEHICLE (owner only - must own the vehicle)
// ==========================================================
if ($action === 'update') {
    requireOwner();
    $ownerId   = $_SESSION['user_id'];
    $vehicleId = $input['vehicle_id'] ?? '';

    if (empty($vehicleId)) {
        echo json_encode(['success' => false, 'message' => 'Vehicle ID is required.']);
        exit;
    }

    // Verify ownership
    try {
        $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE id = ? AND owner_id = ?");
        $stmt->execute([$vehicleId, $ownerId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Vehicle not found or you do not own this vehicle.']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }

    // Check for duplicate license plate on update (exclude current vehicle)
    if (!empty($input['license_plate'])) {
        try {
            $checkPlate = $pdo->prepare("SELECT id FROM vehicles WHERE LOWER(license_plate) = LOWER(?) AND id != ?");
            $checkPlate->execute([trim($input['license_plate']), $vehicleId]);
            if ($checkPlate->fetch()) {
                echo json_encode(['success' => false, 'message' => 'A vehicle with license plate "' . trim($input['license_plate']) . '" already exists. Each vehicle must have a unique license plate.']);
                exit;
            }
        } catch (PDOException $e) {
            // If check fails, let the update handle the unique constraint
        }
    }

    // Build dynamic update
    $updates = [];
    $params = [];

    $allowedFields = [
        'brand', 'model', 'year', 'license_plate', 'category', 'transmission', 'fuel_type',
        'seats', 'color', 'engine_size', 'consumption',
        'price_per_day', 'price_per_week', 'price_per_month',
        'location_city', 'location_address', 'status', 'gps_enabled'
    ];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = $input[$field];
        }
    }

    // Handle arrays specially
    if (isset($input['features'])) {
        $featuresStr = '{' . implode(',', array_map(fn($f) => '"' . str_replace('"', '\\"', $f) . '"', $input['features'])) . '}';
        $updates[] = "features = ?::TEXT[]";
        $params[] = $featuresStr;
    }

    // Handle image_ids: update which images belong to this vehicle
    if (isset($input['image_ids'])) {
        $imageIds = $input['image_ids'];

        // Remove images that are no longer in the list
        $currentIds = getVehicleImageIds($pdo, $vehicleId);
        $removedIds = array_diff($currentIds, $imageIds);
        if (!empty($removedIds)) {
            $placeholders = implode(',', array_fill(0, count($removedIds), '?'));
            $pdo->prepare("DELETE FROM vehicle_images WHERE id IN ($placeholders)")->execute(array_values($removedIds));
        }

        // Update sort order for remaining images
        $updateSort = $pdo->prepare("UPDATE vehicle_images SET sort_order = ?, vehicle_id = ? WHERE id = ?");
        foreach ($imageIds as $i => $imgId) {
            $updateSort->execute([$i, $vehicleId, $imgId]);
        }

        // Reset thumbnails and set new thumbnail
        $pdo->prepare("UPDATE vehicle_images SET is_thumbnail = FALSE WHERE vehicle_id = ?")->execute([$vehicleId]);
        if (!empty($imageIds)) {
            $pdo->prepare("UPDATE vehicle_images SET is_thumbnail = TRUE WHERE id = ?")->execute([$imageIds[0]]);
            $updates[] = "thumbnail_id = ?";
            $params[] = $imageIds[0];
        } else {
            $updates[] = "thumbnail_id = NULL";
        }
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        exit;
    }

    $updates[] = "updated_at = NOW()";
    $params[] = $vehicleId;
    $params[] = $ownerId;

    try {
        $sql = "UPDATE vehicles SET " . implode(', ', $updates) . " WHERE id = ? AND owner_id = ? RETURNING *";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Vehicle updated successfully!',
            'vehicle' => $vehicle
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// DELETE VEHICLE (owner only - must own the vehicle)
// ==========================================================
if ($action === 'delete') {
    requireOwner();
    $ownerId   = $_SESSION['user_id'];
    $vehicleId = $input['vehicle_id'] ?? '';

    if (empty($vehicleId)) {
        echo json_encode(['success' => false, 'message' => 'Vehicle ID is required.']);
        exit;
    }

    try {
        // Check for active bookings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE vehicle_id = ? AND status IN ('pending', 'confirmed', 'in_progress')");
        $stmt->execute([$vehicleId]);
        $activeBookings = $stmt->fetchColumn();

        if ($activeBookings > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete vehicle with active bookings. Please cancel or complete all bookings first.']);
            exit;
        }

        // vehicle_images will be deleted via ON DELETE CASCADE
        $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ? AND owner_id = ?");
        $stmt->execute([$vehicleId, $ownerId]);

        if ($stmt->rowCount() === 0) {
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
// UPLOAD IMAGE (owner only - stores as BYTEA in DB)
// ==========================================================
if ($action === 'upload-image') {
    requireOwner();

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

    // Read binary data from the uploaded file
    $imageData = file_get_contents($file['tmp_name']);
    if ($imageData === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to read uploaded file.']);
        exit;
    }

    $vehicleId = $_POST['vehicle_id'] ?? null; // optional — can link later during add/update

    try {
        $stmt = $pdo->prepare("
            INSERT INTO vehicle_images (vehicle_id, image_data, mime_type, file_name, file_size)
            VALUES (:vid, :imgdata, :mime, :fname, :fsize)
            RETURNING id
        ");
        // Use PDO::PARAM_LOB for binary data to avoid UTF-8 encoding error
        $vid = $vehicleId ?: null;
        $mimeType = $file['type'];
        $fileName = $file['name'];
        $fileSize = $file['size'];

        $stmt->bindParam(':vid', $vid);
        $stmt->bindParam(':imgdata', $imageData, PDO::PARAM_LOB);
        $stmt->bindParam(':mime', $mimeType);
        $stmt->bindParam(':fname', $fileName);
        $stmt->bindParam(':fsize', $fileSize, PDO::PARAM_INT);
        $stmt->execute();
        $imageId = $stmt->fetchColumn();

        $imageUrl = '/api/vehicles.php?action=get-image&id=' . $imageId;

        echo json_encode([
            'success' => true,
            'message' => 'Image uploaded successfully!',
            'image_id' => $imageId,
            'url' => $imageUrl
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// DELETE IMAGE (owner only)
// ==========================================================
if ($action === 'delete-image') {
    requireOwner();

    $imageId = $input['image_id'] ?? '';
    if (empty($imageId)) {
        echo json_encode(['success' => false, 'message' => 'Image ID is required.']);
        exit;
    }

    try {
        // Verify the image belongs to a vehicle owned by this user
        $stmt = $pdo->prepare("
            SELECT vi.id FROM vehicle_images vi
            LEFT JOIN vehicles v ON vi.vehicle_id = v.id
            WHERE vi.id = ? AND (v.owner_id = ? OR vi.vehicle_id IS NULL)
        ");
        $stmt->execute([$imageId, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Image not found or access denied.']);
            exit;
        }

        $pdo->prepare("DELETE FROM vehicle_images WHERE id = ?")->execute([$imageId]);
        echo json_encode(['success' => true, 'message' => 'Image deleted.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// UNKNOWN ACTION
// ==========================================================
echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
