<?php
/**
 * Mem0 Memory Management Integration
 * Tích hợp Mem0 để lưu trữ và truy xuất thông tin người dùng
 */

class Mem0Manager {
    private $apiKey;
    private $baseUrl = 'https://api.mem0.ai/v1';
    private $userId;
    
    /**
     * Constructor
     * @param string $apiKey - API Key từ Mem0
     * @param string $userId - ID người dùng (dùng để phân biệt memories)
     */
    public function __construct($apiKey, $userId) {
        $this->apiKey = $apiKey;
        $this->userId = $userId;
    }
    
    /**
     * Thêm memory mới
     * @param string $message - Thông tin cần lưu
     * @param array $metadata - Metadata bổ sung
     * @return array - Response
     */
    public function addMemory($message, $metadata = []) {
        $payload = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ],
            'user_id' => $this->userId
        ];
        
        if (!empty($metadata)) {
            $payload['metadata'] = $metadata;
        }
        
        return $this->makeRequest('POST', '/memories', $payload);
    }
    
    /**
     * Tìm kiếm memories liên quan
     * @param string $query - Query tìm kiếm
     * @return array - Danh sách memories
     */
    public function searchMemories($query) {
        $payload = [
            'query' => $query,
            'user_id' => $this->userId
        ];
        
        return $this->makeRequest('POST', '/memories/search', $payload);
    }
    
    /**
     * Lấy tất cả memories của user
     * @return array - Danh sách memories
     */
    public function getMemories() {
        return $this->makeRequest('GET', '/memories?user_id=' . $this->userId);
    }
    
    /**
     * Cập nhật memory
     * @param string $memoryId - ID của memory
     * @param string $message - Nội dung cập nhật
     * @return array - Response
     */
    public function updateMemory($memoryId, $message) {
        $payload = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ]
        ];
        
        return $this->makeRequest('PUT', '/memories/' . $memoryId, $payload);
    }
    
    /**
     * Xóa memory
     * @param string $memoryId - ID của memory
     * @return array - Response
     */
    public function deleteMemory($memoryId) {
        return $this->makeRequest('DELETE', '/memories/' . $memoryId);
    }
    
    /**
     * Lấy context từ memories (để gửi đến LLM)
     * @param string $query - Query tìm kiếm context
     * @return string - Context formatted
     */
    public function getContextForLLM($query) {
        $searchResult = $this->searchMemories($query);
        
        if (!$searchResult['success'] || empty($searchResult['data']['memories'])) {
            return '';
        }
        
        $context = "=== User Background Information ===\n";
        foreach ($searchResult['data']['memories'] as $memory) {
            $context .= "- " . $memory['memory'] . "\n";
        }
        $context .= "===================================\n";
        
        return $context;
    }
    
    /**
     * Gửi HTTP request
     */
    private function makeRequest($method, $endpoint, $data = []) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
        
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
}

?>
