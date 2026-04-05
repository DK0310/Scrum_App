<?php include __DIR__ . '/layout/header.html.php'; ?>

<style>
    :root {
        --ctrl-ink: #191c1d;
        --ctrl-subtle: #3e4946;
        --ctrl-line: #bec9c5;
        --ctrl-bg: #f8fafa;
        --ctrl-card: #ffffff;
        --ctrl-primary: #004f45;
        --ctrl-primary-strong: #005046;
        --ctrl-primary-soft: #a0f2e1;
        --ctrl-panel: #f2f4f4;
        --ctrl-warn-bg: #fff2df;
        --ctrl-warn-tx: #8f5600;
        --ctrl-ok-bg: #dbf8e8;
        --ctrl-ok-tx: #00695c;
    }

    body {
        font-family: Inter, Segoe UI, sans-serif;
        background:
            radial-gradient(circle at 12% 18%, rgba(160, 242, 225, 0.33) 0%, rgba(160, 242, 225, 0) 36%),
            radial-gradient(circle at 88% 4%, rgba(0, 105, 92, 0.10) 0%, rgba(0, 105, 92, 0) 28%),
            var(--ctrl-bg);
        color: var(--ctrl-ink);
    }

    .ctrl-wrap {
        max-width: 1260px;
        margin: 0 auto;
        padding: 24px;
        padding-top: clamp(96px, 11vw, 118px);
        min-height: 100vh;
    }

    .ctrl-hero {
        display: grid;
        grid-template-columns: 1.4fr 1fr;
        gap: 18px;
        margin-bottom: 22px;
    }

    .ctrl-hero-main,
    .ctrl-hero-stats {
        background: linear-gradient(140deg, #ffffff 0%, #f4fbf9 100%);
        border: 1px solid var(--ctrl-line);
        border-radius: 16px;
        box-shadow: 0 12px 36px rgba(0, 79, 69, 0.08);
        padding: 18px;
    }

    .ctrl-eyebrow {
        display: inline-block;
        font-size: 0.76rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--ctrl-primary-strong);
        margin-bottom: 8px;
    }

    .ctrl-title {
        font-family: Manrope, Inter, Segoe UI, sans-serif;
        margin: 0;
        font-size: 2rem;
        line-height: 1.15;
        color: var(--ctrl-ink);
    }

    .ctrl-sub {
        margin: 8px 0 0;
        color: var(--ctrl-subtle);
    }

    .ctrl-hero-kpi {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .ctrl-kpi {
        background: #fff;
        border: 1px solid var(--ctrl-line);
        border-radius: 12px;
        padding: 12px;
    }

    .ctrl-kpi-label {
        font-size: 0.76rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #5f6665;
    }

    .ctrl-kpi-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: #13433d;
        margin-top: 4px;
    }

    .ctrl-kpi-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
        background: var(--ctrl-primary);
    }

    .ctrl-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .ctrl-tab {
        border: 1px solid #c5cfcc;
        background: #fff;
        color: #27413c;
        padding: 10px 14px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 700;
        transition: all .2s ease;
    }

    .ctrl-tab.active {
        background: var(--ctrl-primary);
        color: #fff;
        border-color: var(--ctrl-primary);
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(0, 79, 69, 0.24);
    }

    .ctrl-panel { display: none; }
    .ctrl-panel.active { display: block; }

    .ctrl-block-title {
        font-family: Manrope, Inter, Segoe UI, sans-serif;
        margin: 4px 0 10px;
        font-size: 1.3rem;
        color: #1a3833;
    }

    .ctrl-block-sub {
        margin: 0 0 16px;
        color: var(--ctrl-subtle);
        font-size: 0.94rem;
    }

    .ctrl-card {
        background: var(--ctrl-card);
        border: 1px solid var(--ctrl-line);
        border-radius: 14px;
        padding: 16px;
        box-shadow: 0 8px 28px rgba(0, 79, 69, 0.06);
        margin-bottom: 16px;
    }

    .ctrl-card-soft {
        background: var(--ctrl-panel);
    }

    .ctrl-row {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .ctrl-input,
    .ctrl-select {
        padding: 10px 11px;
        border: 1px solid #c8d7e8;
        border-radius: 10px;
        font-size: 0.92rem;
        color: #113b35;
        background: #fff;
    }

    .ctrl-input { min-width: 180px; }

    .ctrl-btn {
        padding: 9px 13px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 700;
    }

    .ctrl-btn-primary { background: linear-gradient(135deg, #004f45 0%, #00695c 100%); color: #fff; }
    .ctrl-btn-danger { background: #cc2f2f; color: #fff; }
    .ctrl-btn-muted { background: #e4ebea; color: #214740; }

    .ctrl-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(160px, 1fr));
        gap: 10px;
    }

    .ctrl-table-wrap {
        overflow-x: auto;
        border-radius: 12px;
        border: 1px solid var(--ctrl-line);
    }

    table { width: 100%; border-collapse: collapse; background: #fff; }
    th, td {
        padding: 11px 12px;
        border-bottom: 1px solid #ebf1f7;
        text-align: left;
        font-size: 0.9rem;
        vertical-align: middle;
    }

    th {
        background: #f2f4f4;
        color: #44514e;
        font-weight: 800;
        font-size: 0.77rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .ctrl-badge {
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: capitalize;
    }

    .ctrl-tier {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .ctrl-tier-eco {
        background: #dbf8e8;
        color: #00695c;
    }

    .ctrl-tier-standard {
        background: #dde4e2;
        color: #2e4a44;
    }

    .ctrl-tier-luxury {
        background: #ffe9cf;
        color: #8f5600;
    }

    .ctrl-vstatus {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.74rem;
        font-weight: 800;
    }

    .ctrl-vstatus::before {
        content: '';
        width: 7px;
        height: 7px;
        border-radius: 50%;
        display: inline-block;
    }

    .ctrl-vstatus-available {
        background: #dbf8e8;
        color: #00695c;
    }

    .ctrl-vstatus-available::before {
        background: #00695c;
    }

    .ctrl-vstatus-in-use,
    .ctrl-vstatus-booked {
        background: #e4f3ff;
        color: #1f5f95;
    }

    .ctrl-vstatus-in-use::before,
    .ctrl-vstatus-booked::before {
        background: #1f5f95;
    }

    .ctrl-vstatus-maintenance {
        background: #ffe3e3;
        color: #a22424;
    }

    .ctrl-vstatus-maintenance::before {
        background: #a22424;
    }

    .ctrl-vstatus-unavailable,
    .ctrl-vstatus-default {
        background: #edeff1;
        color: #4e5a61;
    }

    .ctrl-vstatus-unavailable::before,
    .ctrl-vstatus-default::before {
        background: #4e5a61;
    }

    .ctrl-pending { background: var(--ctrl-warn-bg); color: var(--ctrl-warn-tx); }
    .ctrl-in-progress { background: #deecff; color: #184ea8; }
    .ctrl-done { background: var(--ctrl-ok-bg); color: var(--ctrl-ok-tx); }
    .ctrl-service-free { background: #dbf8e8; color: #00695c; }
    .ctrl-service-on { background: #ffe3e3; color: #a22424; }
    .ctrl-status { color: #355176; font-size: 0.9rem; margin-top: 8px; }
    .ctrl-order-status-cell { min-width: 140px; }
    .ctrl-order-actions-cell { min-width: 210px; }
    .ctrl-order-status-wrap { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .ctrl-order-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

    .ctrl-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 10px;
    }

    .ctrl-search-wrap {
        position: relative;
        flex: 1;
        min-width: 240px;
    }

    .ctrl-search {
        width: 100%;
        padding: 10px 12px 10px 36px;
        border: 1px solid #c9d4d1;
        border-radius: 10px;
        background: #fff;
        color: #224740;
    }

    .ctrl-search-icon {
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        color: #6b7875;
        font-size: 0.9rem;
    }

    .ctrl-modal-overlay {
        position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45);
        display: none; align-items: center; justify-content: center; z-index: 9999;
    }
    .ctrl-modal-overlay.open { display: flex; }
    .ctrl-modal {
        width: min(760px, 94vw); max-height: 90vh; overflow: auto;
        background: #fff; border-radius: 14px; border: 1px solid #e2e8f0;
        box-shadow: 0 28px 65px rgba(2, 6, 23, 0.28);
    }
    .ctrl-modal-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 16px; border-bottom: 1px solid #e2e8f0;
    }
    .ctrl-modal-title { margin: 0; font-size: 1rem; color: #0f172a; }
    .ctrl-modal-close {
        border: none; background: #e2e8f0; color: #0f172a; border-radius: 8px;
        width: 32px; height: 32px; cursor: pointer;
    }
    .ctrl-modal-body { padding: 14px 16px; }
    .ctrl-modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .ctrl-kv {
        border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px;
        background: #f8fafc;
    }
    .ctrl-k { font-size: 0.75rem; color: #64748b; margin-bottom: 4px; }
    .ctrl-v { font-size: 0.9rem; color: #0f172a; font-weight: 600; word-break: break-word; }
    @media (max-width: 980px) {
        .ctrl-hero {
            grid-template-columns: 1fr;
        }

        .ctrl-hero-kpi {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .ctrl-grid {
            grid-template-columns: 1fr 1fr;
        }

        .ctrl-table-wrap.ctrl-responsive-table {
            border: none;
            box-shadow: none;
            overflow: visible;
            padding: 0;
        }

        .ctrl-table-wrap.ctrl-responsive-table table,
        .ctrl-table-wrap.ctrl-responsive-table thead,
        .ctrl-table-wrap.ctrl-responsive-table tbody,
        .ctrl-table-wrap.ctrl-responsive-table th,
        .ctrl-table-wrap.ctrl-responsive-table td,
        .ctrl-table-wrap.ctrl-responsive-table tr {
            display: block;
            width: 100%;
        }

        .ctrl-table-wrap.ctrl-responsive-table thead {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            border: 0;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
        }

        .ctrl-table-wrap.ctrl-responsive-table tbody tr {
            background: #fff;
            border: 1px solid var(--ctrl-line);
            border-radius: 12px;
            padding: 10px;
            margin-bottom: 10px;
            box-shadow: 0 8px 18px rgba(0, 79, 69, 0.05);
        }

        .ctrl-table-wrap.ctrl-responsive-table tbody td {
            border: none;
            border-bottom: 1px dashed #e2e8f0;
            padding: 8px 4px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            font-size: 0.86rem;
            text-align: right;
        }

        .ctrl-table-wrap.ctrl-responsive-table tbody td:last-child {
            border-bottom: none;
            padding-bottom: 2px;
        }

        .ctrl-table-wrap.ctrl-responsive-table tbody td::before {
            content: attr(data-label);
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: left;
            flex: 0 0 auto;
        }

        .ctrl-table-wrap.ctrl-responsive-table tbody td[colspan] {
            text-align: left;
            justify-content: flex-start;
            border-bottom: none;
            background: #f8fafc;
            border-radius: 10px;
            padding: 10px;
        }

        .ctrl-table-wrap.ctrl-responsive-table tbody td[colspan]::before {
            content: '';
            display: none;
        }

        .ctrl-order-actions,
        .ctrl-order-status-wrap {
            justify-content: flex-end;
        }

        .ctrl-order-actions-cell,
        .ctrl-table-wrap.ctrl-responsive-table tbody td[data-label="Action"],
        .ctrl-table-wrap.ctrl-responsive-table tbody td[data-label="Actions"] {
            display: block;
            text-align: left;
        }

        .ctrl-table-wrap.ctrl-responsive-table tbody td[data-label="Action"]::before,
        .ctrl-table-wrap.ctrl-responsive-table tbody td[data-label="Actions"]::before {
            display: block;
            margin-bottom: 8px;
        }

        .ctrl-table-wrap.ctrl-responsive-table tbody td[data-label="Action"] .ctrl-order-actions,
        .ctrl-table-wrap.ctrl-responsive-table tbody td[data-label="Actions"] .ctrl-order-actions {
            justify-content: flex-start;
        }

        .ctrl-table-wrap.ctrl-responsive-table tbody td[data-label="Action"] button,
        .ctrl-table-wrap.ctrl-responsive-table tbody td[data-label="Actions"] button,
        .ctrl-table-wrap.ctrl-responsive-table tbody td[data-label="Action"] select,
        .ctrl-table-wrap.ctrl-responsive-table tbody td[data-label="Actions"] select {
            width: 100%;
            max-width: none;
        }
    }

    @media (max-width: 600px) {
        .ctrl-wrap {
            padding: 16px;
            padding-top: 90px;
        }

        .ctrl-title {
            font-size: 1.64rem;
        }

        .ctrl-hero-kpi {
            grid-template-columns: 1fr;
        }

        .ctrl-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="ctrl-wrap">
    <div class="ctrl-hero">
        <div class="ctrl-hero-main">
            <span class="ctrl-eyebrow">Operations Desk</span>
            <h1 class="ctrl-title">Control Staff Workspace</h1>
            <p class="ctrl-sub">Manage trip orders, dispatch drivers, and maintain vehicle inventory from one control surface.</p>
        </div>
        <div class="ctrl-hero-stats">
            <div class="ctrl-hero-kpi">
                <div class="ctrl-kpi">
                    <div class="ctrl-kpi-label">Orders</div>
                    <div class="ctrl-kpi-value" id="ctrlKpiOrders">-</div>
                </div>
                <div class="ctrl-kpi">
                    <div class="ctrl-kpi-label">Drivers</div>
                    <div class="ctrl-kpi-value" id="ctrlKpiDrivers">-</div>
                </div>
                <div class="ctrl-kpi">
                    <div class="ctrl-kpi-label">Vehicles</div>
                    <div class="ctrl-kpi-value" id="ctrlKpiVehicles">-</div>
                </div>
            </div>
            <div class="ctrl-status" style="margin-top:10px;">
                <span class="ctrl-kpi-dot"></span>Live board synced with staff actions
            </div>
        </div>
    </div>

    <div class="ctrl-tabs">
        <button class="ctrl-tab active" data-tab="orders">Orders</button>
        <button class="ctrl-tab" data-tab="assign-driver">Assign Driver</button>
        <button class="ctrl-tab" data-tab="vehicles">Vehicles</button>
    </div>

    <section class="ctrl-panel active" id="ordersPanel">
        <h2 class="ctrl-block-title">Orders</h2>
        <p class="ctrl-block-sub">Track incoming requests and move active trips through the operational pipeline.</p>
        <div class="ctrl-card ctrl-card-soft">
            <div class="ctrl-row">
                <label>Status:</label>
                <select id="ctrlOrderStatusFilter" class="ctrl-select">
                    <option value="all">All</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <button class="ctrl-btn ctrl-btn-primary" id="ctrlReloadOrders">Reload</button>
            </div>
            <div class="ctrl-status" id="ctrlOrderStatusMsg"></div>
        </div>

        <div class="ctrl-card ctrl-table-wrap ctrl-responsive-table">
            <div class="ctrl-toolbar" style="padding:12px 12px 0;">
                <div class="ctrl-search-wrap">
                    <span class="ctrl-search-icon">&#128269;</span>
                    <input class="ctrl-search" id="ctrlOrderSearch" type="text" placeholder="Filter orders...">
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Pickup Date</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ctrlOrdersTable">
                    <tr><td colspan="6">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="ctrl-panel" id="assignDriverPanel">
        <h2 class="ctrl-block-title">Assign Driver</h2>
        <p class="ctrl-block-sub">Dispatch pending drivers to available vehicles and release assignments when needed.</p>
        <div class="ctrl-card ctrl-card-soft">
            <div class="ctrl-row">
                <label>Driver Status:</label>
                <select id="ctrlDriverStatusFilter" class="ctrl-select">
                    <option value="all">All</option>
                    <option value="pending">Pending</option>
                    <option value="dispatched">Dispatched</option>
                </select>
                <button class="ctrl-btn ctrl-btn-primary" id="ctrlReloadDrivers">Reload</button>
            </div>
            <div class="ctrl-status" id="ctrlDriverStatusMsg"></div>
        </div>

        <div class="ctrl-card ctrl-table-wrap ctrl-responsive-table">
            <div class="ctrl-toolbar" style="padding:12px 12px 0;">
                <div class="ctrl-search-wrap">
                    <span class="ctrl-search-icon">&#128269;</span>
                    <input class="ctrl-search" id="ctrlDriverSearch" type="text" placeholder="Search driver or assigned plate...">
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>Contact</th>
                        <th>Assigned Vehicle</th>
                        <th>Dispatch</th>
                        <th>Service State</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="ctrlDriversTable">
                    <tr><td colspan="6">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="ctrl-panel" id="vehiclesPanel">
        <h2 class="ctrl-block-title">Vehicles</h2>
        <p class="ctrl-block-sub">Create and maintain fleet records, including service tier, seats, and vehicle details.</p>
        <div class="ctrl-card">
            <h2 style="margin:0 0 10px 0;">Add / Edit Vehicle</h2>
            <input type="hidden" id="ctrlVehicleId">
            <div class="ctrl-grid">
                <input class="ctrl-input" id="ctrlBrand" placeholder="Brand">
                <input class="ctrl-input" id="ctrlModel" placeholder="Model">
                <input class="ctrl-input" id="ctrlYear" type="number" placeholder="Year">
                <input class="ctrl-input" id="ctrlLicense" placeholder="License Plate">
                <select class="ctrl-select" id="ctrlCategory">
                    <option value="sedan" selected>Sedan</option>
                    <option value="suv">SUV</option>
                    <option value="luxury">Luxury</option>
                    <option value="electric">Electric</option>
                    <option value="hybrid">Hybrid</option>
                </select>
                <select class="ctrl-select" id="ctrlServiceTier">
                    <option value="eco">Eco</option>
                    <option value="standard" selected>Standard</option>
                    <option value="luxury">Luxury</option>
                </select>
                <select class="ctrl-select" id="ctrlSeats">
                    <option value="5" selected>4 seats</option>
                    <option value="7">7 seats</option>
                </select>
                <input class="ctrl-input" id="ctrlLuggageCapacity" type="number" min="1" step="1" placeholder="Luggage Capacity (lbs)">
                <input class="ctrl-input" id="ctrlColor" placeholder="Color">
                <input class="ctrl-input" id="ctrlVehicleImage" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
            </div>
            <div class="ctrl-status">Vehicle image is optional. Supported: JPG, PNG, WebP, GIF (max 5MB).</div>
            <div class="ctrl-row" style="margin-top:12px;">
                <button class="ctrl-btn ctrl-btn-primary" id="ctrlSaveVehicle">Save</button>
                <button class="ctrl-btn ctrl-btn-muted" id="ctrlResetVehicle">Reset</button>
            </div>
            <div class="ctrl-status" id="ctrlVehicleStatusMsg"></div>
        </div>

        <div class="ctrl-card ctrl-table-wrap ctrl-responsive-table">
            <div class="ctrl-toolbar" style="padding:12px 12px 0;">
                <div class="ctrl-search-wrap">
                    <span class="ctrl-search-icon">&#128269;</span>
                    <input class="ctrl-search" id="ctrlVehicleSearch" type="text" placeholder="Filter fleet...">
                </div>
                <select class="ctrl-select" id="ctrlVehicleTierFilter" style="max-width:170px;">
                    <option value="all">All Tiers</option>
                    <option value="eco">Eco</option>
                    <option value="standard">Standard</option>
                    <option value="luxury">Luxury</option>
                </select>
                <select class="ctrl-select" id="ctrlVehicleSeatsFilter" style="max-width:160px;">
                    <option value="all">All Seats</option>
                    <option value="4">4 seats</option>
                    <option value="7">7 seats</option>
                </select>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Plate</th>
                        <th>Seat</th>
                        <th>Category</th>
                        <th>Tier</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="ctrlVehiclesTable">
                    <tr><td colspan="7">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="ctrl-modal-overlay" id="ctrlOrderModal">
    <div class="ctrl-modal">
        <div class="ctrl-modal-header">
            <h3 class="ctrl-modal-title" id="ctrlOrderModalTitle">Order Details</h3>
            <button type="button" class="ctrl-modal-close" id="ctrlOrderModalClose">✕</button>
        </div>
        <div class="ctrl-modal-body" id="ctrlOrderModalBody"></div>
    </div>
</div>

<script src="/resources/js/control-staff.js"></script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
