<?php
/**
 * Admin Dashboard - Private Hire
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

// Check if user is admin
require_once __DIR__ . '/Database/db.php';
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/sql/UserRepository.php';

$userRepo = new UserRepository($pdo);
$userRole = $userRepo->getUserRole($_SESSION['user_id']);

if (!$userRole || $userRole !== 'admin') {
    http_response_code(403);
    header('Location: /index.php');
    exit;
}

$title = 'Admin Dashboard - Private Hire';
$currentPage = 'admin';

require __DIR__ . '/templates/admin.html.php';
