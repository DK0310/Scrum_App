/**
 * Orders Module - Booking management and order tracking
 * Handles order listing, filtering, status updates, and reviews
 */

// ===== CONSTANTS & CONFIGURATION =====
const BOOKINGS_API = '/api/bookings.php';
const VEHICLES_API = '/api/vehicles.php';

// ===== STATE MANAGEMENT =====
let allOrders = [];
let currentFilter = 'all';
let USER_ROLE = ''; // Will be set from PHP

// ===== REVIEW MODAL STATE =====
let reviewBookingId = null;
let reviewRating = 0;

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', loadOrders);

// ===== LOAD ORDERS =====
async function loadOrders() {
    document.getElementById('ordersLoading').style.display = 'block';
    document.getElementById('ordersEmpty').style.display = 'none';
    document.getElementById('ordersList').style.display = 'none';

    try {
        const res = await fetch(BOOKINGS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'my-orders' })
        });
        const text = await res.text();
        console.log('Orders API raw response:', text);
        let data;
        try { data = JSON.parse(text); } catch(e) { console.error('JSON parse error:', e, text); throw e; }

        document.getElementById('ordersLoading').style.display = 'none';

        if (data.success && data.orders && data.orders.length > 0) {
            allOrders = data.orders;
            renderOrders(allOrders);
            document.getElementById('ordersList').style.display = 'block';
        } else {
            document.getElementById('ordersEmpty').style.display = 'block';
        }
    } catch (err) {
        console.error('Failed to load orders:', err);
        document.getElementById('ordersLoading').style.display = 'none';
        document.getElementById('ordersEmpty').style.display = 'block';
    }
}

// ===== FILTER ORDERS BY STATUS =====
function filterOrders(status) {
    currentFilter = status;
    document.querySelectorAll('.order-tab').forEach(t => t.classList.toggle('active', t.dataset.status === status));

    const filtered = status === 'all' ? allOrders : allOrders.filter(o => o.status === status);
    if (filtered.length === 0) {
        document.getElementById('ordersList').innerHTML = '<div style="text-align:center;padding:40px 0;color:var(--gray-400);">No orders with this status.</div>';
    } else {
        renderOrders(filtered);
    }
}

// ===== RENDER ORDER CARDS =====
function renderOrders(orders) {
    const container = document.getElementById('ordersList');
    container.innerHTML = orders.map(order => {
        const statusLabels = {
            pending: '⏳ Pending',
            confirmed: '✅ Confirmed',
            in_progress: '🚗 In Progress',
            completed: '✔️ Completed',
            cancelled: '❌ Cancelled'
        };
        const typeLabels = {
            'minicab': 'Minicab',
            'with-driver': 'With Driver',
            'self-drive': 'Self-Drive'
        };
        const pmLabels = {
            cash: '💵 Cash',
            bank_transfer: '🏦 Banking',
            paypal: '🅿️ PayPal',
            credit_card: '💳 Card'
        };

        const carName = (order.brand || '') + ' ' + (order.model || '');
        const thumbUrl = order.thumbnail_url || '';
        const thumbHtml = thumbUrl
            ? '<img src="' + thumbUrl + '" alt="' + carName + '">'
            : '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--gray-400);font-size:0.7rem;">No Photo</div>';

        let actionsHtml = '';

        // Owner actions: Confirm → Delivery (in_progress) → Done (completed)
        if (USER_ROLE === 'owner' || USER_ROLE === 'admin') {
            if (order.is_owner) {
                if (order.status === 'pending') {
                    actionsHtml = '<button class="btn btn-primary btn-sm" onclick="updateOrderStatus(\'' + order.id + '\', \'confirmed\')">✅ Confirm</button>'
                        + '<button class="btn btn-danger btn-sm" onclick="updateOrderStatus(\'' + order.id + '\', \'cancelled\')">❌ Cancel</button>';
                } else if (order.status === 'confirmed') {
                    actionsHtml = '<button class="btn btn-primary btn-sm" onclick="updateOrderStatus(\'' + order.id + '\', \'in_progress\')">🚗 Start Delivery</button>';
                } else if (order.status === 'in_progress') {
                    actionsHtml = '<button class="btn btn-primary btn-sm" onclick="updateOrderStatus(\'' + order.id + '\', \'completed\')">✔️ Mark Done</button>';
                }
            }
        }

        // Renter: can cancel if pending
        if (order.is_renter && order.status === 'pending') {
            actionsHtml = '<button class="btn btn-danger btn-sm" onclick="updateOrderStatus(\'' + order.id + '\', \'cancelled\')">❌ Cancel</button>';
        }

        // Renter: can review if completed and not yet reviewed
        if (order.is_renter && order.status === 'completed' && !order.review_id) {
            actionsHtml += '<button class="btn btn-primary btn-sm" onclick="openReviewModal(\'' + order.id + '\', \'' + (order.brand + ' ' + order.model).replace(/'/g, "\\'") + '\')">⭐ Rate & Review</button>';
        }
        // Show "Reviewed" badge if already reviewed
        if (order.is_renter && order.status === 'completed' && order.review_id) {
            actionsHtml += '<span style="display:inline-flex;align-items:center;gap:4px;padding:6px 14px;border-radius:999px;font-size:0.78rem;font-weight:700;background:#dcfce7;color:#166534;">⭐ Reviewed (' + order.review_rating + '/5)</span>';
        }

        // Renter info for owner view
        let renterInfoHtml = '';
        if (order.is_owner && order.renter_name) {
            renterInfoHtml = '<div class="owner-renter-info">👤 Renter: <span>' + order.renter_name + '</span> — ' + (order.renter_email || '') + '</div>';
        }

        return '<div class="order-card" data-status="' + order.status + '">' +
            '<div class="order-card-header">' +
                '<div class="order-card-left">' +
                    '<div class="order-car-thumb">' + thumbHtml + '</div>' +
                    '<div class="order-car-info">' +
                        '<h4>' + carName + '</h4>' +
                        '<p>' + (typeLabels[order.booking_type] || order.booking_type) + (order.service_type ? ' · ' + order.service_type.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : '') + ' · Order #' + order.id.substring(0, 8) + '</p>' +
                    '</div>' +
                '</div>' +
                '<div class="order-status-badge status-' + order.status + '">' + (statusLabels[order.status] || order.status) + '</div>' +
            '</div>' +
            renterInfoHtml +
            '<div class="order-card-body">' +
                '<div class="order-detail-item"><div class="order-detail-label">Pick-up Date</div><div class="order-detail-value">' + formatDate(order.pickup_date) + '</div></div>' +
                (order.return_date ? '<div class="order-detail-item"><div class="order-detail-label">Return Date</div><div class="order-detail-value">' + formatDate(order.return_date) + '</div></div>' : '') +
                '<div class="order-detail-item"><div class="order-detail-label">Pick-up Location</div><div class="order-detail-value">' + truncate(order.pickup_location || '-', 40) + '</div></div>' +
                (order.return_location && order.booking_type === 'minicab' ? '<div class="order-detail-item"><div class="order-detail-label">Destination</div><div class="order-detail-value">' + truncate(order.return_location || '-', 40) + '</div></div>' : '') +
                (order.distance_km ? '<div class="order-detail-item"><div class="order-detail-label">📏 Distance</div><div class="order-detail-value">' + parseFloat(order.distance_km).toFixed(1) + ' km</div></div>' : '') +
                '<div class="order-detail-item"><div class="order-detail-label">Payment</div><div class="order-detail-value">' + (pmLabels[order.payment_method] || order.payment_method || 'N/A') + '</div></div>' +
                '<div class="order-detail-item"><div class="order-detail-label">Booked On</div><div class="order-detail-value">' + formatDate(order.created_at) + '</div></div>' +
            '</div>' +
            '<div class="order-card-footer">' +
                '<div class="order-total">$' + parseFloat(order.total_amount).toFixed(2) + '</div>' +
                '<div class="order-actions">' + actionsHtml + '</div>' +
            '</div>' +
        '</div>';
    }).join('');
}

// ===== UPDATE ORDER STATUS =====
async function updateOrderStatus(bookingId, newStatus) {
    const labels = { confirmed: 'confirm', in_progress: 'start delivery for', completed: 'mark as done', cancelled: 'cancel' };
    if (!confirm('Are you sure you want to ' + (labels[newStatus] || newStatus) + ' this order?')) return;

    try {
        const res = await fetch(BOOKINGS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update-status', booking_id: bookingId, status: newStatus })
        });
        const data = await res.json();
        if (data.success) {
            showToast('✅ Order updated!', 'success');
            loadOrders();
        } else {
            showToast('❌ ' + (data.message || 'Failed to update.'), 'error');
        }
    } catch (err) {
        showToast('Connection error. Please try again.', 'error');
    }
}

// ===== DATE & STRING UTILITIES =====
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function truncate(str, max) {
    return str.length > max ? str.substring(0, max) + '...' : str;
}

// ===== REVIEW MODAL MANAGEMENT =====
function openReviewModal(bookingId, carName) {
    reviewBookingId = bookingId;
    reviewRating = 0;
    document.getElementById('reviewCarName').textContent = carName;
    document.getElementById('reviewContent').value = '';
    renderStars(0);
    document.getElementById('reviewModalOverlay').style.display = 'flex';
}

function closeReviewModal() {
    document.getElementById('reviewModalOverlay').style.display = 'none';
    reviewBookingId = null;
    reviewRating = 0;
}

function setReviewRating(rating) {
    reviewRating = rating;
    renderStars(rating);
}

function renderStars(rating) {
    const container = document.getElementById('reviewStarsInput');
    container.innerHTML = '';
    for (let i = 1; i <= 5; i++) {
        const star = document.createElement('span');
        star.textContent = i <= rating ? '★' : '☆';
        star.className = 'review-star-btn' + (i <= rating ? ' active' : '');
        star.onclick = () => setReviewRating(i);
        container.appendChild(star);
    }
}

// ===== SUBMIT ORDER REVIEW =====
async function submitOrderReview() {
    if (!reviewBookingId) return;
    if (reviewRating < 1) { showToast('Please select a rating (1-5 stars).', 'warning'); return; }
    const content = document.getElementById('reviewContent').value.trim();
    if (!content) { showToast('Please write your review.', 'warning'); return; }
    if (content.length < 10) { showToast('Review must be at least 10 characters.', 'warning'); return; }

    const btn = document.querySelector('#reviewModalOverlay .btn-primary');
    btn.disabled = true;
    btn.textContent = 'Submitting...';

    try {
        const res = await fetch(BOOKINGS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'submit-review',
                booking_id: reviewBookingId,
                rating: reviewRating,
                content: content
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast('⭐ ' + data.message, 'success');
            closeReviewModal();
            loadOrders(); // Refresh to show "Reviewed" badge
        } else {
            showToast('❌ ' + (data.message || 'Failed to submit review.'), 'error');
        }
    } catch (err) {
        console.error('Submit review error:', err);
        showToast('Connection error. Please try again.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '⭐ Submit Review';
    }
}
