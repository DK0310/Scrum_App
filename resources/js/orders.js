/**
 * Orders Module - Booking management and order tracking
 * Handles order listing, filtering, status updates, and reviews
 */

// ===== CONSTANTS & CONFIGURATION =====
const BOOKINGS_API = '/api/orders.php';
const VEHICLES_API = '/api/vehicles.php';
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
    credit_card: 'Card'
};

// ===== STATE MANAGEMENT =====
let allOrders = [];
let currentFilter = 'all';
let USER_ROLE = ''; // Will be set from PHP

// ===== REVIEW MODAL STATE =====
let reviewBookingId = null;
let reviewRating = 0;
let modifyBookingId = null;
let modifyAvailabilityMatrix = null;
let modifyPickupMapObj = null;
let modifyReturnMapObj = null;
let modifyPickupMarker = null;
let modifyReturnMarker = null;
let modifyAutocompleteTimers = {};
let modifySelectedAddresses = { pickup: null, return: null };

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', () => {
    initTripDetailModalEvents();
    initModifyLocationAutocomplete();
    initModifyAvailabilityHandlers();
    loadOrders();
});

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
        const typeLabels = ORDER_TYPE_LABELS;
        const pmLabels = {
            cash: '💵 Cash',
            bank_transfer: '🏦 Banking',
            paypal: '🅿️ PayPal',
            credit_card: '💳 Card'
        };

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

        const thumbUrl = shouldHideVehicle ? '' : (order.thumbnail_url || '');
        const thumbHtml = thumbUrl
            ? '<img src="' + thumbUrl + '" alt="' + displayVehicleName + '">'
            : '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--gray-400);font-size:0.7rem;">No Photo</div>';

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

        return '<div class="' + cardClassName + '"' + cardAttrs + '>' +
            '<div class="order-card-header">' +
                '<div class="order-card-left">' +
                    '<div class="order-car-thumb">' + thumbHtml + '</div>' +
                    '<div class="order-car-info">' +
                        '<h4>' + displayVehicleName + '</h4>' +
                        '<p>' + (typeLabels[order.booking_type] || order.booking_type) + (order.service_type ? ' · ' + order.service_type.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : '') + ' · Order #' + order.id.substring(0, 8) + '</p>' +
                    '</div>' +
                '</div>' +
                '<div class="order-status-badge status-' + order.status + '">' + (statusLabels[order.status] || order.status) + '</div>' +
            '</div>' +
            renterInfoHtml +
            '<div class="order-card-body">' +
                '<div class="order-detail-item"><div class="order-detail-label">Pick-up Date</div><div class="order-detail-value">' + formatPickupDate(order) + '</div></div>' +
                (order.return_date ? '<div class="order-detail-item"><div class="order-detail-label">Return Date</div><div class="order-detail-value">' + formatDate(order.return_date) + '</div></div>' : '') +
                '<div class="order-detail-item"><div class="order-detail-label">Pick-up Location</div><div class="order-detail-value">' + truncate(order.pickup_location || '-', 40) + '</div></div>' +
                (order.return_location && order.booking_type === 'minicab' ? '<div class="order-detail-item"><div class="order-detail-label">Destination</div><div class="order-detail-value">' + truncate(order.return_location || '-', 40) + '</div></div>' : '') +
                (order.distance_km ? '<div class="order-detail-item"><div class="order-detail-label">📏 Distance</div><div class="order-detail-value">' + parseFloat(order.distance_km).toFixed(1) + ' km</div></div>' : '') +
                '<div class="order-detail-item"><div class="order-detail-label">Payment</div><div class="order-detail-value">' + (pmLabels[order.payment_method] || order.payment_method || 'N/A') + '</div></div>' +
                '<div class="order-detail-item"><div class="order-detail-label">Booked On</div><div class="order-detail-value">' + formatDate(order.created_at) + '</div></div>' +
            '</div>' +
            '<div class="order-card-footer">' +
                '<div class="order-total">£' + parseFloat(order.total_amount).toFixed(2) + '</div>' +
                '<div class="order-actions">' + actionsHtml + '</div>' +
            '</div>' +
        '</div>';
    }).join('');

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
            closeTripDetailModal();
            closeModifyBookingModal();
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

    overlay.style.display = 'flex';
}

function closeTripDetailModal() {
    const overlay = document.getElementById('tripDetailModalOverlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
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
        const data = await res.json();
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
        showToast('Connection error. Please try again.', 'error');
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

function formatPickupDate(order) {
    if (!order || !order.pickup_date) return '-';
    if (order.booking_type === 'minicab') {
        // If pickup_time is available, combine date with the correct time
        if (order.pickup_time) {
            const dateOnly = formatDate(order.pickup_date); // e.g., "27 Mar 2026"
            return dateOnly + ', ' + order.pickup_time; // e.g., "27 Mar 2026, 10:00AM"
        }
        return formatDateTime(order.pickup_date);
    }
    return formatDate(order.pickup_date);
}

function canCustomerCancelOrder(order) {
    return canCustomerModifyOrder(order);
}

function canCustomerModifyOrder(order) {
    if (!order || !order.is_renter || order.status !== 'pending') return false;
    return hasAtLeast24HoursBeforePickup(order);
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

async function loadModifyAvailabilityMatrix() {
    try {
        const res = await fetch(VEHICLES_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'tier-seat-availability' })
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
    if (tierSelect) {
        tierSelect.addEventListener('change', () => {
            updateModifySeatsAvailabilityUI();
            updateModifyTierAvailabilityUI();
        });
    }
    if (seatsSelect) {
        seatsSelect.addEventListener('change', () => {
            updateModifyTierAvailabilityUI();
            updateModifySeatsAvailabilityUI();
        });
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
                        item.addEventListener('mousedown', event => {
                            event.preventDefault();
                            const lat = parseFloat(item.getAttribute('data-lat') || '0');
                            const lon = parseFloat(item.getAttribute('data-lon') || '0');
                            const name = item.getAttribute('data-name') || '';
                            input.value = name;
                            dropdown.style.display = 'none';
                            modifySelectedAddresses[type] = { lat, lon, name };
                            moveModifyMapToLocation(type, lat, lon);
                            updateModifyMapCoords(type, lat, lon, name);
                        });
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

    document.getElementById('modifyBookingId').textContent = '#' + String(order.id).substring(0, 8);
    document.getElementById('modifyPickupLocation').value = order.pickup_location || '';
    document.getElementById('modifyDestination').value = order.return_location || '';
    document.getElementById('modifyRideTier').value = normalizeRideTierValue(order.ride_tier);
    document.getElementById('modifySeats').value = String(normalizeModifySeatsValue(order.number_of_passengers));

    modifySelectedAddresses = { pickup: null, return: null };
    closeModifyMapPicker('pickup');
    closeModifyMapPicker('return');

    geocodeModifyAddress('pickup', order.pickup_location || '');
    geocodeModifyAddress('return', order.return_location || '');

    loadModifyAvailabilityMatrix();
    document.getElementById('modifyBookingModalOverlay').style.display = 'flex';
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

function closeModifyBookingModal() {
    modifyBookingId = null;
    closeModifyMapPicker('pickup');
    closeModifyMapPicker('return');
    const overlay = document.getElementById('modifyBookingModalOverlay');
    if (overlay) overlay.style.display = 'none';
}

async function submitModifyBooking() {
    if (!modifyBookingId) return;

    const pickupLocation = document.getElementById('modifyPickupLocation').value.trim();
    const destination = document.getElementById('modifyDestination').value.trim();
    const rideTier = document.getElementById('modifyRideTier').value;
    const seats = parseInt(document.getElementById('modifySeats').value, 10);

    if (!pickupLocation || !destination) {
        showToast('Pickup location and destination are required.', 'warning');
        return;
    }
    if (!['eco', 'standard', 'luxury'].includes(rideTier)) {
        showToast('Please choose a valid service tier.', 'warning');
        return;
    }
    if (![4, 7].includes(seats)) {
        showToast('Seats must be 4 or 7.', 'warning');
        return;
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
                ride_tier: rideTier,
                number_of_passengers: seats
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
