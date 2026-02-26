<?php
/**
 * Quick test: Send a message to n8n and see what comes back
 */
require_once __DIR__ . '/config/env.php';

$url = EnvLoader::get('N8N_WEBHOOK_URL');
echo "N8N URL: $url\n\n";

$payload = [
    'chatInput' => 'Hello, how are you?',
    'sessionId' => 'test_debug_' . time()
];

echo "Sending: " . json_encode($payload) . "\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) echo "cURL Error: $error\n";

echo "\n--- RAW RESPONSE ---\n";
echo $response;
echo "\n--- END RAW ---\n\n";

$decoded = json_decode($response, true);
echo "json_decode result type: " . gettype($decoded) . "\n";
echo "json_decode result:\n";
print_r($decoded);

// Test what the parser would find
echo "\n--- PARSER TEST ---\n";
$data = $decoded !== null ? $decoded : $response;

if (is_string($data)) {
    echo "Data is STRING: " . substr($data, 0, 200) . "\n";
} elseif (is_array($data)) {
    echo "Data is ARRAY with keys: " . implode(', ', array_keys($data)) . "\n";
    
    if (isset($data[0])) {
        echo "First element type: " . gettype($data[0]) . "\n";
        if (is_array($data[0])) {
            echo "First element keys: " . implode(', ', array_keys($data[0])) . "\n";
            print_r($data[0]);
        }
    }
    
    foreach (['output', 'response', 'text', 'message', 'content'] as $key) {
        if (isset($data[$key])) echo "Found '$key': " . substr($data[$key], 0, 100) . "\n";
    }
}
