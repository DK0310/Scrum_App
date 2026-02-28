<?php
/**
 * Proxy for community API
 * PHP built-in server runs from public/, so we need this proxy
 */

// For file uploads (multipart form), don't parse JSON
if (isset($_FILES['image'])) {
    $_GET['action'] = $_GET['action'] ?? $_POST['action'] ?? 'create-post';
}

// For image serving via GET
if (isset($_GET['action']) && $_GET['action'] === 'get-post-image') {
    // Pass through — the main API handles binary output
}

require_once __DIR__ . '/../../api/community.php';
