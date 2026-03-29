<?php include __DIR__ . '/layout/header.html.php'; ?>

<style>
    :root {
        --cc-bg-top: #fff8ee;
        --cc-bg-bottom: #eef6ff;
        --cc-panel: #ffffff;
        --cc-panel-border: #dbe7f5;
        --cc-panel-shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
        --cc-ink: #0f172a;
        --cc-sub: #5a6b83;
        --cc-accent: #0f6bcf;
        --cc-accent-soft: #e8f2ff;
    }

    body {
        background:
            radial-gradient(1000px 420px at -5% -10%, #ffe4bf 0%, transparent 60%),
            radial-gradient(900px 380px at 105% -10%, #cce7ff 0%, transparent 58%),
            linear-gradient(180deg, var(--cc-bg-top) 0%, var(--cc-bg-bottom) 100%);
    }

    .cc-wrap {
        max-width: 1240px;
        margin: 0 auto;
        padding: calc(84px + env(safe-area-inset-top)) 24px 34px;
    }

    .cc-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1f3e6f 55%, #0f6bcf 100%);
        border-radius: 18px;
        padding: 22px 22px;
        color: #eff6ff;
        margin-bottom: 18px;
        box-shadow: 0 12px 26px rgba(15, 23, 42, 0.2);
    }

    .cc-title { font-size: 1.9rem; letter-spacing: 0.2px; color: #f8fafc; margin-bottom: 8px; }
    .cc-sub { color: #bfd7f6; margin-bottom: 0; }

    .cc-tabbar {
        display: flex;
        gap: 10px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .cc-tab-btn {
        border: 1px solid #c6dcf5;
        background: #f8fbff;
        color: #1e3a5f;
        border-radius: 999px;
        padding: 9px 16px;
        font-weight: 700;
        cursor: pointer;
        transition: all .2s ease;
    }

    .cc-tab-btn:hover {
        border-color: #95c4f3;
        transform: translateY(-1px);
    }

    .cc-tab-btn.active {
        border-color: #0f6bcf;
        background: linear-gradient(135deg, #0f6bcf 0%, #1d4ed8 100%);
        color: #fff;
        box-shadow: 0 8px 18px rgba(15, 107, 207, 0.24);
    }

    .cc-tab-panel { display: none; }
    .cc-tab-panel.active { display: block; }

    .cc-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 20px; }
    .cc-card { background: var(--cc-panel); border: 1px solid var(--cc-panel-border); border-radius: 14px; padding: 18px; box-shadow: var(--cc-panel-shadow); }
    .cc-card h2 { margin: 0 0 14px 0; font-size: 1.08rem; color: var(--cc-ink); }
    .cc-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .cc-form-grid .full { grid-column: 1 / -1; }
    .cc-input, .cc-select, .cc-textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.92rem; }
    .cc-textarea { min-height: 90px; resize: vertical; }
    .cc-actions { margin-top: 12px; display: flex; gap: 8px; justify-content: flex-end; }
    .cc-btn { padding: 9px 14px; border: none; border-radius: 9px; cursor: pointer; font-weight: 700; }
    .cc-btn-primary { background: #1d4ed8; color: #fff; }
    .cc-btn-secondary { background: #e2e8f0; color: #0f172a; }
    .cc-btn-danger { background: #dc2626; color: #fff; }
    .cc-status { margin-top: 10px; color: #334155; font-size: 0.9rem; }
    .cc-results { position: relative; }
    .cc-customer-list { position: absolute; z-index: 30; width: 100%; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; max-height: 180px; overflow: auto; display: none; }
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

    .cc-tab-note {
        font-size: 0.85rem;
        color: var(--cc-sub);
        margin-bottom: 12px;
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

        .cc-tabbar {
            position: sticky;
            top: calc(60px + env(safe-area-inset-top));
            z-index: 12;
            background: rgba(238, 246, 255, 0.92);
            backdrop-filter: blur(6px);
            padding: 8px;
            border: 1px solid #d3e4f8;
            border-radius: 12px;
        }

        .cc-tab-btn {
            flex: 1 1 180px;
            text-align: center;
        }

        .cc-form-grid { grid-template-columns: 1fr; }
        .cc-card { padding: 14px; }
        th, td { padding: 9px 8px; font-size: 0.84rem; }
        .map-picker { height: 240px; }
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
        <p class="cc-sub">Manage customer bookings and enquiry responses in dedicated workspaces.</p>
    </section>

    <div class="cc-tabbar" role="tablist" aria-label="Call center tabs">
        <button type="button" class="cc-tab-btn active" data-tab="booking" role="tab" aria-selected="true">Customer Booking</button>
        <button type="button" class="cc-tab-btn" data-tab="enquiry" role="tab" aria-selected="false">Customer Enquiry</button>
    </div>

    <section class="cc-tab-panel active" id="ccTabBooking" role="tabpanel" aria-label="Customer Booking">
        <p class="cc-tab-note">Create requests by phone and manage your submitted booking queue.</p>

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
    </section>

    <section class="cc-tab-panel" id="ccTabEnquiry" role="tabpanel" aria-label="Customer Enquiry">
        <p class="cc-tab-note">Review newest enquiries first and send exactly one response per enquiry.</p>
        <section class="cc-card">
            <h2>Customer Enquiries</h2>
            <div class="cc-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Created</th>
                            <th>Type</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Preview</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="ccEnquiryTable">
                        <tr><td colspan="6">Loading enquiries...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
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

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    (function () {
        function activateTab(tab) {
            const bookingPanel = document.getElementById('ccTabBooking');
            const enquiryPanel = document.getElementById('ccTabEnquiry');
            const buttons = document.querySelectorAll('.cc-tab-btn');

            buttons.forEach(function (btn) {
                const active = btn.getAttribute('data-tab') === tab;
                btn.classList.toggle('active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            if (bookingPanel) bookingPanel.classList.toggle('active', tab === 'booking');
            if (enquiryPanel) enquiryPanel.classList.toggle('active', tab === 'enquiry');
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
