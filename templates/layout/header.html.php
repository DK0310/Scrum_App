<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'DriveNow - Premium Car Rental Platform' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="base.css">
</head>
<body>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar" id="navbar">
        <div class="navbar-inner">
            <a href="index.php" class="navbar-brand">🚗 DriveNow</a>
            
            <ul class="navbar-nav" id="navMenu">
                <li><a href="cars.php" <?= ($currentPage ?? '') === 'cars' ? 'class="active"' : '' ?>>Cars</a></li>
                <li><a href="index.php#how-it-works" <?= ($currentPage ?? '') === 'how-it-works' ? 'class="active"' : '' ?>>How It Works</a></li>
                <li><a href="promotions.php" <?= ($currentPage ?? '') === 'promotions' ? 'class="active"' : '' ?>>Promotions</a></li>
                <li><a href="community.php" <?= ($currentPage ?? '') === 'community' ? 'class="active"' : '' ?>>Community</a></li>
                <li><a href="membership.php" <?= ($currentPage ?? '') === 'membership' ? 'class="active"' : '' ?>>Membership</a></li>
                <li><a href="support.php" <?= ($currentPage ?? '') === 'support' ? 'class="active"' : '' ?>>Support</a></li>
                <?php if (isset($isLoggedIn) && $isLoggedIn && ($userRole ?? '') === 'owner'): ?>
                <li><a href="my-vehicles.php" <?= ($currentPage ?? '') === 'my-vehicles' ? 'class="active"' : '' ?> style="color:var(--primary);font-weight:600;">🚗 My Vehicles</a></li>
                <?php endif; ?>
                <?php if (isset($isLoggedIn) && $isLoggedIn && ($userRole ?? '') === 'admin'): ?>
                <li><a href="admin.php" <?= ($currentPage ?? '') === 'admin' ? 'class="active"' : '' ?> style="color:#ef4444;font-weight:600;">⚙️ Admin</a></li>
                <?php endif; ?>
            </ul>

            <div class="navbar-actions">
                <button class="navbar-lang" onclick="toggleLanguageMenu()" id="langBtn">🌐 EN</button>
                
                <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
                    <button class="navbar-notification" onclick="toggleNotifications()" id="notifBtn">
                        🔔
                        <span class="notification-badge" id="notifCount">3</span>
                    </button>
                    <span style="font-weight:600;font-size:0.875rem;color:var(--gray-700)">👤 <?= htmlspecialchars($currentUser) ?></span>
                    <button class="btn btn-danger btn-sm" onclick="logout()">Logout</button>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline btn-sm">Sign In</a>
                    <a href="register.php" class="btn btn-primary btn-sm">Sign Up</a>
                <?php endif; ?>

                <button class="hamburger" onclick="toggleMobileMenu()" id="hamburger">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </nav>

    <!-- ===== NOTIFICATION PANEL ===== -->
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-panel-header">
            <span class="notification-panel-title">🔔 Notifications</span>
            <button class="btn btn-ghost btn-sm" onclick="markAllRead()">Mark all read</button>
        </div>
        <ul class="notification-list" id="notificationList">
            <li class="notification-item unread">
                <div class="notification-icon booking">📋</div>
                <div class="notification-content">
                    <div class="notification-title">Booking Confirmed</div>
                    <div class="notification-text">Your Toyota Camry booking for March 5 has been confirmed.</div>
                    <div class="notification-time">2 minutes ago</div>
                </div>
            </li>
            <li class="notification-item unread">
                <div class="notification-icon payment">💳</div>
                <div class="notification-content">
                    <div class="notification-title">Payment Received</div>
                    <div class="notification-text">$120.00 payment processed successfully.</div>
                    <div class="notification-time">1 hour ago</div>
                </div>
            </li>
            <li class="notification-item unread">
                <div class="notification-icon promo">🎉</div>
                <div class="notification-content">
                    <div class="notification-title">New Promotion!</div>
                    <div class="notification-text">Get 20% off on your next weekend rental. Code: WEEKEND20</div>
                    <div class="notification-time">3 hours ago</div>
                </div>
            </li>
            <li class="notification-item">
                <div class="notification-icon alert">📧</div>
                <div class="notification-content">
                    <div class="notification-title">Email Confirmed</div>
                    <div class="notification-text">Your email address has been verified.</div>
                    <div class="notification-time">1 day ago</div>
                </div>
            </li>
        </ul>
    </div>
