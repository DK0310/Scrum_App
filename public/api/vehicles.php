<?php
/**
 * Proxy for vehicles API
 * PHP built-in server runs from public/, so we need this proxy
 */

// For file uploads, don't parse JSON input
if (isset($_FILES['image'])) {
    $_GET['action'] = $_GET['action'] ?? $_POST['action'] ?? 'upload-image';
}

// For image serving via GET (get-image action)
if (isset($_GET['action']) && $_GET['action'] === 'get-image') {
    // Pass through directly — the main API handles binary output
}

require_once __DIR__ . '/../../api/vehicles.php';
