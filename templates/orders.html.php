<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== MY ORDERS PAGE ===== -->
    <section class="section" style="padding-top:100px;min-height:100vh;background:var(--gray-50);" id="orders">
        <div class="section-container" style="max-width:1000px;">
            <div class="section-header" style="margin-bottom:32px;">
                <div>
                    <h2 class="section-title">📋 My Orders</h2>
                    <p class="section-subtitle">Track and manage your bookings</p>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="order-tabs" id="orderTabs">
                <button class="order-tab active" data-status="all" onclick="filterOrders('all')">All</button>
                <button class="order-tab" data-status="pending" onclick="filterOrders('pending')">⏳ Pending</button>
                <button class="order-tab" data-status="confirmed" onclick="filterOrders('confirmed')">✅ Confirmed</button>
                <button class="order-tab" data-status="in_progress" onclick="filterOrders('in_progress')">🚗 In Progress</button>
                <button class="order-tab" data-status="completed" onclick="filterOrders('completed')">✔️ Completed</button>
                <button class="order-tab" data-status="cancelled" onclick="filterOrders('cancelled')">❌ Cancelled</button>
            </div>

            <!-- Loading -->
            <div id="ordersLoading" style="text-align:center;padding:60px 0;">
                <div style="width:40px;height:40px;border:3px solid var(--gray-200);border-top-color:var(--primary);border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 16px;"></div>
                <p style="color:var(--gray-500);">Loading your orders...</p>
            </div>

            <!-- Empty State -->
            <div id="ordersEmpty" style="display:none;text-align:center;padding:60px 0;">
                <div style="font-size:3rem;margin-bottom:16px;">📭</div>
                <h3 style="color:var(--gray-700);margin-bottom:8px;">No orders yet</h3>
                <p style="color:var(--gray-500);margin-bottom:24px;">Start by browsing our car listings and make your first booking!</p>
                <a href="/cars.php" class="btn btn-primary" style="display:inline-block;width:auto;padding:12px 32px;">Browse Cars</a>
            </div>

            <!-- Orders List -->
            <div id="ordersList" style="display:none;"></div>
        </div>
    </section>

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }

        .order-tabs {
            display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px;
            padding: 4px; background: white; border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200);
        }
        .order-tab {
            padding: 10px 18px; border: none; border-radius: var(--radius-md);
            background: transparent; font-size: 0.85rem; font-weight: 600;
            color: var(--gray-500); cursor: pointer; transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .order-tab:hover { background: var(--gray-100); color: var(--gray-700); }
        .order-tab.active { background: var(--primary); color: white; }

        .order-card {
            background: white; border-radius: var(--radius-lg); overflow: hidden;
            box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200);
            margin-bottom: 16px; transition: all 0.2s;
        }
        .order-card:hover { box-shadow: var(--shadow-md); }
        .order-card.can-open { cursor: pointer; }
        .order-card.can-open:hover { transform: translateY(-1px); }

        .trip-vehicle-spotlight {
            display: flex; align-items: center; gap: 12px;
            background: linear-gradient(135deg, #ecfeff 0%, #cffafe 100%);
            border: 1px solid #67e8f9; border-radius: 14px;
            padding: 12px 14px; margin-bottom: 14px;
        }
        .trip-vehicle-icon {
            width: 42px; height: 42px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            background: #0f766e; color: #fff; font-size: 1.2rem; flex-shrink: 0;
        }
        .trip-vehicle-meta strong { color: #0f172a; font-size: 0.96rem; display: block; }
        .trip-vehicle-meta span { color: #0f766e; font-size: 0.82rem; font-weight: 700; letter-spacing: 0.02em; }
        .trip-vehicle-waiting {
            display: flex; align-items: center; gap: 10px;
            background: #fffbeb; border: 1px dashed #f59e0b;
            border-radius: 12px; padding: 10px 12px; margin-bottom: 14px;
            color: #92400e; font-size: 0.84rem; font-weight: 600;
        }

        .order-card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 24px; border-bottom: 1px solid var(--gray-100);
        }
        .order-card-left { display: flex; align-items: center; gap: 16px; }
        .order-car-thumb {
            width: 80px; height: 56px; border-radius: var(--radius-md); overflow: hidden;
            background: var(--gray-100); flex-shrink: 0;
        }
        .order-car-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .order-car-info h4 { font-size: 1rem; font-weight: 700; color: var(--gray-900); margin-bottom: 2px; }
        .order-car-info p { font-size: 0.8rem; color: var(--gray-500); }

        .order-status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 999px; font-size: 0.78rem; font-weight: 700;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-in_progress { background: #e0e7ff; color: #3730a3; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        .order-card-body {
            padding: 16px 24px;
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px;
        }
        .order-detail-item {
            display: flex; flex-direction: column; gap: 2px;
        }
        .order-detail-label { font-size: 0.75rem; color: var(--gray-400); font-weight: 500; }
        .order-detail-value { font-size: 0.875rem; font-weight: 600; color: var(--gray-800); }

        .order-card-footer {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 24px; background: var(--gray-50); border-top: 1px solid var(--gray-100);
        }
        .order-total { font-size: 1.1rem; font-weight: 800; color: var(--gray-900); }
        .order-actions { display: flex; gap: 8px; }

        /* Owner section */
        .owner-renter-info {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; background: var(--primary-50); border-radius: var(--radius-md);
            margin: 0 24px 16px; font-size: 0.85rem;
        }
        .owner-renter-info span { font-weight: 600; color: var(--primary); }

        @media (max-width: 600px) {
            .order-card-header { flex-direction: column; gap: 12px; align-items: flex-start; }
            .order-card-body { grid-template-columns: 1fr 1fr; }
        }
    </style>

    <div class="review-modal-overlay" id="tripDetailModalOverlay" style="display:none;">
        <div class="review-modal" role="dialog" aria-modal="true" aria-labelledby="tripDetailTitle">
            <div class="review-modal-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                <div style="text-align:left;">
                    <h3 id="tripDetailTitle" style="margin:0 0 4px;color:var(--gray-900);">Trip Details</h3>
                    <p style="margin:0;color:var(--gray-600);font-size:0.88rem;">Detail of your selected booking</p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeTripDetailModal()" style="min-width:44px;padding:8px 12px;">✕</button>
            </div>
            <div class="review-modal-body" style="padding-top:18px;">
                <div class="trip-vehicle-spotlight" id="tripVehicleSpotlight" style="display:none;">
                    <div class="trip-vehicle-icon">🚘</div>
                    <div class="trip-vehicle-meta">
                        <strong id="tripVehicleName">Assigned Vehicle</strong>
                        <span>License Plate: <span id="tripVehiclePlate">-</span></span>
                    </div>
                </div>
                <div class="trip-vehicle-waiting" id="tripVehicleWaiting" style="display:none;">
                    ⏳ Vehicle details will appear once your trip moves to In Progress.
                </div>

                <div class="order-card-body trip-detail-grid" style="padding:0;gap:14px;">
                    <div class="order-detail-item"><div class="order-detail-label">Order ID</div><div class="order-detail-value" id="tripDetailOrderId">-</div></div>
                    <div class="order-detail-item"><div class="order-detail-label">Status</div><div class="order-detail-value" id="tripDetailStatus">-</div></div>
                    <div class="order-detail-item"><div class="order-detail-label">Booking Type</div><div class="order-detail-value" id="tripDetailBookingType">-</div></div>
                    <div class="order-detail-item"><div class="order-detail-label">Service Type</div><div class="order-detail-value" id="tripDetailServiceType">-</div></div>
                    <div class="order-detail-item"><div class="order-detail-label">Ride Tier</div><div class="order-detail-value" id="tripDetailRideTier">-</div></div>
                    <div class="order-detail-item"><div class="order-detail-label">Seat Capacity</div><div class="order-detail-value" id="tripDetailSeatCapacity">-</div></div>
                    <div class="order-detail-item"><div class="order-detail-label">Pick-up Date</div><div class="order-detail-value" id="tripDetailPickupDate">-</div></div>
                    <div class="order-detail-item"><div class="order-detail-label">Return Date</div><div class="order-detail-value" id="tripDetailReturnDate">-</div></div>
                    <div class="order-detail-item" style="grid-column:1 / -1;"><div class="order-detail-label">Pick-up Location</div><div class="order-detail-value" id="tripDetailPickupLocation">-</div></div>
                    <div class="order-detail-item" style="grid-column:1 / -1;"><div class="order-detail-label">Destination</div><div class="order-detail-value" id="tripDetailDestination">-</div></div>
                    <div class="order-detail-item"><div class="order-detail-label">Distance</div><div class="order-detail-value" id="tripDetailDistance">-</div></div>
                    <div class="order-detail-item"><div class="order-detail-label">Payment</div><div class="order-detail-value" id="tripDetailPaymentMethod">-</div></div>
                    <div class="order-detail-item"><div class="order-detail-label">Booked On</div><div class="order-detail-value" id="tripDetailBookedOn">-</div></div>
                    <div class="order-detail-item"><div class="order-detail-label">Total</div><div class="order-detail-value" id="tripDetailTotalAmount">-</div></div>
                </div>
            </div>
            <div class="review-modal-footer" style="padding-top:8px;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeTripDetailModal()">Close</button>
            </div>
        </div>
    </div>

    <div class="review-modal-overlay" id="modifyBookingModalOverlay" style="display:none;">
        <div class="review-modal" role="dialog" aria-modal="true" aria-labelledby="modifyBookingTitle">
            <div class="review-modal-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                <div style="text-align:left;">
                    <h3 id="modifyBookingTitle" style="margin:0 0 4px;color:var(--gray-900);">Modify Booking</h3>
                    <p style="margin:0;color:var(--gray-600);font-size:0.88rem;">Order <span id="modifyBookingId">-</span> · Editable until 24 hours before pickup</p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModifyBookingModal()" style="min-width:44px;padding:8px 12px;">✕</button>
            </div>
            <div class="review-modal-body" style="padding-top:18px;">
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="order-detail-label" for="modifyPickupLocation" style="display:block;margin-bottom:6px;">Pick-up Location</label>
                    <div class="location-input-wrapper">
                        <input id="modifyPickupLocation" class="form-input" type="text" placeholder="Enter pickup location" autocomplete="off">
                        <button type="button" class="location-map-btn" onclick="openModifyMapPicker('pickup')" title="Choose on map">📍</button>
                    </div>
                    <div id="modifyPickupMapContainer" class="map-picker-container" style="display:none;">
                        <div class="map-picker-wrapper">
                            <div id="modifyPickupMap" class="map-picker"></div>
                            <button type="button" class="map-expand-btn" onclick="toggleModifyMapExpand('pickup')" title="Expand map">⛶</button>
                        </div>
                        <div class="map-picker-footer">
                            <span class="map-coords" id="modifyPickupMapCoords">Drag marker or click to select location</span>
                            <button type="button" class="btn btn-sm btn-primary" onclick="confirmModifyMapLocation('pickup')">✓ Confirm Location</button>
                        </div>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="order-detail-label" for="modifyDestination" style="display:block;margin-bottom:6px;">Destination</label>
                    <div class="location-input-wrapper">
                        <input id="modifyDestination" class="form-input" type="text" placeholder="Enter destination" autocomplete="off">
                        <button type="button" class="location-map-btn" onclick="openModifyMapPicker('return')" title="Choose on map">📍</button>
                    </div>
                    <div id="modifyReturnMapContainer" class="map-picker-container" style="display:none;">
                        <div class="map-picker-wrapper">
                            <div id="modifyReturnMap" class="map-picker"></div>
                            <button type="button" class="map-expand-btn" onclick="toggleModifyMapExpand('return')" title="Expand map">⛶</button>
                        </div>
                        <div class="map-picker-footer">
                            <span class="map-coords" id="modifyReturnMapCoords">Drag marker or click to select location</span>
                            <button type="button" class="btn btn-sm btn-primary" onclick="confirmModifyMapLocation('return')">✓ Confirm Location</button>
                        </div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="order-detail-label" for="modifyRideTier" style="display:block;margin-bottom:6px;">Service Tier</label>
                        <select id="modifyRideTier" class="form-input">
                            <option value="eco">Eco</option>
                            <option value="standard">Standard</option>
                            <option value="luxury">Luxury</option>
                        </select>
                        <small id="modifyTierAvailabilityHint" style="display:block;margin-top:6px;color:var(--gray-500);font-size:0.75rem;">Loading availability...</small>
                    </div>
                    <div class="form-group">
                        <label class="order-detail-label" for="modifySeats" style="display:block;margin-bottom:6px;">Seats</label>
                        <select id="modifySeats" class="form-input">
                            <option value="4">4</option>
                            <option value="7">7</option>
                        </select>
                        <small id="modifySeatsAvailabilityHint" style="display:block;margin-top:6px;color:var(--gray-500);font-size:0.75rem;">Loading availability...</small>
                    </div>
                </div>
            </div>
            <div class="review-modal-footer" style="padding-top:8px;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModifyBookingModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="modifyBookingSaveBtn" onclick="submitModifyBooking()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- OpenStreetMap + Leaflet (free, no API key needed) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script src="/resources/js/orders.js"></script>
    
    <!-- Set USER_ROLE from PHP before orders.js initializes -->
    <script>
    </script>

    <style>
        .review-modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 10000;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            animation: reviewOverlayIn 0.25s ease;
        }
        @keyframes reviewOverlayIn { from { opacity: 0; } to { opacity: 1; } }
        .review-modal {
            background: white; border-radius: 20px; width: 95%; max-width: 460px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25); overflow: hidden;
            animation: reviewModalIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes reviewModalIn { from { opacity: 0; transform: scale(0.85) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .review-modal-header {
            text-align: center; padding: 28px 28px 16px;
            background: linear-gradient(135deg, #fef9c3 0%, #fde68a 100%);
            border-bottom: 1px solid #fcd34d;
        }
        .review-modal-body { padding: 24px 28px; }
        .review-modal-footer { display: flex; gap: 10px; padding: 16px 28px 28px; }

        .review-stars-input {
            display: flex; gap: 6px; justify-content: center;
        }
        .review-star-btn {
            font-size: 2.2rem; cursor: pointer; color: var(--gray-300);
            transition: all 0.15s; user-select: none; line-height: 1;
        }
        .review-star-btn:hover, .review-star-btn.active {
            color: #f59e0b; transform: scale(1.15);
        }
        .review-star-btn:hover { text-shadow: 0 0 12px rgba(245,158,11,0.4); }

        .location-input-wrapper {
            position: relative;
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .location-map-btn {
            width: 44px;
            height: 44px;
            border: 1px solid var(--gray-300);
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
            font-size: 1rem;
        }
        .location-map-btn:hover { background: var(--gray-100); }
        .leaflet-autocomplete-list {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 52px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            box-shadow: var(--shadow-lg);
            z-index: 10020;
            max-height: 240px;
            overflow: auto;
        }
        .leaflet-autocomplete-list .autocomplete-item {
            padding: 10px 12px;
            border-bottom: 1px solid var(--gray-100);
            cursor: pointer;
        }
        .leaflet-autocomplete-list .autocomplete-item:last-child { border-bottom: none; }
        .leaflet-autocomplete-list .autocomplete-item:hover { background: var(--primary-50); }
        .leaflet-autocomplete-list .autocomplete-item .ac-main { font-weight: 600; }
        .leaflet-autocomplete-list .autocomplete-item .ac-sub { font-size: 0.75rem; color: var(--gray-500); margin-top: 2px; }

        .map-picker-container {
            margin-top: 10px;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }
        .map-picker-container.expanded {
            position: fixed;
            inset: 16px;
            z-index: 10030;
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        }
        .map-picker-container.expanded .map-picker { height: calc(100% - 52px); }
        .map-picker-wrapper { position: relative; }
        .map-picker { height: 220px; width: 100%; }
        .map-expand-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            border: 1px solid var(--gray-300);
            background: #fff;
            border-radius: 8px;
            width: 34px;
            height: 34px;
            cursor: pointer;
        }
        .map-picker-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 10px;
            border-top: 1px solid var(--gray-100);
        }
        .map-coords {
            font-size: 0.75rem;
            color: var(--gray-600);
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #tripDetailModalOverlay {
            padding: 12px;
            align-items: center;
        }
        #tripDetailModalOverlay .review-modal {
            width: min(96vw, 680px);
            max-width: 680px;
            max-height: calc(100vh - 24px);
            display: flex;
            flex-direction: column;
        }
        #tripDetailModalOverlay .review-modal-body {
            overflow-y: auto;
            max-height: calc(100vh - 220px);
        }
        .trip-detail-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .trip-detail-grid .order-detail-value {
            word-break: break-word;
        }

        @media (max-width: 768px) {
            #tripDetailModalOverlay {
                padding: 8px;
                align-items: flex-end;
            }
            #tripDetailModalOverlay .review-modal {
                width: 100%;
                max-width: none;
                max-height: calc(100vh - 12px);
                border-radius: 16px 16px 0 0;
            }
            #tripDetailModalOverlay .review-modal-header {
                padding: 18px 16px 12px;
            }
            #tripDetailModalOverlay .review-modal-body {
                padding: 16px;
                max-height: calc(100vh - 180px);
            }
            #tripDetailModalOverlay .review-modal-footer {
                padding: 10px 16px 16px;
            }
            .trip-detail-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }
    </style>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
