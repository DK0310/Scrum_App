<?php
/**
 * N8N Local Connection Helper
 * Kết nối với n8n local instance
 */

class N8NConnector {
    private $baseUrl;
    private $apiKey;
    
    /**
     * Constructor
     * @param string $baseUrl - URL của n8n local (mặc định: http://localhost:5678)
     * @param string $apiKey - API key từ n8n (nếu có)
     */
    public function __construct($baseUrl = 'http://localhost:5678', $apiKey = null) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }
    
    /**
     * Set API Key
     */
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Gửi request đến n8n webhook
     * @param string $webhookPath - Path của webhook (ví dụ: /webhook/scrum-task)
     * @param array $data - Data để gửi
     * @param string $method - HTTP method (GET, POST, PUT, DELETE)
     * @return array - Response từ n8n
     */
    public function triggerWebhook($webhookPath, $data = [], $method = 'POST') {
        $url = $this->baseUrl . '/webhook/' . ltrim($webhookPath, '/');
        return $this->sendRequest($url, $data, $method);
    }
    
    /**
     * Gửi request đến n8n webhook test
     * @param string $webhookPath - Path của webhook
     * @param array $data - Data để gửi
     * @param string $method - HTTP method
     * @return array - Response từ n8n
     */
    public function triggerWebhookTest($webhookPath, $data = [], $method = 'POST') {
        $url = $this->baseUrl . '/webhook-test/' . ltrim($webhookPath, '/');
        return $this->sendRequest($url, $data, $method);
    }
    
    /**
     * Lấy danh sách workflows (cần API key)
     * @return array - Danh sách workflows
     */
    public function getWorkflows() {
        $url = $this->baseUrl . '/api/v1/workflows';
        return $this->sendRequest($url, [], 'GET', true);
    }
    
    /**
     * Kích hoạt workflow theo ID
     * @param string $workflowId - ID của workflow
     * @return array - Response
     */
    public function activateWorkflow($workflowId) {
        $url = $this->baseUrl . '/api/v1/workflows/' . $workflowId . '/activate';
        return $this->sendRequest($url, [], 'POST', true);
    }
    
    /**
     * Tắt workflow theo ID
     * @param string $workflowId - ID của workflow
     * @return array - Response
     */
    public function deactivateWorkflow($workflowId) {
        $url = $this->baseUrl . '/api/v1/workflows/' . $workflowId . '/deactivate';
        return $this->sendRequest($url, [], 'POST', true);
    }
    
    /**
     * Gửi HTTP request
     */
    private function sendRequest($url, $data = [], $method = 'POST', $useApiKey = false) {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        // Thêm API key nếu cần
        if ($useApiKey && $this->apiKey) {
            $headers[] = 'X-N8N-API-KEY: ' . $this->apiKey;
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'GET':
            default:
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => $error,
                'http_code' => $httpCode
            ];
        }
        
        $decodedResponse = json_decode($response, true);
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => $decodedResponse ?? $response
        ];
    }
    
    /**
     * Test kết nối n8n
     * @return bool - True nếu kết nối thành công
     */
    public function testConnection() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/healthz');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
}

// ============================================
// Ví dụ sử dụng
// ============================================

/*
// Khởi tạo connector
$n8n = new N8NConnector('http://localhost:5678');

// Hoặc với API key (lấy từ n8n Settings > API)
$n8n = new N8NConnector('http://localhost:5678', 'your-api-key-here');

// Test kết nối
if ($n8n->testConnection()) {
    echo "✅ Kết nối n8n thành công!";
} else {
    echo "❌ Không thể kết nối đến n8n";
}

// Gửi data đến webhook
$result = $n8n->triggerWebhook('scrum-task', [
    'action' => 'create_task',
    'task_name' => 'New Task',
    'priority' => 'high',
    'assigned_to' => 'user@example.com'
]);

if ($result['success']) {
    echo "Webhook triggered successfully!";
    print_r($result['data']);
} else {
    echo "Error: " . ($result['error'] ?? 'Unknown error');
}
*/
