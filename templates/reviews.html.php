<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== REVIEWS & RATINGS ===== -->
    <section class="section" style="padding-top:100px;">
        <div class="section-container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Customer Reviews</h2>
                    <p class="section-subtitle">See what our customers are saying about their experience</p>
                </div>
            </div>

            <!-- Review Stats (dynamic) -->
            <div style="display:grid;grid-template-columns:1fr 3fr;gap:40px;margin-bottom:40px;" id="reviewStatsSection">
                <div style="text-align:center;background:var(--gray-50);border-radius:var(--radius-md);padding:32px;">
                    <div style="font-size:3rem;font-weight:900;color:var(--gray-900);" id="avgRatingNum">-</div>
                    <div style="font-size:1.25rem;color:var(--warning);margin:8px 0;" id="avgRatingStars">☆☆☆☆☆</div>
                    <div style="color:var(--gray-500);font-size:0.875rem;" id="totalReviewsLabel">Based on 0 reviews</div>
                </div>
                <div style="display:flex;flex-direction:column;justify-content:center;gap:8px;" id="ratingBars">
                    <!-- Dynamically populated -->
                </div>
            </div>

            <!-- Loading -->
            <div id="reviewsLoading" style="text-align:center;padding:60px 0;">
                <div style="width:40px;height:40px;border:3px solid var(--gray-200);border-top-color:var(--primary);border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 16px;"></div>
                <p style="color:var(--gray-500);">Loading reviews...</p>
            </div>

            <!-- Empty State -->
            <div id="reviewsEmpty" style="display:none;text-align:center;padding:60px 0;">
                <div style="font-size:3rem;margin-bottom:16px;">📝</div>
                <h3 style="color:var(--gray-700);margin-bottom:8px;">No reviews yet</h3>
                <p style="color:var(--gray-500);margin-bottom:24px;">Be the first to leave a review after completing a booking!</p>
                <a href="/cars.php" class="btn btn-primary" style="display:inline-block;width:auto;padding:12px 32px;">Browse Cars</a>
            </div>

            <!-- Review Cards (dynamic) -->
            <div class="review-grid" id="reviewGrid" style="display:none;"></div>
        </div>
    </section>

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>

    <script src="/resources/js/reviews.js"></script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
