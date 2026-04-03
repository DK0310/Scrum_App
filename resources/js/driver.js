const DRIVER_API = '/api/driver.php';
let currentOrders = [];
let historyOrders = [];
let activeTab = 'current';

function driverSwitchTab(tab) {
    activeTab = tab;
    document.getElementById('tabCurrent').classList.toggle('active', tab === 'current');
    document.getElementById('tabHistory').classList.toggle('active', tab === 'history');
    document.getElementById('panelCurrent').classList.toggle('active', tab === 'current');
    document.getElementById('panelHistory').classList.toggle('active', tab === 'history');
}

function driverShowStatus(message, type) {
    const bar = document.getElementById('driverStatusBar');
    bar.style.display = 'block';
    bar.className = 'driver-status-bar ' + type;
    bar.textContent = message;
    setTimeout(() => {
        bar.style.display = 'none';
    }, 3500);
}

function formatDateTime(pickupDate, pickupTime) {
    if (!pickupDate) return '-';

    const dateObj = new Date(pickupDate + 'Z');
    const dateText = Number.isNaN(dateObj.getTime())
        ? String(pickupDate)
        : dateObj.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });

    if (pickupTime) {
        const raw = String(pickupTime).trim();
        const m = raw.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])?$/);
        if (m) {
            let hour = parseInt(m[1], 10);
            const minute = m[2];
            const suffixInput = m[3] ? m[3].toUpperCase() : '';
            if (suffixInput === 'PM' && hour < 12) hour += 12;
            if (suffixInput === 'AM' && hour === 12) hour = 0;
            const suffix = hour >= 12 ? 'PM' : 'AM';
            const hour12 = ((hour + 11) % 12) + 1;
            return dateText + ' ' + String(hour12).padStart(2, '0') + ':' + minute + ' ' + suffix;
        }
        return dateText + ' ' + raw;
    }

    return dateText;
}

function formatMoney(value) {
    const n = Number(value || 0);
    return '$' + n.toFixed(2);
}

function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>"]/g, function(c) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
    });
}

function closePassengerOrdersModal() {
    const modal = document.getElementById('passengerOrdersModal');
    if (!modal) return;
    modal.style.display = 'none';
}

function renderPassengerOrdersList(orders) {
    if (!orders || !orders.length) {
        return '<li>No orders.</li>';
    }

    return orders.map(function(order) {
        const statusRaw = String(order.status || '-');
        const statusText = statusRaw.replace(/_/g, ' ');
        return '<li>' +
            '<div class="driver-modal-item-head">' +
                '<strong>Booking #' + escapeHtml(order.booking_id) + '</strong>' +
                '<span class="driver-modal-status status-' + escapeHtml(statusRaw) + '">' + escapeHtml(statusText) + '</span>' +
            '</div>' +
            '<div class="driver-modal-sub"><span class="driver-modal-label">Pickup:</span> ' + escapeHtml(order.pickup_location || '-') + '</div>' +
            '<div class="driver-modal-sub"><span class="driver-modal-label">Destination:</span> ' + escapeHtml(order.destination || '-') + '</div>' +
            '<div class="driver-modal-sub"><span class="driver-modal-label">Time:</span> ' + escapeHtml(formatDateTime(order.pickup_date, order.pickup_time)) + '</div>' +
            '<div class="driver-modal-sub"><span class="driver-modal-label">Price:</span> ' + escapeHtml(formatMoney(order.price)) + '</div>' +
        '</li>';
    }).join('');
}

async function openPassengerOrdersModal(passengerId, passengerName, viewType) {
    const modal = document.getElementById('passengerOrdersModal');
    const body = document.getElementById('passengerOrdersBody');
    const title = document.getElementById('passengerOrdersTitle');
    if (!modal || !body || !title) return;

    const type = viewType === 'history' ? 'history' : 'current';
    title.textContent = 'Passenger ' + (type === 'history' ? 'History' : 'Current') + ' Orders - ' + (passengerName || 'Passenger');
    body.innerHTML = 'Loading passenger orders...';
    modal.style.display = 'flex';

    try {
        const data = await driverRequest('get_passenger_orders', { passenger_id: passengerId });
        if (!data.success) {
            body.innerHTML = '<div style="color:#991b1b;">' + escapeHtml(data.message || 'Unable to load passenger orders') + '</div>';
            return;
        }

        const current = Array.isArray(data.current_orders) ? data.current_orders : [];
        const history = Array.isArray(data.history_orders) ? data.history_orders : [];

        if (type === 'history') {
            body.innerHTML =
                '<section class="driver-modal-section">' +
                    '<h4>Order History</h4>' +
                    '<ul class="driver-modal-list">' + renderPassengerOrdersList(history) + '</ul>' +
                '</section>';
            return;
        }

        body.innerHTML =
            '<section class="driver-modal-section">' +
                '<h4>Current Orders</h4>' +
                '<ul class="driver-modal-list">' + renderPassengerOrdersList(current) + '</ul>' +
            '</section>';
    } catch (err) {
        body.innerHTML = '<div style="color:#991b1b;">Network error while loading passenger orders.</div>';
    }
}

function initialsFromName(name) {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'CU';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function nextActionLabel(status) {
    if (status === 'on_route') return 'Start Trip';
    if (status === 'on_trip') return 'Complete Trip';
    return '';
}

function nextActionStatus(status) {
    if (status === 'on_route') return 'on_trip';
    if (status === 'on_trip') return 'completed';
    return null;
}

async function driverRequest(action, payload = null) {
    if (payload) {
        const res = await fetch(DRIVER_API, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(Object.assign({action}, payload))
        });
        return res.json();
    }

    const res = await fetch(DRIVER_API + '?action=' + encodeURIComponent(action));
    return res.json();
}

async function loadAssignedVehicle() {
    const box = document.getElementById('driverAssignedVehicle');
    const titleEl = box.querySelector('.av-title');
    const valueEl = box.querySelector('.av-value');
    const metaEl = document.getElementById('driverAssignedVehicleMeta');
    const imgEl = document.getElementById('driverAssignedVehicleImg');
    const fallbackEl = document.getElementById('driverAssignedVehicleImgFallback');

    try {
        const data = await driverRequest('get_assigned_vehicle');
        if (!data.success || !data.vehicle) {
            titleEl.textContent = 'No Assigned Vehicle';
            valueEl.textContent = 'No vehicle assigned';
            metaEl.textContent = 'Waiting for dispatch from control staff.';
            imgEl.style.display = 'none';
            fallbackEl.style.display = 'grid';
            return;
        }
        const vehicle = data.vehicle;
        titleEl.textContent = vehicle.name || 'Assigned vehicle';
        valueEl.textContent = vehicle.license_plate || '-';

        const seats = vehicle.seats ? String(vehicle.seats) + ' seats' : 'Seats N/A';
        const serviceTierRaw = vehicle.service_tier || vehicle.tier || '';
        const serviceTier = serviceTierRaw ? String(serviceTierRaw) + ' tier' : 'Tier N/A';
        metaEl.textContent = seats + ' • ' + serviceTier;

        if (vehicle.image_url) {
            imgEl.src = vehicle.image_url;
            imgEl.style.display = 'block';
            fallbackEl.style.display = 'none';
        } else {
            imgEl.style.display = 'none';
            fallbackEl.style.display = 'grid';
        }
    } catch (e) {
        titleEl.textContent = 'Assigned Vehicle';
        valueEl.textContent = 'Unable to load vehicle';
        metaEl.textContent = 'Please reload in a moment.';
        imgEl.style.display = 'none';
        fallbackEl.style.display = 'grid';
    }
}

function renderCurrentOrders() {
    const loading = document.getElementById('currentOrdersLoading');
    const empty = document.getElementById('currentOrdersEmpty');
    const table = document.getElementById('currentOrdersTable');
    const body = document.getElementById('currentOrdersBody');

    loading.style.display = 'none';
    if (!currentOrders.length) {
        empty.style.display = 'block';
        table.style.display = 'none';
        return;
    }

    empty.style.display = 'none';
    table.style.display = 'table';

    const activeOnTrip = currentOrders.find(function(order) {
        return (order.status || '') === 'on_trip';
    });
    const activeOnTripBookingId = activeOnTrip ? String(activeOnTrip.booking_id || '') : '';

    body.innerHTML = currentOrders.map(function(order) {
        const status = order.status || 'on_route';
        const bookingId = String(order.booking_id || '');
        let actionBtn = '-';

        if (status === 'on_trip' && bookingId === activeOnTripBookingId) {
            actionBtn = '<button class="order-action-btn" data-booking-id="' + escapeHtml(order.booking_id) + '" data-target-status="completed">Complete Trip</button>';
        } else if (status === 'on_route' && !activeOnTripBookingId) {
            actionBtn = '<button class="order-action-btn" data-booking-id="' + escapeHtml(order.booking_id) + '" data-target-status="on_trip">Start Trip</button>';
        }

        return '<tr>' +
            '<td>' +
                '<div class="driver-passenger">' +
                    (order.passenger_avatar
                        ? '<img class="driver-passenger-avatar" src="' + escapeHtml(order.passenger_avatar) + '" alt="Passenger avatar">'
                        : '<div class="driver-passenger-fallback">' + escapeHtml(initialsFromName(order.passenger_name)) + '</div>') +
                    '<div>' +
                        '<button type="button" class="driver-passenger-link" data-passenger-id="' + escapeHtml(order.passenger_id || '') + '" data-passenger-name="' + escapeHtml(order.passenger_name || 'Passenger') + '"><div class="driver-route-main">' + escapeHtml(order.passenger_name) + '</div></button>' +
                        '<div class="driver-route-sub">Current</div>' +
                    '</div>' +
                '</div>' +
            '</td>' +
            '<td>' +
                '<div class="driver-route-row">' +
                    '<span class="driver-route-dot driver-route-dot-main"></span>' +
                    '<div class="driver-route-main">' + escapeHtml(order.pickup_location) + '</div>' +
                '</div>' +
                '<div class="driver-route-row">' +
                    '<span class="driver-route-dot driver-route-dot-sub"></span>' +
                    '<div class="driver-route-sub">' + escapeHtml(order.destination) + '</div>' +
                '</div>' +
            '</td>' +
            '<td>' + escapeHtml(formatDateTime(order.pickup_date, order.pickup_time)) + '</td>' +
            '<td>' + escapeHtml(formatMoney(order.price)) + '</td>' +
            '<td><span class="status-chip status-' + escapeHtml(status) + '">' + escapeHtml(status.replace('_', ' ')) + '</span></td>' +
            '<td>' + actionBtn + '</td>' +
        '</tr>';
    }).join('');

    document.querySelectorAll('.order-action-btn').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            if (btn.disabled) return;
            btn.disabled = true;
            const bookingId = btn.getAttribute('data-booking-id');
            const targetStatus = btn.getAttribute('data-target-status');

            try {
                const data = await driverRequest('advance_order_status', {
                    booking_id: bookingId,
                    target_status: targetStatus
                });

                if (!data.success) {
                    driverShowStatus(data.message || 'Unable to update status', 'error');
                    btn.disabled = false;
                    return;
                }

                driverShowStatus('Order updated to ' + data.status.replace('_', ' '), 'success');
                await Promise.all([loadCurrentOrders(), loadHistoryOrders()]);
            } catch (e) {
                driverShowStatus('Network error while updating order', 'error');
                btn.disabled = false;
            }
        });
    });

    document.querySelectorAll('.driver-passenger-link').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const passengerId = btn.getAttribute('data-passenger-id') || '';
            const passengerName = btn.getAttribute('data-passenger-name') || 'Passenger';
            const viewType = btn.getAttribute('data-view-type') || 'current';
            if (!passengerId) return;
            openPassengerOrdersModal(passengerId, passengerName, viewType);
        });
    });
}

function renderHistoryOrders() {
    const loading = document.getElementById('historyOrdersLoading');
    const empty = document.getElementById('historyOrdersEmpty');
    const table = document.getElementById('historyOrdersTable');
    const body = document.getElementById('historyOrdersBody');

    loading.style.display = 'none';
    if (!historyOrders.length) {
        empty.style.display = 'block';
        table.style.display = 'none';
        return;
    }

    empty.style.display = 'none';
    table.style.display = 'table';

    body.innerHTML = historyOrders.map(function(order) {
        return '<tr>' +
            '<td>' +
                '<div class="driver-passenger">' +
                    (order.passenger_avatar
                        ? '<img class="driver-passenger-avatar" src="' + escapeHtml(order.passenger_avatar) + '" alt="Passenger avatar">'
                        : '<div class="driver-passenger-fallback">' + escapeHtml(initialsFromName(order.passenger_name)) + '</div>') +
                    '<div>' +
                        '<button type="button" class="driver-passenger-link" data-passenger-id="' + escapeHtml(order.passenger_id || '') + '" data-passenger-name="' + escapeHtml(order.passenger_name || 'Passenger') + '" data-view-type="history"><div class="driver-route-main">' + escapeHtml(order.passenger_name) + '</div></button>' +
                        '<div class="driver-route-sub">History</div>' +
                    '</div>' +
                '</div>' +
            '</td>' +
            '<td>' +
                '<div class="driver-route-row">' +
                    '<span class="driver-route-dot driver-route-dot-main"></span>' +
                    '<div class="driver-route-main">' + escapeHtml(order.pickup_location) + '</div>' +
                '</div>' +
                '<div class="driver-route-row">' +
                    '<span class="driver-route-dot driver-route-dot-sub"></span>' +
                    '<div class="driver-route-sub">' + escapeHtml(order.destination) + '</div>' +
                '</div>' +
            '</td>' +
            '<td>' + escapeHtml(formatDateTime(order.pickup_date, order.pickup_time)) + '</td>' +
            '<td>' + escapeHtml(formatMoney(order.price)) + '</td>' +
            '<td><span class="status-chip status-completed">completed</span></td>' +
        '</tr>';
    }).join('');

    document.querySelectorAll('.driver-passenger-link').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const passengerId = btn.getAttribute('data-passenger-id') || '';
            const passengerName = btn.getAttribute('data-passenger-name') || 'Passenger';
            const viewType = btn.getAttribute('data-view-type') || 'history';
            if (!passengerId) return;
            openPassengerOrdersModal(passengerId, passengerName, viewType);
        });
    });
}

async function loadCurrentOrders() {
    document.getElementById('currentOrdersLoading').style.display = 'block';
    document.getElementById('currentOrdersEmpty').style.display = 'none';
    document.getElementById('currentOrdersTable').style.display = 'none';

    try {
        const data = await driverRequest('get_current_orders');
        currentOrders = data.success ? (data.orders || []) : [];
        document.getElementById('currentOrderCount').textContent = String(currentOrders.length);
        renderCurrentOrders();
    } catch (e) {
        currentOrders = [];
        document.getElementById('currentOrderCount').textContent = '0';
        renderCurrentOrders();
        driverShowStatus('Unable to load current orders', 'error');
    }
}

async function loadHistoryOrders() {
    document.getElementById('historyOrdersLoading').style.display = 'block';
    document.getElementById('historyOrdersEmpty').style.display = 'none';
    document.getElementById('historyOrdersTable').style.display = 'none';

    try {
        const data = await driverRequest('get_past_orders');
        historyOrders = data.success ? (data.orders || []) : [];
        document.getElementById('pastOrderCount').textContent = String(historyOrders.length);
        renderHistoryOrders();
    } catch (e) {
        historyOrders = [];
        document.getElementById('pastOrderCount').textContent = '0';
        renderHistoryOrders();
        driverShowStatus('Unable to load order history', 'error');
    }
}

document.addEventListener('DOMContentLoaded', async function() {
    await loadAssignedVehicle();
    await Promise.all([loadCurrentOrders(), loadHistoryOrders()]);

    const modal = document.getElementById('passengerOrdersModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closePassengerOrdersModal();
            }
        });
    }
});
