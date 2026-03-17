<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'PrivateHire - Premium Car Rental Platform' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="base.css">
</head>
<body>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar" id="navbar">
        <div class="navbar-inner">
            <a href="index.php" class="navbar-brand">PrivateHire</a>

            <!-- ===== NAVBAR SEARCH BAR (center) ===== -->
            <div class="navbar-search-wrapper" id="navbarSearchWrapper">
                <div class="navbar-search-bar">
                    <span class="navbar-search-icon">🔍</span>
                    <input type="text" 
                           id="navbarSearchInput" 
                           class="navbar-search-input" 
                           placeholder="Search cars... e.g. Mercedes, BMW X5" 
                           autocomplete="off"
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    <button class="navbar-search-clear" id="navbarSearchClear" onclick="navbarClearSearch()" style="display:none;">✕</button>
                </div>
                <div class="navbar-suggestions" id="navbarSuggestions"></div>
            </div>

            <div class="navbar-actions">
                <button class="navbar-lang" onclick="toggleLanguageMenu()" id="langBtn">🌐 EN</button>
                
                <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
                    <button class="navbar-notification" onclick="toggleNotifications()" id="notifBtn">
                        🔔
                        <span class="notification-badge" id="notifCount" style="display:none;">0</span>
                    </button>
                    <a href="profile.php" class="navbar-profile-link" <?= ($currentPage ?? '') === 'profile' ? 'style="background:var(--primary-50);color:var(--primary);"' : '' ?>>👤 <?= htmlspecialchars($currentUser) ?></a>
                    <?php if (!in_array(($userRole ?? ''), ['admin', 'staff'])): ?>
                    <a href="orders.php" class="btn btn-outline btn-sm navbar-action-link" style="<?= ($currentPage ?? '') === 'orders' ? 'background:var(--primary);color:white;border-color:var(--primary);' : 'color:var(--primary);border-color:var(--primary);' ?>">📋 My Orders</a>
                    <?php endif; ?>
                    <button class="btn btn-danger btn-sm" onclick="logout()">Logout</button>
                <?php else: ?>
                    <button class="btn btn-outline btn-sm" onclick="showAuthModal('login'); return false;">Sign In</button>
                    <button class="btn btn-primary btn-sm" onclick="showAuthModal('register'); return false;">Sign Up</button>
                <?php endif; ?>

                <!-- Side Menu Toggle Button -->
                <button class="side-menu-toggle" onclick="toggleSideMenu()" id="sideMenuToggle" title="Menu">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Side Menu Overlay -->
    <div class="side-menu-overlay" id="sideMenuOverlay" onclick="closeSideMenu()"></div>

    <!-- ===== SIDE MENU (slides from right) ===== -->
    <aside class="side-menu" id="sideMenu">
        <div class="side-menu-header">
            <span class="side-menu-title">☰ Menu</span>
            <button class="side-menu-close" onclick="closeSideMenu()">✕</button>
        </div>
        <nav class="side-menu-nav">
            <a href="cars.php" class="side-menu-item <?= ($currentPage ?? '') === 'cars' ? 'active' : '' ?>">
                <span class="side-menu-icon">🚗</span> Cars
            </a>
            <?php if (!in_array(($userRole ?? ''), ['admin', 'staff'])): ?>
            <a href="booking.php?mode=minicab" class="side-menu-item <?= ($currentPage ?? '') === 'booking' ? 'active' : '' ?>">
                <span class="side-menu-icon">🚕</span> Book a Minicab
            </a>
            <?php endif; ?>
            <a href="index.php#how-it-works" class="side-menu-item <?= ($currentPage ?? '') === 'how-it-works' ? 'active' : '' ?>">
                <span class="side-menu-icon">📖</span> How It Works
            </a>
            <a href="promotions.php" class="side-menu-item <?= ($currentPage ?? '') === 'promotions' ? 'active' : '' ?>">
                <span class="side-menu-icon">🏷️</span> Promotions
            </a>
            <a href="community.php" class="side-menu-item <?= ($currentPage ?? '') === 'community' ? 'active' : '' ?>">
                <span class="side-menu-icon">👥</span> Community
            </a>
            <a href="membership.php" class="side-menu-item <?= ($currentPage ?? '') === 'membership' ? 'active' : '' ?>">
                <span class="side-menu-icon">⭐</span> Membership
            </a>
            <a href="support.php" class="side-menu-item <?= ($currentPage ?? '') === 'support' ? 'active' : '' ?>">
                <span class="side-menu-icon">💬</span> Support
            </a>
            <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
            <div class="side-menu-divider side-menu-mobile-only"></div>
            <a href="profile.php" class="side-menu-item side-menu-mobile-only <?= ($currentPage ?? '') === 'profile' ? 'active' : '' ?>">
                <span class="side-menu-icon">👤</span> My Profile
            </a>
            <?php if (!in_array(($userRole ?? ''), ['admin', 'staff'])): ?>
            <a href="orders.php" class="side-menu-item side-menu-mobile-only <?= ($currentPage ?? '') === 'orders' ? 'active' : '' ?>">
                <span class="side-menu-icon">📋</span> My Orders
            </a>
            <?php endif; ?>
            <?php endif; ?>
            <?php if (isset($isLoggedIn) && $isLoggedIn && ($userRole ?? '') === 'admin'): ?>
            <div class="side-menu-divider"></div>
            <a href="admin.php" class="side-menu-item side-menu-admin <?= ($currentPage ?? '') === 'admin' ? 'active' : '' ?>">
                <span class="side-menu-icon">⚙️</span> Admin Dashboard
            </a>
            <?php endif; ?>

            <?php if (isset($isLoggedIn) && $isLoggedIn && ($userRole ?? '') === 'staff'): ?>
            <div class="side-menu-divider"></div>
            <a href="staff.php" class="side-menu-item side-menu-staff <?= ($currentPage ?? '') === 'staff' ? 'active' : '' ?>">
                <span class="side-menu-icon">⚙️</span> Staff Dashboard
            </a>
            <?php endif; ?>

            <?php if (isset($isLoggedIn) && $isLoggedIn && ($userRole ?? '') === 'driver'): ?>
            <div class="side-menu-divider"></div>
            <a href="driver.php" class="side-menu-item side-menu-driver <?= ($currentPage ?? '') === 'driver' ? 'active' : '' ?>">
                <span class="side-menu-icon">🚗</span> Driver Dashboard
            </a>
            <?php endif; ?>

        </nav>
        <div class="side-menu-footer">
            <p>© <?= date('Y') ?> DriveNow</p>
        </div>
    </aside>

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
