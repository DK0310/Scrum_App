<?php
/**
 * Proxy for auth API
 * PHP built-in server runs from public/, so we need this proxy
 */

// For avatar uploads (multipart form)
if (isset($_FILES['avatar'])) {
    $_GET['action'] = $_GET['action'] ?? $_POST['action'] ?? 'upload-avatar';
}

// For avatar image serving via GET
if (isset($_GET['action']) && $_GET['action'] === 'get-avatar') {
    // Pass through — the main API handles binary output
}

require_once __DIR__ . '/../../api/auth.php';
