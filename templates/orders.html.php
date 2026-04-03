<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== MY ORDERS PAGE ===== -->
    <section class="section" style="padding-top:110px;min-height:100vh;background:linear-gradient(180deg,#f8faf9 0%,#f2f5f4 100%);" id="orders">
        <div class="section-container" style="max-width:980px;">
            <div class="section-header orders-header-shell" style="margin-bottom:26px;">
                <div class="orders-title-wrap">
                    <h2 class="section-title" style="margin:0;color:#0f172a;font-size:2rem;font-weight:900;letter-spacing:-0.02em;">Rate Your Recent Trips</h2>
                    <p class="section-subtitle" style="margin:6px 0 0;color:#64748b;">Track all trip statuses and submit feedback to earn loyalty points.</p>
                </div>
                <div class="orders-points-actions">
                    <div class="orders-points-pill">
                        <img src="/resources/images/logo/star.png" alt="Star" class="orders-points-icon">
                        <span id="ordersLoyaltyPoints">0 Points</span>
                    </div>
                    <a href="/profile.php?tab=exchange-gifts" class="orders-exchange-link">
                        <span>To Exchange Points</span>
                        <span aria-hidden="true">➜</span>
                    </a>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="order-tabs" id="orderTabs">
                <button type="button" class="order-tab active" data-status="all" onclick="filterOrders('all')">All</button>
                <button type="button" class="order-tab" data-status="pending" onclick="filterOrders('pending')">Pending</button>
                <button type="button" class="order-tab" data-status="in_progress" onclick="filterOrders('in_progress')">In Progress</button>
                <button type="button" class="order-tab" data-status="completed" onclick="filterOrders('completed')">Completed</button>
                <button type="button" class="order-tab" data-status="cancelled" onclick="filterOrders('cancelled')">Cancelled</button>
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

        .orders-header-shell {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .orders-title-wrap {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .orders-points-actions {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .orders-points-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid #bfd7d1;
            background: #eef7f5;
            color: #0f766e;
            font-size: 0.82rem;
            font-weight: 800;
            white-space: nowrap;
        }
        .orders-points-icon {
            width: 20px;
            height: 20px;
            object-fit: cover;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .orders-exchange-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 13px;
            border-radius: 12px;
            border: 1px solid #bfdbd3;
            background: #ffffff;
            color: #0f766e;
            font-size: 0.8rem;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.18s ease;
            white-space: nowrap;
        }
        .orders-exchange-link:hover {
            background: #ecfdf5;
            border-color: #86efac;
            transform: translateX(1px);
        }

        .order-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 28px;
            padding: 6px;
            background: #ffffff;
            border-radius: 999px;
            border: 1px solid #d5dfdc;
            box-shadow: 0 8px 24px rgba(0, 79, 69, 0.06);
        }
        .order-tab {
            padding: 10px 18px;
            border: none;
            border-radius: 999px;
            background: transparent;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--gray-500);
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .order-tab:hover { background: var(--gray-100); color: var(--gray-700); }
        .order-tab.active { background: var(--primary); color: white; }

        .orders-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }

        .order-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #d5dfdc;
            box-shadow: 0 12px 40px rgba(0, 79, 69, 0.06);
            transition: all 0.22s ease;
            position: relative;
        }
        .order-card:hover { transform: translateY(-2px); box-shadow: 0 18px 48px rgba(0, 79, 69, 0.12); }
        .order-card.status-cancelled { opacity: 0.84; box-shadow: 0 12px 40px rgba(0, 0, 0, 0.04); }
        .order-card.status-in_progress {
            border-left: 6px solid #00695c;
            box-shadow: 0 14px 42px rgba(0, 79, 69, 0.11);
        }
        .order-card.can-open { cursor: pointer; }

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
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            padding: 22px 22px 16px;
        }
        .order-card-left { display: flex; align-items: center; gap: 16px; }
        .order-car-thumb {
            width: 172px; height: 124px; border-radius: 12px; overflow: hidden;
            background: var(--gray-100); flex-shrink: 0;
        }
        .order-car-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .order-car-info { padding-top: 2px; }
        .order-car-info h4 { font-size: 1.28rem; line-height: 1.25; font-weight: 900; color: #0f172a; margin-bottom: 4px; letter-spacing: -0.01em; }
        .order-car-info p { font-size: 0.84rem; color: #64748b; font-weight: 600; }

        .order-status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 8px; font-size: 0.66rem; font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .status-pending { background: rgba(89, 96, 95, 0.12); color: #59605f; }
        .status-confirmed { background: rgba(4, 107, 94, 0.12); color: #046b5e; }
        .status-in_progress { background: rgba(0, 79, 69, 0.12); color: #004f45; }
        .status-completed { background: rgba(0, 105, 92, 0.14); color: #00695c; }
        .status-cancelled { background: rgba(186, 26, 26, 0.14); color: #ba1a1a; }

        .order-card-body {
            padding: 0 22px 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 16px;
        }
        .order-detail-item {
            display: flex; flex-direction: column; gap: 2px;
        }
        .order-detail-label {
            font-size: 0.64rem;
            color: #94a3b8;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .order-detail-value { font-size: 0.86rem; font-weight: 800; color: #1f2937; }
        .order-detail-item.is-wide { grid-column: 1 / -1; }

        .order-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 22px;
            margin-top: 16px;
            background: rgba(236, 238, 238, 0.45);
            border-top: 1px solid #e2e8f0;
        }
        .order-total { font-size: 1.52rem; font-weight: 900; color: #00695c; letter-spacing: -0.01em; }
        .order-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }

        .order-loyalty-popover {
            position: absolute;
            top: -46px;
            right: 0;
            background: #00695c;
            color: #fff;
            font-size: 0.68rem;
            font-weight: 800;
            border-radius: 10px;
            padding: 8px 12px;
            box-shadow: 0 12px 26px rgba(0, 105, 92, 0.32);
            white-space: nowrap;
            animation: popBounce 1.8s ease-in-out infinite;
        }
        .order-loyalty-popover::after {
            content: '';
            position: absolute;
            right: 20px;
            bottom: -7px;
            width: 0;
            height: 0;
            border-left: 7px solid transparent;
            border-right: 7px solid transparent;
            border-top: 7px solid #00695c;
        }

        @keyframes popBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        /* Owner section */
        .owner-renter-info {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            padding: 6px 10px;
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            border-radius: 999px;
            font-size: 0.74rem;
        }
        .owner-renter-info span { font-weight: 700; color: #065f46; }

        @media (max-width: 700px) {
            .orders-points-actions {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
            }
            .orders-points-pill { width: 100%; justify-content: center; }
            .orders-exchange-link { width: 100%; justify-content: center; }
            .order-card-header { flex-direction: column; gap: 12px; align-items: flex-start; }
            .order-car-thumb { width: 100%; height: 180px; }
            .order-card-left { width: 100%; flex-direction: column; align-items: flex-start; }
            .order-card-body { grid-template-columns: 1fr; }
            .order-card-footer { flex-direction: column; align-items: flex-start; gap: 12px; }
            .order-actions { width: 100%; }
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
                    <div class="location-input-wrapper" id="modifyDestinationInputWrapper">
                        <input id="modifyDestination" class="form-input" type="text" placeholder="Enter destination" autocomplete="off">
                        <button type="button" class="location-map-btn" id="modifyDestinationMapBtn" onclick="openModifyMapPicker('return')" title="Choose on map">📍</button>
                    </div>
                    <div id="modifyAirportSelectWrapper" style="display:none;">
                        <select class="form-input" id="modifyAirportSelect" onchange="onModifyAirportSelect()">
                            <option value="">-- Select Airport --</option>
                            <option value="Heathrow Airport, London, United Kingdom" data-lat="51.4700" data-lon="-0.4543">Heathrow (LHR) - London</option>
                            <option value="Gatwick Airport, London, United Kingdom" data-lat="51.1537" data-lon="-0.1821">Gatwick (LGW) - London</option>
                            <option value="Stansted Airport, London, United Kingdom" data-lat="51.8850" data-lon="0.2350">Stansted (STN) - London</option>
                            <option value="Luton Airport, London, United Kingdom" data-lat="51.8747" data-lon="-0.3683">Luton (LTN) - London</option>
                            <option value="London City Airport, London, United Kingdom" data-lat="51.5053" data-lon="0.0553">London City (LCY) - London</option>
                            <option value="Manchester Airport, Manchester, United Kingdom" data-lat="53.3650" data-lon="-2.2728">Manchester (MAN) - Manchester</option>
                            <option value="Birmingham Airport, Birmingham, United Kingdom" data-lat="52.4539" data-lon="-1.7480">Birmingham (BHX) - Birmingham</option>
                            <option value="Edinburgh Airport, Edinburgh, United Kingdom" data-lat="55.9500" data-lon="-3.3725">Edinburgh (EDI) - Edinburgh</option>
                            <option value="Glasgow Airport, Glasgow, United Kingdom" data-lat="55.8719" data-lon="-4.4331">Glasgow (GLA) - Glasgow</option>
                            <option value="Bristol Airport, Bristol, United Kingdom" data-lat="51.3827" data-lon="-2.7191">Bristol (BRS) - Bristol</option>
                        </select>
                    </div>
                    <div id="modifyHotelSelectWrapper" style="display:none;">
                        <select class="form-input" id="modifyHotelSelect" onchange="onModifyHotelSelect()">
                            <option value="">-- Select Hotel --</option>
                            <option value="The Savoy, Strand, London, United Kingdom" data-lat="51.5100" data-lon="-0.1206">The Savoy - Strand</option>
                            <option value="The Ritz London, Piccadilly, London, United Kingdom" data-lat="51.5070" data-lon="-0.1416">The Ritz London - Piccadilly</option>
                            <option value="Shangri-La The Shard, London, United Kingdom" data-lat="51.5045" data-lon="-0.0865">Shangri-La The Shard - Southwark</option>
                            <option value="The Langham, 1C Portland Place, London, United Kingdom" data-lat="51.5178" data-lon="-0.1440">The Langham - Marylebone</option>
                            <option value="Corinthia London, Whitehall Place, London, United Kingdom" data-lat="51.5067" data-lon="-0.1246">Corinthia London - Westminster</option>
                            <option value="Park Plaza Westminster Bridge London, London, United Kingdom" data-lat="51.5008" data-lon="-0.1167">Park Plaza Westminster Bridge</option>
                            <option value="The Dorchester, Park Lane, London, United Kingdom" data-lat="51.5078" data-lon="-0.1527">The Dorchester - Mayfair</option>
                            <option value="The Ned London, Poultry, London, United Kingdom" data-lat="51.5134" data-lon="-0.0892">The Ned London - City of London</option>
                            <option value="Sea Containers London, South Bank, London, United Kingdom" data-lat="51.5077" data-lon="-0.1072">Sea Containers London - South Bank</option>
                            <option value="InterContinental London - The O2, London, United Kingdom" data-lat="51.5033" data-lon="0.0032">InterContinental The O2 - Greenwich</option>
                        </select>
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
                        <label class="order-detail-label" for="modifyServiceType" style="display:block;margin-bottom:6px;">Service Type</label>
                        <div class="service-purpose-grid" id="modifyServicePurposeGrid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                            <button type="button" class="service-purpose-card" data-service="local" onclick="selectModifyServiceTypeCard('local')" aria-label="Local Journey">
                                <span class="service-purpose-overlay"></span>
                                <span class="service-purpose-content">
                                    <strong>Local Journey</strong>
                                    <small>Within city</small>
                                </span>
                            </button>
                            <button type="button" class="service-purpose-card" data-service="long-distance" onclick="selectModifyServiceTypeCard('long-distance')" aria-label="Long Journey">
                                <span class="service-purpose-overlay"></span>
                                <span class="service-purpose-content">
                                    <strong>Long Journey</strong>
                                    <small>Intercity travel</small>
                                </span>
                            </button>
                            <button type="button" class="service-purpose-card" data-service="airport-transfer" onclick="selectModifyServiceTypeCard('airport-transfer')" aria-label="Airport Transfer">
                                <span class="service-purpose-overlay"></span>
                                <span class="service-purpose-content">
                                    <strong>Airport Transfer</strong>
                                    <small>Reliable pickups</small>
                                </span>
                            </button>
                            <button type="button" class="service-purpose-card" data-service="hotel-transfer" onclick="selectModifyServiceTypeCard('hotel-transfer')" aria-label="Hotel Transfer">
                                <span class="service-purpose-overlay"></span>
                                <span class="service-purpose-content">
                                    <strong>Hotel Transfer</strong>
                                    <small>Executive comfort</small>
                                </span>
                            </button>
                        </div>
                        <select id="modifyServiceType" class="form-input" style="position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;">
                            <option value="local">Local Journey</option>
                            <option value="long-distance">Long Distance Journey</option>
                            <option value="airport-transfer">Airport Transfer</option>
                            <option value="hotel-transfer">Hotel Transfer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="order-detail-label" for="modifyPickupDateTime" style="display:block;margin-bottom:6px;">Pick-up Date & Time</label>
                        <input id="modifyPickupDateTime" class="form-input" type="datetime-local">
                    </div>
                    <div class="form-group">
                        <label class="order-detail-label" for="modifyRideTier" style="display:block;margin-bottom:6px;">Ride Tier</label>
                        <select id="modifyRideTier" class="form-input">
                            <option value="eco">Eco</option>
                            <option value="standard">Standard</option>
                            <option value="luxury">Luxury</option>
                        </select>
                        <small id="modifyTierAvailabilityHint" style="display:block;margin-top:6px;color:var(--gray-500);font-size:0.75rem;">Loading availability...</small>
                    </div>
                    <div class="form-group">
                        <label class="order-detail-label" for="modifySeats" style="display:block;margin-bottom:6px;">Seats</label>
                        <div class="seat-capacity-grid" id="modifySeatCapacityGrid">
                            <button type="button" class="seat-capacity-option" data-seat="4" onclick="selectModifySeatCapacity(4)">
                                <span class="seat-capacity-title">4 Seats</span>
                                <span class="seat-capacity-sub">Compact fare class</span>
                            </button>
                            <button type="button" class="seat-capacity-option" data-seat="7" onclick="selectModifySeatCapacity(7)">
                                <span class="seat-capacity-title">7 Seats</span>
                                <span class="seat-capacity-sub">Group fare class</span>
                            </button>
                        </div>
                        <select id="modifySeats" class="form-input" style="position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;">
                            <option value="4">4</option>
                            <option value="7">7</option>
                        </select>
                        <small id="modifySeatsAvailabilityHint" style="display:block;margin-top:6px;color:var(--gray-500);font-size:0.75rem;">Loading availability...</small>
                    </div>
                </div>

                <div id="modifyPreviewPanel" style="margin-top:14px;border:1px solid #d5dfdc;border-radius:12px;background:linear-gradient(180deg,#f8fffd 0%,#f2f9f7 100%);padding:12px 14px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px;">
                        <strong style="font-size:0.85rem;color:#0f172a;">After Modify (Estimated)</strong>
                        <small style="font-size:0.74rem;color:#64748b;">Final values are confirmed after save</small>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;">
                        <div style="padding:8px 10px;border-radius:10px;background:#fff;border:1px solid #e2e8f0;">
                            <div style="font-size:0.7rem;color:#64748b;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;">Distance</div>
                            <div id="modifyPreviewDistance" style="font-size:0.9rem;color:#0f172a;font-weight:800;">-</div>
                        </div>
                        <div style="padding:8px 10px;border-radius:10px;background:#fff;border:1px solid #e2e8f0;">
                            <div style="font-size:0.7rem;color:#64748b;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;">Fare</div>
                            <div id="modifyPreviewFare" style="font-size:0.9rem;color:#0f172a;font-weight:800;">-</div>
                        </div>
                        <div style="padding:8px 10px;border-radius:10px;background:#ecfdf5;border:1px solid #a7f3d0;">
                            <div style="font-size:0.7rem;color:#065f46;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;">Total</div>
                            <div id="modifyPreviewTotal" style="font-size:1rem;color:#065f46;font-weight:900;">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="review-modal-footer" style="padding-top:8px;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModifyBookingModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="modifyBookingSaveBtn" onclick="submitModifyBooking()">Save Changes</button>
            </div>
        </div>
    </div>

    <div class="review-modal-overlay" id="reviewModalOverlay" style="display:none;">
        <div class="review-modal" role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle">
            <div class="review-modal-header">
                <h3 id="reviewModalTitle" style="margin:0 0 6px;color:var(--gray-900);">Rate & Feedback</h3>
                <p style="margin:0;color:var(--gray-600);font-size:0.9rem;">
                    Share your experience for <strong id="reviewCarName">this trip</strong> and earn loyalty points.
                </p>
            </div>
            <div class="review-modal-body">
                <label class="order-detail-label" style="display:block;margin-bottom:8px;">Your Rating</label>
                <div class="review-stars-input" id="reviewStarsInput" style="margin-bottom:18px;"></div>

                <label class="order-detail-label" for="reviewContent" style="display:block;margin-bottom:8px;">Your Feedback</label>
                <textarea
                    id="reviewContent"
                    class="form-input"
                    rows="4"
                    placeholder="Tell us about your completed trip..."
                    style="resize:vertical;min-height:110px;"
                ></textarea>
            </div>
            <div class="review-modal-footer" style="justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeReviewModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitOrderReview()">⭐ Submit Feedback (+ Loyalty Points)</button>
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
        #modifyBookingModalOverlay .review-modal {
            width: min(96vw, 980px);
            max-width: 980px;
            max-height: calc(100vh - 24px);
            display: flex;
            flex-direction: column;
        }
        #modifyBookingModalOverlay .review-modal-body {
            overflow-y: auto;
            max-height: calc(100vh - 220px);
        }
        #modifyBookingModalOverlay .review-modal-footer {
            flex-wrap: wrap;
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

    <style>
        #modifyBookingModalOverlay .service-purpose-grid {
            display: grid;
            gap: 8px;
        }
        #modifyBookingModalOverlay .service-purpose-card {
            position: relative;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #ffffff;
            padding: 10px 10px;
            min-height: 68px;
            text-align: left;
            cursor: pointer;
            transition: all 0.18s ease;
            overflow: hidden;
        }
        #modifyBookingModalOverlay .service-purpose-card .service-purpose-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(0, 105, 92, 0.12), rgba(15, 118, 110, 0.04));
            opacity: 0;
            transition: opacity 0.18s ease;
            pointer-events: none;
        }
        #modifyBookingModalOverlay .service-purpose-card .service-purpose-content {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 3px;
            z-index: 1;
        }
        #modifyBookingModalOverlay .service-purpose-card strong {
            color: #0f172a;
            font-size: 0.82rem;
            line-height: 1.2;
        }
        #modifyBookingModalOverlay .service-purpose-card small {
            color: #64748b;
            font-size: 0.74rem;
            line-height: 1.2;
        }
        #modifyBookingModalOverlay .service-purpose-card.active {
            border-color: #0f766e;
            box-shadow: 0 8px 20px rgba(15, 118, 110, 0.16);
            transform: translateY(-1px);
        }
        #modifyBookingModalOverlay .service-purpose-card.active .service-purpose-overlay {
            opacity: 1;
        }
        #modifyBookingModalOverlay .seat-capacity-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
        #modifyBookingModalOverlay .seat-capacity-option {
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #fff;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            text-align: left;
            cursor: pointer;
            transition: all 0.18s ease;
        }
        #modifyBookingModalOverlay .seat-capacity-option .seat-capacity-title {
            color: #0f172a;
            font-size: 0.82rem;
            font-weight: 800;
        }
        #modifyBookingModalOverlay .seat-capacity-option .seat-capacity-sub {
            color: #64748b;
            font-size: 0.74rem;
        }
        #modifyBookingModalOverlay .seat-capacity-option.active {
            border-color: #0f766e;
            background: linear-gradient(180deg, #ecfdf5 0%, #f0fdfa 100%);
            box-shadow: 0 8px 20px rgba(15, 118, 110, 0.15);
            transform: translateY(-1px);
        }
        @media (max-width: 760px) {
            #modifyBookingModalOverlay .service-purpose-grid {
                grid-template-columns: 1fr;
            }
            #modifyBookingModalOverlay .seat-capacity-grid {
                grid-template-columns: 1fr;
            }
            #modifyBookingModalOverlay .review-modal {
                width: 96vw;
                max-width: 96vw;
            }
        }
    </style>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
