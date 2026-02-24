<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Load environment variables
require_once __DIR__ . '/../config/env.php';

// Cấu hình từ .env
define('MEM0_API_KEY', EnvLoader::get('MEM0_API_KEY'));
define('N8N_WEBHOOK_URL', EnvLoader::get('N8N_WEBHOOK_URL'));

// Include libraries
require_once __DIR__ . '/../api/mem0.php';

// Lấy input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['message'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$message = trim($input['message']);
$userId = $_SESSION['user_id'] ?? 'anonymous_' . session_id();

try {
    // Khởi tạo Mem0
    $mem0 = new Mem0Manager(MEM0_API_KEY, $userId);
    
    // Bước 1: Tìm kiếm memories liên quan đến message
    $context = $mem0->getContextForLLM($message);
    
    // Bước 2: Gửi message + context đến n8n
    $n8nPayload = [
        'message' => $message,
        'userContext' => $context,
        'timestamp' => date('c'),
        'userId' => $userId
    ];
    
    $n8nResponse = sendToN8N($n8nPayload);
    
    if (!$n8nResponse['success']) {
        throw new Exception('n8n request failed: ' . ($n8nResponse['error'] ?? 'Unknown error'));
    }
    
    // Bước 3: Lấy response từ n8n
    $botResponse = $n8nResponse['data']['output'] ?? $n8nResponse['data']['response'] ?? 'No response';
    
    // Bước 4: Lưu conversation vào Mem0
    $memoryMessage = "User: " . $message . "\nBot: " . $botResponse;
    $mem0->addMemory($memoryMessage, [
        'type' => 'conversation',
        'timestamp' => date('c')
    ]);
    
    // Trả về response
    echo json_encode([
        'success' => true,
        'response' => $botResponse,
        'context_used' => !empty($context)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Gửi request đến n8n
 */
function sendToN8N($data) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, N8N_WEBHOOK_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => $error
        ];
    }
    
    $decoded = json_decode($response, true);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'data' => $decoded ?? $response,
        'http_code' => $httpCode
    ];
}

?>
