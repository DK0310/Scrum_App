<?php
/**
 * Proxy for admin API
 * PHP built-in server runs from public/, so we need this proxy
 */

// For file uploads (hero slide image)
if (isset($_FILES['image'])) {
    $_GET['action'] = $_GET['action'] ?? $_POST['action'] ?? 'hero-slide-upload';
}

// For image serving via GET
if (isset($_GET['action']) && in_array($_GET['action'], ['hero-slide-image', 'hero-slides-public'])) {
    // Pass through directly
}

require_once __DIR__ . '/../../api/admin.php';
