<?php
/**
 * Staff Dashboard Template
 * Requires: $userRole, $currentUser, $isLoggedIn (set by public/staff.php)
 */
?>
<?php include __DIR__ . '/layout/header.html.php'; ?>

<style>
    body { background: #f8fafc; }

    .dashboard-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .dashboard-header { margin-bottom: 30px; }
    .dashboard-header h1 {
        color: #1e293b;
        font-size: 2rem;
        margin: 0 0 10px 0;
    }
    .dashboard-header p { color: #64748b; font-size: 0.95rem; }

    /* Tabs */
    .dashboard-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        border-bottom: 2px solid #e2e8f0;
    }
    .dashboard-tabs button {
        padding: 12px 20px;
        border: none;
        background: none;
        cursor: pointer;
        font-size: 1rem;
        color: #64748b;
        border-bottom: 3px solid transparent;
        transition: all 0.3s;
        font-weight: 500;
    }
    .dashboard-tabs button.active {
        color: #2563eb;
        border-bottom-color: #2563eb;
    }
    .dashboard-tabs button:hover { color: #1e293b; }

    /* Tab Content */
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* Cards Grid */
    .card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.3s;
    }
    .card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }
    .card-icon { font-size: 2rem; margin-bottom: 12px; }
    .card-title { font-weight: 600; color: #1e293b; margin: 12px 0; }
    .card-desc { font-size: 0.875rem; color: #64748b; margin-bottom: 16px; }

    /* Stats */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        border-left: 4px solid #2563eb;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .stat-card.green { border-left-color: #16a34a; }
    .stat-card.orange { border-left-color: #ea580c; }
    .stat-card.red { border-left-color: #dc2626; }
    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 8px;
    }
    .stat-label { font-size: 0.875rem; color: #64748b; }

    /* Table */
    .table-container {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    table { width: 100%; border-collapse: collapse; }
    thead { background: #f1f5f9; border-bottom: 1px solid #e2e8f0; }
    th {
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: #475569;
        font-size: 0.875rem;
    }
    td {
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
    }
    tr:hover { background: #f8fafc; }

    /* Buttons */
    .btn-sm {
        padding: 8px 16px;
        font-size: 0.875rem;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 500;
    }
    .btn-primary-sm { background: #2563eb; color: white; }
    .btn-primary-sm:hover { background: #1d4ed8; }
    .btn-secondary-sm { background: #e2e8f0; color: #334155; }
    .btn-secondary-sm:hover { background: #cbd5e1; }
    .btn-danger-sm { background: #ef4444; color: white; }
    .btn-danger-sm:hover { background: #dc2626; }

    /* Loading */
    .loading { text-align: center; padding: 40px; color: #64748b; }

    .spinner {
        display: inline-block;
        width: 40px;
        height: 40px;
        border: 4px solid #e2e8f0;
        border-top-color: #2563eb;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    .modal.active { display: flex; }
    .modal-content {
        background: white;
        border-radius: 12px;
        padding: 24px;
        max-width: 860px;
        width: min(92vw, 860px);
        max-height: 86vh;
        overflow: auto;
        box-shadow: 0 20px 25px rgba(0,0,0,0.15);
    }
    .modal-header {
        font-size: 1.125rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #94a3b8;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }
    @media (max-width: 860px) {
        .form-grid { grid-template-columns: 1fr; }
    }

    .form-group { margin-bottom: 10px; }
    .form-label {
        display: block;
        margin-bottom: 6px;
        color: #334155;
        font-weight: 600;
        font-size: 0.8125rem;
    }
    .form-input, .form-select, .form-textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.95rem;
        font-family: inherit;
        transition: all 0.2s;
        background: white;
    }
    .form-textarea { min-height: 88px; resize: vertical; }
    .form-input:focus, .form-select:focus, .form-textarea:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }

    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 14px;
    }

    .muted-help { color: #64748b; font-size: 0.8125rem; line-height: 1.45; }

    /* Simple modal for assign / order details */
    .mini-modal { position: fixed; inset: 0; display: none; z-index: 1000; }
    .mini-modal.active { display: block; }
    .mini-modal .overlay { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.55); }
    .mini-modal .panel {
        position: relative;
        max-width: 560px;
        margin: 80px auto;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }

    /* Prevent booking detail popup from overflowing viewport */
    .mini-modal .panel {
        width: min(92vw, 720px);
        max-width: 720px;
        max-height: min(86vh, 720px);
        margin: 6vh auto;
        display: flex;
        flex-direction: column;
    }
    .mini-modal .panel-body {
        padding: 16px;
        overflow: auto;
        -webkit-overflow-scrolling: touch;
    }
    .mini-modal .panel-actions {
        padding: 14px 16px;
        border-top: 1px solid #e2e8f0;
        display:flex;
        justify-content:flex-end;
        gap:10px;
        flex: 0 0 auto;
    }

    .mini-modal .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 16px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 700;
        color: #0f172a;
        flex: 0 0 auto;
    }

    .mini-modal .close-btn { background: none; border: none; font-size: 18px; cursor: pointer; color:#64748b; }

    /* Make key/value table wrap instead of pushing width */
    .kv {
        display: grid;
        grid-template-columns: 180px 1fr;
        gap: 8px 12px;
        font-size: 0.9rem;
        min-width: 0;
    }
    @media (max-width: 520px) {
        .kv { grid-template-columns: 1fr; }
        .kv .k { font-weight: 700; }
    }
    .kv div { padding: 6px 0; border-bottom: 1px dashed #e2e8f0; min-width: 0; }
    .kv .k { color:#64748b; }
    .kv .v {
        color:#0f172a;
        font-weight:600;
        overflow-wrap: anywhere;
        word-break: break-word;
        white-space: normal;
    }

    /* Optional: prevent background scroll when modal is open */
    body.modal-open { overflow: hidden; }
</style>

<div class="dashboard-container">
    <!-- Header -->
    <div class="dashboard-header">
        <h1>👨 Staff Dashboard</h1>
        <p>Manage drivers, vehicles, and monitor orders</p>
    </div>

    <!-- Tabs -->
    <div class="dashboard-tabs">
        <button class="tab-btn active" onclick="switchTab('overview')">📊 Overview</button>
        <button class="tab-btn" onclick="switchTab('drivers')">👥 Drivers</button>
        <button class="tab-btn" onclick="switchTab('vehicles')">🚗 Vehicles</button>
        <button class="tab-btn" onclick="switchTab('orders')">📦 Orders</button>
        <button class="tab-btn" onclick="switchTab('phone-booking')">📞 Phone Booking</button>
    </div>

    <!-- TAB 1: Overview -->
    <div id="overview" class="tab-content active">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="totalDrivers">0</div>
                <div class="stat-label">Active Drivers</div>
            </div>
            <div class="stat-card green">
                <div class="stat-value" id="totalVehicles">0</div>
                <div class="stat-label">Total Vehicles</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-value" id="availableVehicles">0</div>
                <div class="stat-label">Available Now</div>
            </div>
            <div class="stat-card red">
                <div class="stat-value" id="rentedVehicles">0</div>
                <div class="stat-label">Rented</div>
            </div>
        </div>

        <div class="card-grid">
            <div class="card">
                <div class="card-icon">🚗</div>
                <div class="card-title">Vehicle Management</div>
                <div class="card-desc">Add, edit, or remove vehicles from your fleet</div>
                <button class="btn-sm btn-primary-sm" onclick="switchTab('vehicles')">Manage Vehicles</button>
            </div>
            <div class="card">
                <div class="card-icon">👥</div>
                <div class="card-title">Driver Management</div>
                <div class="card-desc">Assign vehicles to drivers and manage assignments</div>
                <button class="btn-sm btn-primary-sm" onclick="switchTab('drivers')">Manage Drivers</button>
            </div>
            <div class="card">
                <div class="card-icon">📋</div>
                <div class="card-title">View Orders</div>
                <div class="card-desc">Monitor all booking orders and trip status</div>
                <button class="btn-sm btn-primary-sm" onclick="switchTab('orders')">View Orders</button>
            </div>
        </div>
    </div>

    <!-- TAB 2: Drivers -->
    <div id="drivers" class="tab-content">
        <div style="margin-bottom: 20px;">
            <h2 style="color: #1e293b; margin-bottom: 16px;">Active Drivers</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Driver Name</th>
                            <th>Phone</th>
                            <th>Assigned Vehicle</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="driversTable">
                        <tr>
                            <td colspan="5" class="loading">
                                <div class="spinner"></div>     
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: Vehicles -->
    <div id="vehicles" class="tab-content">
        <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="color: #1e293b; margin: 0;">Fleet Management</h2>
            <button class="btn-sm btn-primary-sm" onclick="openAddVehicleModal()">+ Add Vehicle</button>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Brand & Model</th>
                        <th>License Plate</th>
                        <th>Category</th>
                        <th>Price/Day</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="vehiclesTable">
                    <tr>
                        <td colspan="6" class="loading">
                            <div class="spinner"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 4: Orders -->
    <div id="orders" class="tab-content">
        <div style="margin-bottom: 20px;">
            <h2 style="color: #1e293b; margin-bottom: 16px;">Booking Orders</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Driver</th>
                            <th>Pickup Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTable">
                        <tr>
                            <td colspan="7" class="loading">
                                <div class="spinner"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB: Phone Booking -->
    <div id="phone-booking" class="tab-content">
        <div class="card" style="padding:18px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <div class="card-title" style="margin:0;">📞 Booking by Request</div>
                    <div class="card-desc" style="margin-top:6px;">Create a booking on behalf of a customer calling by phone. Select an existing customer or enter details manually.</div>
                </div>
                <div id="phoneBookingStatus" style="font-size:0.9rem;color:var(--gray-600);"></div>
            </div>

            <form id="phoneBookingForm" onsubmit="submitPhoneBooking(event)" style="margin-top:16px;">
                <div class="form-grid">
                    <div>
                        <label class="form-label">Customer (search & select)</label>
                        <input class="form-input" id="customerSearch" placeholder="Type name / email / phone…" autocomplete="off" oninput="debouncedSearchCustomers()" />
                        <div id="customerResults" style="margin-top:8px;display:none;border:1px solid var(--gray-200);border-radius:10px;overflow:hidden;"></div>
                        <p class="muted-help" style="margin-top:8px;">Tip: pick a customer from results to auto-fill name/email/phone.</p>
                    </div>

                    <div>
                        <label class="form-label">Selected customer</label>
                        <select class="form-select" id="customerSelect" onchange="onCustomerSelected()">
                            <option value="">— Manual entry —</option>
                        </select>
                        <p class="muted-help" style="margin-top:8px;">If customer is not registered, leave Manual entry and fill fields below.</p>
                    </div>

                    <div>
                        <label class="form-label">Customer name *</label>
                        <input class="form-input" id="customerName" required />
                    </div>
                    <div>
                        <label class="form-label">Customer phone *</label>
                        <input class="form-input" id="customerPhone" required />
                    </div>
                    <div>
                        <label class="form-label">Customer email *</label>
                        <input class="form-input" id="customerEmail" type="email" required />
                    </div>

                    <div>
                        <label class="form-label">Vehicle *</label>
                        <select class="form-select" id="vehicleSelect" required>
                            <option value="">Loading vehicles…</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Pickup date *</label>
                        <input class="form-input" id="pickupDate" type="date" required />
                    </div>
                    <div>
                        <label class="form-label">Return date *</label>
                        <input class="form-input" id="returnDate" type="date" required />
                    </div>

                    <div>
                        <label class="form-label">Pickup location *</label>
                        <input class="form-input" id="pickupLocation" required />
                    </div>
                    <div>
                        <label class="form-label">Return location</label>
                        <input class="form-input" id="returnLocation" />
                    </div>

                    <div>
                        <label class="form-label">Initial status</label>
                        <select class="form-select" id="initialStatus">
                            <option value="pending">Pending (recommended)</option>
                            <option value="confirmed">Confirmed</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Payment method</label>
                        <select class="form-select" id="paymentMethod">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="credit_card">Credit card</option>
                            <option value="paypal">PayPal</option>
                        </select>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label class="form-label">Special requests / note</label>
                        <textarea class="form-textarea" id="specialRequests" placeholder="Optional…"></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-sm btn-secondary-sm" onclick="resetPhoneBookingForm()">Reset</button>
                    <button type="submit" class="btn-sm btn-primary-sm" id="phoneBookingSubmitBtn">Create Booking</button>
                </div>

                <div id="phoneBookingResult" style="margin-top:14px;display:none;padding:12px 14px;border-radius:10px;border:1px solid var(--gray-200);"></div>
            </form>
        </div>
    </div>
</div>

<!-- Add Vehicle Modal -->
<div class="modal" id="addVehicleModal">
    <div class="modal-content" style="max-width:720px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header" style="justify-content: space-between;">
            <span id="staffVehicleModalTitle">➕ Add New Vehicle</span>
            <button class="modal-close" onclick="closeAddVehicleModal()">✕</button>
        </div>

        <input type="hidden" id="staffEditVehicleId" value="">

        <!-- Row 1: Brand, Model, Year -->
        <div style="display:grid;grid-template-columns:1fr 1fr 120px;gap:12px;margin-bottom:16px;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Brand *</label>
                <input type="text" class="form-input" id="vBrand" placeholder="e.g. Toyota, BMW...">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Model *</label>
                <input type="text" class="form-input" id="vModel" placeholder="e.g. Camry, 3 Series...">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Year *</label>
                <input type="number" class="form-input" id="vYear" placeholder="2025" min="1990" max="2030">
            </div>
        </div>

        <!-- Row 2: License, Category, Color -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">License Plate *</label>
                <input type="text" class="form-input" id="vLicensePlate" placeholder="e.g. 51A-12345">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Category</label>
                <select class="form-select" id="vCategory">
                    <option value="sedan">Sedan</option>
                    <option value="suv">SUV</option>
                    <option value="luxury">Luxury</option>
                    <option value="sports">Sports</option>
                    <option value="electric">Electric</option>
                    <option value="van">Van / Minibus</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Color</label>
                <input type="text" class="form-input" id="vColor" placeholder="e.g. White, Black...">
            </div>
        </div>

        <!-- Row 3: Transmission, Fuel, Seats -->
        <div style="display:grid;grid-template-columns:1fr 1fr 100px;gap:12px;margin-bottom:16px;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Transmission</label>
                <select class="form-select" id="vTransmission">
                    <option value="automatic">Automatic</option>
                    <option value="manual">Manual</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Fuel Type</label>
                <select class="form-select" id="vFuelType">
                    <option value="petrol">Petrol</option>
                    <option value="diesel">Diesel</option>
                    <option value="electric">Electric</option>
                    <option value="hybrid">Hybrid</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Seats</label>
                <input type="number" class="form-input" id="vSeats" value="5" min="2" max="50">
            </div>
        </div>

        <!-- Row 4: Engine, Consumption -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;" id="engineConsumptionRow">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" id="engineLabel">Engine Size</label>
                <div style="position:relative;">
                    <input type="text" class="form-input" id="vEngine" placeholder="e.g. 2, 3.5" oninput="autoFormatEngine(this)" style="padding-right:40px;">
                    <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.875rem;pointer-events:none;" id="engineSuffix">L</span>
                </div>
                <small class="muted-help" id="engineHint">Enter number only, e.g. "2" → 2L</small>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" id="consumptionLabel">Consumption</label>
                <div style="position:relative;">
                    <input type="text" class="form-input" id="vConsumption" placeholder="e.g. 9.72" oninput="autoFormatConsumption(this)" style="padding-right:80px;">
                    <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.875rem;pointer-events:none;" id="consumptionSuffix">L/100km</span>
                </div>
                <small class="muted-help" id="consumptionHint">Enter number only, e.g. "9.72" → 9.72L/100km</small>
            </div>
        </div>

        <!-- Row 5: Pricing -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Price/Day ($) *</label>
                <input type="number" class="form-input" id="vPriceDay" placeholder="65" min="1" step="0.01">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Price/Week ($)</label>
                <input type="number" class="form-input" id="vPriceWeek" placeholder="400" min="0" step="0.01">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Price/Month ($)</label>
                <input type="number" class="form-input" id="vPriceMonth" placeholder="1500" min="0" step="0.01">
            </div>
        </div>

        <!-- Row 6: Location -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">City</label>
                <input type="text" class="form-input" id="vCity" placeholder="e.g. Ho Chi Minh City">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Address</label>
                <input type="text" class="form-input" id="vAddress" placeholder="Full pickup address">
            </div>
        </div>

        <!-- Features -->
        <div class="form-group">
            <label class="form-label">Features (select all that apply)</label>
            <div style="display:flex;flex-wrap:wrap;gap:8px;" id="featureCheckboxes">
                <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:0.875rem;">
                    <input type="checkbox" value="GPS" class="vFeature"> 📍 GPS
                </label>
                <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:0.875rem;">
                    <input type="checkbox" value="A/C" class="vFeature"> ❄️ A/C
                </label>
                <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:0.875rem;">
                    <input type="checkbox" value="Bluetooth" class="vFeature"> 🎵 Bluetooth
                </label>
                <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:0.875rem;">
                    <input type="checkbox" value="Backup Camera" class="vFeature"> 📷 Backup Camera
                </label>
                <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:0.875rem;">
                    <input type="checkbox" value="4WD" class="vFeature"> 🏔️ 4WD
                </label>
                <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:0.875rem;">
                    <input type="checkbox" value="Sunroof" class="vFeature"> ☀️ Sunroof
                </label>
                <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:0.875rem;">
                    <input type="checkbox" value="Autopilot" class="vFeature"> 🤖 Autopilot
                </label>
                <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:0.875rem;">
                    <input type="checkbox" value="Child Seat" class="vFeature"> 👶 Child Seat
                </label>
            </div>
        </div>

        <!-- Image Upload -->
        <div class="form-group">
            <label class="form-label">Vehicle Images</label>
            <div style="border:2px dashed #cbd5e1;border-radius:12px;padding:24px;text-align:center;cursor:pointer;" id="imageDropZone" onclick="document.getElementById('imageInput').click()">
                <div style="font-size:2rem;margin-bottom:8px;">📸</div>
                <p class="muted-help" style="margin:0;">Click to upload or drag & drop images here</p>
                <p class="muted-help" style="margin:4px 0 0 0;">JPEG, PNG, WebP — Max 5MB each</p>
            </div>
            <input type="file" id="imageInput" accept="image/*" multiple style="display:none" onchange="handleImageUpload(this)">
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;" id="imagePreviewList"></div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn-sm btn-secondary-sm" onclick="closeAddVehicleModal()">Cancel</button>
            <button type="button" class="btn-sm btn-primary-sm" id="staffVehicleSubmitBtn" onclick="submitAddVehicle(event)">➕ Add Vehicle</button>
        </div>
    </div>
</div>

<!-- Assign Vehicle Modal -->
<div class="mini-modal" id="assignVehicleModal">
    <div class="overlay" onclick="closeAssignVehicleModal()"></div>
    <div class="panel">
        <div class="panel-header">
            <span>🚙 Assign Vehicle</span>
            <button class="close-btn" onclick="closeAssignVehicleModal()">✕</button>
        </div>
        <div class="panel-body">
            <input type="hidden" id="assignDriverId" value="">
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Driver</label>
                <input class="form-input" id="assignDriverName" readonly style="background: var(--gray-50);">
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Vehicle (available)</label>
                <select class="form-select" id="assignVehicleSelect"></select>
                <small class="muted-help">Only vehicles with status <strong>available</strong> are listed.</small>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Assigned date</label>
                <input type="date" class="form-input" id="assignDate" value="">
            </div>
        </div>
        <div class="panel-actions">
            <button class="btn-sm btn-secondary-sm" onclick="closeAssignVehicleModal()">Cancel</button>
            <button class="btn-sm btn-success-sm" id="assignSubmitBtn" onclick="submitAssignment()">Assign</button>
        </div>
    </div>
</div>

<!-- Order Detail Modal -->
<div class="mini-modal" id="orderDetailModal">
    <div class="overlay" onclick="closeOrderDetailModal()"></div>
    <div class="panel" style="max-width:720px;">
        <div class="panel-header">
            <span>📋 Booking Detail</span>
            <button class="close-btn" onclick="closeOrderDetailModal()">✕</button>
        </div>
        <div class="panel-body">
            <div id="orderDetailBody" class="assigned-vehicle-empty">Loading...</div>
        </div>
        <div class="panel-actions">
            <button class="btn-sm btn-secondary-sm" onclick="closeOrderDetailModal()">Close</button>
        </div>
    </div>
</div>

<script>
    // Staff dashboard is served from /public, so use absolute path to API
    const API_BASE = '/api/staff.php';
    const VEHICLES_API = '/api/vehicles.php';

    let uploadedImages = []; // [{id, url}]

    // ===== ELECTRIC CATEGORY DETECTION (same as My Vehicles UI) =====
    function isElectricMode() {
        return (document.getElementById('vCategory')?.value || '') === 'electric';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const cat = document.getElementById('vCategory');
        if (cat) {
            cat.addEventListener('change', function() {
                updateFieldsForCategory(this.value);
            });
            updateFieldsForCategory(cat.value || 'sedan');
        }

        loadVehiclesForPhoneBooking();
    });

    function updateFieldsForCategory(category) {
        const engineLabel = document.getElementById('engineLabel');
        const engineSuffix = document.getElementById('engineSuffix');
        const engineHint = document.getElementById('engineHint');
        const engineInput = document.getElementById('vEngine');

        const consumptionLabel = document.getElementById('consumptionLabel');
        const consumptionSuffix = document.getElementById('consumptionSuffix');
        const consumptionHint = document.getElementById('consumptionHint');
        const consumptionInput = document.getElementById('vConsumption');

        const fuelSelect = document.getElementById('vFuelType');
        const transSelect = document.getElementById('vTransmission');

        if (!engineLabel || !engineSuffix || !engineHint || !engineInput || !consumptionLabel || !consumptionSuffix || !consumptionHint || !consumptionInput) {
            return;
        }

        if (category === 'electric') {
            engineLabel.textContent = 'Battery Range';
            engineSuffix.textContent = 'Km';
            engineHint.textContent = 'Enter number only, e.g. "400" → 400Km';
            engineInput.placeholder = 'e.g. 400, 550';
            engineInput.value = '';

            consumptionLabel.textContent = 'Energy Consumption';
            consumptionSuffix.textContent = 'Wh/Km';
            consumptionHint.textContent = 'Enter number only, e.g. "150" → 150Wh/Km';
            consumptionInput.placeholder = 'e.g. 150, 180';
            consumptionInput.value = '';

            if (fuelSelect) fuelSelect.value = 'electric';
            if (transSelect) transSelect.value = 'automatic';
        } else {
            engineLabel.textContent = 'Engine Size';
            engineSuffix.textContent = 'L';
            engineHint.textContent = 'Enter number only, e.g. "2" → 2L';
            engineInput.placeholder = 'e.g. 2, 3.5';

            consumptionLabel.textContent = 'Consumption';
            consumptionSuffix.textContent = 'L/100km';
            consumptionHint.textContent = 'Enter number only, e.g. "9.72" → 9.72L/100km';
            consumptionInput.placeholder = 'e.g. 9.72';

            if (fuelSelect && fuelSelect.value === 'electric') fuelSelect.value = 'petrol';
        }
    }

    function autoFormatEngine(input) {
        let val = (input.value || '').replace(/[^0-9.]/g, '');
        const parts = val.split('.');
        if (parts.length > 2) val = parts[0] + '.' + parts.slice(1).join('');
        input.value = val;
    }

    function autoFormatConsumption(input) {
        let val = (input.value || '').replace(/[^0-9.]/g, '');
        const parts = val.split('.');
        if (parts.length > 2) val = parts[0] + '.' + parts.slice(1).join('');
        input.value = val;
    }

    function getFormattedEngine() {
        const raw = (document.getElementById('vEngine')?.value || '').trim().replace(/[^0-9.]/g, '');
        if (!raw) return '';
        return isElectricMode() ? (raw + 'Km') : (raw + 'L');
    }

    function getFormattedConsumption() {
        const raw = (document.getElementById('vConsumption')?.value || '').trim().replace(/[^0-9.]/g, '');
        if (!raw) return '';
        return isElectricMode() ? (raw + 'Wh/Km') : (raw + 'L/100km');
    }

    // ===== IMAGE UPLOAD (same behavior as My Vehicles UI) =====
    async function handleImageUpload(input) {
        const files = input.files;
        if (!files || !files.length) return;

        for (const file of files) {
            if (file.size > 5 * 1024 * 1024) {
                alert(`${file.name} is too large (max 5MB).`);
                continue;
            }

            const formData = new FormData();
            formData.append('image', file);
            formData.append('action', 'upload-image');

            try {
                const res = await fetch(VEHICLES_API + '?action=upload-image', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    uploadedImages.push({ id: data.image_id, url: data.url });
                    renderImagePreviews();
                } else {
                    alert(data.message || 'Upload failed.');
                }
            } catch (err) {
                alert('Failed to upload image.');
            }
        }

        input.value = '';
    }

    function renderImagePreviews() {
        const container = document.getElementById('imagePreviewList');
        if (!container) return;

        container.innerHTML = uploadedImages.map((img, i) => `
            <div style="position:relative;width:80px;height:60px;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;">
                <img src="${img.url}" alt="Car image" style="width:100%;height:100%;object-fit:cover;" />
                <button type="button" onclick="removeImage(${i})" style="position:absolute;top:2px;right:2px;width:18px;height:18px;border-radius:50%;background:rgba(0,0,0,0.6);color:white;border:none;cursor:pointer;font-size:10px;display:flex;align-items:center;justify-content:center;">✕</button>
            </div>
        `).join('');
    }

    function removeImage(index) {
        uploadedImages.splice(index, 1);
        renderImagePreviews();
    }

    // Add Vehicle Modal
    function openAddVehicleModal() {
        document.getElementById('addVehicleModal').classList.add('active');

        // Reset modal fields to defaults
        document.getElementById('staffEditVehicleId').value = '';
        document.getElementById('staffVehicleModalTitle').textContent = '➕ Add New Vehicle';
        document.getElementById('staffVehicleSubmitBtn').textContent = '➕ Add Vehicle';

        ['vBrand','vModel','vYear','vLicensePlate','vColor','vEngine','vConsumption','vPriceDay','vPriceWeek','vPriceMonth','vCity','vAddress'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('vCategory').value = 'sedan';
        document.getElementById('vTransmission').value = 'automatic';
        document.getElementById('vFuelType').value = 'petrol';
        document.getElementById('vSeats').value = '5';
        document.querySelectorAll('.vFeature').forEach(cb => cb.checked = false);

        uploadedImages = [];
        renderImagePreviews();
        updateFieldsForCategory('sedan');
    }

    function closeAddVehicleModal() {
        document.getElementById('addVehicleModal').classList.remove('active');
        uploadedImages = [];
        const inp = document.getElementById('imageInput');
        if (inp) inp.value = '';
    }

    async function submitAddVehicle(e) {
        if (e && e.preventDefault) e.preventDefault();

        const brand = (document.getElementById('vBrand').value || '').trim();
        const model = (document.getElementById('vModel').value || '').trim();
        const year = parseInt(document.getElementById('vYear').value || '0', 10);
        const licensePlate = (document.getElementById('vLicensePlate').value || '').trim();
        const priceDay = parseFloat(document.getElementById('vPriceDay').value || '0');

        if (!brand || !model || !year || !licensePlate || !priceDay) {
            alert('Please fill in required fields (Brand, Model, Year, License Plate, Price/Day).');
            return;
        }

        const features = [];
        document.querySelectorAll('.vFeature:checked').forEach(cb => features.push(cb.value));

        // Use the Vehicles API (same as My Vehicles) so images can be linked automatically
        const payload = {
            action: 'add',
            brand, model, year,
            license_plate: licensePlate,
            category: document.getElementById('vCategory').value,
            transmission: document.getElementById('vTransmission').value,
            fuel_type: document.getElementById('vFuelType').value,
            seats: parseInt(document.getElementById('vSeats').value) || 5,
            color: (document.getElementById('vColor').value || '').trim(),
            engine_size: getFormattedEngine(),
            consumption: getFormattedConsumption(),
            price_per_day: priceDay,
            price_per_week: parseFloat(document.getElementById('vPriceWeek').value) || null,
            price_per_month: parseFloat(document.getElementById('vPriceMonth').value) || null,
            location_city: (document.getElementById('vCity').value || '').trim(),
            location_address: (document.getElementById('vAddress').value || '').trim(),
            features: features,
            image_ids: uploadedImages.map(i => i.id)
        };

        const btn = document.getElementById('staffVehicleSubmitBtn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = '➕ Adding...';
        }

        try {
            const res = await fetch(VEHICLES_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (data.force_logout) {
                alert(data.message || 'Session invalid. Please login again.');
                window.location.href = 'index.php';
                return;
            }

            if (data.success) {
                closeAddVehicleModal();
                loadVehicles();
                loadOverview();
            } else {
                alert(data.message || 'Failed to add vehicle');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to add vehicle');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = '➕ Add Vehicle';
            }
        }
    }

    // Assign Vehicle Modal
    function closeAssignVehicleModal() {
        document.getElementById('assignVehicleModal')?.classList.remove('active');
        document.body.classList.remove('modal-open');
    }

    async function openAssignVehicleModal(driverId, driverName) {
        document.getElementById('assignDriverId').value = driverId;
        document.getElementById('assignDriverName').value = driverName || '';

        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        document.getElementById('assignDate').value = `${yyyy}-${mm}-${dd}`;

        const select = document.getElementById('assignVehicleSelect');
        select.innerHTML = '<option value="">Loading...</option>';

        document.getElementById('assignVehicleModal')?.classList.add('active');
        document.body.classList.add('modal-open');

        try {
            const res = await fetch(`${API_BASE}?action=get_vehicles&status=available`);
            const data = await res.json();
            const vehicles = data.vehicles || [];

            if (vehicles.length === 0) {
                select.innerHTML = '<option value="">No available vehicles</option>';
                return;
            }

            select.innerHTML = vehicles.map(v => {
                const label = `${escapeHtml(v.brand)} ${escapeHtml(v.model)} (${escapeHtml(v.year)}) — ${escapeHtml(v.license_plate)}`;
                return `<option value="${escapeHtml(v.id)}">${label}</option>`;
            }).join('');
        } catch (e) {
            select.innerHTML = '<option value="">Failed to load vehicles</option>';
        }
    }

    async function submitAssignment() {
        const driverId = (document.getElementById('assignDriverId').value || '').trim();
        const vehicleId = (document.getElementById('assignVehicleSelect').value || '').trim();
        const assignedDate = (document.getElementById('assignDate').value || '').trim();

        if (!driverId || !vehicleId) {
            alert('Please choose a vehicle.');
            return;
        }

        const btn = document.getElementById('assignSubmitBtn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Assigning...';
        }

        try {
            const form = new FormData();
            form.append('action', 'assign_vehicle');
            form.append('driver_id', driverId);
            form.append('vehicle_id', vehicleId);
            if (assignedDate) form.append('assigned_date', assignedDate);

            const res = await fetch(API_BASE, { method: 'POST', body: form });
            const data = await res.json();

            if (data.success) {
                closeAssignVehicleModal();
                await loadDrivers();
                await loadVehicles();
                await loadOverview();
                alert('Assigned successfully.');
            } else {
                alert(data.message || 'Assign failed');
            }
        } catch (e) {
            alert('Assign failed');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Assign';
            }
        }
    }

    // Override placeholder: open modal instead of alert
    function assignVehicleToDriver(driverId) {
        const row = document.querySelector(`#driversTable button[onclick="assignVehicleToDriver('${driverId}')"]`)?.closest('tr');
        const driverName = row?.children?.[0]?.textContent?.trim() || '';
        openAssignVehicleModal(driverId, driverName);
    }

    function closeOrderDetailModal() {
        document.getElementById('orderDetailModal')?.classList.remove('active');
        document.body.classList.remove('modal-open');
    }

    async function viewOrder(orderId) {
        document.getElementById('orderDetailModal')?.classList.add('active');
        document.body.classList.add('modal-open');

        const body = document.getElementById('orderDetailBody');
        if (body) body.innerHTML = '<div class="assigned-vehicle-empty">Loading...</div>';

        try {
            const res = await fetch(`${API_BASE}?action=get_order&order_id=${encodeURIComponent(orderId)}`);
            const data = await res.json();

            if (!data.success || !data.order) {
                if (body) body.innerHTML = `<div class="assigned-vehicle-empty">${escapeHtml(data.message || 'Order not found')}</div>`;
                return;
            }

            const o = data.order;
            const rows = [
                ['Order ID', o.id],
                ['Status', o.status],
                ['Booking Type', o.booking_type],
                ['Service Type', o.service_type],
                ['Pickup location', o.pickup_location],
                ['Return/Destination', o.return_location],
                ['Pickup date', o.pickup_date],
                ['Return date', o.return_date],
                ['Passengers', o.number_of_passengers],
                ['Ride tier', o.ride_tier],
                ['Total amount', o.total_amount],
                ['Customer', `${o.user_name || ''} (${o.user_phone || ''})`],
                ['Customer email', o.email],
                ['Driver', o.driver_name ? `${o.driver_name} (${o.driver_phone || ''})` : 'Not assigned'],
                ['Special requests', o.special_requests || '—'],
                ['Created at', o.created_at],
                ['Accepted by driver at', o.accepted_by_driver_at],
                ['Ride completed at', o.ride_completed_at]
            ];

            if (body) {
                body.innerHTML = `
                    <div class="kv">
                        ${rows.map(([k,v]) => `
                            <div class="k">${escapeHtml(k)}</div>
                            <div class="v">${escapeHtml(v ?? '—')}</div>
                        `).join('')}
                    </div>
                `;
            }
        } catch (e) {
            if (body) body.innerHTML = '<div class="assigned-vehicle-empty">Failed to load detail</div>';
        }
    }

    // Tab switching
    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

        document.getElementById(tabName).classList.add('active');
        event.target.classList.add('active');

        // Load data based on tab
        if (tabName === 'drivers') loadDrivers();
        else if (tabName === 'vehicles') loadVehicles();
        else if (tabName === 'orders') loadOrders();
        else if (tabName === 'overview') loadOverview();
    }

    // Load Overview
    async function loadOverview() {
        try {
            const driversRes = await fetch(`${API_BASE}?action=get_drivers`);
            const driversData = await driversRes.json();
            document.getElementById('totalDrivers').textContent = driversData.drivers?.length || 0;

            const vehiclesRes = await fetch(`${API_BASE}?action=get_vehicles&status=all`);
            const vehiclesData = await vehiclesRes.json();
            const allVehicles = vehiclesData.vehicles || [];
            document.getElementById('totalVehicles').textContent = allVehicles.length;
            document.getElementById('availableVehicles').textContent = allVehicles.filter(v => v.status === 'available').length;
            document.getElementById('rentedVehicles').textContent = allVehicles.filter(v => v.status === 'rented').length;
        } catch (error) {
            console.error('Error loading overview:', error);
        }
    }

    // Load Drivers
    async function loadDrivers() {
        try {
            const res = await fetch(`${API_BASE}?action=get_drivers`);
            const data = await res.json();
            const tbody = document.getElementById('driversTable');
            tbody.innerHTML = '';

            if (data.drivers && data.drivers.length > 0) {
                data.drivers.forEach(driver => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${driver.full_name}</td>
                        <td>${driver.phone || 'N/A'}</td>
                        <td>${driver.brand ? driver.brand + ' ' + driver.model : 'Unassigned'}</td>
                        <td><span style="color: #16a34a; font-weight: 500;">Active</span></td>
                        <td>
                            <button class="btn-sm btn-secondary-sm" onclick="assignVehicleToDriver('${driver.id}')">Assign</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px;">No drivers found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading drivers:', error);
        }
    }

    // Load Vehicles
    async function loadVehicles() {
        try {
            const res = await fetch(`${API_BASE}?action=get_vehicles&status=all`);
            const data = await res.json();
            const tbody = document.getElementById('vehiclesTable');
            tbody.innerHTML = '';

            if (data.vehicles && data.vehicles.length > 0) {
                data.vehicles.forEach(vehicle => {
                    const row = document.createElement('tr');
                    const statusColor = vehicle.status === 'available' ? '#16a34a' : '#ea580c';
                    const thumb = vehicle.thumbnail_url
                        ? `<img src="${vehicle.thumbnail_url}" alt="${vehicle.brand} ${vehicle.model}" style="width:54px;height:40px;object-fit:cover;border-radius:8px;border:1px solid var(--gray-200);"/>`
                        : `<div style="width:54px;height:40px;border-radius:8px;border:1px dashed var(--gray-200);display:flex;align-items:center;justify-content:center;color:var(--gray-400);font-size:0.7rem;">No photo</div>`;

                    row.innerHTML = `
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                ${thumb}
                                <div>
                                    <div style="font-weight:600;">${vehicle.brand} ${vehicle.model}</div>
                                    <div style="font-size:0.75rem;color:var(--gray-500);">${vehicle.year || ''}</div>
                                </div>
                            </div>
                        </td>
                        <td>${vehicle.license_plate}</td>
                        <td>${vehicle.category}</td>
                        <td>$${vehicle.price_per_day}</td>
                        <td><span style="color: ${statusColor}; font-weight: 500; text-transform: capitalize;">${vehicle.status}</span></td>
                        <td>
                            <button class="btn-sm btn-secondary-sm" onclick="alert('Edit feature coming soon')">Edit</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px;">No vehicles found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading vehicles:', error);
        }
    }

    // Load Orders
    async function loadOrders() {
        try {
            const res = await fetch(`${API_BASE}?action=get_orders`);
            const data = await res.json();
            const tbody = document.getElementById('ordersTable');
            tbody.innerHTML = '';

            if (data.orders && data.orders.length > 0) {
                data.orders.forEach(order => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${order.id.substring(0, 8)}...</td>
                        <td>${order.user_name}</td>
                        <td>${order.driver_name || 'Not assigned'}</td>
                        <td>${new Date(order.pickup_date).toLocaleDateString()}</td>
                        <td>$${order.total_amount}</td>
                        <td><span style="text-transform: capitalize; font-weight: 500;">${order.status}</span></td>
                        <td>
                            <button class="btn-sm btn-secondary-sm" onclick="viewOrder('${order.id}')">View</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px;">No orders found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading orders:', error);
        }
    }

    // Phone Booking
    let customerSearchTimer = null;
    let cachedCustomers = new Map();

    document.addEventListener('DOMContentLoaded', function() {
        loadVehiclesForPhoneBooking();
    });

    function debouncedSearchCustomers() {
        clearTimeout(customerSearchTimer);
        customerSearchTimer = setTimeout(searchCustomers, 200);
    }

    async function searchCustomers() {
        const q = (document.getElementById('customerSearch').value || '').trim();
        const results = document.getElementById('customerResults');

        if (!q) {
            results.style.display = 'none';
            results.innerHTML = '';
            return;
        }

        const url = new URL(API_BASE, window.location.origin);
        url.searchParams.set('action', 'search_customers');
        url.searchParams.set('q', q);

        const res = await fetch(url.toString());
        const data = await res.json();
        const customers = (data.success && Array.isArray(data.customers)) ? data.customers : [];

        if (customers.length === 0) {
            results.style.display = 'none';
            results.innerHTML = '';
            return;
        }

        // update dropdown (unique)
        const sel = document.getElementById('customerSelect');
        const existing = new Set(Array.from(sel.options).map(o => o.value).filter(Boolean));
        customers.forEach(c => {
            cachedCustomers.set(c.id, c);
            if (!existing.has(c.id)) {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = `${c.full_name || '(No name)'} — ${c.email || ''} ${c.phone ? '• ' + c.phone : ''}`.trim();
                sel.appendChild(opt);
            }
        });

        results.innerHTML = customers.map(c => {
            const name = escapeHtml(c.full_name || '');
            const email = escapeHtml(c.email || '');
            const phone = escapeHtml(c.phone || '');
            return `
                <div style="padding:10px 12px;border-top:1px solid var(--gray-100);cursor:pointer;" onclick="selectCustomer('${escapeHtmlAttr(c.id)}')">
                    <div style="font-weight:800;color:var(--gray-900);">${name}</div>
                    <div style="font-size:0.82rem;color:var(--gray-600);">${email}${phone ? ' • ' + phone : ''}</div>
                </div>
            `;
        }).join('');
        results.firstElementChild && (results.firstElementChild.style.borderTop = 'none');
        results.style.display = 'block';
    }

    function selectCustomer(id) {
        const sel = document.getElementById('customerSelect');
        sel.value = id;
        onCustomerSelected();

        document.getElementById('customerResults').style.display = 'none';
        document.getElementById('customerResults').innerHTML = '';
        document.getElementById('customerSearch').value = '';
    }

    function onCustomerSelected() {
        const id = document.getElementById('customerSelect').value;
        const c = id ? cachedCustomers.get(id) : null;
        if (!c) return;

        document.getElementById('customerName').value = c.full_name || '';
        document.getElementById('customerEmail').value = c.email || '';
        document.getElementById('customerPhone').value = c.phone || '';
    }

    async function loadVehiclesForPhoneBooking() {
        const sel = document.getElementById('vehicleSelect');
        try {
            const url = new URL(API_BASE, window.location.origin);
            url.searchParams.set('action', 'get_vehicles');
            url.searchParams.set('status', 'available');

            const res = await fetch(url.toString());
            const data = await res.json();

            if (!data.success) throw new Error(data.message || 'Failed');

            const vehicles = Array.isArray(data.vehicles) ? data.vehicles : [];
            sel.innerHTML = '<option value="">— Select vehicle —</option>';
            vehicles.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.id;
                opt.textContent = `${v.brand || ''} ${v.model || ''} — ${v.license_plate || ''} — $${Number(v.price_per_day || 0)} / day`;
                sel.appendChild(opt);
            });
        } catch (e) {
            sel.innerHTML = '<option value="">Failed to load vehicles</option>';
        }
    }

    function resetPhoneBookingForm() {
        document.getElementById('phoneBookingForm').reset();
        document.getElementById('customerSelect').value = '';
        document.getElementById('phoneBookingResult').style.display = 'none';
        document.getElementById('phoneBookingResult').innerHTML = '';
    }

    async function submitPhoneBooking(e) {
        e.preventDefault();

        const btn = document.getElementById('phoneBookingSubmitBtn');
        const result = document.getElementById('phoneBookingResult');
        btn.disabled = true;
        btn.textContent = 'Creating…';

        try {
            const payload = {
                action: 'booking_by_request',
                customer_id: document.getElementById('customerSelect').value || null,
                customer_name: document.getElementById('customerName').value.trim(),
                customer_phone: document.getElementById('customerPhone').value.trim(),
                customer_email: document.getElementById('customerEmail').value.trim(),
                vehicle_id: document.getElementById('vehicleSelect').value,
                pickup_date: document.getElementById('pickupDate').value,
                return_date: document.getElementById('returnDate').value,
                pickup_location: document.getElementById('pickupLocation').value.trim(),
                return_location: document.getElementById('returnLocation').value.trim(),
                initial_status: document.getElementById('initialStatus').value,
                payment_method: document.getElementById('paymentMethod').value,
                special_requests: document.getElementById('specialRequests').value
            };

            const res = await fetch(API_BASE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (!data.success) throw new Error(data.message || 'Failed to create booking');

            const ref = data.booking?.booking_ref ? escapeHtml(data.booking.booking_ref) : '(no ref)';
            const id = data.booking?.id ? escapeHtml(data.booking.id) : '';

            result.style.display = 'block';
            result.style.borderColor = 'rgba(22,163,74,0.35)';
            result.style.background = 'var(--success-light)';
            result.style.color = 'var(--gray-900)';
            result.innerHTML = `<div style="font-weight:900;">✅ Booking created successfully</div>
                                <div style="margin-top:6px;font-size:0.9rem;">Reference: <b>${ref}</b></div>
                                <div style="margin-top:4px;font-size:0.85rem;color:var(--gray-700);">Booking ID: ${id}</div>`;

            // refresh vehicles list (the booked vehicle becomes rented)
            loadVehiclesForPhoneBooking();
        } catch (err) {
            result.style.display = 'block';
            result.style.borderColor = 'rgba(239,68,68,0.35)';
            result.style.background = 'var(--danger-light)';
            result.style.color = 'var(--gray-900)';
            result.innerHTML = `<div style="font-weight:900;">❌ Failed</div><div style="margin-top:6px;font-size:0.9rem;">${escapeHtml(err.message || String(err))}</div>`;
        } finally {
            btn.disabled = false;
            btn.textContent = 'Create Booking';
        }
    }

    function escapeHtml(str) {
        return String(str)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
    function escapeHtmlAttr(str) {
        return escapeHtml(str).replaceAll('`', '&#096;');
    }
</script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
