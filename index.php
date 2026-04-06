<?php
session_start();
$title = "Private Hire - Premium Car Rental Platform";
$currentPage = 'home';

// Include environment loader
require_once __DIR__ . '/config/env.php';

// Include database connection
require_once __DIR__ . '/Database/db.php';
require_once __DIR__ . '/sql/PromotionRepository.php';

// Include n8n connector
require_once __DIR__ . '/api/n8n.php';

// Initialize N8N connector
$n8n = new N8NConnector(\EnvLoader::get('N8N_BASE_URL', 'http://localhost:5678'));

// Test n8n connection
$n8nConnected = $n8n->testConnection();

// Check login status
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$userRole = $_SESSION['role'] ?? 'user';

// Only redirect driver to driver dashboard
// Admin, Staff, User can all see the home page (index.php)
if ($isLoggedIn && $userRole === 'driver') {
    header('Location: /driver.php');
    exit;
}

$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') : null;

$promoRepo = new PromotionRepository($pdo);
$now = time();
$homePromotionCards = [];

try {
    $allPromotions = $promoRepo->listAll();
    $activePromotions = array_values(array_filter($allPromotions, static function (array $promo) use ($now): bool {
        if (empty($promo['is_active'])) {
            return false;
        }
        if (!empty($promo['starts_at']) && strtotime((string)$promo['starts_at']) > $now) {
            return false;
        }
        if (!empty($promo['expires_at']) && strtotime((string)$promo['expires_at']) < $now) {
            return false;
        }
        if (!empty($promo['max_uses']) && (int)$promo['total_used'] >= (int)$promo['max_uses']) {
            return false;
        }
        return true;
    }));

    $activePromotions = array_slice($activePromotions, 0, 3);
    $cardClasses = ['promo-weekend', 'promo-welcome', 'promo-longterm'];
    $pillClasses = ['', ' promo-pill-alt', ' promo-pill-neutral'];

    foreach ($activePromotions as $idx => $promo) {
        $discountType = strtolower((string)($promo['discount_type'] ?? 'percentage'));
        $discountValue = (float)($promo['discount_value'] ?? 0);
        $discountText = $discountType === 'percentage'
            ? (rtrim(rtrim(number_format($discountValue, 2, '.', ''), '0'), '.') . '% OFF')
            : ('£' . number_format($discountValue, 2) . ' OFF');

        $homePromotionCards[] = [
            'code' => strtoupper((string)($promo['code'] ?? '')),
            'title' => $discountText . ' ' . ((string)($promo['title'] ?: 'Special Offer')),
            'description' => (string)($promo['description'] ?? ''),
            'badge' => 'Limited Offer',
            'card_class' => $cardClasses[$idx % count($cardClasses)],
            'pill_class' => $pillClasses[$idx % count($pillClasses)],
        ];
    }
} catch (Throwable $e) {
    $homePromotionCards = [];
}

// ===== ROUTE HANDLING =====
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// /auth route - Auth page
if ($requestPath === '/auth' || $requestPath === '/auth/') {
    $title = "Sign In / Sign Up - Private Hire";
    include __DIR__ . '/templates/auth.html.php';
    exit;
}

// Load template
include __DIR__ . '/templates/index.html.php';
?>