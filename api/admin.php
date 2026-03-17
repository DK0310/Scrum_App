<?php
/**
 * Admin API - DriveNow
 * Admin-only operations:
 * - Hero slides CRUD (Supabase Storage)
 * - Promotions CRUD
 * - Delete any vehicle / booking
 */

// Handle hero-slide-image serving — redirect to Supabase Storage or serve BYTEA fallback
$action = $_GET['action'] ?? '';
if ($action === 'hero-slide-image') {
    session_start();
    require_once __DIR__ . '/../Database/db.php';
    require_once __DIR__ . '/../lib/repositories/HeroSlideRepository.php';

    $slideId = $_GET['id'] ?? '';
    if (empty($slideId)) {
        http_response_code(400);
        echo 'Slide ID required';
        exit;
    }

    try {
        $slideRepo = new HeroSlideRepository($pdo);
        $img = $slideRepo->findImageRowById((string)$slideId);

        if (!$img) {
            http_response_code(404);
            echo 'Slide image not found';
            exit;
        }

        // If storage_path exists → redirect to Supabase
        if (!empty($img['storage_path'])) {
            require_once __DIR__ . '/supabase-storage.php';
            $storage = new SupabaseStorage();
            $publicUrl = $storage->getPublicUrl($img['storage_path']);
            header('Location: ' . $publicUrl, true, 302);
            header('Cache-Control: public, max-age=86400');
            exit;
        }

        // Fallback: serve image_data BYTEA directly
        if (!empty($img['image_data'])) {
            $binaryData = is_resource($img['image_data']) ? stream_get_contents($img['image_data']) : $img['image_data'];
            header('Content-Type: ' . ($img['mime_type'] ?? 'image/jpeg'));
            header('Content-Length: ' . strlen($binaryData));
            header('Cache-Control: public, max-age=86400');
            echo $binaryData;
            exit;
        }

        http_response_code(404);
        echo 'No image data found';
    } catch (Exception $e) {
        http_response_code(500);
        echo 'Server error';
    }
    exit;
}

// Also allow public hero-slides-public (for homepage slideshow — no auth needed)
if ($action === 'hero-slides-public') {
    header('Content-Type: application/json');
    session_start();
    require_once __DIR__ . '/../Database/db.php';
    require_once __DIR__ . '/../lib/repositories/HeroSlideRepository.php';

    try {
        $slideRepo = new HeroSlideRepository($pdo);
        $slides = $slideRepo->listActiveSlidesForPublic();

        $storage = null;
        foreach ($slides as &$s) {
            if (!empty($s['storage_path'])) {
                if (!$storage) {
                    require_once __DIR__ . '/supabase-storage.php';
                    $storage = new SupabaseStorage();
                }
                $s['image_url'] = $storage->getPublicUrl($s['storage_path']);
            } else {
                $s['image_url'] = '/api/admin.php?action=hero-slide-image&id=' . $s['id'];
            }
            unset($s['storage_path']);
        }

        echo json_encode(['success' => true, 'slides' => $slides]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ===== JSON API from here =====
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/notification-helpers.php';
require_once __DIR__ . '/supabase-storage.php';
require_once __DIR__ . '/../lib/repositories/HeroSlideRepository.php';
require_once __DIR__ . '/../lib/repositories/PromotionRepository.php';
require_once __DIR__ . '/../lib/repositories/VehicleRepository.php';
require_once __DIR__ . '/../lib/repositories/BookingRepository.php';
require_once __DIR__ . '/../lib/repositories/UserRepository.php';

$heroSlideRepo = new HeroSlideRepository($pdo);
$promotionRepo = new PromotionRepository($pdo);
$vehicleRepo = new VehicleRepository($pdo);
$bookingRepo = new BookingRepository($pdo);
$userRepo = new UserRepository($pdo);

// Auto-migration: ensure storage_path column exists on hero_slides
try {
    $heroSlideRepo->ensureStoragePathColumnExists();
} catch (Exception $e) { /* column may already exist */ }

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Action is required.']);
    exit;
}

// Helper: require login
function requireAuth() {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Authentication required.', 'require_login' => true]);
        exit;
    }
}

// Helper: require admin role
function requireAdmin() {
    requireAuth();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required.']);
        exit;
    }
}

// ==========================================================
// HERO SLIDES - LIST (admin)
// ==========================================================
if ($action === 'hero-slides-list') {
    requireAdmin();

    try {
        $slides = $heroSlideRepo->listAllSlides();

        $storage = null;
        foreach ($slides as &$s) {
            if (!empty($s['storage_path'])) {
                if (!$storage) {
                    $storage = new SupabaseStorage();
                }
                $s['image_url'] = $storage->getPublicUrl($s['storage_path']);
            } else {
                // Fallback: serve via hero-slide-image endpoint (handles BYTEA)
                $s['image_url'] = '/api/admin.php?action=hero-slide-image&id=' . $s['id'];
            }
        }

        echo json_encode(['success' => true, 'slides' => $slides]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// HERO SLIDES - UPLOAD (admin, multipart — Supabase Storage)
// ==========================================================
if ($action === 'hero-slide-upload') {
    requireAdmin();

    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'message' => 'No image file provided.']);
        exit;
    }

    $file = $_FILES['image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize = 10 * 1024 * 1024; // 10MB for hero slides

    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, WebP, GIF allowed.']);
        exit;
    }

    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File too large. Max 10MB.']);
        exit;
    }

    $imageData = file_get_contents($file['tmp_name']);
    if ($imageData === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to read uploaded file.']);
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $linkUrl = trim($_POST['link_url'] ?? '');
    $sortOrder = intval($_POST['sort_order'] ?? 0);
    $isActive = ($_POST['is_active'] ?? '1') === '1';
    $adminId = $_SESSION['user_id'];

    try {
        $storage = new SupabaseStorage();
        $uniqueName = SupabaseStorage::uniqueName($file['name']);
        $storagePath = 'hero-slides/' . $uniqueName;

        // Upload to Supabase Storage
        $uploadResult = $storage->upload($storagePath, $imageData, $file['type']);
        if (!$uploadResult['success']) {
            echo json_encode(['success' => false, 'message' => 'Storage upload failed: ' . ($uploadResult['message'] ?? 'Unknown error')]);
            exit;
        }

        $slideId = $heroSlideRepo->addSlide([
            'storage_path' => $storagePath,
            'image_data' => $imageData,
            'mime_type' => $file['type'],
            'file_name' => $file['name'],
            'file_size' => $file['size'],
            'title' => $title,
            'subtitle' => $subtitle,
            'link_url' => $linkUrl,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
            'created_by' => $adminId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Hero slide uploaded successfully.',
            'slide_id' => $slideId,
            'image_url' => $uploadResult['public_url']
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// HERO SLIDES - UPDATE (admin)
// ==========================================================
if ($action === 'hero-slide-update') {
    requireAdmin();

    $slideId = $input['slide_id'] ?? '';
    if (empty($slideId)) {
        echo json_encode(['success' => false, 'message' => 'Slide ID is required.']);
        exit;
    }

    $fields = [];

    if (isset($input['title'])) { $fields['title'] = $input['title']; }
    if (isset($input['subtitle'])) { $fields['subtitle'] = $input['subtitle']; }
    if (isset($input['link_url'])) { $fields['link_url'] = $input['link_url']; }
    if (isset($input['sort_order'])) { $fields['sort_order'] = intval($input['sort_order']); }
    if (isset($input['is_active'])) { $fields['is_active'] = (bool)$input['is_active']; }

    if (empty($fields)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        exit;
    }

    try {
        $heroSlideRepo->updateSlide($slideId, $fields);

        echo json_encode(['success' => true, 'message' => 'Slide updated successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// HERO SLIDES - DELETE (admin)
// ==========================================================
if ($action === 'hero-slide-delete') {
    requireAdmin();

    $slideId = $input['slide_id'] ?? '';
    if (empty($slideId)) {
        echo json_encode(['success' => false, 'message' => 'Slide ID is required.']);
        exit;
    }

    try {
        // Get storage path before deleting
        $slide = $heroSlideRepo->findSlideById($slideId);

        if (!$slide) {
            echo json_encode(['success' => false, 'message' => 'Slide not found.']);
            exit;
        }

        // Delete from Supabase Storage
        if (!empty($slide['storage_path'])) {
            $storage = new SupabaseStorage();
            $storage->delete($slide['storage_path']);
        }

        // Delete DB record
        $heroSlideRepo->deleteSlide($slideId);

        echo json_encode(['success' => true, 'message' => 'Slide deleted successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// PROMOTIONS - LIST (admin sees all, including inactive)
// ==========================================================
if ($action === 'promotions-list') {
    requireAdmin();

    try {
        $promotions = $promotionRepo->listAllPromotions();
        echo json_encode(['success' => true, 'promotions' => $promotions]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// PROMOTIONS - ADD (admin)
// ==========================================================
if ($action === 'promotion-add') {
    requireAdmin();

    $code = trim($input['code'] ?? '');
    $description = trim($input['description'] ?? '');
    $discountType = trim($input['discount_type'] ?? 'percentage');
    $discountValue = floatval($input['discount_value'] ?? 0);
    $startDate = trim($input['start_date'] ?? '');
    $endDate = trim($input['end_date'] ?? '');
    $usageLimit = isset($input['usage_limit']) ? intval($input['usage_limit']) : null;

    if (empty($code) || empty($description) || $discountValue <= 0) {
        echo json_encode(['success' => false, 'message' => 'Code, description and discount value are required.']);
        exit;
    }

    try {
        if ($promotionRepo->codeExists($code)) {
            echo json_encode(['success' => false, 'message' => 'Promotion code already exists.']);
            exit;
        }

        $promoId = $promotionRepo->addPromotion([
            'code' => strtoupper($code),
            'title' => strtoupper($code),
            'description' => $description,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'starts_at' => $startDate ?: null,
            'expires_at' => $endDate ?: null,
            'max_uses' => $usageLimit
        ]);

        // Broadcast notification to all users
        $discountText = $discountType === 'percentage' ? "{$discountValue}%" : "\${$discountValue}";
        createNotificationForAll($pdo, 'promo', '🎁 New Promotion: ' . strtoupper($code), "Use code {$code} to get {$discountText} off your next rental! {$description}");

        echo json_encode(['success' => true, 'message' => 'Promotion created.', 'promotion' => $promoId]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// PROMOTIONS - UPDATE (admin)
// ==========================================================
if ($action === 'promotion-update') {
    requireAdmin();

    $promoId = $input['promotion_id'] ?? '';
    if (empty($promoId)) {
        echo json_encode(['success' => false, 'message' => 'Promotion ID is required.']);
        exit;
    }

    $fields = [];

    if (isset($input['code'])) { $fields['code'] = strtoupper($input['code']); }
    if (isset($input['description'])) { $fields['description'] = $input['description']; }
    if (isset($input['discount_type'])) { $fields['discount_type'] = $input['discount_type']; }
    if (isset($input['discount_value'])) { $fields['discount_value'] = floatval($input['discount_value']); }
    if (isset($input['start_date'])) { $fields['starts_at'] = $input['start_date']; }
    if (isset($input['end_date'])) { $fields['expires_at'] = $input['end_date']; }
    if (isset($input['usage_limit'])) { $fields['max_uses'] = intval($input['usage_limit']); }
    if (isset($input['is_active'])) { $fields['is_active'] = (bool)$input['is_active']; }

    if (empty($fields)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        exit;
    }

    try {
        $promotionRepo->updatePromotion($promoId, $fields);

        echo json_encode(['success' => true, 'message' => 'Promotion updated.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// PROMOTIONS - DELETE (admin)
// ==========================================================
if ($action === 'promotion-delete') {
    requireAdmin();

    $promoId = $input['promotion_id'] ?? '';
    if (empty($promoId)) {
        echo json_encode(['success' => false, 'message' => 'Promotion ID is required.']);
        exit;
    }

    try {
        $promotionRepo->deletePromotion($promoId);

        echo json_encode(['success' => true, 'message' => 'Promotion deleted.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// ADMIN DELETE VEHICLE (any vehicle, regardless of owner)
// ==========================================================
if ($action === 'admin-delete-vehicle') {
    requireAdmin();

    $vehicleId = $input['vehicle_id'] ?? '';
    if (empty($vehicleId)) {
        echo json_encode(['success' => false, 'message' => 'Vehicle ID is required.']);
        exit;
    }

    try {
        // Check for active bookings
        $activeBookings = $vehicleRepo->countActiveBookings($vehicleId);
        if ($activeBookings > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete vehicle with active bookings (' . $activeBookings . ' active). Cancel them first.']);
            exit;
        }

        // Delete images from Supabase Storage first
        $storagePaths = $vehicleRepo->getImageStoragePaths($vehicleId);
        if (!empty($storagePaths)) {
            $storage = new SupabaseStorage();
            $storage->deleteMultiple($storagePaths);
        }

        // Admin can delete any vehicle - use repo without owner check
        $deleted = $vehicleRepo->deleteVehicleAdmin($vehicleId);

        if (!$deleted) {
            echo json_encode(['success' => false, 'message' => 'Vehicle not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Vehicle deleted by admin.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// ADMIN DELETE/CANCEL BOOKING
// ==========================================================
if ($action === 'admin-delete-booking') {
    requireAdmin();

    $bookingId = $input['booking_id'] ?? '';
    if (empty($bookingId)) {
        echo json_encode(['success' => false, 'message' => 'Booking ID is required.']);
        exit;
    }

    try {
        $deleted = $bookingRepo->deleteBooking($bookingId);

        if (!$deleted) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Booking deleted by admin.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// ADMIN - LIST ALL VEHICLES
// ==========================================================
if ($action === 'admin-list-vehicles') {
    requireAdmin();

    try {
        // Get vehicles via repository
        $vehicles = $vehicleRepo->listAllVehiclesForAdmin();

        // Add image URLs (get first image as thumbnail)
        foreach ($vehicles as &$v) {
            // Get thumbnail ID using repository helper
            $imgId = $vehicleRepo->getThumbnailImageId($v['id']);
            $v['thumbnail'] = $imgId ? '/api/vehicles.php?action=get-image&id=' . $imgId : null;
        }
        echo json_encode(['success' => true, 'vehicles' => $vehicles]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// ADMIN - LIST ALL BOOKINGS
// ==========================================================
if ($action === 'admin-list-bookings') {
    requireAdmin();

    try {
        // Get bookings via repository
        $bookings = $bookingRepo->listAllBookingsForAdmin();
        echo json_encode(['success' => true, 'bookings' => $bookings]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// ADMIN - LIST ALL USERS
// ==========================================================
if ($action === 'admin-list-users') {
    requireAdmin();

    try {
        // Get users via repository
        $users = $userRepo->listAllUsers();

        // Cast booleans for JS
        foreach ($users as &$u) {
            $u['is_active'] = ($u['is_active'] === 't' || $u['is_active'] === true || $u['is_active'] === '1');
            $u['email_verified'] = ($u['email_verified'] === 't' || $u['email_verified'] === true || $u['email_verified'] === '1');
            $u['phone_verified'] = ($u['phone_verified'] === 't' || $u['phone_verified'] === true || $u['phone_verified'] === '1');
            $u['faceid_enabled'] = ($u['faceid_enabled'] === 't' || $u['faceid_enabled'] === true || $u['faceid_enabled'] === '1');
        }

        echo json_encode(['success' => true, 'users' => $users]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// ADMIN - UPDATE USER (role, is_active)
// ==========================================================
if ($action === 'admin-update-user') {
    requireAdmin();

    $userId = $input['user_id'] ?? '';
    if (empty($userId)) {
        echo json_encode(['success' => false, 'message' => 'User ID is required.']);
        exit;
    }

    // Prevent self-modification for safety
    if ($userId === $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot modify your own account from admin panel.']);
        exit;
    }

    $fields = [];

    if (isset($input['role']) && in_array($input['role'], ['renter', 'owner', 'admin'])) {
        $fields['role'] = $input['role'];
    }

    if (isset($input['is_active'])) {
        $fields['is_active'] = $input['is_active'] ? 't' : 'f';
    }

    if (empty($fields)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        exit;
    }

    try {
        // Update user via repository
        $updated = $userRepo->updateUserAdmin($userId, $fields);

        if (!$updated) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// ADMIN - DELETE USER
// ==========================================================
if ($action === 'admin-delete-user') {
    requireAdmin();

    $userId = $input['user_id'] ?? '';
    if (empty($userId)) {
        echo json_encode(['success' => false, 'message' => 'User ID is required.']);
        exit;
    }

    // Prevent self-deletion
    if ($userId === $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account.']);
        exit;
    }

    try {
        // Check for active bookings
        $activeBookings = $userRepo->countActiveBookings($userId);

        if ($activeBookings > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete user with ' . $activeBookings . ' active booking(s). Cancel them first.']);
            exit;
        }

        // Delete user via repository
        $deleted = $userRepo->deleteUser($userId);

        if (!$deleted) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown admin action: ' . $action]);

