<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

final class PayPalGateway
{
    private string $clientId;
    private string $secret;
    private string $baseUrl;
    private bool $mockEnabled;

    public function __construct()
    {
        $this->clientId = (string) EnvLoader::get('PAYPAL_CLIENT_ID', '');
        $this->secret = (string) EnvLoader::get('PAYPAL_SECRET', '');
        $this->baseUrl = (string) EnvLoader::get('PAYPAL_BASE_URL', 'https://api-m.sandbox.paypal.com');
        $this->mockEnabled = strtolower((string) EnvLoader::get('PAYPAL_MOCK_ENABLED', 'true')) === 'true';
    }

    public function createOrder(float $amount, string $currency, string $bookingId, string $returnUrl, string $cancelUrl): array
    {
        $amountValue = number_format(max(0.0, $amount), 2, '.', '');
        $currencyCode = strtoupper(trim($currency));

        if ($this->mockEnabled) {
            $orderId = 'MOCK-' . strtoupper(bin2hex(random_bytes(8)));
            $approvalUrl = $returnUrl . '&token=' . urlencode($orderId) . '&PayerID=MOCKPAYER';
            return [
                'success' => true,
                'order_id' => $orderId,
                'status' => 'CREATED',
                'approval_url' => $approvalUrl,
                'mock' => true,
                'raw' => [
                    'id' => $orderId,
                    'booking_id' => $bookingId,
                    'amount' => $amountValue,
                    'currency' => $currencyCode,
                ],
            ];
        }

        $token = $this->getAccessToken();
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $bookingId,
                'amount' => [
                    'currency_code' => $currencyCode,
                    'value' => $amountValue,
                ],
            ]],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'user_action' => 'PAY_NOW',
            ],
        ];

        $response = $this->request('POST', '/v2/checkout/orders', $token, $payload);
        $orderId = (string) ($response['id'] ?? '');
        $approvalUrl = '';

        if (!empty($response['links']) && is_array($response['links'])) {
            foreach ($response['links'] as $link) {
                if (($link['rel'] ?? '') === 'approve') {
                    $approvalUrl = (string) ($link['href'] ?? '');
                    break;
                }
            }
        }

        if ($orderId === '' || $approvalUrl === '') {
            return [
                'success' => false,
                'message' => 'Failed to create PayPal order.',
                'raw' => $response,
            ];
        }

        return [
            'success' => true,
            'order_id' => $orderId,
            'status' => (string) ($response['status'] ?? 'CREATED'),
            'approval_url' => $approvalUrl,
            'mock' => false,
            'raw' => $response,
        ];
    }

    public function captureOrder(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return ['success' => false, 'message' => 'Order ID is required.'];
        }

        if ($this->mockEnabled) {
            return [
                'success' => true,
                'order_id' => $orderId,
                'status' => 'COMPLETED',
                'capture_id' => 'MOCKCAP-' . strtoupper(bin2hex(random_bytes(6))),
                'mock' => true,
                'raw' => [
                    'id' => $orderId,
                    'status' => 'COMPLETED',
                ],
            ];
        }

        $token = $this->getAccessToken();
        $response = $this->request('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', $token, new stdClass());

        $status = (string) ($response['status'] ?? '');
        $captureId = '';

        if (!empty($response['purchase_units'][0]['payments']['captures'][0]['id'])) {
            $captureId = (string) $response['purchase_units'][0]['payments']['captures'][0]['id'];
        }

        if ($status !== 'COMPLETED') {
            return [
                'success' => false,
                'message' => 'PayPal capture failed.',
                'raw' => $response,
            ];
        }

        return [
            'success' => true,
            'order_id' => $orderId,
            'status' => $status,
            'capture_id' => $captureId,
            'mock' => false,
            'raw' => $response,
        ];
    }

    public function markCancelled(string $orderId): array
    {
        return [
            'success' => true,
            'order_id' => $orderId,
            'status' => 'CANCELLED',
            'mock' => $this->mockEnabled,
        ];
    }

    public function refundCapture(string $captureId, ?float $amount = null, string $currency = 'GBP'): array
    {
        $captureId = trim($captureId);
        if ($captureId === '') {
            return ['success' => false, 'message' => 'Capture ID is required for refund.'];
        }

        if ($this->mockEnabled) {
            return [
                'success' => true,
                'status' => 'COMPLETED',
                'refund_id' => 'MOCKREF-' . strtoupper(bin2hex(random_bytes(6))),
                'capture_id' => $captureId,
                'mock' => true,
                'raw' => [
                    'id' => 'MOCKREF-' . strtoupper(bin2hex(random_bytes(4))),
                    'status' => 'COMPLETED',
                    'capture_id' => $captureId,
                ],
            ];
        }

        $token = $this->getAccessToken();
        $payload = new stdClass();
        if ($amount !== null && $amount > 0) {
            $payload = [
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => strtoupper(trim($currency)),
                ],
            ];
        }

        $response = $this->request('POST', '/v2/payments/captures/' . rawurlencode($captureId) . '/refund', $token, $payload);
        $status = (string)($response['status'] ?? '');
        $refundId = (string)($response['id'] ?? '');

        if (!in_array($status, ['COMPLETED', 'PENDING'], true) || $refundId === '') {
            return [
                'success' => false,
                'message' => 'PayPal refund failed.',
                'raw' => $response,
            ];
        }

        return [
            'success' => true,
            'status' => $status,
            'refund_id' => $refundId,
            'capture_id' => $captureId,
            'mock' => false,
            'raw' => $response,
        ];
    }

    private function getAccessToken(): string
    {
        if ($this->clientId === '' || $this->secret === '') {
            throw new RuntimeException('PayPal credentials are missing.');
        }

        $ch = curl_init($this->baseUrl . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: en_US',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_USERPWD => $this->clientId . ':' . $this->secret,
            CURLOPT_TIMEOUT => 20,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            throw new RuntimeException('Failed to connect to PayPal OAuth endpoint.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || $httpCode >= 400 || empty($data['access_token'])) {
            throw new RuntimeException('Failed to obtain PayPal access token.');
        }

        return (string) $data['access_token'];
    }

    private function request(string $method, string $path, string $token, $payload = null): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];

        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            throw new RuntimeException('Failed to connect to PayPal API.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid PayPal API response.');
        }

        if ($httpCode >= 400) {
            return [
                'error' => true,
                'status_code' => $httpCode,
                'details' => $data,
            ];
        }

        return $data;
    }
}
