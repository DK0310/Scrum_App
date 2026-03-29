<?php
session_start();
$title = 'My Profile - Private Hire';
$currentPage = 'profile';

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? 'User') : null;
$userRole = $_SESSION['role'] ?? 'user';

if (!$isLoggedIn) {
    $_SESSION['login_flash'] = [
        'type' => 'error',
        'message' => 'Please sign in to view your profile.'
    ];
    header('Location: /');
    exit;
}

require __DIR__ . '/../templates/profile.html.php';
