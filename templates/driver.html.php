<?php include __DIR__ . '/layout/header.html.php'; ?>

<section class="driver-shell">
    <div class="driver-hero">
        <div>
            <h1>Driver Operations Center</h1>
            <p>Manage your assigned orders and update trip progress in real time.</p>
        </div>
        <div id="driverAssignedVehicle" class="assigned-vehicle-card">
            <div class="av-label">Assigned Vehicle</div>
            <div class="av-value">Loading...</div>
        </div>
    </div>

    <div class="driver-summary">
        <article>
            <span>Current Orders</span>
            <strong id="currentOrderCount">0</strong>
        </article>
        <article>
            <span>Completed Orders</span>
            <strong id="pastOrderCount">0</strong>
        </article>
    </div>

    <div class="driver-tabs">
        <button id="tabCurrent" class="active" type="button" onclick="driverSwitchTab('current')">Current Orders</button>
        <button id="tabHistory" type="button" onclick="driverSwitchTab('history')">Order History</button>
    </div>

    <div id="driverStatusBar" class="driver-status-bar" style="display:none;"></div>

    <div id="panelCurrent" class="driver-panel active">
        <div id="currentOrdersLoading" class="driver-loading">Loading current orders...</div>
        <div id="currentOrdersEmpty" class="driver-empty" style="display:none;">No current orders assigned.</div>
        <div class="driver-table-wrap">
            <table id="currentOrdersTable" style="display:none;">
                <thead>
                    <tr>
                        <th>Passenger</th>
                        <th>Pickup</th>
                        <th>Destination</th>
                        <th>Pickup Time</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="currentOrdersBody"></tbody>
            </table>
        </div>
    </div>

    <div id="panelHistory" class="driver-panel">
        <div id="historyOrdersLoading" class="driver-loading">Loading order history...</div>
        <div id="historyOrdersEmpty" class="driver-empty" style="display:none;">No completed orders yet.</div>
        <div class="driver-table-wrap">
            <table id="historyOrdersTable" style="display:none;">
                <thead>
                    <tr>
                        <th>Passenger</th>
                        <th>Pickup</th>
                        <th>Destination</th>
                        <th>Pickup Time</th>
                        <th>Price</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="historyOrdersBody"></tbody>
            </table>
        </div>
    </div>
</section>

<style>
.driver-shell {
    max-width: 1200px;
    margin: 0 auto;
    padding: 96px 20px 36px;
}
.driver-hero {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: stretch;
    margin-bottom: 18px;
}
.driver-hero h1 {
    margin: 0;
    font-size: 2rem;
    color: #0f172a;
}
.driver-hero p {
    margin: 8px 0 0;
    color: #475569;
}
.assigned-vehicle-card {
    min-width: 280px;
    background: linear-gradient(135deg, #1d4ed8, #0ea5e9);
    color: #fff;
    border-radius: 12px;
    padding: 16px;
}
.av-label {
    font-size: 0.75rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    opacity: 0.9;
}
.av-value {
    margin-top: 8px;
    font-size: 1.02rem;
    font-weight: 700;
}
.driver-summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.driver-summary article {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
}
.driver-summary span {
    display: block;
    color: #64748b;
    font-size: 0.78rem;
    margin-bottom: 4px;
}
.driver-summary strong {
    font-size: 1.5rem;
    color: #0f172a;
}
.driver-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 14px;
}
.driver-tabs button {
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    padding: 10px 16px;
    border-radius: 999px;
    cursor: pointer;
    font-weight: 600;
}
.driver-tabs button.active {
    background: #0f172a;
    color: #fff;
    border-color: #0f172a;
}
.driver-status-bar {
    margin-bottom: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 0.9rem;
}
.driver-status-bar.success { background: #dcfce7; color: #166534; }
.driver-status-bar.error { background: #fee2e2; color: #991b1b; }
.driver-panel { display: none; }
.driver-panel.active { display: block; }
.driver-loading, .driver-empty {
    text-align: center;
    padding: 22px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #64748b;
}
.driver-table-wrap {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}
.driver-table-wrap table {
    width: 100%;
    border-collapse: collapse;
}
.driver-table-wrap th,
.driver-table-wrap td {
    padding: 12px;
    border-bottom: 1px solid #f1f5f9;
    text-align: left;
    font-size: 0.87rem;
}
.driver-table-wrap th {
    background: #f8fafc;
    color: #334155;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.status-chip {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
}
.status-on_route { background: #e0f2fe; color: #075985; }
.status-on_trip { background: #ede9fe; color: #5b21b6; }
.status-completed { background: #dcfce7; color: #166534; }
.order-action-btn {
    border: 0;
    border-radius: 8px;
    padding: 8px 12px;
    cursor: pointer;
    font-weight: 700;
    background: #0f172a;
    color: #fff;
}
.order-action-btn[disabled] {
    cursor: not-allowed;
    opacity: 0.6;
}
@media (max-width: 980px) {
    .driver-hero { flex-direction: column; }
    .assigned-vehicle-card { min-width: 0; }
    .driver-table-wrap { overflow-x: auto; }
    .driver-table-wrap table { min-width: 920px; }
}
</style>

<script>
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

function formatDateTime(input) {
    if (!input) return '-';
    const date = new Date(input);
    if (Number.isNaN(date.getTime())) return input;
    return date.toLocaleString('en-GB', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
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
    try {
        const data = await driverRequest('get_assigned_vehicle');
        if (!data.success || !data.vehicle) {
            box.querySelector('.av-value').textContent = 'No vehicle assigned';
            return;
        }
        const vehicle = data.vehicle;
        const label = (vehicle.name || 'Assigned vehicle') + ' | ' + (vehicle.license_plate || '-');
        box.querySelector('.av-value').textContent = label;
    } catch (e) {
        box.querySelector('.av-value').textContent = 'Unable to load vehicle';
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

    body.innerHTML = currentOrders.map(function(order) {
        const status = order.status || 'on_route';
        const next = nextActionStatus(status);
        const actionBtn = next
            ? '<button class="order-action-btn" data-booking-id="' + escapeHtml(order.booking_id) + '" data-target-status="' + escapeHtml(next) + '">' + escapeHtml(nextActionLabel(status)) + '</button>'
            : '-';

        return '<tr>' +
            '<td>' + escapeHtml(order.passenger_name) + '</td>' +
            '<td>' + escapeHtml(order.pickup_location) + '</td>' +
            '<td>' + escapeHtml(order.destination) + '</td>' +
            '<td>' + escapeHtml(formatDateTime(order.pickup_time)) + '</td>' +
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
            '<td>' + escapeHtml(order.passenger_name) + '</td>' +
            '<td>' + escapeHtml(order.pickup_location) + '</td>' +
            '<td>' + escapeHtml(order.destination) + '</td>' +
            '<td>' + escapeHtml(formatDateTime(order.pickup_time)) + '</td>' +
            '<td>' + escapeHtml(formatMoney(order.price)) + '</td>' +
            '<td><span class="status-chip status-completed">completed</span></td>' +
        '</tr>';
    }).join('');
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
});
</script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
