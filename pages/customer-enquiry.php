<?php
session_start();

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/EnquiryRepository.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$userId = (string)($_SESSION['user_id'] ?? '');
$userRole = strtolower(trim((string)($_SESSION['role'] ?? 'user')));
$title = 'Customer Enquiry - Private Hire';
$currentPage = 'customer-enquiry';
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? 'User') : null;

$enquiryRepo = new EnquiryRepository($pdo);
try {
    $enquiryRepo->ensureSchema();
} catch (Throwable $e) {
    // Keep page reachable; API calls can surface runtime errors when needed.
}

require __DIR__ . '/../templates/customer-enquiry.html.php';
