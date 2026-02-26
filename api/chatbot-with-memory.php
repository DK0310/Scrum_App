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
    // n8n can return many formats:
    //   { output: "..." }                    — AI Agent standard
    //   [{ output: "..." }]                  — Webhook wrapped in array
    //   [{ json: { output: "..." } }]        — n8n item format
    //   "plain text"                         — Plain string
    //   { response: "..." }                  — Custom workflow
    // ============================================
    $responseData = $n8nResponse['data'];
    
    // Debug: log raw response to help troubleshoot
    $debugLog = __DIR__ . '/../logs/chatbot_debug.log';
    $debugDir = dirname($debugLog);
    if (!is_dir($debugDir)) @mkdir($debugDir, 0777, true);
    @file_put_contents($debugLog, date('[Y-m-d H:i:s]') . " Raw response: " . json_encode($responseData, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    
    $botResponse = null;
    
    // If response is a string, use directly
    if (is_string($responseData) && !empty(trim($responseData))) {
        $botResponse = trim($responseData);
    }
    
    // If response is an array
    if (!$botResponse && is_array($responseData)) {
        // Case 1: n8n wraps result in array — [{ output: "..." }]
        // Check if it's a numerically indexed array (list)
        if (isset($responseData[0]) && is_array($responseData[0])) {
            $firstItem = $responseData[0];
            // n8n item format: [{ json: { output: "..." } }]
            if (isset($firstItem['json']) && is_array($firstItem['json'])) {
                $firstItem = $firstItem['json'];
            }
            $botResponse = $firstItem['output'] 
                        ?? $firstItem['response'] 
                        ?? $firstItem['text'] 
                        ?? $firstItem['message']
                        ?? $firstItem['content']
                        ?? null;
        }
        
        // Case 2: Direct object — { output: "..." }
        if (!$botResponse) {
            $botResponse = $responseData['output'] 
                        ?? $responseData['response'] 
                        ?? $responseData['text']
                        ?? $responseData['message']
                        ?? $responseData['content']
                        ?? null;
        }

        // Case 3: Nested in 'data' key — { data: { output: "..." } }
        if (!$botResponse && isset($responseData['data']) && is_array($responseData['data'])) {
            $botResponse = $responseData['data']['output']
                        ?? $responseData['data']['response']
                        ?? $responseData['data']['text']
                        ?? $responseData['data']['message']
                        ?? null;
        }
        
        // Case 4: First string value in the array (last resort)
        if (!$botResponse) {
            foreach ($responseData as $key => $val) {
                if (is_string($val) && !empty(trim($val)) && !in_array($key, ['sessionId', 'userId', 'timestamp', 'chatInput'])) {
                    $botResponse = trim($val);
                    break;
                }
            }
        }
    }

    if (empty($botResponse)) {
        // Include debug info so user can report what n8n returned
        $debugInfo = is_string($responseData) ? substr($responseData, 0, 200) : json_encode($responseData, JSON_UNESCAPED_UNICODE);
        @file_put_contents($debugLog, date('[Y-m-d H:i:s]') . " WARNING: Could not extract bot response. Data type: " . gettype($responseData) . "\n", FILE_APPEND);
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

    $decoded = json_decode($response, true);
    
    // Debug: log raw curl response
    $debugLog = __DIR__ . '/../logs/chatbot_debug.log';
    $debugDir = dirname($debugLog);
    if (!is_dir($debugDir)) @mkdir($debugDir, 0777, true);
    @file_put_contents($debugLog, date('[Y-m-d H:i:s]') . " n8n HTTP $httpCode | URL: $url | Raw body: " . substr($response, 0, 500) . "\n", FILE_APPEND);

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'success' => false,
            'error' => "n8n returned HTTP $httpCode. Response: " . substr($response, 0, 300),
            'http_code' => $httpCode
        ];
    }
    
    return [
        'success' => true,
        'data' => $decoded !== null ? $decoded : $response,
        'http_code' => $httpCode
    ];
}

?>
