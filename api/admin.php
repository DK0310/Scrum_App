<?php
/**
 * Admin API - DriveNow
 * Admin-only operations:
 * - Hero slides CRUD (BYTEA image storage)
 * - Promotions CRUD
 * - Delete any vehicle / booking
 */

// Handle hero-slide-image serving BEFORE setting JSON headers
$action = $_GET['action'] ?? '';
if ($action === 'hero-slide-image') {
    session_start();
    require_once __DIR__ . '/../Database/db.php';

    $slideId = $_GET['id'] ?? '';
    if (empty($slideId)) {
        http_response_code(400);
        echo 'Slide ID required';
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT image_data, mime_type, file_name FROM hero_slides WHERE id = ?");
        $stmt->execute([$slideId]);
        $img = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$img) {
            http_response_code(404);
            echo 'Slide image not found';
            exit;
        }

        $imageData = $img['image_data'];
        if (is_resource($imageData)) {
            $imageData = stream_get_contents($imageData);
        }
        if (is_string($imageData) && substr($imageData, 0, 2) === '\\x') {
            $imageData = hex2bin(substr($imageData, 2));
        }

        header('Content-Type: ' . $img['mime_type']);
        header('Content-Length: ' . strlen($imageData));
        header('Cache-Control: public, max-age=86400');
        header('Content-Disposition: inline; filename="' . ($img['file_name'] ?? 'slide') . '"');
        echo $imageData;
    } catch (PDOException $e) {
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

    try {
        $stmt = $pdo->query("SELECT id, title, subtitle, link_url, sort_order FROM hero_slides WHERE is_active = TRUE ORDER BY sort_order ASC, created_at DESC");
        $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add image URLs
        foreach ($slides as &$s) {
            $s['image_url'] = '/api/admin.php?action=hero-slide-image&id=' . $s['id'];
        }

        echo json_encode(['success' => true, 'slides' => $slides]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
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
        $stmt = $pdo->query("SELECT id, file_name, mime_type, file_size, title, subtitle, link_url, sort_order, is_active, created_at FROM hero_slides ORDER BY sort_order ASC, created_at DESC");
        $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($slides as &$s) {
            $s['image_url'] = '/api/admin.php?action=hero-slide-image&id=' . $s['id'];
        }

        echo json_encode(['success' => true, 'slides' => $slides]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// HERO SLIDES - UPLOAD (admin, multipart)
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
        $stmt = $pdo->prepare("
            INSERT INTO hero_slides (image_data, mime_type, file_name, file_size, title, subtitle, link_url, sort_order, is_active, created_by)
            VALUES (:data, :mime, :fname, :fsize, :title, :sub, :link, :sort, :active, :uid)
            RETURNING id
        ");
        $stmt->bindParam(':data', $imageData, PDO::PARAM_LOB);
        $stmt->bindParam(':mime', $file['type']);
        $stmt->bindParam(':fname', $file['name']);
        $stmt->bindParam(':fsize', $file['size'], PDO::PARAM_INT);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':sub', $subtitle);
        $stmt->bindParam(':link', $linkUrl);
        $stmt->bindParam(':sort', $sortOrder, PDO::PARAM_INT);
        $stmt->bindParam(':active', $isActive, PDO::PARAM_BOOL);
        $stmt->bindParam(':uid', $adminId);
        $stmt->execute();

        $newId = $stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'message' => 'Hero slide uploaded successfully.',
            'slide_id' => $newId,
            'image_url' => '/api/admin.php?action=hero-slide-image&id=' . $newId
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
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
    $params = [':id' => $slideId];

    if (isset($input['title'])) { $fields[] = 'title = :title'; $params[':title'] = $input['title']; }
    if (isset($input['subtitle'])) { $fields[] = 'subtitle = :sub'; $params[':sub'] = $input['subtitle']; }
    if (isset($input['link_url'])) { $fields[] = 'link_url = :link'; $params[':link'] = $input['link_url']; }
    if (isset($input['sort_order'])) { $fields[] = 'sort_order = :sort'; $params[':sort'] = intval($input['sort_order']); }
    if (isset($input['is_active'])) { $fields[] = 'is_active = :active'; $params[':active'] = (bool)$input['is_active']; }

    if (empty($fields)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        exit;
    }

    try {
        $sql = "UPDATE hero_slides SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Slide not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Slide updated successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
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
        $stmt = $pdo->prepare("DELETE FROM hero_slides WHERE id = ?");
        $stmt->execute([$slideId]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Slide not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Slide deleted successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// PROMOTIONS - LIST (admin sees all, including inactive)
// ==========================================================
if ($action === 'promotions-list') {
    requireAdmin();

    try {
        $stmt = $pdo->query("SELECT * FROM promotions ORDER BY created_at DESC");
        $promos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'promotions' => $promos]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
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
    $minRentalDays = intval($input['min_rental_days'] ?? 1);
    $maxDiscount = isset($input['max_discount']) ? floatval($input['max_discount']) : null;
    $startDate = trim($input['start_date'] ?? '');
    $endDate = trim($input['end_date'] ?? '');
    $usageLimit = isset($input['usage_limit']) ? intval($input['usage_limit']) : null;

    if (empty($code) || empty($description) || $discountValue <= 0) {
        echo json_encode(['success' => false, 'message' => 'Code, description and discount value are required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM promotions WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Promotion code already exists.']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO promotions (code, description, discount_type, discount_value, min_rental_days, max_discount, start_date, end_date, usage_limit)
            VALUES (?, ?, ?::discount_type, ?, ?, ?, ?::DATE, ?::DATE, ?)
            RETURNING *
        ");
        $stmt->execute([
            strtoupper($code), $description, $discountType, $discountValue,
            $minRentalDays, $maxDiscount,
            $startDate ?: null, $endDate ?: null, $usageLimit
        ]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC);

        // Broadcast notification to all users
        $discountText = $discountType === 'percentage' ? "{$discountValue}%" : "\${$discountValue}";
        createNotificationForAll($pdo, 'promo', '🎁 New Promotion: ' . strtoupper($code), "Use code {$code} to get {$discountText} off your next rental! {$description}");

        echo json_encode(['success' => true, 'message' => 'Promotion created.', 'promotion' => $promo]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
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
    $params = [':id' => $promoId];

    if (isset($input['code'])) { $fields[] = 'code = :code'; $params[':code'] = strtoupper($input['code']); }
    if (isset($input['description'])) { $fields[] = 'description = :desc'; $params[':desc'] = $input['description']; }
    if (isset($input['discount_type'])) { $fields[] = "discount_type = :dtype::discount_type"; $params[':dtype'] = $input['discount_type']; }
    if (isset($input['discount_value'])) { $fields[] = 'discount_value = :dval'; $params[':dval'] = floatval($input['discount_value']); }
    if (isset($input['min_rental_days'])) { $fields[] = 'min_rental_days = :mrd'; $params[':mrd'] = intval($input['min_rental_days']); }
    if (isset($input['max_discount'])) { $fields[] = 'max_discount = :maxd'; $params[':maxd'] = floatval($input['max_discount']); }
    if (isset($input['start_date'])) { $fields[] = 'start_date = :sd::DATE'; $params[':sd'] = $input['start_date']; }
    if (isset($input['end_date'])) { $fields[] = 'end_date = :ed::DATE'; $params[':ed'] = $input['end_date']; }
    if (isset($input['usage_limit'])) { $fields[] = 'usage_limit = :ul'; $params[':ul'] = intval($input['usage_limit']); }
    if (isset($input['is_active'])) { $fields[] = 'is_active = :active'; $params[':active'] = (bool)$input['is_active']; }

    if (empty($fields)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        exit;
    }

    try {
        $sql = "UPDATE promotions SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Promotion not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Promotion updated.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
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
        $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = ?");
        $stmt->execute([$promoId]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Promotion not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Promotion deleted.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
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
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE vehicle_id = ? AND status IN ('pending', 'confirmed', 'in_progress')");
        $stmt->execute([$vehicleId]);
        $activeBookings = $stmt->fetchColumn();

        if ($activeBookings > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete vehicle with active bookings (' . $activeBookings . ' active). Cancel them first.']);
            exit;
        }

        // Admin can delete any vehicle (no owner_id check)
        $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ?");
        $stmt->execute([$vehicleId]);

        if ($stmt->rowCount() === 0) {
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
        $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
        $stmt->execute([$bookingId]);

        if ($stmt->rowCount() === 0) {
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
        $stmt = $pdo->query("
            SELECT v.*, u.full_name AS owner_name, u.email AS owner_email,
                   (SELECT COUNT(*) FROM bookings WHERE vehicle_id = v.id) AS total_bookings,
                   (SELECT COUNT(*) FROM bookings WHERE vehicle_id = v.id AND status IN ('pending','confirmed','in_progress')) AS active_bookings
            FROM vehicles v
            LEFT JOIN users u ON v.owner_id = u.id
            ORDER BY v.created_at DESC
        ");
        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add image URLs
        foreach ($vehicles as &$v) {
            $imgStmt = $pdo->prepare("SELECT id FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_thumbnail DESC, sort_order ASC LIMIT 1");
            $imgStmt->execute([$v['id']]);
            $imgId = $imgStmt->fetchColumn();
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
        $stmt = $pdo->query("
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
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $stmt = $pdo->query("
            SELECT id, email, phone, auth_provider, role, full_name, date_of_birth,
                   avatar_url, city, country, driving_license, membership,
                   is_active, email_verified, phone_verified, faceid_enabled,
                   profile_completed, created_at, last_login_at
            FROM users
            ORDER BY created_at DESC
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    $params = [];

    if (isset($input['role']) && in_array($input['role'], ['renter', 'owner', 'admin'])) {
        $fields[] = "role = ?::user_role";
        $params[] = $input['role'];
    }

    if (isset($input['is_active'])) {
        $fields[] = "is_active = ?";
        $params[] = $input['is_active'] ? 't' : 'f';
    }

    if (empty($fields)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        exit;
    }

    $fields[] = "updated_at = NOW()";
    $params[] = $userId;

    try {
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
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
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE (renter_id = ? OR owner_id = ?) AND status IN ('pending', 'confirmed', 'in_progress')");
        $stmt->execute([$userId, $userId]);
        $activeBookings = $stmt->fetchColumn();

        if ($activeBookings > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete user with ' . $activeBookings . ' active booking(s). Cancel them first.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);

        if ($stmt->rowCount() === 0) {
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

