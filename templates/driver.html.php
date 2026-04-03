<?php include __DIR__ . '/layout/header.html.php'; ?>

<section class="driver-shell">
    <header class="driver-hero">
        <div class="driver-hero-left">
            <p class="driver-eyebrow">Fleet Command</p>
            <h1>Driver Performance</h1>
            <p>Handle active queue updates and complete trips with live dispatch sync.</p>
        </div>
        <div class="driver-hero-rating">
            <div class="driver-rate-icon">★</div>
            <div>
                <div class="driver-rate-label">Driver Rating</div>
                <div class="driver-rate-value">4.98 / 5.0</div>
            </div>
        </div>
    </header>

    <div class="driver-top-grid">
        <article id="driverAssignedVehicle" class="assigned-vehicle-card">
            <div class="assigned-vehicle-content">
                <div class="av-label">Current Assignment</div>
                <h2 class="av-title">Assigned Vehicle</h2>
                <div class="av-value">Loading...</div>
                <div class="av-meta" id="driverAssignedVehicleMeta">Waiting for vehicle data...</div>
            </div>
            <div class="assigned-vehicle-image-wrap">
                <img id="driverAssignedVehicleImg" class="assigned-vehicle-image" src="" alt="Assigned vehicle" style="display:none;">
                <div id="driverAssignedVehicleImgFallback" class="assigned-vehicle-image-fallback">No Image</div>
            </div>
        </article>

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
                        <th>Route</th>
                        <th>Time Schedule</th>
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
                        <th>Route</th>
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

<div id="passengerOrdersModal" class="driver-modal-overlay" style="display:none;">
    <div class="driver-modal" role="dialog" aria-modal="true" aria-labelledby="passengerOrdersTitle">
        <div class="driver-modal-header">
            <h3 id="passengerOrdersTitle">Passenger Orders</h3>
            <button type="button" class="driver-modal-close" onclick="closePassengerOrdersModal()">✕</button>
        </div>
        <div class="driver-modal-body" id="passengerOrdersBody">
            Loading passenger orders...
        </div>
    </div>
</div>

<style>
.driver-shell {
    max-width: 1280px;
    margin: 0 auto;
    padding: 96px 20px 40px;
    color: #191c1d;
}
.driver-hero {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    align-items: end;
    margin-bottom: 16px;
}
.driver-hero-left {
    max-width: 700px;
}
.driver-eyebrow {
    margin: 0 0 6px;
    font-size: 0.73rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-weight: 800;
    color: #005046;
}
.driver-hero h1 {
    margin: 0;
    font-size: 2.2rem;
    color: #191c1d;
}
.driver-hero p {
    margin: 8px 0 0;
    color: #3e4946;
}
.driver-hero-rating {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f2f4f4;
    border: 1px solid #d9e0de;
    border-radius: 14px;
    padding: 12px 14px;
}
.driver-rate-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #004f45;
    color: #fff;
    font-weight: 800;
}
.driver-rate-label {
    font-size: 0.72rem;
    color: #59605f;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
}
.driver-rate-value {
    font-weight: 800;
    font-size: 1rem;
}

.driver-top-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 14px;
    margin-bottom: 20px;
}
.assigned-vehicle-card {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    min-height: 220px;
    background: #ffffff;
    border: 1px solid #dce6e2;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 12px 40px rgba(0, 79, 69, 0.06);
}
.assigned-vehicle-content {
    padding: 18px;
}
.assigned-vehicle-image-wrap {
    position: relative;
    background: #e6ebea;
}
.assigned-vehicle-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.assigned-vehicle-image-fallback {
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
    color: #64748b;
    font-weight: 700;
}
.av-label {
    font-size: 0.7rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #005046;
    font-weight: 800;
}
.av-title {
    margin: 8px 0 4px;
    font-size: 1.55rem;
    color: #191c1d;
}
.av-value {
    margin-top: 2px;
    font-size: 0.98rem;
    font-weight: 700;
    color: #1f2937;
}
.av-meta {
    margin-top: 10px;
    color: #5f6665;
    font-size: 0.82rem;
}
.driver-summary {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}
.driver-summary article {
    background: #ffffff;
    border: 1px solid #dce6e2;
    border-radius: 14px;
    padding: 16px;
}
.driver-summary span {
    display: block;
    color: #5f6665;
    font-size: 0.78rem;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
}
.driver-summary strong {
    font-size: 2.2rem;
    color: #191c1d;
}
.driver-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 14px;
}
.driver-tabs button {
    border: 1px solid #c5cfcc;
    background: #fff;
    color: #27413c;
    padding: 11px 16px;
    border-radius: 999px;
    cursor: pointer;
    font-weight: 700;
}
.driver-tabs button.active {
    background: #004f45;
    color: #fff;
    border-color: #004f45;
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
    border: 1px solid #dce6e2;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 12px 40px rgba(0, 79, 69, 0.06);
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
    background: #f2f4f4;
    color: #44514e;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.driver-passenger {
    display: flex;
    align-items: center;
    gap: 10px;
}
.driver-passenger-avatar {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    object-fit: cover;
    background: #e2e8f0;
}
.driver-passenger-fallback {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    display: grid;
    place-items: center;
    background: #dde4e2;
    color: #005046;
    font-size: 0.72rem;
    font-weight: 800;
}
.driver-route-main {
    font-weight: 700;
    color: #1f2937;
}
.driver-route-sub {
    color: #64748b;
    font-size: 0.76rem;
}
.driver-route-row {
    display: flex;
    align-items: center;
    gap: 7px;
}
.driver-route-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    flex: 0 0 8px;
}
.driver-route-dot-main {
    background: #84d5c5;
}
.driver-route-dot-sub {
    background: #004f45;
}
.status-chip {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 0.66rem;
    line-height: 1;
    white-space: nowrap;
    font-weight: 700;
    text-transform: uppercase;
}
.status-on_route { background: #e0f2fe; color: #075985; }
.status-on_trip { background: #dbf8e8; color: #00695c; }
.status-completed { background: #dcfce7; color: #166534; }
.driver-passenger-link {
    border: none;
    background: transparent;
    padding: 0;
    margin: 0;
    cursor: pointer;
    text-align: left;
    color: inherit;
    font: inherit;
}
.driver-passenger-link .driver-route-main {
    text-decoration: underline;
    text-underline-offset: 2px;
}
.driver-passenger-link:hover .driver-route-main {
    color: #0b4f46;
}
.driver-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 1050;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.driver-modal {
    width: min(920px, 96vw);
    max-height: 88vh;
    overflow: hidden;
    border-radius: 14px;
    background: #fff;
    border: 1px solid #dce6e2;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.22);
}
.driver-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    border-bottom: 1px solid #e5e7eb;
}
.driver-modal-header h3 {
    margin: 0;
    font-size: 1rem;
    color: #111827;
}
.driver-modal-close {
    border: none;
    background: #f3f4f6;
    width: 30px;
    height: 30px;
    border-radius: 999px;
    cursor: pointer;
}
.driver-modal-body {
    padding: 14px 16px 18px;
    overflow: auto;
    max-height: calc(88vh - 62px);
}
.driver-modal-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
.driver-modal-section {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}
.driver-modal-section h4 {
    margin: 0;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 10px 12px;
    background: #f8fafc;
    color: #334155;
}
.driver-modal-list {
    margin: 0;
    padding: 0;
    list-style: none;
}
.driver-modal-list li {
    padding: 10px 12px;
    border-top: 1px solid #f1f5f9;
    font-size: 0.85rem;
    color: #1f2937;
}
.driver-modal-list li:first-child {
    border-top: none;
}
.driver-modal-item-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 4px;
}
.driver-modal-status {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.66rem;
    line-height: 1;
    white-space: nowrap;
    font-weight: 700;
    text-transform: uppercase;
}
.driver-modal-sub {
    display: block;
    margin-top: 4px;
    font-size: 0.75rem;
    color: #64748b;
}
.driver-modal-label {
    color: #334155;
    font-weight: 700;
}
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
    .driver-hero { flex-direction: column; align-items: flex-start; }
    .driver-top-grid { grid-template-columns: 1fr; }
    .assigned-vehicle-card { grid-template-columns: 1fr; }
    .assigned-vehicle-image-wrap { min-height: 180px; }
    .assigned-vehicle-card { min-width: 0; }
    .driver-table-wrap { overflow-x: auto; }
    .driver-table-wrap table { min-width: 980px; }
    .driver-modal-grid { grid-template-columns: 1fr; }
}
</style>

<script src="/resources/js/driver.js"></script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
