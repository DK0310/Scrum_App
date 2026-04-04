<?php include __DIR__ . '/layout/header.html.php'; ?>

<style>
    :root {
        --cc-primary: #004f45;
        --cc-surface: #f8fafa;
        --cc-card: #ffffff;
        --cc-soft: #f2f4f4;
        --cc-border: #dce4e2;
    }

    body {
        background: var(--cc-surface);
    }

    .cc-wrap {
        max-width: 1560px;
        margin: 0 auto;
        padding: calc(84px + env(safe-area-inset-top)) 22px 28px;
    }

    .cc-hero {
        margin-bottom: 12px;
    }

    .cc-title {
        font-size: 2.1rem;
        font-weight: 800;
        line-height: 1.1;
        color: #191c1d;
        margin-bottom: 6px;
    }

    .cc-sub {
        color: #4f5b58;
        margin: 0;
        font-size: 0.96rem;
    }

    .cc-tabbar {
        display: flex;
        gap: 8px;
        margin-bottom: 16px;
        width: fit-content;
        background: #eef2f2;
        border-radius: 14px;
        padding: 6px;
    }

    .cc-tab-btn {
        border: none;
        background: transparent;
        color: #4d5b57;
        border-radius: 10px;
        padding: 10px 16px;
        font-weight: 700;
        cursor: pointer;
        transition: all .18s ease;
    }

    .cc-tab-btn.active {
        background: #fff;
        color: var(--cc-primary);
        box-shadow: 0 8px 20px rgba(0, 79, 69, 0.08);
    }

    .cc-tab-panel { display: none; }
    .cc-tab-panel.active { display: block; }

    .cc-tab-note {
        font-size: 0.86rem;
        color: #62706d;
        margin-bottom: 12px;
    }

    .cc-grid {
        display: grid;
        grid-template-columns: 1.35fr 1fr;
        gap: 22px;
    }

    .cc-card {
        background: var(--cc-card);
        border: 1px solid var(--cc-border);
        border-radius: 18px;
        padding: 22px;
        box-shadow: 0 12px 30px rgba(0, 79, 69, 0.06);
    }

    .cc-card h2 {
        margin: 0;
        font-size: 1.35rem;
        color: #1a2020;
        font-weight: 800;
    }

    .cc-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 18px;
    }

    .cc-card-head-icon {
        color: var(--cc-primary);
        font-size: 1.65rem;
    }

    .cc-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .cc-form-grid .full { grid-column: 1 / -1; }

    .cc-form-field label {
        display: block;
        margin-bottom: 6px;
        font-size: 0.69rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        color: #4c5a57;
    }

    .cc-input, .cc-select, .cc-textarea {
        width: 100%;
        padding: 12px 14px;
        border: none;
        border-radius: 12px;
        font-size: 0.92rem;
        background: var(--cc-soft);
        color: #1d2523;
    }

    .cc-input:focus, .cc-select:focus, .cc-textarea:focus {
        outline: 2px solid rgba(0, 79, 69, 0.22);
    }

    .cc-textarea { min-height: 90px; resize: vertical; }

    .cc-actions {
        margin-top: 14px;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    .cc-btn {
        padding: 10px 14px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 800;
    }

    .cc-btn-primary { background: linear-gradient(135deg, #004f45, #00695c); color: #fff; }
    .cc-btn-secondary { background: #e6eceb; color: #243330; }
    .cc-btn-danger { background: #dc2626; color: #fff; }

    .cc-status { margin-top: 10px; color: #334155; font-size: 0.88rem; }
    .cc-results { position: relative; }
    .cc-customer-list { position: absolute; z-index: 30; width: 100%; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; max-height: 180px; overflow: auto; display: none; }
    .cc-customer-item { padding: 10px; border-bottom: 1px solid #f1f5f9; cursor: pointer; }
    .cc-customer-item:hover { background: #eff6ff; }
    .cc-table-wrap {
        overflow: auto;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        max-height: clamp(240px, 48vh, 560px);
        -webkit-overflow-scrolling: touch;
    }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 0.9rem; }
    th { background: #f4f8ff; color: #334155; }
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

    .cc-requests-stack {
        display: grid;
        gap: 12px;
    }

    .cc-request-card {
        padding: 14px;
        border-radius: 14px;
        background: #fff;
        border: 1px solid #e0e8e6;
        transition: all .2s ease;
    }

    .cc-request-card:hover {
        box-shadow: 0 10px 20px rgba(0, 79, 69, 0.08);
    }

    .cc-request-head {
        display: flex;
        justify-content: space-between;
        align-items: start;
        gap: 8px;
        margin-bottom: 8px;
    }

    .cc-request-title {
        font-size: 0.95rem;
        font-weight: 800;
        color: #1b2321;
        margin: 0;
    }

    .cc-request-meta {
        font-size: 0.75rem;
        color: #64748b;
    }

    .cc-request-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 10px;
        font-size: 0.81rem;
        color: #596867;
    }

    .cc-request-actions {
        display: flex;
        gap: 8px;
        padding-top: 10px;
        border-top: 1px solid #ebf1f0;
    }

    .cc-request-actions .cc-btn { flex: 1; padding: 8px 10px; font-size: 0.78rem; }

    .cc-create-wrap {
        max-width: 930px;
        margin: 0 auto;
    }

    .cc-summary-card {
        display: none;
        margin-top: 14px;
        padding: 14px;
        border-radius: 14px;
        border: 1px solid #bde5da;
        background: #e8f6f1;
        color: #134e4a;
        font-size: 0.86rem;
    }

    .cc-enquiry-shell {
        background: #fff;
        border: 1px solid #dce4e2;
        border-radius: 26px;
        overflow: hidden;
        box-shadow: 0 12px 30px rgba(0, 79, 69, 0.06);
    }

    .cc-enquiry-head {
        padding: 18px 22px;
        border-bottom: 1px solid #e8efed;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }

    .cc-enquiry-title {
        font-size: 1.22rem;
        font-weight: 800;
        color: #1a2020;
        margin: 0;
    }

    .cc-enquiry-tools {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .cc-enquiry-search {
        padding: 9px 12px;
        border-radius: 10px;
        border: none;
        background: #f2f4f4;
        min-width: 240px;
    }

    .cc-enquiry-foot {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 14px 18px;
        background: #f6f9f8;
        border-top: 1px solid #e8efed;
        font-size: 0.78rem;
        color: #64748b;
    }

    #ccReplyModal .modal {
        width: min(620px, calc(100vw - 20px));
        max-height: calc(100vh - 20px);
        display: flex;
        flex-direction: column;
    }

    #ccReplyModal .modal-body {
        overflow-y: auto;
    }

    @media (max-width: 1180px) {
        .cc-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 980px) {
        .cc-wrap {
            padding: calc(78px + env(safe-area-inset-top)) 14px 20px;
        }

        .cc-hero {
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 14px;
        }

        .cc-title { font-size: 1.35rem; }

        .cc-tabbar { width: 100%; overflow-x: auto; }
        .cc-tab-btn { white-space: nowrap; }

        .cc-form-grid { grid-template-columns: 1fr; }
        .cc-card { padding: 14px; }
        th, td { padding: 9px 8px; font-size: 0.84rem; }
        .map-picker { height: 240px; }
        .cc-request-row { grid-template-columns: 1fr; }
        .cc-enquiry-head { flex-direction: column; align-items: stretch; }
        .cc-enquiry-search { min-width: 0; width: 100%; }
    }

    @media (max-height: 820px) {
        .cc-wrap {
            padding-top: calc(74px + env(safe-area-inset-top));
        }

        .cc-hero {
            padding: 14px 16px;
            margin-bottom: 12px;
        }

        .cc-title {
            font-size: 1.4rem;
            margin-bottom: 4px;
        }

        .cc-tabbar { margin-bottom: 10px; }
        .cc-tab-note { margin-bottom: 8px; }
        .cc-table-wrap { max-height: 38vh; }
        .map-picker { height: 210px; }
    }

    @media (max-height: 680px) {
        .cc-wrap {
            padding-top: calc(68px + env(safe-area-inset-top));
            padding-bottom: 14px;
        }

        .cc-card {
            padding: 12px;
            border-radius: 12px;
        }

        .cc-actions {
            position: sticky;
            bottom: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0.75), #fff 35%);
            padding-top: 8px;
        }

        .cc-table-wrap { max-height: 34vh; }
        .map-picker { height: 180px; }
    }
</style>

<div class="cc-wrap">
    <section class="cc-hero">
        <h1 class="cc-title">Call Center Staff Dashboard</h1>
        <p class="cc-sub">Manage customer bookings and create new accounts in one workspace.</p>
    </section>

    <div class="cc-tabbar" role="tablist" aria-label="Call center tabs">
        <button type="button" class="cc-tab-btn active" data-tab="booking" role="tab" aria-selected="true">Customer Booking</button>
        <?php if (!empty($canCreateAccount)): ?>
        <button type="button" class="cc-tab-btn" data-tab="create-account" role="tab" aria-selected="false">Create Account</button>
        <?php endif; ?>
    </div>

    <section class="cc-tab-panel active" id="ccTabBooking" role="tabpanel" aria-label="Customer Booking">
        <p class="cc-tab-note">Create requests by phone and manage your submitted booking queue.</p>

        <div class="cc-grid">
            <section class="cc-card">
                <div class="cc-card-head">
                    <h2>Create Booking Request</h2>
                </div>
                <form id="ccBookingForm">
                    <div class="cc-form-grid">
                        <div class="full cc-results cc-form-field">
                            <label>Search existing customer</label>
                            <input class="cc-input" id="ccCustomerSearch" placeholder="Search by name, email, phone">
                            <div class="cc-customer-list" id="ccCustomerResults"></div>
                        </div>

                        <input type="hidden" id="ccCustomerId">

                        <div class="cc-form-field">
                            <label>Customer Name *</label>
                            <input class="cc-input" id="ccCustomerName" required>
                        </div>
                        <div class="cc-form-field">
                            <label>Customer Phone *</label>
                            <input class="cc-input" id="ccCustomerPhone" required>
                        </div>
                        <div class="cc-form-field">
                            <label>Customer Email *</label>
                            <input class="cc-input" id="ccCustomerEmail" type="email" required>
                        </div>
                        <div class="cc-form-field">
                            <label>Ride Tier *</label>
                            <select class="cc-select" id="ccRideTier" required>
                                <option value="">Select tier</option>
                                <option value="eco">Eco</option>
                                <option value="standard">Standard</option>
                                <option value="premium">Premium</option>
                            </select>
                            <small id="ccRideTierHint" class="cc-help">Checking available ride tiers...</small>
                        </div>
                        <div class="cc-form-field">
                            <label>Seat Capacity *</label>
                            <select class="cc-select" id="ccSeatCapacity" required>
                                <option value="4">4 seats</option>
                                <option value="7">7 seats</option>
                            </select>
                            <small id="ccSeatCapacityHint" class="cc-help">Choose seat capacity based on available fleet.</small>
                        </div>
                        <div class="cc-form-field">
                            <label>Service Type *</label>
                            <select class="cc-select" id="ccServiceType" required>
                                <option value="local">Local Journey</option>
                                <option value="long-distance">Long Distance Journey</option>
                                <option value="airport-transfer">Airport Transfer</option>
                                <option value="hotel-transfer">Hotel Transfer</option>
                            </select>
                        </div>
                        <div class="cc-form-field">
                            <label>Pickup Date & Time *</label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                <input class="cc-input" id="ccPickupDateOnly" type="date" required>
                                <select class="cc-select" id="ccPickupTimeSlot" required>
                                    <option value="">Select time</option>
                                </select>
                            </div>
                            <input class="cc-input" id="ccPickupDate" type="hidden" value="">
                            <small id="ccPickupDateHint" class="cc-help">Pickup must be at least 30 minutes from now.</small>
                            <small id="ccPickupSlotHint" class="cc-help">Choose a date to load available time slots.</small>
                        </div>
                        <div class="cc-form-field">
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
                        <div class="cc-form-field">
                            <label>Destination *</label>
                            <div class="location-input-wrapper" id="ccReturnInputWrapper">
                                <input class="cc-input" id="ccReturnLocation" placeholder="Search destination" autocomplete="off" required>
                                <button type="button" class="location-map-btn" id="ccReturnMapBtn" onclick="openMapPicker('return')" title="Choose on map">📍</button>
                            </div>
                            <div id="ccAirportSelectWrapper" style="display:none;">
                                <select class="cc-select" id="ccAirportSelect">
                                    <option value="">-- Select Airport --</option>
                                    <option value="Heathrow Airport, London, United Kingdom" data-lat="51.4700" data-lon="-0.4543">Heathrow (LHR) - London</option>
                                    <option value="Gatwick Airport, London, United Kingdom" data-lat="51.1537" data-lon="-0.1821">Gatwick (LGW) - London</option>
                                    <option value="Stansted Airport, London, United Kingdom" data-lat="51.8850" data-lon="0.2350">Stansted (STN) - London</option>
                                    <option value="Luton Airport, London, United Kingdom" data-lat="51.8747" data-lon="-0.3683">Luton (LTN) - London</option>
                                    <option value="London City Airport, London, United Kingdom" data-lat="51.5053" data-lon="0.0553">London City (LCY) - London</option>
                                </select>
                            </div>
                            <div id="ccHotelSelectWrapper" style="display:none;">
                                <select class="cc-select" id="ccHotelSelect">
                                    <option value="">-- Select Hotel --</option>
                                    <option value="The Savoy, Strand, London, United Kingdom" data-lat="51.5100" data-lon="-0.1206">The Savoy - Strand</option>
                                    <option value="The Ritz London, Piccadilly, London, United Kingdom" data-lat="51.5070" data-lon="-0.1416">The Ritz London - Piccadilly</option>
                                    <option value="Shangri-La The Shard, London, United Kingdom" data-lat="51.5045" data-lon="-0.0865">Shangri-La The Shard - Southwark</option>
                                    <option value="The Langham, 1C Portland Place, London, United Kingdom" data-lat="51.5178" data-lon="-0.1440">The Langham - Marylebone</option>
                                    <option value="Corinthia London, Whitehall Place, London, United Kingdom" data-lat="51.5067" data-lon="-0.1246">Corinthia London - Westminster</option>
                                </select>
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
                        <div class="cc-form-field">
                            <label>Payment Method</label>
                            <select class="cc-select" id="ccPaymentMethod">
                                <option value="cash">Cash</option>
                                <option value="account_balance">Account Balance</option>
                            </select>
                            <small id="ccPaymentMethodHint" class="cc-help">Account balance is available only for existing customers with enough balance.</small>
                        </div>
                        <div class="full cc-form-field">
                            <label>Note</label>
                            <textarea class="cc-textarea" id="ccSpecialRequests"></textarea>
                        </div>
                    </div>

                    <div class="cc-summary-card" id="ccBookingEstimatePanel" style="display:block;">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:8px;">
                            <strong>Estimated Trip Summary</strong>
                            <small>Live preview before submit</small>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
                            <div style="background:#fff;border:1px solid #bde5da;border-radius:10px;padding:8px 10px;">
                                <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Distance</div>
                                <div id="ccEstimateDistance" style="font-size:0.98rem;font-weight:800;color:#0f766e;">-</div>
                            </div>
                            <div style="background:#fff;border:1px solid #bde5da;border-radius:10px;padding:8px 10px;">
                                <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Estimated Total</div>
                                <div id="ccEstimateTotal" style="font-size:1rem;font-weight:900;color:#065f46;">-</div>
                            </div>
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
                <div class="cc-card-head">
                    <h2>My Requests</h2>
                </div>
                <div class="cc-requests-stack" id="ccRequestsTable">
                    <div class="cc-request-card">Loading...</div>
                </div>
            </section>
        </div>
    </section>

    <?php if (!empty($canCreateAccount)): ?>
    <section class="cc-tab-panel" id="ccTabCreateAccount" role="tabpanel" aria-label="Create Account">
        <p class="cc-tab-note">Create customer accounts with temporary password <strong>123456</strong>.</p>
        <section class="cc-card cc-create-wrap">
            <div class="cc-card-head">
                <h2>Create Customer Account</h2>
            </div>
            <form id="ccCreateAccountForm">
                <div class="cc-form-grid">
                    <div class="cc-form-field">
                        <label>Username *</label>
                        <input class="cc-input" id="ccAccountUsername" required>
                    </div>
                    <div class="cc-form-field">
                        <label>Email *</label>
                        <input class="cc-input" id="ccAccountEmail" type="email" required>
                    </div>
                    <div class="cc-form-field">
                        <label>Phone Number *</label>
                        <input class="cc-input" id="ccAccountPhone" required>
                    </div>
                    <div class="cc-form-field">
                        <label>Date of Birth *</label>
                        <input class="cc-input" id="ccAccountDob" type="date" required>
                    </div>
                    <div class="full cc-form-field">
                        <label>Default Password</label>
                        <input class="cc-input" value="123456" readonly>
                        <small class="cc-help">Temporary credential will be emailed to the customer.</small>
                    </div>
                </div>

                <div class="cc-actions">
                    <button type="button" class="cc-btn cc-btn-secondary" id="ccCreateAccountResetBtn">Reset</button>
                    <button type="submit" class="cc-btn cc-btn-primary" id="ccCreateAccountSubmitBtn">Create Account</button>
                </div>
                <div class="cc-status" id="ccCreateAccountStatus"></div>
            </form>

            <div id="ccCreateAccountSummary" class="cc-summary-card"></div>
        </section>
    </section>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="ccReplyModal">
    <div class="modal" style="max-width:620px;">
        <div class="modal-header">
            <h3 class="modal-title">Reply Enquiry</h3>
            <button class="modal-close" onclick="closeModal('ccReplyModal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="ccReplyEnquiryId">
            <div style="font-size:0.86rem;color:#475569;margin-bottom:8px;" id="ccReplyMeta"></div>
            <div class="form-group">
                <label>Reply Content *</label>
                <textarea class="cc-textarea" id="ccReplyContent" placeholder="Write your response to the customer..."></textarea>
            </div>
            <div class="form-group">
                <label>Reply Image (optional)</label>
                <input class="cc-input" type="file" id="ccReplyImage" accept="image/jpeg,image/png,image/webp,image/gif">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('ccReplyModal')">Cancel</button>
            <button class="btn btn-primary" id="ccReplySubmitBtn" onclick="submitEnquiryReply()">Send Reply</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="ccRequestDetailModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <h3 class="modal-title">Request Details</h3>
            <button class="modal-close" onclick="closeModal('ccRequestDetailModal')">✕</button>
        </div>
        <div class="modal-body" id="ccRequestDetailBody" style="font-size:0.92rem;color:#334155;">
            Loading details...
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('ccRequestDetailModal')">Close</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    (function () {
        function activateTab(tab) {
            const bookingPanel = document.getElementById('ccTabBooking');
            const createAccountPanel = document.getElementById('ccTabCreateAccount');
            const buttons = document.querySelectorAll('.cc-tab-btn');

            buttons.forEach(function (btn) {
                const active = btn.getAttribute('data-tab') === tab;
                btn.classList.toggle('active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            if (bookingPanel) bookingPanel.classList.toggle('active', tab === 'booking');
            if (createAccountPanel) createAccountPanel.classList.toggle('active', tab === 'create-account');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const buttons = document.querySelectorAll('.cc-tab-btn');
            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    activateTab(btn.getAttribute('data-tab') || 'booking');
                });
            });
            activateTab('booking');
        });
    })();
</script>
<script src="/resources/js/call-center-staff.js"></script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
