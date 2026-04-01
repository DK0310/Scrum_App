<?php
$sessionData = isset($_SESSION) && is_array($_SESSION) ? $_SESSION : [];
$sessionLoggedIn = isset($sessionData['logged_in']) && $sessionData['logged_in'];
$isLoggedIn = isset($isLoggedIn) ? (bool)$isLoggedIn : $sessionLoggedIn;

$userRole = $userRole ?? ($sessionData['role'] ?? 'user');
$normalizedRole = strtolower(str_replace(['-', ' ', '_'], '', (string)$userRole));
$normalizedRole = $normalizedRole === 'staff' ? 'controlstaff' : $normalizedRole;
$isAdminRole = ($normalizedRole === 'admin');
$isControlStaffRole = ($normalizedRole === 'controlstaff');
$isCallCenterStaffRole = ($normalizedRole === 'callcenterstaff');
$isDriverRole = ($normalizedRole === 'driver');
$isAnyStaffRole = $isControlStaffRole || $isCallCenterStaffRole;
$sessionRoleNormalized = strtolower(str_replace(['-', ' ', '_'], '', (string)($sessionData['role'] ?? '')));
$showMyOrders = $isLoggedIn
    && !$isAdminRole
    && !$isAnyStaffRole
    && !$isDriverRole
    && $sessionRoleNormalized !== 'driver';
$currentUser = $currentUser ?? ($sessionData['full_name'] ?? $sessionData['username'] ?? $sessionData['email'] ?? 'User');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'PrivateHire - Premium Car Rental Platform' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/resources/css/base.css">
</head>
<body>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar" id="navbar">
        <div class="navbar-inner">
            <a href="/" class="navbar-brand">PrivateHire</a>

            <div class="navbar-nav" <?= $isDriverRole ? 'style="display:none;"' : '' ?>>
                <a href="/cars.php" class="navbar-nav-link <?= ($currentPage ?? '') === 'cars' ? 'active' : '' ?>">Cars</a>
                <?php if (!$isAdminRole && !$isAnyStaffRole && !$isDriverRole): ?>
                <a href="/booking.php?mode=minicab" class="navbar-nav-link <?= ($currentPage ?? '') === 'booking' ? 'active' : '' ?>">Book a Minicab</a>
                <?php endif; ?>
                <a href="/#how-it-works" class="navbar-nav-link <?= ($currentPage ?? '') === 'how-it-works' ? 'active' : '' ?>">How It Works</a>
                <a href="/promotions.php" class="navbar-nav-link <?= ($currentPage ?? '') === 'promotions' ? 'active' : '' ?>">Promotions</a>
                <a href="/customer-enquiry.php" class="navbar-nav-link <?= ($currentPage ?? '') === 'customer-enquiry' ? 'active' : '' ?>">Customer Enquiry</a>
                <a href="/membership.php" class="navbar-nav-link <?= ($currentPage ?? '') === 'membership' ? 'active' : '' ?>">Membership</a>
                <a href="/support.php" class="navbar-nav-link <?= ($currentPage ?? '') === 'support' ? 'active' : '' ?>">Support</a>
            </div>

            <div class="navbar-actions">
                <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
                    <button class="navbar-notification" onclick="toggleNotifications()" id="notifBtn">
                        🔔
                        <span class="notification-badge" id="notifCount" style="display:none;">0</span>
                    </button>
                    <a href="/profile.php" class="navbar-profile-link" <?= ($currentPage ?? '') === 'profile' ? 'style="background:var(--primary-50);color:var(--primary);"' : '' ?>>👤 <?= htmlspecialchars($currentUser) ?></a>
                    <?php if ($showMyOrders): ?>
                    <a href="/orders.php" class="btn btn-outline btn-sm navbar-action-link" style="<?= ($currentPage ?? '') === 'orders' ? 'background:var(--primary);color:white;border-color:var(--primary);' : 'color:var(--primary);border-color:var(--primary);' ?>">📋 My Orders</a>
                    <?php endif; ?>
                    
                    <!-- Dashboard Link by Role -->
                    <?php if ($isAdminRole): ?>
                    <a href="/admin.php" class="btn btn-primary btn-sm" style="<?= ($currentPage ?? '') === 'admin' ? 'background:var(--primary-dark);' : '' ?>">⚙️ Admin Dashboard</a>
                    <?php elseif ($isControlStaffRole): ?>
                    <a href="/control-staff.php" class="btn btn-primary btn-sm" style="<?= ($currentPage ?? '') === 'control-staff' ? 'background:var(--primary-dark);' : '' ?>">🧭 Control Staff</a>
                    <?php elseif ($isCallCenterStaffRole): ?>
                    <a href="/call-center-staff.php" class="btn btn-primary btn-sm" style="<?= ($currentPage ?? '') === 'call-center-staff' ? 'background:var(--primary-dark);' : '' ?>">📞 Call Center</a>
                    <?php endif; ?>
                    
                    <button class="btn btn-danger btn-sm" onclick="logout()">Logout</button>
                <?php else: ?>
                    <button class="btn btn-outline btn-sm" onclick="showAuthModal('login'); return false;">Sign In</button>
                    <button class="btn btn-primary btn-sm" onclick="showAuthModal('register'); return false;">Sign Up</button>
                <?php endif; ?>

                <!-- Side Menu Toggle Button -->
                <button class="side-menu-toggle" onclick="toggleSideMenu()" id="sideMenuToggle" title="Menu" <?= $isDriverRole ? 'style="display:none;"' : '' ?>>
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Side Menu Overlay -->
    <?php if (!$isDriverRole): ?>
    <div class="side-menu-overlay" id="sideMenuOverlay" onclick="closeSideMenu()"></div>

    <!-- ===== SIDE MENU (slides from right) ===== -->
    <aside class="side-menu" id="sideMenu">
        <div class="side-menu-header">
            <span class="side-menu-title">☰ Menu</span>
            <button class="side-menu-close" onclick="closeSideMenu()">✕</button>
        </div>
        <nav class="side-menu-nav">
            <a href="/cars.php" class="side-menu-item <?= ($currentPage ?? '') === 'cars' ? 'active' : '' ?>">
                <span class="side-menu-icon">🚗</span> Cars
            </a>
            <?php if (!$isAdminRole && !$isAnyStaffRole && !$isDriverRole): ?>
            <a href="/booking.php?mode=minicab" class="side-menu-item <?= ($currentPage ?? '') === 'booking' ? 'active' : '' ?>">
                <span class="side-menu-icon">🚕</span> Book a Minicab
            </a>
            <?php endif; ?>
            <a href="/#how-it-works" class="side-menu-item <?= ($currentPage ?? '') === 'how-it-works' ? 'active' : '' ?>">
                <span class="side-menu-icon">📖</span> How It Works
            </a>
            <a href="/promotions.php" class="side-menu-item <?= ($currentPage ?? '') === 'promotions' ? 'active' : '' ?>">
                <span class="side-menu-icon">🏷️</span> Promotions
            </a>
            <a href="/customer-enquiry.php" class="side-menu-item <?= ($currentPage ?? '') === 'customer-enquiry' ? 'active' : '' ?>">
                <span class="side-menu-icon">✉️</span> Customer Enquiry
            </a>
            <a href="/membership.php" class="side-menu-item <?= ($currentPage ?? '') === 'membership' ? 'active' : '' ?>">
                <span class="side-menu-icon">⭐</span> Membership
            </a>
            <a href="/support.php" class="side-menu-item <?= ($currentPage ?? '') === 'support' ? 'active' : '' ?>">
                <span class="side-menu-icon">💬</span> Support
            </a>
            <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
            <div class="side-menu-divider side-menu-mobile-only"></div>
            <a href="/profile.php" class="side-menu-item side-menu-mobile-only <?= ($currentPage ?? '') === 'profile' ? 'active' : '' ?>">
                <span class="side-menu-icon">👤</span> My Profile
            </a>
            <?php if ($showMyOrders): ?>
            <a href="/orders.php" class="side-menu-item side-menu-mobile-only <?= ($currentPage ?? '') === 'orders' ? 'active' : '' ?>">
                <span class="side-menu-icon">�</span> My Orders
            </a>
            <?php endif; ?>
            <?php endif; ?>
            <?php if (isset($isLoggedIn) && $isLoggedIn && $isAdminRole): ?>
            <div class="side-menu-divider"></div>
            <a href="/admin.php" class="side-menu-item side-menu-admin <?= ($currentPage ?? '') === 'admin' ? 'active' : '' ?>">
                <span class="side-menu-icon">⚙️</span> Admin Dashboard
            </a>
            <?php endif; ?>

            <?php if (isset($isLoggedIn) && $isLoggedIn && $isControlStaffRole): ?>
            <div class="side-menu-divider"></div>
            <a href="/control-staff.php" class="side-menu-item side-menu-staff <?= ($currentPage ?? '') === 'control-staff' ? 'active' : '' ?>">
                <span class="side-menu-icon">🧭</span> Control Staff
            </a>
            <?php endif; ?>

            <?php if (isset($isLoggedIn) && $isLoggedIn && $isCallCenterStaffRole): ?>
            <div class="side-menu-divider"></div>
            <a href="/call-center-staff.php" class="side-menu-item side-menu-staff <?= ($currentPage ?? '') === 'call-center-staff' ? 'active' : '' ?>">
                <span class="side-menu-icon">📞</span> Call Center Staff
            </a>
            <?php endif; ?>

            <?php if (isset($isLoggedIn) && $isLoggedIn && ($userRole ?? '') === 'driver'): ?>
            <div class="side-menu-divider"></div>
            <a href="/driver.php" class="side-menu-item side-menu-driver <?= ($currentPage ?? '') === 'driver' ? 'active' : '' ?>">
                <span class="side-menu-icon">🚗</span> Driver Dashboard
            </a>
            <?php endif; ?>

        </nav>
        <div class="side-menu-footer">
            <p>© <?= date('Y') ?> Private Hire</p>
        </div>
    </aside>
    <?php endif; ?>

    <!-- ===== NOTIFICATION PANEL ===== -->
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-panel-header">
            <span class="notification-panel-title">🔔 Notifications</span>
            <div style="display:flex;gap:4px;">
                <button class="btn btn-ghost btn-sm" onclick="markAllRead()">Mark all read</button>
                <button class="btn btn-ghost btn-sm" onclick="clearAllNotifications()" style="color:var(--danger);">Clear all</button>
            </div>
        </div>
        <ul class="notification-list" id="notificationList">
            <li class="notification-item" style="justify-content:center;color:var(--gray-400);font-size:0.85rem;">
                <span>Loading notifications...</span>
            </li>
        </ul>
    </div>
