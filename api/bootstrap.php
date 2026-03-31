<?php
if (!function_exists('api_init')) {
    function api_init(array $options = []): array
    {
        $allowOrigin = $options['allow_origin'] ?? '*';
        $contentType = $options['content_type'] ?? 'application/json';

        if (!headers_sent()) {
            header('Content-Type: ' . $contentType);
            header('Access-Control-Allow-Origin: ' . $allowOrigin);
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            exit(0);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        return $input;
    }
}

if (!function_exists('api_action')) {
    function api_action(array $input): string
    {
        return (string)($input['action'] ?? ($_GET['action'] ?? ''));
    }
}

if (!function_exists('api_require_auth')) {
    function api_require_auth(): void
    {
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            echo json_encode(['success' => false, 'message' => 'Authentication required.', 'require_login' => true]);
            exit;
        }
    }
}

if (!function_exists('api_json')) {
    function api_json(array $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json');
        }
        echo json_encode($payload);
    }
}
