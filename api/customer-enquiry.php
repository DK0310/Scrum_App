<?php
/**
 * Customer Enquiry Page & API - Private Hire
 * - Page view: /api/customer-enquiry.php
 * - API mode: /api/customer-enquiry.php?action=...
 */

session_start();

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/supabase-storage.php';
require_once __DIR__ . '/../sql/EnquiryRepository.php';
require_once __DIR__ . '/../sql/UserRepository.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$userId = (string)($_SESSION['user_id'] ?? '');
$userRole = strtolower(trim((string)($_SESSION['role'] ?? 'user')));
$title = 'Customer Enquiry - Private Hire';
$currentPage = 'customer-enquiry';

$enquiryRepo = new EnquiryRepository($pdo);
$userRepo = new UserRepository($pdo);

try {
    $enquiryRepo->ensureSchema();
} catch (Throwable $e) {
    // Keep page reachable; API calls will fail with proper message when DB is not writable.
}

function normalizeRoleEnquiry(string $role): string
{
    return str_replace([' ', '-', '_'], '', strtolower(trim($role)));
}

function isCallCenterStaffRole(string $role): bool
{
    return normalizeRoleEnquiry($role) === 'callcenterstaff';
}

function requireLoginEnquiry(bool $isLoggedIn): void
{
    if (!$isLoggedIn) {
        echo json_encode(['success' => false, 'message' => 'Please sign in first.', 'require_login' => true]);
        exit;
    }
}

function formatEnquiryRow(array $row, SupabaseStorage $storage): array
{
    $row['image_src'] = !empty($row['image_storage_path']) ? $storage->getPublicUrl((string)$row['image_storage_path']) : null;
    $row['reply_image_src'] = !empty($row['reply_image_storage_path']) ? $storage->getPublicUrl((string)$row['reply_image_storage_path']) : null;
    return $row;
}

$rawBody = file_get_contents('php://input');
$bodyJson = null;
if (is_string($rawBody) && trim($rawBody) !== '') {
    $bodyJson = json_decode($rawBody, true);
}
if (!is_array($bodyJson)) {
    $bodyJson = [];
}

$action = $_GET['action'] ?? $_POST['action'] ?? ($bodyJson['action'] ?? '');

if ($action === 'get-enquiry-image') {
    $id = trim((string)($_GET['id'] ?? ''));
    $kind = trim((string)($_GET['kind'] ?? 'enquiry'));
    if ($id === '') {
        http_response_code(400);
        echo 'Image ID is required.';
        exit;
    }

    try {
        $row = $enquiryRepo->findById($id);
        if (!$row) {
            http_response_code(404);
            echo 'Enquiry not found.';
            exit;
        }

        $path = $kind === 'reply' ? (string)($row['reply_image_storage_path'] ?? '') : (string)($row['image_storage_path'] ?? '');
        if ($path === '') {
            http_response_code(404);
            echo 'Image not found.';
            exit;
        }

        $storage = new SupabaseStorage();
        header('Location: ' . $storage->getPublicUrl($path), true, 302);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Server error';
        exit;
    }
}

if ($action !== '') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        exit(0);
    }

    $storage = new SupabaseStorage();

    // Customer actions
    if ($action === 'create-enquiry') {
        requireLoginEnquiry($isLoggedIn);
        if (isCallCenterStaffRole($userRole)) {
            echo json_encode(['success' => false, 'message' => 'Staff accounts cannot create customer enquiries.']);
            exit;
        }

        // For multipart/form-data requests, $_POST contains fields even when no file is selected.
        $payload = !empty($_POST) ? $_POST : $bodyJson;
        $enquiryType = strtolower(trim((string)($payload['enquiry_type'] ?? '')));
        $content = trim((string)($payload['content'] ?? ''));

        if (!in_array($enquiryType, ['trip', 'general'], true)) {
            echo json_encode(['success' => false, 'message' => 'Enquiry type must be trip or general.']);
            exit;
        }
        if ($content === '') {
            echo json_encode(['success' => false, 'message' => 'Enquiry content is required.']);
            exit;
        }

        $imagePath = null;
        if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize = 5 * 1024 * 1024;

            if (!in_array((string)$file['type'], $allowedTypes, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image type.']);
                exit;
            }
            if ((int)$file['size'] > $maxSize) {
                echo json_encode(['success' => false, 'message' => 'Image too large. Max 5MB.']);
                exit;
            }

            $binary = file_get_contents((string)$file['tmp_name']);
            $imagePath = 'enquiries/customer-' . SupabaseStorage::uniqueName((string)$file['name']);
            $upload = $storage->upload($imagePath, $binary, (string)$file['type']);
            if (empty($upload['success'])) {
                echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
                exit;
            }
        }

        try {
            $enquiry = $enquiryRepo->createEnquiry($userId, $enquiryType, $content, $imagePath);
            $enquiry = formatEnquiryRow($enquiry, $storage);
            echo json_encode(['success' => true, 'message' => 'Enquiry submitted successfully.', 'enquiry' => $enquiry]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to submit enquiry.']);
        }
        exit;
    }

    if ($action === 'list-my-enquiries') {
        requireLoginEnquiry($isLoggedIn);
        try {
            $rows = $enquiryRepo->listByCustomer($userId);
            $rows = array_map(static function (array $row) use ($storage): array {
                return formatEnquiryRow($row, $storage);
            }, $rows);
            echo json_encode(['success' => true, 'enquiries' => $rows]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to load enquiries.']);
        }
        exit;
    }

    if ($action === 'delete-enquiry') {
        requireLoginEnquiry($isLoggedIn);
        $enquiryId = trim((string)($bodyJson['enquiry_id'] ?? $_POST['enquiry_id'] ?? ''));
        if ($enquiryId === '') {
            echo json_encode(['success' => false, 'message' => 'Enquiry ID is required.']);
            exit;
        }

        try {
            $row = $enquiryRepo->findById($enquiryId);
            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'Enquiry not found.']);
                exit;
            }
            if ((string)$row['customer_id'] !== $userId) {
                echo json_encode(['success' => false, 'message' => 'No permission to delete this enquiry.']);
                exit;
            }

            $deleted = $enquiryRepo->deleteByCustomer($enquiryId, $userId);
            if (!$deleted) {
                echo json_encode(['success' => false, 'message' => 'Only open enquiries without staff reply can be deleted.']);
                exit;
            }

            if (!empty($row['image_storage_path'])) {
                $storage->delete((string)$row['image_storage_path']);
            }
            echo json_encode(['success' => true, 'message' => 'Enquiry deleted.']);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to delete enquiry.']);
        }
        exit;
    }

    if ($action === 'get-enquiry-detail') {
        requireLoginEnquiry($isLoggedIn);
        $enquiryId = trim((string)($_GET['enquiry_id'] ?? $bodyJson['enquiry_id'] ?? ''));
        if ($enquiryId === '') {
            echo json_encode(['success' => false, 'message' => 'Enquiry ID is required.']);
            exit;
        }

        try {
            $row = $enquiryRepo->findById($enquiryId);
            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'Enquiry not found.']);
                exit;
            }

            $isStaff = isCallCenterStaffRole($userRole);
            if (!$isStaff && (string)$row['customer_id'] !== $userId) {
                echo json_encode(['success' => false, 'message' => 'No permission.']);
                exit;
            }

            echo json_encode(['success' => true, 'enquiry' => formatEnquiryRow($row, $storage)]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to load enquiry detail.']);
        }
        exit;
    }

    // Staff actions
    if ($action === 'list-staff-enquiries') {
        requireLoginEnquiry($isLoggedIn);
        if (!isCallCenterStaffRole($userRole)) {
            echo json_encode(['success' => false, 'message' => 'Only call center staff can access this endpoint.']);
            exit;
        }

        try {
            $rows = $enquiryRepo->listForStaff();
            $rows = array_map(static function (array $row) use ($storage): array {
                return formatEnquiryRow($row, $storage);
            }, $rows);
            echo json_encode(['success' => true, 'enquiries' => $rows]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to load enquiries.']);
        }
        exit;
    }

    if ($action === 'reply-enquiry') {
        requireLoginEnquiry($isLoggedIn);
        if (!isCallCenterStaffRole($userRole)) {
            echo json_encode(['success' => false, 'message' => 'Only call center staff can reply enquiries.']);
            exit;
        }

        // For multipart/form-data requests, $_POST contains fields even when no file is selected.
        $payload = !empty($_POST) ? $_POST : $bodyJson;
        $enquiryId = trim((string)($payload['enquiry_id'] ?? ''));
        $content = trim((string)($payload['content'] ?? ''));

        if ($enquiryId === '' || $content === '') {
            echo json_encode(['success' => false, 'message' => 'Enquiry ID and reply content are required.']);
            exit;
        }

        $imagePath = null;
        if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize = 5 * 1024 * 1024;

            if (!in_array((string)$file['type'], $allowedTypes, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image type.']);
                exit;
            }
            if ((int)$file['size'] > $maxSize) {
                echo json_encode(['success' => false, 'message' => 'Image too large. Max 5MB.']);
                exit;
            }

            $binary = file_get_contents((string)$file['tmp_name']);
            $imagePath = 'enquiries/staff-' . SupabaseStorage::uniqueName((string)$file['name']);
            $upload = $storage->upload($imagePath, $binary, (string)$file['type']);
            if (empty($upload['success'])) {
                echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
                exit;
            }
        }

        try {
            $reply = $enquiryRepo->createReply($enquiryId, $userId, $content, $imagePath);
            $staff = $userRepo->findById($userId);
            $reply['staff_name'] = $staff['full_name'] ?? 'Call Center Staff';
            $reply['reply_image_src'] = !empty($reply['image_storage_path']) ? $storage->getPublicUrl((string)$reply['image_storage_path']) : null;
            echo json_encode(['success' => true, 'message' => 'Reply sent.', 'reply' => $reply]);
        } catch (RuntimeException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to send reply.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
    exit;
}

require __DIR__ . '/../templates/customer-enquiry.html.php';
