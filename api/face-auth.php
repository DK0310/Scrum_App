<?php
// Chỉ set header nếu chưa được set bởi proxy
if (!headers_sent()) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database - dùng đường dẫn tuyệt đối
require_once __DIR__ . '/../Database/db.php';

// Lấy JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$action = $input['action'];

// =====================
// ĐĂNG KÝ
// =====================
if ($action === 'register') {
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $faceDescriptor = $input['faceDescriptor'] ?? null;

    // Validate
    if (empty($username) || empty($email) || !$faceDescriptor) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin và chụp khuôn mặt']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email không hợp lệ']);
        exit;
    }

    try {
        // Kiểm tra username/email đã tồn tại
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Username hoặc email đã tồn tại']);
            exit;
        }

        // Lưu user mới
        $faceDescriptorJson = json_encode($faceDescriptor);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, face_descriptor) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $faceDescriptorJson]);

        echo json_encode([
            'success' => true, 
            'message' => 'Đăng ký thành công!',
            'userId' => $pdo->lastInsertId()
        ]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
    }
    exit;
}

// =====================
// ĐĂNG NHẬP
// =====================
if ($action === 'login') {
    $faceDescriptor = $input['faceDescriptor'] ?? null;

    if (!$faceDescriptor || !is_array($faceDescriptor)) {
        echo json_encode(['success' => false, 'message' => 'Không có dữ liệu khuôn mặt']);
        exit;
    }

    try {
        // Lấy tất cả users có face_descriptor
        $stmt = $pdo->query("SELECT id, username, email, face_descriptor FROM users WHERE face_descriptor IS NOT NULL");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($users)) {
            echo json_encode(['success' => false, 'message' => 'Chưa có người dùng nào đăng ký']);
            exit;
        }

        $bestMatch = null;
        $bestScore = PHP_FLOAT_MAX;
        $threshold = 0.6; // Ngưỡng chấp nhận (càng nhỏ càng giống)

        foreach ($users as $user) {
            $storedDescriptor = json_decode($user['face_descriptor'], true);
            
            if (!$storedDescriptor) continue;

            // Tính Euclidean distance
            $distance = calculateEuclideanDistance($faceDescriptor, $storedDescriptor);
            
            if ($distance < $bestScore) {
                $bestScore = $distance;
                $bestMatch = $user;
            }
        }

        if ($bestMatch && $bestScore < $threshold) {
            // Tính điểm khớp (0-1, 1 là hoàn hảo)
            $matchScore = max(0, 1 - ($bestScore / $threshold));

            // Nếu độ khớp dưới 50% → yêu cầu quét lại
            if ($matchScore < 0.5) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Độ khớp quá thấp (' . round($matchScore * 100, 1) . '%). Vui lòng quét lại khuôn mặt.',
                    'matchScore' => round($matchScore, 3),
                    'distance' => round($bestScore, 4),
                    'retryRequired' => true
                ]);
                exit;
            }

            // Đăng nhập thành công
            session_start();
            $_SESSION['user_id'] = $bestMatch['id'];
            $_SESSION['username'] = $bestMatch['username'];
            $_SESSION['logged_in'] = true;

            echo json_encode([
                'success' => true,
                'message' => 'Đăng nhập thành công!',
                'user' => [
                    'id' => $bestMatch['id'],
                    'username' => $bestMatch['username'],
                    'email' => $bestMatch['email']
                ],
                'matchScore' => round($matchScore, 3),
                'distance' => round($bestScore, 4)
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Không nhận diện được khuôn mặt. Vui lòng thử lại hoặc đăng ký.',
                'distance' => $bestScore ? round($bestScore, 4) : null
            ]);
        }

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
    }
    exit;
}

// =====================
// KIỂM TRA ĐĂNG NHẬP
// =====================
if ($action === 'check') {
    session_start();
    
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
        echo json_encode([
            'success' => true,
            'logged_in' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'logged_in' => false
        ]);
    }
    exit;
}

// =====================
// ĐĂNG XUẤT
// =====================
if ($action === 'logout') {
    session_start();
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => 'Đã đăng xuất']);
    exit;
}

// Action không hợp lệ
echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);

// =====================
// HELPER FUNCTIONS
// =====================

/**
 * Tính khoảng cách Euclidean giữa 2 face descriptors
 */
function calculateEuclideanDistance($descriptor1, $descriptor2) {
    if (count($descriptor1) !== count($descriptor2)) {
        return PHP_FLOAT_MAX;
    }

    $sum = 0;
    for ($i = 0; $i < count($descriptor1); $i++) {
        $diff = $descriptor1[$i] - $descriptor2[$i];
        $sum += $diff * $diff;
    }

    return sqrt($sum);
}
