<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== REVIEWS & RATINGS ===== -->
    <section class="section" style="padding-top:100px;">
        <div class="section-container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">⭐ Customer Reviews</h2>
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
                <a href="cars.php" class="btn btn-primary" style="display:inline-block;width:auto;padding:12px 32px;">Browse Cars</a>
            </div>

            <!-- Review Cards (dynamic) -->
            <div class="review-grid" id="reviewGrid" style="display:none;"></div>
        </div>
    </section>

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>

    <!-- ===== REVIEWS PAGE JAVASCRIPT ===== -->
    <script>
        const BOOKINGS_API = '/api/bookings.php';

        document.addEventListener('DOMContentLoaded', loadReviews);

        async function loadReviews() {
            try {
                const res = await fetch(BOOKINGS_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get-reviews', limit: 50 })
                });
                const data = await res.json();
                document.getElementById('reviewsLoading').style.display = 'none';

                if (data.success && data.reviews && data.reviews.length > 0) {
                    renderReviewStats(data.stats);
                    renderReviewCards(data.reviews);
                    document.getElementById('reviewGrid').style.display = '';
                } else {
                    document.getElementById('reviewStatsSection').style.display = 'none';
                    document.getElementById('reviewsEmpty').style.display = 'block';
                }
            } catch (err) {
                console.error('Failed to load reviews:', err);
                document.getElementById('reviewsLoading').style.display = 'none';
                document.getElementById('reviewsEmpty').style.display = 'block';
            }
        }

        function renderReviewStats(stats) {
            if (!stats) return;
            const avg = parseFloat(stats.avg_rating) || 0;
            const total = parseInt(stats.total) || 0;

            document.getElementById('avgRatingNum').textContent = avg.toFixed(1);
            document.getElementById('avgRatingStars').textContent = '★'.repeat(Math.round(avg)) + '☆'.repeat(5 - Math.round(avg));
            document.getElementById('totalReviewsLabel').textContent = 'Based on ' + total + ' review' + (total !== 1 ? 's' : '');

            const barsContainer = document.getElementById('ratingBars');
            barsContainer.innerHTML = '';
            for (let i = 5; i >= 1; i--) {
                const count = parseInt(stats['stars_' + i]) || 0;
                const pct = total > 0 ? Math.round((count / total) * 100) : 0;
                barsContainer.innerHTML += `
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="width:60px;font-size:0.875rem;color:var(--gray-500);">${i} star${i > 1 ? 's' : ''}</span>
                        <div style="flex:1;height:8px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                            <div style="width:${pct}%;height:100%;background:var(--warning);border-radius:4px;transition:width 0.5s;"></div>
                        </div>
                        <span style="width:35px;font-size:0.813rem;color:var(--gray-500);">${pct}%</span>
                    </div>`;
            }
        }

        function renderReviewCards(reviews) {
            const grid = document.getElementById('reviewGrid');
            grid.innerHTML = reviews.map(r => {
                const name = r.full_name || 'Anonymous';
                const initials = name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
                const stars = '★'.repeat(r.rating) + '☆'.repeat(5 - r.rating);
                const trip = (r.brand || '') + ' ' + (r.model || '');
                const date = new Date(r.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                const avatarHtml = r.avatar_url
                    ? '<img src="' + r.avatar_url + '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" alt="' + name + '">'
                    : initials;

                return `<div class="review-card">
                    <div class="review-stars">${stars}</div>
                    <p class="review-text">"${escapeHtml(r.content)}"</p>
                    <div class="review-author">
                        <div class="review-avatar">${avatarHtml}</div>
                        <div class="review-author-info">
                            <div class="review-author-name">${escapeHtml(name)}</div>
                            <div class="review-author-trip">${escapeHtml(trip)} · ${date}</div>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function truncate(str, max) {
            return str && str.length > max ? str.substring(0, max) + '...' : (str || '');
        }
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
