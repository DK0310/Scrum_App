/**
 * Orders Module - Booking management and order tracking
 * Handles order listing, filtering, status updates, and reviews
 */

// ===== CONSTANTS & CONFIGURATION =====
const BOOKINGS_API = '/api/orders.php';
const REVIEWS_API = '/api/reviews.php';
const VEHICLES_API = '/api/vehicles.php';
const PROFILE_API = '/api/profile.php';
const ORDER_STATUS_LABELS = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    in_progress: 'In Progress',
    completed: 'Completed',
    cancelled: 'Cancelled'
};
const ORDER_TYPE_LABELS = {
    minicab: 'Minicab',
    'with-driver': 'With Driver',
    'self-drive': 'Self-Drive'
};
const PAYMENT_METHOD_LABELS = {
    cash: 'Cash',
    bank_transfer: 'Bank Transfer',
    paypal: 'PayPal',
    credit_card: 'Card',
    account_balance: 'Account Balance'
};

// ===== STATE MANAGEMENT =====
let allOrders = [];
let currentFilter = 'all';
let USER_ROLE = ''; // Will be set from PHP

// ===== REVIEW MODAL STATE =====
let reviewBookingId = null;
let reviewRating = 0;
let modifyBookingId = null;
let modifyOriginalOrder = null;
let modifyAvailabilityMatrix = null;
let modifyPickupMapObj = null;
let modifyReturnMapObj = null;
let modifyPickupMarker = null;
let modifyReturnMarker = null;
let modifyAutocompleteTimers = {};
let modifySelectedAddresses = { pickup: null, return: null };
const ORDERS_MODAL_STACK = ['tripDetailModalOverlay', 'modifyBookingModalOverlay', 'reviewModalOverlay'];
const MODIFY_MINICAB_RATES_PER_MILE = {
    4: { eco: 2.0, standard: 2.5, luxury: 3.5 },
    7: { eco: 3.0, standard: 3.5, luxury: 4.5 }
};
const MODIFY_DAILY_HIRE_RATES = {
    4: { eco: 180.0, standard: 220.0, luxury: 300.0 },
    7: { eco: 220.0, standard: 270.0, luxury: 400.0 }
};

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', () => {
    initTripDetailModalEvents();
    initModifyLocationAutocomplete();
    initModifyAvailabilityHandlers();
    loadLoyaltyPoints();
    loadOrders();
});

function getOrdersModalOverlay(id) {
    return document.getElementById(id);
}

function isOrdersModalOpen(id) {
    const overlay = getOrdersModalOverlay(id);
    return !!(overlay && overlay.style.display === 'flex');
}

function updateOrdersModalBodyLock() {
    const hasOpenOverlay = ORDERS_MODAL_STACK.some(id => isOrdersModalOpen(id));
    document.body.classList.toggle('orders-modal-open', hasOpenOverlay);
}

function openOrdersModal(id) {
    const overlay = getOrdersModalOverlay(id);
    if (!overlay) return;
    overlay.style.display = 'flex';
    updateOrdersModalBodyLock();
}

function closeOrdersModal(id) {
    const overlay = getOrdersModalOverlay(id);
    if (!overlay) return;
    overlay.style.display = 'none';
    updateOrdersModalBodyLock();
}

function closeTopOrdersModal() {
    for (let i = ORDERS_MODAL_STACK.length - 1; i >= 0; i -= 1) {
        const modalId = ORDERS_MODAL_STACK[i];
        if (isOrdersModalOpen(modalId)) {
            closeOrdersModal(modalId);
            return true;
        }
    }
    return false;
}

function setOrdersLoyaltyPoints(points) {
    const el = document.getElementById('ordersLoyaltyPoints');
    if (!el) return;
    const safePoints = Math.max(0, parseInt(points, 10) || 0);
    el.textContent = safePoints.toLocaleString('en-GB') + ' Points';
}

async function loadLoyaltyPoints() {
    try {
        const res = await fetch(PROFILE_API + '?action=get-profile', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data && data.success && data.user) {
            setOrdersLoyaltyPoints(data.user.loyalty_point);
        }
    } catch (err) {
        console.error('Failed to load loyalty points:', err);
    }
}

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
    const cards = orders.map(order => {
        const statusUi = {
            pending: { label: 'Pending •' },
            confirmed: { label: 'Confirmed •' },
            in_progress: { label: 'In Progress ⟳' },
            completed: { label: 'Completed ✓' },
            cancelled: { label: 'Cancelled ×' }
        };
        const typeLabels = ORDER_TYPE_LABELS;

        const canOpenTripDetail = order.status !== 'cancelled';
        const cardClassName = canOpenTripDetail ? 'order-card can-open' : 'order-card';
        const cardAttrs = ' data-status="' + order.status + '" data-booking-id="' + order.id + '" data-can-open="' + (canOpenTripDetail ? '1' : '0') + '"';

        const shouldHideVehicle = (
            !!order.is_renter
            && order.booking_type === 'minicab'
            && (order.status === 'pending' || order.status === 'confirmed' || order.status === 'cancelled')
        );

        const displayVehicleName = shouldHideVehicle
            ? 'Vehicle will be assigned'
            : (((order.brand || '') + ' ' + (order.model || '')).trim() || 'Vehicle');
        const displayVehicleNameSafe = escapeHtml(displayVehicleName);
        const orderIdShort = '#' + String(order.id || '').substring(0, 8).toUpperCase();

        const bookingTypeLabel = typeLabels[order.booking_type] || order.booking_type || 'Trip';
        const serviceTypeLabel = formatServiceType(order.service_type);
        const cardSubtitle = serviceTypeLabel && serviceTypeLabel !== '-'
            ? bookingTypeLabel + ' - ' + serviceTypeLabel
            : bookingTypeLabel;
        const cardSubtitleSafe = escapeHtml(cardSubtitle);

        const pickupLabel = order.status === 'in_progress' ? 'Current Trip' : (order.status === 'pending' ? 'Pickup Time' : 'Scheduled');
        const pickupValue = order.status === 'in_progress'
            ? 'On route to destination'
            : formatPickupDate(order);
        const pickupValueSafe = escapeHtml(pickupValue || '-');

        const distanceText = order.distance_km
            ? parseFloat(order.distance_km).toFixed(1) + ' km'
            : '-';

        const totalAmountNumber = parseFloat(order.total_amount);
        const totalAmountText = Number.isFinite(totalAmountNumber) ? '£' + totalAmountNumber.toFixed(2) : '£0.00';
        const paymentLabel = PAYMENT_METHOD_LABELS[order.payment_method] || order.payment_method || 'N/A';

        const thumbUrl = shouldHideVehicle ? '' : (order.thumbnail_url || '');
        const thumbHtml = thumbUrl
            ? '<img src="' + thumbUrl + '" alt="' + displayVehicleNameSafe + '">'
            : '<img src="/resources/images/logo/logo.png" alt="PrivateHire logo" style="width:100%;height:100%;object-fit:cover;display:block;">';

        let actionsHtml = '';

        // Control staff actions: Confirm → Delivery (in_progress) → Done (completed)
        if (USER_ROLE === 'controlstaff' || USER_ROLE === 'admin' || USER_ROLE === 'owner') {
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

        // Renter: can modify and cancel only while order is pending and outside cutoff window
        if (order.is_renter && order.status === 'pending') {
            if (canCustomerModifyOrder(order)) {
                actionsHtml += '<button class="btn btn-outline btn-sm" onclick="openModifyBookingModal(\'' + order.id + '\')">✏️ Modify</button>';
            }
            if (canCustomerCancelOrder(order)) {
                actionsHtml += '<button class="btn btn-danger btn-sm" onclick="updateOrderStatus(\'' + order.id + '\', \'cancelled\')">❌ Cancel</button>';
            }
        }

        // Renter: can review if completed and not yet reviewed
        if (order.is_renter && order.status === 'completed' && !order.review_id) {
            actionsHtml += '<button class="btn btn-primary btn-sm" onclick="openReviewModal(\'' + order.id + '\', \'' + (((order.brand || '') + ' ' + (order.model || '')).trim() || 'this trip').replace(/'/g, "\\'") + '\')">⭐ Rate & Feedback (+ Loyalty Points)</button>';
        }
        // Show "Reviewed" badge if already reviewed
        if (order.is_renter && order.status === 'completed' && order.review_id) {
            actionsHtml += '<span style="display:inline-flex;align-items:center;gap:4px;padding:6px 14px;border-radius:999px;font-size:0.78rem;font-weight:700;background:#dcfce7;color:#166534;">⭐ Reviewed (' + order.review_rating + '/5)</span>';
        }

        // Renter info for owner view
        let renterInfoHtml = '';
        if (order.is_owner && order.renter_name) {
            renterInfoHtml = '<div class="owner-renter-info">👤 Renter: <span>' + escapeHtml(order.renter_name) + '</span> — ' + escapeHtml(order.renter_email || '') + '</div>';
        }

        const loyaltyPopoverHtml = (order.is_renter && order.status === 'completed' && !order.review_id)
            ? '<div class="order-loyalty-popover">Earn loyalty points after feedback</div>'
            : '';

        const footerTitle = order.status === 'cancelled'
            ? '<div class="order-detail-label">Refund Status</div><div class="order-total" style="font-size:1.18rem;color:#64748b;">Processed</div>'
            : '<div class="order-detail-label">Total Fare (' + escapeHtml(paymentLabel) + ')</div><div class="order-total">' + totalAmountText + '</div>';

        const detailRowsHtml =
            '<div class="order-detail-item"><div class="order-detail-label">' + pickupLabel + '</div><div class="order-detail-value">' + pickupValueSafe + '</div></div>' +
            '<div class="order-detail-item"><div class="order-detail-label">Distance</div><div class="order-detail-value">' + distanceText + '</div></div>' +
            '<div class="order-detail-item is-wide"><div class="order-detail-label">Pick-up Location</div><div class="order-detail-value">' + escapeHtml(truncate(order.pickup_location || '-', 70)) + '</div></div>' +
            (order.return_location ? '<div class="order-detail-item is-wide"><div class="order-detail-label">Destination</div><div class="order-detail-value">' + escapeHtml(truncate(order.return_location || '-', 70)) + '</div></div>' : '');

        return '<div class="' + cardClassName + ' status-' + order.status + '"' + cardAttrs + '>' +
            '<div class="order-card-header">' +
                '<div class="order-card-left">' +
                    '<div class="order-car-thumb">' + thumbHtml + '</div>' +
                    '<div class="order-car-info">' +
                        '<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px;">' +
                            '<span class="order-status-badge status-' + order.status + '">' + (statusUi[order.status]?.label || ORDER_STATUS_LABELS[order.status] || order.status) + '</span>' +
                            '<span style="font-size:0.74rem;color:#64748b;font-weight:700;">' + orderIdShort + '</span>' +
                        '</div>' +
                        '<h4>' + displayVehicleNameSafe + '</h4>' +
                        '<p>' + cardSubtitleSafe + '</p>' +
                        renterInfoHtml +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="order-card-body">' +
                detailRowsHtml +
            '</div>' +
            '<div class="order-card-footer">' +
                '<div>' + footerTitle + '</div>' +
                '<div class="order-actions" style="position:relative;">' + loyaltyPopoverHtml + actionsHtml + '</div>' +
            '</div>' +
        '</div>';
    }).join('');

    container.innerHTML = '<div class="orders-grid">' + cards + '</div>';

    bindOrderCardClicks();
}

function bindOrderCardClicks() {
    document.querySelectorAll('.order-card[data-can-open="1"]').forEach(card => {
        card.addEventListener('click', event => {
            if (event.target.closest('.order-actions') || event.target.closest('button') || event.target.closest('a')) {
                return;
            }
            openTripDetailModal(card.dataset.bookingId);
        });
    });
}

function initTripDetailModalEvents() {
    const overlay = document.getElementById('tripDetailModalOverlay');
    if (!overlay) return;

    overlay.addEventListener('click', event => {
        if (event.target === overlay) {
            closeTripDetailModal();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeTopOrdersModal();
        }
    });

    const modifyOverlay = document.getElementById('modifyBookingModalOverlay');
    if (modifyOverlay) {
        modifyOverlay.addEventListener('click', event => {
            if (event.target === modifyOverlay) {
                closeModifyBookingModal();
            }
        });
    }

    const reviewOverlay = document.getElementById('reviewModalOverlay');
    if (reviewOverlay) {
        reviewOverlay.addEventListener('click', event => {
            if (event.target === reviewOverlay) {
                closeReviewModal();
            }
        });
    }
}

function openTripDetailModal(bookingId) {
    const order = allOrders.find(o => String(o.id) === String(bookingId));
    const overlay = document.getElementById('tripDetailModalOverlay');
    if (!order || !overlay || order.status === 'cancelled') return;

    const totalAmount = Number(order.total_amount);

    setDetailField('tripDetailOrderId', '#' + String(order.id).substring(0, 8));
    setDetailField('tripDetailStatus', ORDER_STATUS_LABELS[order.status] || order.status || '-');
    setDetailField('tripDetailBookingType', ORDER_TYPE_LABELS[order.booking_type] || order.booking_type || '-');
    setDetailField('tripDetailServiceType', formatServiceType(order.service_type));
    setDetailField('tripDetailRideTier', formatRideTier(order.ride_tier));
    setDetailField('tripDetailSeatCapacity', formatSeatCapacity(order));
    setDetailField('tripDetailPickupDate', formatPickupDate(order));
    setDetailField('tripDetailReturnDate', order.return_date ? formatDate(order.return_date) : '-');
    setDetailField('tripDetailPickupLocation', order.pickup_location || '-');
    setDetailField('tripDetailDestination', order.return_location || '-');
    setDetailField('tripDetailDistance', order.distance_km ? parseFloat(order.distance_km).toFixed(1) + ' km' : '-');
    setDetailField('tripDetailPaymentMethod', PAYMENT_METHOD_LABELS[order.payment_method] || order.payment_method || 'N/A');
    setDetailField('tripDetailBookedOn', formatDate(order.created_at));
    setDetailField('tripDetailTotalAmount', Number.isFinite(totalAmount) ? '£' + totalAmount.toFixed(2) : '-');

    updateTripVehicleSpotlight(order);

    openOrdersModal('tripDetailModalOverlay');
}

function closeTripDetailModal() {
    closeOrdersModal('tripDetailModalOverlay');
}

function setDetailField(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value ?? '-';
    }
}

function formatServiceType(serviceType) {
    if (!serviceType) return '-';
    return serviceType.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatRideTier(rideTier) {
    if (!rideTier) return '-';
    const normalized = rideTier === 'premium' ? 'luxury' : rideTier;
    return normalized.replace(/\b\w/g, c => c.toUpperCase());
}

function formatSeatCapacity(order) {
    const seat = order && (order.seat_capacity || order.number_of_passengers);
    if (!seat) return '-';
    return String(seat) + ' seats';
}

function updateTripVehicleSpotlight(order) {
    const vehicleBox = document.getElementById('tripVehicleSpotlight');
    const waitingBox = document.getElementById('tripVehicleWaiting');
    if (!vehicleBox || !waitingBox) return;

    const isPending = String(order.status || '') === 'pending';
    const hasVehicleData = !!(order.brand || order.model || order.license_plate);

    if (isPending || !hasVehicleData) {
        vehicleBox.style.display = 'none';
        waitingBox.style.display = 'flex';
        return;
    }

    const vehicleName = (((order.brand || '') + ' ' + (order.model || '')).trim() || 'Assigned Vehicle');
    const licensePlate = (order.license_plate || '').trim() || 'Updating...';

    setDetailField('tripVehicleName', vehicleName);
    setDetailField('tripVehiclePlate', licensePlate);

    vehicleBox.style.display = 'flex';
    waitingBox.style.display = 'none';
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
        const raw = await res.text();
        let data;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            throw new Error(raw || 'Invalid response from server');
        }
        if (data.success) {
            showToast('✅ Order updated!', 'success');
            
            // If booking is completed, refresh available vehicles so they display immediately
            if (newStatus === 'completed' && data.vehicle_id && data.vehicle_status === 'available') {
                // Broadcast update to any vehicle list displays
                window.dispatchEvent(new CustomEvent('vehicleAvailabilityUpdated', {
                    detail: {
                        vehicle_id: data.vehicle_id,
                        status: data.vehicle_status,
                        booking_id: data.booking_id
                    }
                }));
                
                // Reload vehicle list on cars page if it exists
                if (typeof window.loadCars === 'function') {
                    setTimeout(() => window.loadCars(), 300);
                }
                
                // Reload home page vehicle display if it exists
                if (typeof window.loadHomeVehicles === 'function') {
                    setTimeout(() => window.loadHomeVehicles(), 300);
                }
            }
            
            loadOrders();
        } else {
            showToast('❌ ' + (data.message || 'Failed to update.'), 'error');
        }
    } catch (err) {
        showToast((err && err.message) ? err.message : 'Connection error. Please try again.', 'error');
    }
}

// ===== DATE & STRING UTILITIES =====
function formatDate(dateStr) {
    if (!dateStr) return '-';
    try {
        // Parse as UTC by appending Z if not already present
        const utcStr = dateStr.includes('Z') || dateStr.includes('+') || dateStr.includes('-00:00') ? dateStr : dateStr + 'Z';
        const d = new Date(utcStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch (err) {
        return dateStr;
    }
}

function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    try {
        // Parse as UTC by appending Z if not already present
        const utcStr = dateStr.includes('Z') || dateStr.includes('+') || dateStr.includes('-00:00') ? dateStr : dateStr + 'Z';
        const d = new Date(utcStr);
        if (isNaN(d.getTime())) return dateStr;
        
        // Format in user's local timezone (no specific timezone forced)
        const formatter = new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
        return formatter.format(d);
    } catch (err) {
        return dateStr;
    }
}

function formatPickupTimeDisplay(timeValue) {
    const raw = String(timeValue || '').trim();
    if (!raw) return '-';

    const m = raw.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/i);
    if (!m) return raw;

    let hour = parseInt(m[1], 10);
    const minute = String(m[2] || '00').padStart(2, '0');
    const meridiem = String(m[3] || '').toUpperCase();

    if (meridiem === 'PM' && hour < 12) hour += 12;
    if (meridiem === 'AM' && hour === 12) hour = 0;

    const ampm = hour >= 12 ? 'PM' : 'AM';
    let displayHour = hour % 12;
    if (displayHour === 0) displayHour = 12;

    return String(displayHour).padStart(2, '0') + ':' + minute + ampm;
}

function formatPickupDate(order) {
    if (!order || !order.pickup_date) return '-';
    if (order.booking_type === 'minicab') {
        // If pickup_time is available, combine date with the correct time
        if (order.pickup_time) {
            const dateOnly = formatDate(order.pickup_date); // e.g., "27 Mar 2026"
            return dateOnly + ', ' + formatPickupTimeDisplay(order.pickup_time); // e.g., "27 Mar 2026, 10:00AM"
        }
        return formatDateTime(order.pickup_date);
    }
    return formatDate(order.pickup_date);
}

function canCustomerCancelOrder(order) {
    if (!order || !order.is_renter || order.status !== 'pending') return false;
    return hasAtLeast24HoursBeforePickup(order);
}

function canCustomerModifyOrder(order) {
    if (!order || !order.is_renter || order.status !== 'pending') return false;
    const method = normalizeOrderPaymentMethod(order);
    if (method !== 'cash') {
        return false;
    }
    return hasAtLeast24HoursBeforePickup(order);
}

function normalizeOrderPaymentMethod(order) {
    const method = String(order?.payment_method || '').trim().toLowerCase();
    if (!method) return 'cash';
    return method;
}

function hasAtLeast24HoursBeforePickup(order) {
    const pickupAt = parseOrderPickupDateTime(order);
    if (!pickupAt) return false;
    const diffMs = pickupAt.getTime() - Date.now();
    return diffMs >= 24 * 60 * 60 * 1000;
}

function parseOrderPickupDateTime(order) {
    if (!order || !order.pickup_date) return null;

    const pickupDate = String(order.pickup_date).trim();
    const pickupTime = String(order.pickup_time || '').trim();

    const direct = new Date(pickupDate);
    if (!Number.isNaN(direct.getTime()) && pickupDate.includes('T')) {
        return direct;
    }

    if (pickupTime) {
        const m = pickupTime.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)?$/i);
        if (m) {
            let hour = parseInt(m[1], 10);
            const minute = parseInt(m[2], 10);
            const meridiem = (m[3] || '').toUpperCase();

            if (meridiem === 'PM' && hour < 12) hour += 12;
            if (meridiem === 'AM' && hour === 12) hour = 0;

            const dt = new Date(pickupDate + 'T00:00:00');
            if (!Number.isNaN(dt.getTime())) {
                dt.setHours(hour, minute, 0, 0);
                return dt;
            }
        }
    }

    const dateOnly = new Date(pickupDate + 'T00:00:00');
    if (Number.isNaN(dateOnly.getTime())) return null;
    return dateOnly;
}

function getAvailabilityCountFor(tier, seats) {
    const safeTier = String(tier || '').toLowerCase();
    const safeSeats = String(parseInt(seats, 10) || 0);
    if (!modifyAvailabilityMatrix || !modifyAvailabilityMatrix[safeTier]) return 0;
    return parseInt(modifyAvailabilityMatrix[safeTier][safeSeats] || 0, 10);
}

function isModifyDailyHireService(serviceType) {
    return String(serviceType || '').toLowerCase() === 'daily-hire';
}

function buildModifyAvailabilityPayload() {
    const payload = { action: 'tier-seat-availability' };
    const serviceType = String(document.getElementById('modifyServiceType')?.value || (modifyOriginalOrder && modifyOriginalOrder.service_type) || 'local').toLowerCase();
    const pickupDateTime = String(document.getElementById('modifyPickupDateTime')?.value || '').trim();

    payload.service_type = serviceType;
    if (pickupDateTime) {
        payload.pickup_datetime = pickupDateTime;
    }

    if (!isModifyDailyHireService(serviceType)) {
        let distanceKm = null;
        const pickupCoords = modifySelectedAddresses.pickup;
        const returnCoords = modifySelectedAddresses.return;
        if (pickupCoords && returnCoords) {
            distanceKm = haversineDistanceKm(
                Number(pickupCoords.lat),
                Number(pickupCoords.lon),
                Number(returnCoords.lat),
                Number(returnCoords.lon)
            );
        } else if (modifyOriginalOrder) {
            const existingDistance = Number(modifyOriginalOrder.distance_km || 0);
            if (Number.isFinite(existingDistance) && existingDistance > 0) {
                distanceKm = existingDistance;
            }
        }

        if (Number.isFinite(distanceKm) && distanceKm > 0) {
            payload.distance_km = Number(distanceKm.toFixed(2));
        }
    }

    return payload;
}

async function loadModifyAvailabilityMatrix() {
    try {
        const res = await fetch(VEHICLES_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(buildModifyAvailabilityPayload())
        });
        const data = await res.json();
        if (data && data.success && data.availability) {
            modifyAvailabilityMatrix = data.availability;
        } else {
            modifyAvailabilityMatrix = null;
        }
    } catch (err) {
        console.error('Failed to load modify availability matrix:', err);
        modifyAvailabilityMatrix = null;
    }

    updateModifyTierAvailabilityUI();
    updateModifySeatsAvailabilityUI();
}

function initModifyAvailabilityHandlers() {
    const tierSelect = document.getElementById('modifyRideTier');
    const seatsSelect = document.getElementById('modifySeats');
    const serviceSelect = document.getElementById('modifyServiceType');
    if (tierSelect) {
        tierSelect.addEventListener('change', () => {
            updateModifySeatsAvailabilityUI();
            updateModifyTierAvailabilityUI();
        });
    }
    if (seatsSelect) {
        seatsSelect.addEventListener('change', () => {
            syncModifySeatCapacityUI(seatsSelect.value);
            updateModifyTierAvailabilityUI();
            updateModifySeatsAvailabilityUI();
        });
    }
    if (serviceSelect) {
        serviceSelect.addEventListener('change', () => {
            syncModifyServiceTypeUI(serviceSelect.value);
            updateModifyDestinationMode(serviceSelect.value);
            refreshModifyPreview();
            loadModifyAvailabilityMatrix();
        });
    }

    const pickupDateTimeInput = document.getElementById('modifyPickupDateTime');
    if (pickupDateTimeInput) {
        pickupDateTimeInput.addEventListener('change', () => {
            loadModifyAvailabilityMatrix();
        });
    }

    const previewBindings = [
        ['modifyPickupLocation', 'input'],
        ['modifyDestination', 'input'],
        ['modifyPickupDateTime', 'change'],
        ['modifyRideTier', 'change'],
        ['modifySeats', 'change'],
        ['modifyAirportSelect', 'change'],
        ['modifyHotelSelect', 'change']
    ];
    previewBindings.forEach(([id, eventName]) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener(eventName, () => refreshModifyPreview());
    });
}

function formatMoneyPounds(value) {
    return '£' + Number(value || 0).toFixed(2);
}

function computeModifySubtotal(serviceType, rideTier, seats, distanceKm) {
    const tier = String(rideTier || '').toLowerCase();
    const seatCount = parseInt(seats, 10);

    if (isModifyDailyHireService(serviceType)) {
        if (!MODIFY_DAILY_HIRE_RATES[seatCount] || !MODIFY_DAILY_HIRE_RATES[seatCount][tier]) {
            return null;
        }
        return Number(MODIFY_DAILY_HIRE_RATES[seatCount][tier].toFixed(2));
    }

    const distance = Number(distanceKm);
    if (!MODIFY_MINICAB_RATES_PER_MILE[seatCount] || !MODIFY_MINICAB_RATES_PER_MILE[seatCount][tier]) {
        return null;
    }
    if (!Number.isFinite(distance) || distance <= 0) {
        return null;
    }
    const distanceMiles = distance * 0.621371;
    const rate = MODIFY_MINICAB_RATES_PER_MILE[seatCount][tier];
    return Number((distanceMiles * rate).toFixed(2));
}

function setModifyPreview(distanceText, fareText, totalText) {
    const distanceEl = document.getElementById('modifyPreviewDistance');
    const fareEl = document.getElementById('modifyPreviewFare');
    const totalEl = document.getElementById('modifyPreviewTotal');
    if (distanceEl) distanceEl.textContent = distanceText;
    if (fareEl) fareEl.textContent = fareText;
    if (totalEl) totalEl.textContent = totalText;
}

function refreshModifyPreview() {
    if (!modifyOriginalOrder) {
        setModifyPreview('-', '-', '-');
        return;
    }

    const pickupLocation = String(document.getElementById('modifyPickupLocation')?.value || '').trim();
    const destination = String(document.getElementById('modifyDestination')?.value || '').trim();
    const serviceType = String(document.getElementById('modifyServiceType')?.value || modifyOriginalOrder.service_type || 'local').toLowerCase();
    const isDailyHire = isModifyDailyHireService(serviceType);
    const rideTier = String(document.getElementById('modifyRideTier')?.value || normalizeRideTierValue(modifyOriginalOrder.ride_tier));
    const seats = parseInt(String(document.getElementById('modifySeats')?.value || normalizeModifySeatsValue(modifyOriginalOrder.number_of_passengers)), 10);

    const originalPickup = String(modifyOriginalOrder.pickup_location || '').trim();
    const originalDestination = String(modifyOriginalOrder.return_location || '').trim();
    const locationChanged = pickupLocation !== originalPickup || destination !== originalDestination;

    let effectiveDistance = null;
    if (locationChanged) {
        const pickupCoords = modifySelectedAddresses.pickup;
        const returnCoords = modifySelectedAddresses.return;
        if (pickupCoords && returnCoords) {
            effectiveDistance = haversineDistanceKm(
                Number(pickupCoords.lat),
                Number(pickupCoords.lon),
                Number(returnCoords.lat),
                Number(returnCoords.lon)
            );
        }
    } else {
        const originalDistance = Number(modifyOriginalOrder.distance_km || 0);
        if (Number.isFinite(originalDistance) && originalDistance > 0) {
            effectiveDistance = originalDistance;
        }
    }

    const subtotal = computeModifySubtotal(serviceType, rideTier, seats, effectiveDistance);
    if (subtotal === null) {
        setModifyPreview('-', '-', '-');
        return;
    }

    if (!isDailyHire && (!Number.isFinite(effectiveDistance) || effectiveDistance <= 0)) {
        const distanceText = locationChanged
            ? 'Select map/dropdown'
            : ((Number(modifyOriginalOrder.distance_km || 0) > 0) ? Number(modifyOriginalOrder.distance_km).toFixed(1) + ' km' : '-');
        setModifyPreview(distanceText, '-', '-');
        return;
    }

    const oldSubtotal = Number(modifyOriginalOrder.subtotal || 0);
    const oldTotal = Number(modifyOriginalOrder.total_amount || oldSubtotal || 0);
    const fixedDiscount = Math.max(0, oldSubtotal - oldTotal);
    const newTotal = Math.max(0, subtotal - fixedDiscount);

    setModifyPreview(
        isDailyHire ? 'Not required' : (Number(effectiveDistance).toFixed(1) + ' km'),
        formatMoneyPounds(subtotal),
        formatMoneyPounds(newTotal)
    );
}

function isModifyPresetDestinationService(serviceType) {
    const service = String(serviceType || '').toLowerCase();
    return service === 'airport-transfer' || service === 'hotel-transfer';
}

function setModifyPresetSelectByDestination(serviceType, destination) {
    const value = String(destination || '').trim();
    const airportSelect = document.getElementById('modifyAirportSelect');
    const hotelSelect = document.getElementById('modifyHotelSelect');
    if (airportSelect) airportSelect.value = '';
    if (hotelSelect) hotelSelect.value = '';

    if (!value) return;

    const target = String(serviceType || '').toLowerCase() === 'airport-transfer' ? airportSelect : hotelSelect;
    if (!target) return;

    const options = Array.from(target.options || []);
    const found = options.find(opt => String(opt.value).toLowerCase() === value.toLowerCase());
    if (found) {
        target.value = found.value;
    }
}

function setModifyDestinationFromSelectedOption(selectEl) {
    if (!selectEl) return;
    const selected = selectEl.options[selectEl.selectedIndex];
    const destinationInput = document.getElementById('modifyDestination');
    if (!destinationInput || !selected || !selected.value) return;

    const name = String(selected.value || '').trim();
    const lat = parseFloat(selected.getAttribute('data-lat') || '0');
    const lon = parseFloat(selected.getAttribute('data-lon') || '0');

    destinationInput.value = name;
    if (Number.isFinite(lat) && Number.isFinite(lon) && (lat !== 0 || lon !== 0)) {
        modifySelectedAddresses.return = { lat, lon, name };
        updateModifyMapCoords('return', lat, lon, name);
    } else {
        modifySelectedAddresses.return = null;
    }
    refreshModifyPreview();
}

function onModifyAirportSelect() {
    const select = document.getElementById('modifyAirportSelect');
    setModifyDestinationFromSelectedOption(select);
}

function onModifyHotelSelect() {
    const select = document.getElementById('modifyHotelSelect');
    setModifyDestinationFromSelectedOption(select);
}

function updateModifyDestinationMode(serviceType) {
    const service = String(serviceType || '').toLowerCase();
    const destinationInputWrapper = document.getElementById('modifyDestinationInputWrapper');
    const destinationInput = document.getElementById('modifyDestination');
    const destinationLabel = document.getElementById('modifyDestinationLabel');
    const returnMapContainer = document.getElementById('modifyReturnMapContainer');
    const airportWrapper = document.getElementById('modifyAirportSelectWrapper');
    const hotelWrapper = document.getElementById('modifyHotelSelectWrapper');
    const mapBtn = document.getElementById('modifyDestinationMapBtn');

    const isAirport = service === 'airport-transfer';
    const isHotel = service === 'hotel-transfer';
    const isDailyHire = service === 'daily-hire';
    const isPreset = isAirport || isHotel;

    if (destinationInputWrapper) {
        destinationInputWrapper.style.display = isPreset ? 'none' : 'flex';
    }
    if (airportWrapper) {
        airportWrapper.style.display = isAirport ? 'block' : 'none';
    }
    if (hotelWrapper) {
        hotelWrapper.style.display = isHotel ? 'block' : 'none';
    }
    if (mapBtn) {
        mapBtn.style.display = isPreset ? 'none' : 'inline-flex';
    }
    if (returnMapContainer && isPreset) {
        closeModifyMapPicker('return');
        returnMapContainer.style.display = 'none';
    }

    if (!destinationInput) return;

    destinationInput.required = true;
    destinationInput.placeholder = isDailyHire ? 'Enter drop off location' : 'Enter destination';
    if (destinationLabel) {
        destinationLabel.textContent = isDailyHire ? 'Drop Off *' : 'Destination *';
    }

    if (isAirport) {
        setModifyPresetSelectByDestination(service, destinationInput.value);
        onModifyAirportSelect();
    } else if (isHotel) {
        setModifyPresetSelectByDestination(service, destinationInput.value);
        onModifyHotelSelect();
    }
}

function updateModifyTierAvailabilityUI() {
    const tierSelect = document.getElementById('modifyRideTier');
    const seatsSelect = document.getElementById('modifySeats');
    const hint = document.getElementById('modifyTierAvailabilityHint');
    if (!tierSelect || !seatsSelect) return;

    const seats = parseInt(seatsSelect.value, 10) || 1;
    const labels = { eco: 'Eco', standard: 'Standard', luxury: 'Luxury' };
    const options = Array.from(tierSelect.options);

    let enabledCount = 0;
    options.forEach(opt => {
        const tier = String(opt.value || '').toLowerCase();
        const availableCount = getAvailabilityCountFor(tier, seats);
        opt.textContent = (labels[tier] || opt.value) + ' (' + availableCount + ' available)';
        opt.disabled = availableCount <= 0;
        if (!opt.disabled) enabledCount += 1;
    });

    if (tierSelect.selectedOptions.length === 0 || tierSelect.selectedOptions[0].disabled) {
        const firstEnabled = options.find(o => !o.disabled);
        if (firstEnabled) {
            tierSelect.value = firstEnabled.value;
        }
    }

    const selectedTier = String(tierSelect.value || '').toLowerCase();
    const selectedCount = getAvailabilityCountFor(selectedTier, seats);
    if (hint) {
        if (!modifyAvailabilityMatrix) {
            hint.textContent = 'Unable to load availability right now.';
        } else if (enabledCount === 0) {
            hint.textContent = 'No service tiers available for selected seats.';
        } else {
            hint.textContent = labels[selectedTier] + ': ' + selectedCount + ' vehicle(s) available for ' + seats + ' seat(s).';
        }
    }
}

function updateModifySeatsAvailabilityUI() {
    const tierSelect = document.getElementById('modifyRideTier');
    const seatsSelect = document.getElementById('modifySeats');
    const hint = document.getElementById('modifySeatsAvailabilityHint');
    if (!tierSelect || !seatsSelect) return;

    const tier = String(tierSelect.value || '').toLowerCase();
    const options = Array.from(seatsSelect.options);
    let enabledCount = 0;

    options.forEach(opt => {
        const seatCount = parseInt(opt.value, 10);
        const availableCount = getAvailabilityCountFor(tier, seatCount);
        opt.textContent = String(seatCount) + ' (' + availableCount + ' available)';
        opt.disabled = availableCount <= 0;
        if (!opt.disabled) enabledCount += 1;
    });

    if (seatsSelect.selectedOptions.length === 0 || seatsSelect.selectedOptions[0].disabled) {
        const firstEnabled = options.find(o => !o.disabled);
        if (firstEnabled) {
            seatsSelect.value = firstEnabled.value;
        }
    }

    const selectedSeats = parseInt(seatsSelect.value, 10) || 1;
    const selectedCount = getAvailabilityCountFor(tier, selectedSeats);
    if (hint) {
        if (!modifyAvailabilityMatrix) {
            hint.textContent = 'Unable to load availability right now.';
        } else if (enabledCount === 0) {
            hint.textContent = 'No seat options available for selected service tier.';
        } else {
            hint.textContent = selectedSeats + ' seat(s): ' + selectedCount + ' vehicle(s) available for this tier.';
        }
    }
}

function truncateText(text, maxLen) {
    const t = String(text || '');
    return t.length > maxLen ? t.substring(0, maxLen) + '...' : t;
}

function updateModifyMapCoords(type, lat, lon, name) {
    const el = document.getElementById(type === 'pickup' ? 'modifyPickupMapCoords' : 'modifyReturnMapCoords');
    if (!el) return;
    const short = name ? truncateText(name, 65) : (Number(lat).toFixed(5) + ', ' + Number(lon).toFixed(5));
    el.textContent = '📍 ' + short;
}

function moveModifyMapToLocation(type, lat, lon) {
    const map = type === 'pickup' ? modifyPickupMapObj : modifyReturnMapObj;
    const marker = type === 'pickup' ? modifyPickupMarker : modifyReturnMarker;
    if (!map || !marker || !window.L) return;
    const latlng = L.latLng(lat, lon);
    marker.setLatLng(latlng);
    map.setView(latlng, 16, { animate: true });
}

function initModifyLocationAutocomplete() {
    const setup = [
        { id: 'modifyPickupLocation', type: 'pickup' },
        { id: 'modifyDestination', type: 'return' }
    ];

    setup.forEach(({ id, type }) => {
        const input = document.getElementById(id);
        if (!input) return;

        let dropdown = input.parentElement.querySelector('.leaflet-autocomplete-list');
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.className = 'leaflet-autocomplete-list';
            dropdown.style.display = 'none';
            input.parentElement.appendChild(dropdown);
        }

        input.addEventListener('input', function() {
            modifySelectedAddresses[type] = null;
            const query = this.value.trim();
            if (query.length < 3) {
                dropdown.style.display = 'none';
                return;
            }

            clearTimeout(modifyAutocompleteTimers[id]);
            modifyAutocompleteTimers[id] = setTimeout(async () => {
                try {
                    const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query) + '&limit=5&addressdetails=1&countrycodes=gb', {
                        headers: { 'Accept-Language': 'en' }
                    });
                    const results = await res.json();
                    if (!Array.isArray(results) || results.length === 0) {
                        dropdown.style.display = 'none';
                        return;
                    }

                    dropdown.innerHTML = results.map(r => {
                        const display = String(r.display_name || '');
                        const parts = display.split(',');
                        const main = parts.slice(0, 2).join(',').trim();
                        const sub = parts.slice(2).join(',').trim();
                        const safeName = display.replace(/"/g, '&quot;');
                        return '<div class="autocomplete-item" data-lat="' + r.lat + '" data-lon="' + r.lon + '" data-name="' + safeName + '">' +
                            '<div class="ac-main">' + escapeHtml(main) + '</div>' +
                            (sub ? '<div class="ac-sub">' + escapeHtml(sub) + '</div>' : '') +
                        '</div>';
                    }).join('');

                    dropdown.style.display = 'block';
                    dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                        const commitSelection = () => {
                            const lat = parseFloat(item.getAttribute('data-lat') || '0');
                            const lon = parseFloat(item.getAttribute('data-lon') || '0');
                            const name = item.getAttribute('data-name') || '';
                            input.value = name;
                            dropdown.style.display = 'none';
                            modifySelectedAddresses[type] = { lat, lon, name };
                            moveModifyMapToLocation(type, lat, lon);
                            updateModifyMapCoords(type, lat, lon, name);
                            refreshModifyPreview();
                        };

                        if (window.PointerEvent) {
                            item.addEventListener('pointerdown', event => {
                                event.preventDefault();
                                commitSelection();
                            }, { passive: false });
                        } else {
                            item.addEventListener('mousedown', event => {
                                event.preventDefault();
                                commitSelection();
                            });
                            item.addEventListener('touchend', event => {
                                event.preventDefault();
                                commitSelection();
                            }, { passive: false });
                        }
                    });
                } catch (err) {
                    console.error('Modify address search error:', err);
                    dropdown.style.display = 'none';
                }
            }, 320);
        });

        input.addEventListener('blur', () => setTimeout(() => { dropdown.style.display = 'none'; }, 200));
        input.addEventListener('focus', function() {
            if (this.value.trim().length >= 3 && dropdown.innerHTML) {
                dropdown.style.display = 'block';
            }
        });
    });
}

function closeModifyMapPicker(type) {
    const containerId = type === 'pickup' ? 'modifyPickupMapContainer' : 'modifyReturnMapContainer';
    const container = document.getElementById(containerId);
    if (container) {
        container.style.display = 'none';
        container.classList.remove('expanded');
    }
}

function openModifyMapPicker(type) {
    if (type === 'return') {
        const serviceType = document.getElementById('modifyServiceType')?.value || '';
        if (isModifyPresetDestinationService(serviceType)) {
            showToast('For airport/hotel transfer, please choose destination from the dropdown list.', 'warning');
            return;
        }
    }

    if (!window.L) {
        showToast('Map is not available right now.', 'warning');
        return;
    }

    const isPickup = type === 'pickup';
    const container = document.getElementById(isPickup ? 'modifyPickupMapContainer' : 'modifyReturnMapContainer');
    const mapEl = document.getElementById(isPickup ? 'modifyPickupMap' : 'modifyReturnMap');
    if (!container || !mapEl) return;

    if (container.style.display !== 'none') {
        closeModifyMapPicker(type);
        return;
    }

    container.style.display = 'block';

    if (isPickup && modifyPickupMapObj) {
        modifyPickupMapObj.remove();
        modifyPickupMapObj = null;
    }
    if (!isPickup && modifyReturnMapObj) {
        modifyReturnMapObj.remove();
        modifyReturnMapObj = null;
    }

    const saved = modifySelectedAddresses[type];
    const center = saved ? [saved.lat, saved.lon] : [51.5074, -0.1278];
    const zoom = saved ? 16 : 13;
    const map = L.map(mapEl).setView(center, zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    const marker = L.marker(center, { draggable: true }).addTo(map);

    map.on('click', e => {
        marker.setLatLng(e.latlng);
        modifySelectedAddresses[type] = null;
        updateModifyMapCoords(type, e.latlng.lat, e.latlng.lng, null);
    });

    marker.on('dragend', () => {
        const pos = marker.getLatLng();
        modifySelectedAddresses[type] = null;
        updateModifyMapCoords(type, pos.lat, pos.lng, null);
    });

    if (isPickup) {
        modifyPickupMapObj = map;
        modifyPickupMarker = marker;
    } else {
        modifyReturnMapObj = map;
        modifyReturnMarker = marker;
    }

    if (saved) updateModifyMapCoords(type, saved.lat, saved.lon, saved.name || null);
    setTimeout(() => map.invalidateSize(), 200);
}

function toggleModifyMapExpand(type) {
    const container = document.getElementById(type === 'pickup' ? 'modifyPickupMapContainer' : 'modifyReturnMapContainer');
    if (!container) return;
    container.classList.toggle('expanded');
    const map = type === 'pickup' ? modifyPickupMapObj : modifyReturnMapObj;
    if (map) setTimeout(() => map.invalidateSize(), 300);
}

async function confirmModifyMapLocation(type) {
    const isPickup = type === 'pickup';
    const input = document.getElementById(isPickup ? 'modifyPickupLocation' : 'modifyDestination');
    const marker = isPickup ? modifyPickupMarker : modifyReturnMarker;
    const saved = modifySelectedAddresses[type];

    if (!input || !marker) {
        closeModifyMapPicker(type);
        return;
    }

    if (saved && saved.name) {
        input.value = saved.name;
        closeModifyMapPicker(type);
        refreshModifyPreview();
        showToast('📍 Location confirmed!', 'success');
        return;
    }

    const pos = marker.getLatLng();
    try {
        const res = await fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + pos.lat + '&lon=' + pos.lng + '&zoom=18&addressdetails=1', {
            headers: { 'Accept-Language': 'en' }
        });
        const data = await res.json();
        const address = data.display_name || (pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6));
        input.value = address;
        modifySelectedAddresses[type] = { lat: pos.lat, lon: pos.lng, name: address };
        updateModifyMapCoords(type, pos.lat, pos.lng, address);
    } catch (err) {
        const fallback = pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6);
        input.value = fallback;
        modifySelectedAddresses[type] = { lat: pos.lat, lon: pos.lng, name: fallback };
        updateModifyMapCoords(type, pos.lat, pos.lng, fallback);
    }

    closeModifyMapPicker(type);
    refreshModifyPreview();
    showToast('📍 Location confirmed!', 'success');
}

async function geocodeModifyAddress(type, text) {
    const query = String(text || '').trim();
    if (query.length < 3) {
        modifySelectedAddresses[type] = null;
        return;
    }
    try {
        const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query) + '&limit=1&countrycodes=gb', {
            headers: { 'Accept-Language': 'en' }
        });
        const results = await res.json();
        if (Array.isArray(results) && results.length > 0) {
            const r = results[0];
            const lat = parseFloat(r.lat);
            const lon = parseFloat(r.lon);
            const name = String(r.display_name || query);
            modifySelectedAddresses[type] = { lat, lon, name };
            updateModifyMapCoords(type, lat, lon, name);
        }
    } catch (err) {
        console.error('Failed to geocode modify address:', err);
    }
}

function escapeHtml(text) {
    const str = String(text || '');
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function openModifyBookingModal(bookingId) {
    const order = allOrders.find(o => String(o.id) === String(bookingId));
    if (!order) {
        showToast('Booking not found.', 'error');
        return;
    }

    if (!canCustomerModifyOrder(order)) {
        showToast('Bookings cannot be modified within 24 hours before pickup.', 'warning');
        return;
    }

    modifyBookingId = bookingId;
    modifyOriginalOrder = order;

    document.getElementById('modifyBookingId').textContent = '#' + String(order.id).substring(0, 8);
    document.getElementById('modifyPickupLocation').value = order.pickup_location || '';
    document.getElementById('modifyDestination').value = order.return_location || '';
    document.getElementById('modifyServiceType').value = String(order.service_type || 'local');
    const pickupDateTimeInput = document.getElementById('modifyPickupDateTime');
    if (pickupDateTimeInput) {
        pickupDateTimeInput.value = toDateTimeLocalValue(order.pickup_date, order.pickup_time);
        pickupDateTimeInput.min = getNowDateTimeLocalValue();

        const originalPickup = parseOrderPickupDateTime(order);
        if (originalPickup) {
            const maxAllowed = new Date(originalPickup.getTime());
            maxAllowed.setDate(maxAllowed.getDate() + 7);
            pickupDateTimeInput.max = toYmd(maxAllowed) + 'T23:59';
        } else {
            pickupDateTimeInput.removeAttribute('max');
        }
    }
    document.getElementById('modifyRideTier').value = normalizeRideTierValue(order.ride_tier);
    document.getElementById('modifySeats').value = String(normalizeModifySeatsValue(order.number_of_passengers));

    modifySelectedAddresses = { pickup: null, return: null };
    closeModifyMapPicker('pickup');
    closeModifyMapPicker('return');

    syncModifyServiceTypeUI(document.getElementById('modifyServiceType').value);
    syncModifySeatCapacityUI(document.getElementById('modifySeats').value);
    updateModifyDestinationMode(document.getElementById('modifyServiceType').value);

    geocodeModifyAddress('pickup', order.pickup_location || '');
    if (!isModifyPresetDestinationService(document.getElementById('modifyServiceType').value)) {
        geocodeModifyAddress('return', order.return_location || '');
    }

    loadModifyAvailabilityMatrix();
    refreshModifyPreview();
    openOrdersModal('modifyBookingModalOverlay');
}

function normalizeRideTierValue(rideTier) {
    const value = String(rideTier || '').toLowerCase();
    if (value === 'premium') return 'luxury';
    if (['eco', 'standard', 'luxury'].includes(value)) return value;
    return 'standard';
}

function normalizeModifySeatsValue(seats) {
    const value = parseInt(seats, 10);
    return value >= 7 ? 7 : 4;
}

function toDateTimeLocalValue(dateValue, timeValue) {
    const date = String(dateValue || '').trim();
    if (!date) return '';
    const timeRaw = String(timeValue || '').trim();
    const m = timeRaw.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/i);
    let hour = m ? Math.max(0, Math.min(23, parseInt(m[1], 10) || 0)) : 0;
    const mm = m ? String(Math.max(0, Math.min(59, parseInt(m[2], 10) || 0))).padStart(2, '0') : '00';
    const meridiem = m ? String(m[3] || '').toUpperCase() : '';

    if (meridiem === 'PM' && hour < 12) hour += 12;
    if (meridiem === 'AM' && hour === 12) hour = 0;

    const hh = String(hour).padStart(2, '0');
    return date.substring(0, 10) + 'T' + hh + ':' + mm;
}

function getNowDateTimeLocalValue() {
    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    const hh = String(now.getHours()).padStart(2, '0');
    const min = String(now.getMinutes()).padStart(2, '0');
    return yyyy + '-' + mm + '-' + dd + 'T' + hh + ':' + min;
}

function toYmd(date) {
    const yyyy = date.getFullYear();
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const dd = String(date.getDate()).padStart(2, '0');
    return yyyy + '-' + mm + '-' + dd;
}

function selectModifyServiceTypeCard(serviceType) {
    const select = document.getElementById('modifyServiceType');
    if (!select) return;
    select.value = serviceType;
    syncModifyServiceTypeUI(serviceType);
    updateModifyDestinationMode(serviceType);
    refreshModifyPreview();
}

function syncModifyServiceTypeUI(selectedServiceType) {
    const cards = document.querySelectorAll('#modifyServicePurposeGrid .service-purpose-card');
    const selected = String(selectedServiceType || '').toLowerCase();
    cards.forEach(card => {
        const service = String(card.getAttribute('data-service') || '').toLowerCase();
        card.classList.toggle('active', service === selected);
    });
}

function selectModifySeatCapacity(seatValue) {
    const select = document.getElementById('modifySeats');
    if (!select) return;
    const normalized = normalizeModifySeatsValue(seatValue);
    select.value = String(normalized);
    syncModifySeatCapacityUI(normalized);
    updateModifyTierAvailabilityUI();
    updateModifySeatsAvailabilityUI();
    refreshModifyPreview();
}

function syncModifySeatCapacityUI(selectedSeatValue) {
    const cards = document.querySelectorAll('#modifySeatCapacityGrid .seat-capacity-option');
    const selected = String(normalizeModifySeatsValue(selectedSeatValue));
    cards.forEach(card => {
        const seat = String(card.getAttribute('data-seat') || '');
        card.classList.toggle('active', seat === selected);
    });
}

function closeModifyBookingModal() {
    modifyBookingId = null;
    modifyOriginalOrder = null;
    closeModifyMapPicker('pickup');
    closeModifyMapPicker('return');
    closeOrdersModal('modifyBookingModalOverlay');
}

function toRad(value) {
    return (value * Math.PI) / 180;
}

function haversineDistanceKm(lat1, lon1, lat2, lon2) {
    const earthKm = 6371;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
        + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2))
        * Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return earthKm * c;
}

async function submitModifyBooking() {
    if (!modifyBookingId) return;

    const pickupLocation = document.getElementById('modifyPickupLocation').value.trim();
    const destination = document.getElementById('modifyDestination').value.trim();
    const serviceType = document.getElementById('modifyServiceType').value;
    const isDailyHire = isModifyDailyHireService(serviceType);
    const pickupDateTime = document.getElementById('modifyPickupDateTime').value;
    const rideTier = document.getElementById('modifyRideTier').value;
    const seats = parseInt(document.getElementById('modifySeats').value, 10);

    if (!pickupLocation || !destination) {
        showToast('Pickup and drop off destinations are required.', 'warning');
        return;
    }
    if (!['eco', 'standard', 'luxury'].includes(rideTier)) {
        showToast('Please choose a valid service tier.', 'warning');
        return;
    }
    if (!['local', 'long-distance', 'airport-transfer', 'hotel-transfer', 'daily-hire'].includes(serviceType)) {
        showToast('Please choose a valid service type.', 'warning');
        return;
    }
    if (!pickupDateTime) {
        showToast('Please choose pickup date and time.', 'warning');
        return;
    }
    const pickupAt = new Date(pickupDateTime);
    if (Number.isNaN(pickupAt.getTime()) || pickupAt.getTime() < Date.now()) {
        showToast('Pickup date and time must be in the future.', 'warning');
        return;
    }
    if (![4, 7].includes(seats)) {
        showToast('Seats must be 4 or 7.', 'warning');
        return;
    }

    const pickupDate = pickupDateTime.substring(0, 10);
    const pickupTime = pickupDateTime.substring(11, 16);

    let distanceKm = null;
    if (!isDailyHire) {
        const pickupCoords = modifySelectedAddresses.pickup;
        const returnCoords = modifySelectedAddresses.return;
        if (pickupCoords && returnCoords) {
            distanceKm = haversineDistanceKm(
                Number(pickupCoords.lat),
                Number(pickupCoords.lon),
                Number(returnCoords.lat),
                Number(returnCoords.lon)
            );
            distanceKm = Number.isFinite(distanceKm) ? Number(distanceKm.toFixed(2)) : null;
        } else if (modifyOriginalOrder && (pickupLocation !== (modifyOriginalOrder.pickup_location || '') || destination !== (modifyOriginalOrder.return_location || ''))) {
            showToast('Please confirm locations on map to recalculate distance and fare.', 'warning');
            return;
        }
    }

    const btn = document.getElementById('modifyBookingSaveBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    try {
        const res = await fetch(BOOKINGS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'modify-booking',
                booking_id: modifyBookingId,
                pickup_location: pickupLocation,
                return_location: destination,
                service_type: serviceType,
                pickup_date: pickupDate,
                pickup_time: pickupTime,
                ride_tier: rideTier,
                number_of_passengers: seats,
                distance_km: distanceKm
            })
        });

        const data = await res.json();
        if (data.success) {
            showToast('✅ Booking updated successfully!', 'success');
            closeModifyBookingModal();
            closeTripDetailModal();
            loadOrders();
        } else {
            showToast('❌ ' + (data.message || 'Failed to modify booking.'), 'error');
        }
    } catch (err) {
        showToast('Connection error. Please try again.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save Changes';
    }
}

function truncate(str, max) {
    return str.length > max ? str.substring(0, max) + '...' : str;
}

window.openModifyMapPicker = openModifyMapPicker;
window.toggleModifyMapExpand = toggleModifyMapExpand;
window.confirmModifyMapLocation = confirmModifyMapLocation;
window.selectModifyServiceTypeCard = selectModifyServiceTypeCard;
window.selectModifySeatCapacity = selectModifySeatCapacity;
window.onModifyAirportSelect = onModifyAirportSelect;
window.onModifyHotelSelect = onModifyHotelSelect;

// ===== REVIEW MODAL MANAGEMENT =====
function openReviewModal(bookingId, carName) {
    const modal = document.getElementById('reviewModalOverlay');
    const carNameEl = document.getElementById('reviewCarName');
    const contentEl = document.getElementById('reviewContent');
    if (!modal || !carNameEl || !contentEl) {
        showToast('Feedback modal is not available right now. Please refresh and try again.', 'error');
        return;
    }

    reviewBookingId = bookingId;
    reviewRating = 0;
    carNameEl.textContent = carName;
    contentEl.value = '';
    renderStars(0);
    openOrdersModal('reviewModalOverlay');
}

function closeReviewModal() {
    closeOrdersModal('reviewModalOverlay');
    reviewBookingId = null;
    reviewRating = 0;
}

function setReviewRating(rating) {
    reviewRating = rating;
    renderStars(rating);
}

function renderStars(rating) {
    const container = document.getElementById('reviewStarsInput');
    if (!container) return;
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
        const res = await fetch(REVIEWS_API, {
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
            const earnedPoints = Number(data.earned_points || 0);
            if (typeof data.loyalty_point !== 'undefined') {
                setOrdersLoyaltyPoints(data.loyalty_point);
            } else {
                loadLoyaltyPoints();
            }
            if (earnedPoints > 0) {
                showToast('⭐ Feedback submitted! You earned ' + earnedPoints + ' loyalty points.', 'success');
            } else {
                showToast('⭐ ' + data.message, 'success');
            }
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
        btn.textContent = '⭐ Submit Feedback (+ Loyalty Points)';
    }
}
