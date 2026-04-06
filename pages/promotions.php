<?php
session_start();
$title = 'Private Hire - Promotions & Deals';
$currentPage = 'promotions';

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/PromotionRepository.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? null) : null;
$userRole = $_SESSION['role'] ?? 'user';

$promoRepo = new PromotionRepository($pdo);
$promoStyles = ['', 'accent', 'dark'];
$now = time();

$promotionsRaw = $promoRepo->listAll();
$promotionsRaw = array_values(array_filter($promotionsRaw, static function (array $promo) use ($now): bool {
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

$promotions = array_map(static function (array $promo, int $index) use ($promoStyles): array {
    $discountType = strtolower((string)($promo['discount_type'] ?? 'percentage'));
    $discountValue = (float)($promo['discount_value'] ?? 0);
    $discountText = $discountType === 'percentage'
        ? (rtrim(rtrim(number_format($discountValue, 2, '.', ''), '0'), '.') . '% OFF')
        : ('£' . number_format($discountValue, 2) . ' OFF');

    return [
        'code' => strtoupper((string)($promo['code'] ?? '')),
        'discount' => $discountText,
        'title' => (string)($promo['title'] ?: strtoupper((string)($promo['code'] ?? ''))),
        'description' => (string)($promo['description'] ?? ''),
        'style' => $promoStyles[$index % count($promoStyles)],
    ];
}, $promotionsRaw, array_keys($promotionsRaw));

require __DIR__ . '/../templates/promotions.html.php';
