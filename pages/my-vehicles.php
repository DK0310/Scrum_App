<?php
session_start();
$title = 'Private Hire - My Vehicles';
$currentPage = 'my-vehicles';

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? null) : null;
$userRole = $_SESSION['role'] ?? 'user';

if (!$isLoggedIn) {
    $_SESSION['login_flash'] = [
        'type' => 'error',
        'message' => 'Please sign in to manage vehicles.'
    ];
    header('Location: /');
    exit;
}

if ($userRole !== 'staff' && $userRole !== 'admin') {
    header('Location: /');
    exit;
}

require __DIR__ . '/../templates/my-vehicles.html.php';
