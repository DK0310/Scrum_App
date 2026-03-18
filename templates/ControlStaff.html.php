<?php include __DIR__ . '/layout/header.html.php'; ?>

<style>
    body { background: #f8fafc; }
    .ctrl-wrap { max-width: 1240px; margin: 0 auto; padding: 24px; }
    .ctrl-title { font-size: 1.8rem; color: #0f172a; margin-bottom: 6px; }
    .ctrl-sub { color: #64748b; margin-bottom: 20px; }
    .ctrl-tabs { display: flex; gap: 8px; margin-bottom: 16px; }
    .ctrl-tab { border: 1px solid #cbd5e1; background: #fff; color: #1e293b; padding: 9px 14px; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .ctrl-tab.active { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
    .ctrl-panel { display: none; }
    .ctrl-panel.active { display: block; }
    .ctrl-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); margin-bottom: 18px; }
    .ctrl-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .ctrl-input, .ctrl-select { padding: 9px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.92rem; }
    .ctrl-input { min-width: 180px; }
    .ctrl-btn { padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .ctrl-btn-primary { background: #1d4ed8; color: #fff; }
    .ctrl-btn-danger { background: #dc2626; color: #fff; }
    .ctrl-btn-muted { background: #e2e8f0; color: #0f172a; }
    .ctrl-grid { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 10px; }
    .ctrl-table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 0.9rem; }
    th { background: #f8fafc; color: #334155; }
    .ctrl-badge { padding: 4px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
    .ctrl-pending { background: #fef3c7; color: #92400e; }
    .ctrl-in-progress { background: #dbeafe; color: #1d4ed8; }
    .ctrl-done { background: #dcfce7; color: #166534; }
    .ctrl-status { color: #334155; font-size: 0.9rem; margin-top: 8px; }
    .ctrl-order-status-cell { min-width: 280px; }
    .ctrl-order-status-wrap { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .ctrl-order-actions { display: flex; align-items: center; gap: 6px; }

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
        .ctrl-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 600px) {
        .ctrl-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="ctrl-wrap">
    <h1 class="ctrl-title">Control Staff</h1>
    <p class="ctrl-sub">Review booking requests and control fleet vehicles.</p>

    <div class="ctrl-tabs">
        <button class="ctrl-tab active" data-tab="orders">Orders</button>
        <button class="ctrl-tab" data-tab="vehicles">Vehicles</button>
    </div>

    <section class="ctrl-panel active" id="ordersPanel">
        <div class="ctrl-card">
            <div class="ctrl-row">
                <label>Status:</label>
                <select id="ctrlOrderStatusFilter" class="ctrl-select">
                    <option value="all">All</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                </select>
                <button class="ctrl-btn ctrl-btn-primary" id="ctrlReloadOrders">Reload</button>
            </div>
            <div class="ctrl-status" id="ctrlOrderStatusMsg"></div>
        </div>

        <div class="ctrl-card ctrl-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Pickup Date</th>
                        <th>Total</th>
                        <th>Status & Actions</th>
                    </tr>
                </thead>
                <tbody id="ctrlOrdersTable">
                    <tr><td colspan="5">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="ctrl-panel" id="vehiclesPanel">
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
                    <option value="hatchback">Hatchback</option>
                    <option value="pickup">Pickup</option>
                    <option value="van">Van</option>
                    <option value="electric">Electric</option>
                    <option value="luxury">Luxury</option>
                </select>
                <select class="ctrl-select" id="ctrlServiceTier">
                    <option value="eco">Eco</option>
                    <option value="standard" selected>Standard</option>
                    <option value="luxury">Luxury</option>
                </select>
                <input class="ctrl-input" id="ctrlSeats" type="number" placeholder="Seats" value="5">
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

        <div class="ctrl-card ctrl-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Plate</th>
                        <th>Category</th>
                        <th>Tier</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="ctrlVehiclesTable">
                    <tr><td colspan="6">Loading...</td></tr>
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
