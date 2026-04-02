const REVIEWS_API = '/api/reviews.php';

document.addEventListener('DOMContentLoaded', loadReviews);

async function loadReviews() {
    try {
        const res = await fetch(REVIEWS_API, {
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
        barsContainer.innerHTML +=
            '<div style="display:flex;align-items:center;gap:12px;">' +
                '<span style="width:60px;font-size:0.875rem;color:var(--gray-500);">' + i + ' star' + (i > 1 ? 's' : '') + '</span>' +
                '<div style="flex:1;height:8px;background:var(--gray-200);border-radius:4px;overflow:hidden;">' +
                    '<div style="width:' + pct + '%;height:100%;background:var(--warning);border-radius:4px;transition:width 0.5s;"></div>' +
                '</div>' +
                '<span style="width:35px;font-size:0.813rem;color:var(--gray-500);">' + pct + '%</span>' +
            '</div>';
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

        const reviewText = (r.content ?? r.comment ?? '');

        return '<div class="review-card">' +
            '<div class="review-stars">' + stars + '</div>' +
            '<p class="review-text">"' + escapeHtml(reviewText) + '"</p>' +
            '<div class="review-author">' +
                '<div class="review-avatar">' + avatarHtml + '</div>' +
                '<div class="review-author-info">' +
                    '<div class="review-author-name">' + escapeHtml(name) + '</div>' +
                    '<div class="review-author-trip">' + escapeHtml(trip) + ' · ' + date + '</div>' +
                '</div>' +
            '</div>' +
        '</div>';
    }).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}
