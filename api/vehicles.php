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

session_start();
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/supabase-storage.php';
require_once __DIR__ . '/../sql/VehicleRepository.php';
require_once __DIR__ . '/../sql/VehicleImageRepository.php';

$vehicleRepo = new VehicleRepository($pdo);
$vehicleImageRepo = new VehicleImageRepository($pdo);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
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
    return array_map(function($row) use ($storage) {
        // storage_path-only
        return $storage->getPublicUrl($row['storage_path']);
    }, $rows);
}

// Helper: get image IDs for a vehicle
function getVehicleImageIds($pdo, $vehicleId) {
    $repo = new VehicleImageRepository($pdo);
    return $repo->listIdsByVehicleId((string)$vehicleId);
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
    $passengers = (int) ($input['passengers'] ?? 1);
    
    try {
        // Get available vehicles grouped by service_tier
        $query = "SELECT DISTINCT service_tier FROM vehicles WHERE status = 'available' AND service_tier IN ('eco', 'standard', 'luxury')";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        $availableTiers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $availableTiers[$row['service_tier']] = true;
        }
        
        // Filter by passenger count
        // Eco: max 4 passengers
        if ($passengers > 4 && isset($availableTiers['eco'])) {
            unset($availableTiers['eco']);
        }
        
        echo json_encode([
            'success' => true,
            'available_tiers' => $availableTiers,
            'passenger_count' => $passengers
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error checking available tiers']);
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
// Only show vehicles that staff published (available)
// ==========================================================
if ($action === 'public-list') {
    $brand  = $_GET['brand'] ?? $input['brand'] ?? '';
    $search = $_GET['search'] ?? $input['search'] ?? '';
    $limit  = min((int)($_GET['limit'] ?? $input['limit'] ?? 50), 100);

    try {
        $vehicles = $vehicleRepo->listPublic(['brand' => (string)$brand, 'search' => (string)$search, 'limit' => (int)$limit]);

        // Attach first image (thumbnail if present) as vehicle_image
        foreach ($vehicles as &$v) {
            $v['price_per_day'] = isset($v['price_per_day']) ? (float)$v['price_per_day'] : null;
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
// Tightened: only expose available vehicles to customers
// ==========================================================
if ($action === 'list') {
    $filters = [
        'category' => $_GET['category'] ?? $input['category'] ?? '',
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

        foreach ($vehicles as &$v) {
            $v['images'] = getVehicleImageUrls($pdo, $v['id']);
            $v['image_ids'] = getVehicleImageIds($pdo, $v['id']);
            $v['features'] = $v['features'] ? trim($v['features'], '{}') : '';
            $v['features'] = $v['features'] ? explode(',', str_replace('"', '', $v['features'])) : [];
            $v['price_per_day'] = (float)$v['price_per_day'];
            $v['avg_rating'] = (float)$v['avg_rating'];
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
        $vehicle = $vehicleRepo->getById((string)$vehicleId);

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

    if (empty($vehicleId)) {
        echo json_encode(['success' => false, 'message' => 'Vehicle ID is required.']);
        exit;
    }

    // Verify ownership
    if (!$vehicleRepo->existsForUser($vehicleId, $ownerId)) {
        echo json_encode(['success' => false, 'message' => 'Vehicle not found or you do not own this vehicle.']);
        exit;
    }

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
        'seats', 'color', 'engine_size', 'consumption',
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

        $imageId = $vehicleRepo->insertImage($vehicleId, $storagePath, $file['type'], $file['name'], $file['size']);

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
