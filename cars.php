<?php
/**
 * Cars Page - Private Hire
 * Route controller for cars page view
 * Handles car listing, filtering, and search
 */
session_start();

// Include dependencies
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/Database/db.php';

// Page setup
$title = "Private Hire - Browse Cars";
$currentPage = 'cars';

// Check login status
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? null) : null;
$userRole = $_SESSION['role'] ?? 'user';

// Cars are loaded dynamically from database via vehicles API (JavaScript fetch)
// No server-side car data needed — the template uses client-side API calls

// Load template
require __DIR__ . '/templates/cars.html.php';
