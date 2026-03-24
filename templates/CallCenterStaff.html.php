<?php include __DIR__ . '/layout/header.html.php'; ?>

<style>
    body { background: #f8fafc; }
    .cc-wrap { max-width: 1200px; margin: 0 auto; padding: 24px; }
    .cc-title { font-size: 1.8rem; color: #0f172a; margin-bottom: 6px; }
    .cc-sub { color: #64748b; margin-bottom: 20px; }
    .cc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .cc-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .cc-card h2 { margin: 0 0 14px 0; font-size: 1.1rem; color: #1e293b; }
    .cc-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .cc-form-grid .full { grid-column: 1 / -1; }
    .cc-input, .cc-select, .cc-textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.92rem; }
    .cc-textarea { min-height: 90px; resize: vertical; }
    .cc-actions { margin-top: 12px; display: flex; gap: 8px; justify-content: flex-end; }
    .cc-btn { padding: 9px 14px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .cc-btn-primary { background: #1d4ed8; color: #fff; }
    .cc-btn-secondary { background: #e2e8f0; color: #0f172a; }
    .cc-btn-danger { background: #dc2626; color: #fff; }
    .cc-status { margin-top: 10px; color: #334155; font-size: 0.9rem; }
    .cc-results { position: relative; }
    .cc-customer-list { position: absolute; z-index: 30; width: 100%; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; max-height: 180px; overflow: auto; display: none; }
    .cc-customer-item { padding: 10px; border-bottom: 1px solid #f1f5f9; cursor: pointer; }
    .cc-customer-item:hover { background: #eff6ff; }
    .cc-table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 0.9rem; }
    th { background: #f8fafc; color: #334155; }
    .cc-badge { padding: 4px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
    .cc-pending { background: #fef3c7; color: #92400e; }
    .cc-in-progress { background: #dbeafe; color: #1d4ed8; }
    .cc-done { background: #dcfce7; color: #166534; }
    .cc-cancelled { background: #fee2e2; color: #991b1b; }
    .cc-help { display: block; margin-top: 6px; font-size: 0.78rem; color: #64748b; }
    .cc-help-error { color: #b91c1c; }
    .cc-help-ok { color: #166534; }

    .location-input-wrapper { display: flex; gap: 8px; align-items: center; position: relative; }
    .location-input-wrapper .cc-input { flex: 1; }
    .location-map-btn {
        width: 42px; height: 42px; border: 2px solid #cbd5e1; border-radius: 8px;
        background: #fff; font-size: 1rem; cursor: pointer; display: flex;
        align-items: center; justify-content: center;
    }
    .location-map-btn:hover { border-color: #1d4ed8; background: #eff6ff; }
    .map-picker-container {
        margin-top: 10px; border-radius: 10px; border: 2px solid #bfdbfe;
        overflow: hidden; background: #fff;
    }
    .map-picker-container.expanded {
        position: fixed; top: 10px; left: 10px; right: 10px; bottom: 10px;
        z-index: 9999; margin-top: 0;
    }
    .map-picker-container.expanded .map-picker { height: calc(100% - 52px); }
    .map-picker-wrapper { position: relative; }
    .map-picker-wrapper .map-expand-btn {
        position: absolute; top: 10px; right: 10px; z-index: 1000;
        width: 36px; height: 36px; border: none; border-radius: 6px;
        background: #fff; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }
    .map-picker-footer {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        padding: 10px 12px; background: #f8fafc; border-top: 1px solid #e2e8f0;
    }
    .map-coords {
        font-size: 0.75rem; color: #64748b; flex: 1; min-width: 0;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .map-picker { width: 100%; height: 300px; background: #f1f5f9; }

    .leaflet-autocomplete-list {
        position: absolute; z-index: 1000; background: #fff; border: 1px solid #cbd5e1;
        border-radius: 8px; max-height: 220px; overflow-y: auto; width: 100%; top: 100%; left: 0; margin-top: 4px;
        box-shadow: 0 12px 30px rgba(2, 6, 23, 0.12);
    }
    .leaflet-autocomplete-list .autocomplete-item {
        padding: 10px 14px; cursor: pointer; font-size: 0.875rem; color: #334155;
        border-bottom: 1px solid #f1f5f9;
    }
    .leaflet-autocomplete-list .autocomplete-item:last-child { border-bottom: none; }
    .leaflet-autocomplete-list .autocomplete-item:hover { background: #eff6ff; color: #1d4ed8; }
    .leaflet-autocomplete-list .autocomplete-item .ac-main { font-weight: 600; }
    .leaflet-autocomplete-list .autocomplete-item .ac-sub { font-size: 0.75rem; color: #64748b; margin-top: 2px; }
    @media (max-width: 980px) {
        .cc-grid { grid-template-columns: 1fr; }
        .cc-form-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="cc-wrap">
    <h1 class="cc-title">Call Center Staff</h1>
    <p class="cc-sub">Create booking requests for customers and track your submitted requests.</p>

    <div class="cc-grid">
        <section class="cc-card">
            <h2>Create Booking Request</h2>
            <form id="ccBookingForm">
                <div class="cc-form-grid">
                    <div class="full cc-results">
                        <label>Search existing customer</label>
                        <input class="cc-input" id="ccCustomerSearch" placeholder="Search by name, email, phone">
                        <div class="cc-customer-list" id="ccCustomerResults"></div>
                    </div>

                    <input type="hidden" id="ccCustomerId">

                    <div>
                        <label>Customer Name *</label>
                        <input class="cc-input" id="ccCustomerName" required>
                    </div>
                    <div>
                        <label>Customer Phone *</label>
                        <input class="cc-input" id="ccCustomerPhone" required>
                    </div>
                    <div>
                        <label>Customer Email *</label>
                        <input class="cc-input" id="ccCustomerEmail" type="email" required>
                    </div>
                    <div>
                        <label>Ride Tier *</label>
                        <select class="cc-select" id="ccRideTier" required>
                            <option value="">Select tier</option>
                            <option value="eco">Eco</option>
                            <option value="standard">Standard</option>
                            <option value="premium">Premium</option>
                        </select>
                        <small id="ccRideTierHint" class="cc-help">Checking available ride tiers...</small>
                    </div>
                    <div>
                        <label>Seat Capacity *</label>
                        <select class="cc-select" id="ccSeatCapacity" required>
                            <option value="4">4 seats</option>
                            <option value="7">7 seats</option>
                        </select>
                        <small id="ccSeatCapacityHint" class="cc-help">Choose seat capacity based on available fleet.</small>
                    </div>
                    <div>
                        <label>Pickup Date & Time *</label>
                        <input class="cc-input" id="ccPickupDate" type="datetime-local" required>
                        <small id="ccPickupDateHint" class="cc-help">Pickup must be at least 30 minutes from now.</small>
                    </div>
                    <div>
                        <label>Pickup Location *</label>
                        <div class="location-input-wrapper">
                            <input class="cc-input" id="ccPickupLocation" placeholder="Search pickup location" autocomplete="off" required>
                            <button type="button" class="location-map-btn" onclick="openMapPicker('pickup')" title="Choose on map">📍</button>
                        </div>
                        <div id="pickupMapContainer" class="map-picker-container" style="display:none;">
                            <div class="map-picker-wrapper">
                                <div id="pickupMap" class="map-picker"></div>
                                <button type="button" class="map-expand-btn" onclick="toggleMapExpand('pickup')" title="Expand map">⛶</button>
                            </div>
                            <div class="map-picker-footer">
                                <span class="map-coords" id="pickupMapCoords">Drag marker or click to select location</span>
                                <button type="button" class="cc-btn cc-btn-primary" onclick="confirmMapLocation('pickup')">✓ Confirm</button>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label>Destination *</label>
                        <div class="location-input-wrapper">
                            <input class="cc-input" id="ccReturnLocation" placeholder="Search destination" autocomplete="off" required>
                            <button type="button" class="location-map-btn" onclick="openMapPicker('return')" title="Choose on map">📍</button>
                        </div>
                        <div id="returnMapContainer" class="map-picker-container" style="display:none;">
                            <div class="map-picker-wrapper">
                                <div id="returnMap" class="map-picker"></div>
                                <button type="button" class="map-expand-btn" onclick="toggleMapExpand('return')" title="Expand map">⛶</button>
                            </div>
                            <div class="map-picker-footer">
                                <span class="map-coords" id="returnMapCoords">Drag marker or click to select location</span>
                                <button type="button" class="cc-btn cc-btn-primary" onclick="confirmMapLocation('return')">✓ Confirm</button>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label>Payment Method</label>
                        <select class="cc-select" id="ccPaymentMethod">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="paypal">PayPal</option>
                        </select>
                    </div>
                    <div class="full">
                        <label>Note</label>
                        <textarea class="cc-textarea" id="ccSpecialRequests"></textarea>
                    </div>
                    
                </div>

                <div class="cc-actions">
                    <button type="button" class="cc-btn cc-btn-secondary" id="ccResetBtn">Reset</button>
                    <button type="submit" class="cc-btn cc-btn-primary">Submit Request</button>
                </div>
                <div class="cc-status" id="ccFormStatus"></div>
            </form>
        </section>

        <section class="cc-card">
            <h2>My Requests</h2>
            <div class="cc-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Ref/ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="ccRequestsTable">
                        <tr><td colspan="5">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/resources/js/call-center-staff.js"></script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
