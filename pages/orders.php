<?php
session_start();
$title = 'Private Hire - My Orders';
$currentPage = 'orders';

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? null) : null;
$userRole = $_SESSION['role'] ?? 'user';

if (!$isLoggedIn) {
    $_SESSION['login_flash'] = [
        'type' => 'error',
        'message' => 'Please sign in to view your orders.'
    ];
    header('Location: /');
    exit;
}

require __DIR__ . '/../templates/orders.html.php';
