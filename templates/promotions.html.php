<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== PROMOTIONS ===== -->
    <section class="section" style="padding-top:100px;background:var(--gray-100);" id="promotions">
        <div class="section-container">
            <div class="section-header" style="justify-content:center;text-align:center;">
                <div>
                    <h2 class="section-title">🎉 Promotions & Deals</h2>
                    <p class="section-subtitle">Save big with our exclusive offers and promo codes</p>
                </div>
            </div>
            <div class="promo-grid">
                <?php foreach ($promotions as $promo): ?>
                <div class="promo-card <?= $promo['style'] ?>" onclick="applyPromo('<?= $promo['code'] ?>')">
                    <div class="promo-discount"><?= $promo['discount'] ?></div>
                    <div class="promo-title"><?= htmlspecialchars($promo['title']) ?></div>
                    <div class="promo-description"><?= htmlspecialchars($promo['description']) ?></div>
                    <div class="promo-code"><?= $promo['code'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===== PROMOTIONS PAGE JAVASCRIPT ===== -->
    <script>
        function applyPromo(code) {
            showToast('Promo code "' + code + '" copied! Redirecting to booking...', 'success');
            setTimeout(() => {
                window.location.href = 'booking.php?promo=' + encodeURIComponent(code);
            }, 1000);
        }
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
