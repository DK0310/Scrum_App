<?php
/**
 * DriveNow Chatbot API — Sends messages to n8n AI Agent
 * 
 * n8n AI Agent uses PostgreSQL Chat Memory node with table: n8n_chat_histories
 * n8n handles conversation history automatically — PHP just proxies messages
 * 
 * n8n expects: { chatInput: "user message", sessionId: "unique-session-id" }
 * n8n returns: { output: "bot response" }
 */
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

define('N8N_WEBHOOK_URL', EnvLoader::get('N8N_WEBHOOK_URL'));

// Get input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty(trim($input['message'] ?? $input['chatInput'] ?? ''))) {
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

$message = trim($input['message'] ?? $input['chatInput'] ?? '');
$userId = $_SESSION['user_id'] ?? null;
$sessionToken = session_id();

// Build a unique session ID for n8n PostgreSQL Chat Memory
// n8n uses this to store/retrieve conversation history in n8n_chat_histories table
$chatSessionId = $userId ? ('user_' . $userId) : ('anon_' . $sessionToken);

try {
    // ============================================
    // Send to n8n AI Agent webhook
    // n8n AI Agent expects 'chatInput' and 'sessionId'
    // The AI Agent + PostgreSQL Chat Memory node handles:
    //   - Storing user message in n8n_chat_histories
    //   - Loading conversation history
    //   - Sending to LLM with context
    //   - Storing bot response in n8n_chat_histories
    // ============================================
    $n8nPayload = [
        'chatInput' => $message,           // n8n AI Agent's expected input field
        'sessionId' => $chatSessionId,     // Used by PostgreSQL Chat Memory node
        'userId' => $userId,
        'timestamp' => date('c')
    ];

    $n8nResponse = sendToN8N($n8nPayload);

    if (!$n8nResponse['success']) {
        $errorDetail = $n8nResponse['error'] ?? 'Unknown error';
        $httpCode = $n8nResponse['http_code'] ?? 0;
        throw new Exception("n8n connection failed (HTTP $httpCode): $errorDetail. Make sure n8n is running and the workflow is active.");
    }

    // ============================================
    // Extract bot response from n8n
    // n8n AI Agent typically returns { output: "..." }
    // ============================================
    $responseData = $n8nResponse['data'];
    
    // Handle various response formats from n8n
    $botResponse = null;
    
    if (is_array($responseData)) {
        // n8n AI Agent standard output
        $botResponse = $responseData['output'] 
                    ?? $responseData['response'] 
                    ?? $responseData['text']
                    ?? $responseData['message']
                    ?? null;
    } elseif (is_string($responseData) && !empty($responseData)) {
        // Plain text response
        $botResponse = $responseData;
    }

    if (empty($botResponse)) {
        $botResponse = 'I received your message but could not generate a response. Please try again.';
    }

    // ============================================
    // Return response to frontend
    // ============================================
    echo json_encode([
        'success' => true,
        'response' => $botResponse,
        'session_id' => $chatSessionId
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// =====================================================
// HELPER FUNCTIONS
// =====================================================

/**
 * Send request to n8n webhook
 */
function sendToN8N($data) {
    $url = N8N_WEBHOOK_URL;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 60,       // AI Agent can take longer to respond
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => 'cURL error: ' . $error,
            'http_code' => 0
        ];
    }

    if ($httpCode === 404) {
        return [
            'success' => false,
            'error' => 'Webhook not found. Make sure the n8n workflow is ACTIVE (not just saved). URL: ' . $url,
            'http_code' => 404
        ];
    }

    if ($httpCode === 500) {
        return [
            'success' => false,
            'error' => 'n8n internal error. Check n8n execution logs for details.',
            'http_code' => 500
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
