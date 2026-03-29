<?php
session_start();
$title = 'Admin Dashboard - Private Hire';
$currentPage = 'admin';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$userRole = $_SESSION['role'] ?? '';
$currentUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? 'Admin';

if (!$isLoggedIn || $userRole !== 'admin') {
    http_response_code(403);
    $_SESSION['login_flash'] = [
        'type' => 'error',
        'message' => 'Admin access required.'
    ];
    header('Location: /');
    exit;
}

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';

require __DIR__ . '/../templates/admin.html.php';
