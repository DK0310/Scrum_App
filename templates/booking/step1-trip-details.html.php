            <!-- ===== STEP 1: TRIP DETAILS ===== -->
            <div id="step1Content" style="display:none;">
                <div class="booking-grid <?= $isMinicabPage ? 'booking-grid-minicab' : '' ?>">
                    <!-- Left: Car Info Card -->
                    <?php if ($isMinicabPage): ?>
                    <div class="booking-car-card minicab-summary-card">
                        <div class="minicab-summary-hero">
                            <div class="minicab-summary-badge">MINICAB MODE</div>
                            <h3 class="minicab-summary-title">Smart Ride Summary</h3>
                            <p class="minicab-summary-sub">Live estimate updates as you choose destination, service type, and ride tier.</p>
                        </div>
                        <div class="minicab-summary-body">
                            <div class="minicab-summary-row">
                                <span>Service Type</span>
                                <strong id="miniSummaryService">Local Journey</strong>
                            </div>
                            <div class="minicab-summary-row">
                                <span>Ride Tier</span>
                                <strong id="miniSummaryTier">Select tier</strong>
                            </div>
                            <div class="minicab-summary-row">
                                <span>Estimated Distance</span>
                                <strong id="miniSummaryDistance">Set locations first</strong>
                            </div>
                            <div class="minicab-summary-row">
                                <span>Estimated Fare</span>
                                <strong id="miniSummaryFare">Select tier</strong>
                            </div>
                        </div>
                        <div class="minicab-summary-footer">
                            <div class="minicab-trust-pill">Secure Payment</div>
                            <div class="minicab-trust-pill">24/7 Dispatch</div>
                            <div class="minicab-trust-pill">Live Tracking</div>
                        </div>
                        <div class="minicab-summary-actions">
                            <button class="btn btn-primary btn-lg btn-block" onclick="goToStep2()" id="continueBtnMini">
                                Continue to Payment →
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="booking-car-card">
                        <div class="booking-car-image" id="bookingCarImage">
                            <div class="no-image-placeholder" style="height:100%;display:flex;align-items:center;justify-content:center;color:var(--gray-400);">No Photo</div>
                        </div>
                        <div class="booking-car-details">
                            <h3 id="bookingCarTitle" style="font-size:1.25rem;font-weight:800;color:var(--gray-900);margin-bottom:4px;"></h3>
                            <p id="bookingCarSub" style="font-size:0.85rem;color:var(--gray-500);margin-bottom:16px;"></p>
                            <div class="booking-car-specs" id="bookingCarSpecs"></div>
                            <div class="booking-car-price">
                                <span style="font-size:1.5rem;font-weight:800;color:var(--primary);" id="bookingCarPrice"></span>
                                <span style="font-size:0.85rem;color:var(--gray-500);">/day</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Right: Trip Form -->
                    <div class="booking-form-card">
                        <h3 style="font-size:1.125rem;font-weight:700;color:var(--gray-900);margin-bottom:20px;">📋 Trip Details</h3>

                        <!-- Booking Type -->
                        <div class="form-group" id="bookingTypeGroup">
                            <label class="form-label">Booking Type</label>
                            <div class="booking-type-grid" style="grid-template-columns: repeat(2, 1fr);">
                                <label class="booking-type-option" data-type="minicab" onclick="selectBookingType('minicab')">
                                    <span class="booking-type-icon">🚕</span>
                                    <span class="booking-type-name">Book a Minicab</span>
                                    <span class="booking-type-desc">Instant ride, like Uber/Grab</span>
                                </label>
                                <label class="booking-type-option active" data-type="with-driver" onclick="selectBookingType('with-driver')">
                                    <span class="booking-type-icon">🚗</span>
                                    <span class="booking-type-name">With Driver</span>
                                    <span class="booking-type-desc">Choose a car + assigned driver</span>
                                </label>
                            </div>
                        </div>

                        <!-- Service Type (minicab only) -->
                        <div class="form-group" id="serviceTypeGroup" style="display:none;">
                            <label class="form-label">Service Type</label>
                            <div class="service-purpose-grid" id="servicePurposeGrid">
                                <button type="button" class="service-purpose-card active" data-service="local" onclick="selectServiceTypeCard('local')" aria-label="Local Journey">
                                    <span class="service-purpose-overlay"></span>
                                    <span class="service-purpose-content">
                                        <strong>Local Journey</strong>
                                        <small>Within city</small>
                                    </span>
                                </button>
                                <button type="button" class="service-purpose-card" data-service="long-distance" onclick="selectServiceTypeCard('long-distance')" aria-label="Long Journey">
                                    <span class="service-purpose-overlay"></span>
                                    <span class="service-purpose-content">
                                        <strong>Long Journey</strong>
                                        <small>Intercity travel</small>
                                    </span>
                                </button>
                                <button type="button" class="service-purpose-card" data-service="airport-transfer" onclick="selectServiceTypeCard('airport-transfer')" aria-label="Airport Transfer">
                                    <span class="service-purpose-overlay"></span>
                                    <span class="service-purpose-content">
                                        <strong>Airport Transfer</strong>
                                        <small>Reliable pickups</small>
                                    </span>
                                </button>
                                <button type="button" class="service-purpose-card" data-service="hotel-transfer" onclick="selectServiceTypeCard('hotel-transfer')" aria-label="Hotel Transfer">
                                    <span class="service-purpose-overlay"></span>
                                    <span class="service-purpose-content">
                                        <strong>Hotel Transfer</strong>
                                        <small>Executive comfort</small>
                                    </span>
                                </button>
                            </div>
                            <select class="form-input" id="serviceType" onchange="onServiceTypeChange()" style="position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;">
                                <option value="local">🏙️ Local Journey (under 30 miles)</option>
                                <option value="long-distance">🛣️ Long Distance Journey (over 30 miles)</option>
                                <option value="airport-transfer">✈️ Airport Transfer</option>
                                <option value="hotel-transfer">🏨 Hotel Transfer</option>
                            </select>
                            <small style="display:block;margin-top:8px;color:var(--gray-600);font-size:0.8rem;">Available seat classes for every service type: 4-seat and 7-seat.</small>
                        </div>

                        <!-- Date Fields -->
                        <div id="dateFields">
                            <div class="form-row">
                                <div class="form-group" id="pickupDateGroup">
                                    <label class="form-label" id="pickupDateLabel">Pick-up Date</label>
                                    <input type="date" class="form-input" id="pickupDate" min="">
                                </div>
                                <div class="form-group" id="returnDateGroup">
                                    <label class="form-label">Return Date</label>
                                    <input type="date" class="form-input" id="returnDate" min="">
                                </div>
                            </div>
                            <!-- Scheduled datetime for minicab (hidden by default) -->
                            <div class="form-group" id="scheduledDateTimeGroup" style="display:none;">
                                <label class="form-label">📅 Scheduled Pick-up Date & Time</label>
                                <input type="datetime-local" class="form-input" id="scheduledDateTime" min="">
                                <div id="pickupDateTimeError" style="display:none;margin-top:8px;padding:10px 12px;background:#fee;border-radius:6px;border-left:3px solid #d32f2f;color:#d32f2f;font-size:0.85rem;"></div>
                                <small style="display:block;margin-top:8px;color:var(--gray-600);font-size:0.8rem;">📌 <strong>Cancellation Policy:</strong> Free cancellation and booking modifications are available only if pickup is at least 24 hours away.</small>
                            </div>
                        </div>

                        <!-- Pickup Location (all types) -->
                        <div class="form-group" id="pickupLocationGroup">
                            <label class="form-label" id="pickupLocationLabel">Pick-up Location</label>
                            <div class="location-input-wrapper">
                                <input type="text" class="form-input" id="pickupLocation" placeholder="Search for a location..." autocomplete="off">
                                <button type="button" class="location-map-btn" onclick="openMapPicker('pickup')" title="Choose on map">📍</button>
                            </div>
                            <div id="pickupMapContainer" class="map-picker-container" style="display:none;">
                                <div class="map-picker-wrapper">
                                    <div id="pickupMap" class="map-picker"></div>
                                    <button type="button" class="map-expand-btn" onclick="toggleMapExpand('pickup')" title="Expand map">⛶</button>
                                </div>
                                <div class="map-picker-footer">
                                    <span class="map-coords" id="pickupMapCoords">Drag marker or click to select location</span>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="confirmMapLocation('pickup')">✓ Confirm Location</button>
                                </div>
                            </div>
                        </div>

                        <!-- Destination Location (minicab only — hidden for with-driver) -->
                        <div class="form-group" id="returnLocationGroup" style="display:none;">
                            <label class="form-label" id="returnLocationLabel">Destination</label>
                            <div class="location-input-wrapper" id="returnLocationInputWrapper">
                                <input type="text" class="form-input" id="returnLocation" placeholder="Where do you want to go?" autocomplete="off">
                                <button type="button" class="location-map-btn" onclick="openMapPicker('return')" title="Choose on map">📍</button>
                            </div>
                            <!-- Airport selector (shown only for airport-transfer) -->
                            <div id="airportSelectWrapper" style="display:none;">
                                <select class="form-input" id="airportSelect" onchange="onAirportSelect()">
                                    <option value="">-- Select Airport --</option>
                                    <option value="Heathrow Airport, London, United Kingdom" data-lat="51.4700" data-lon="-0.4543">✈️ Heathrow (LHR) — London</option>
                                    <option value="Gatwick Airport, London, United Kingdom" data-lat="51.1537" data-lon="-0.1821">✈️ Gatwick (LGW) — London</option>
                                    <option value="Stansted Airport, London, United Kingdom" data-lat="51.8850" data-lon="0.2350">✈️ Stansted (STN) — London</option>
                                    <option value="Luton Airport, London, United Kingdom" data-lat="51.8747" data-lon="-0.3683">✈️ Luton (LTN) — London</option>
                                    <option value="London City Airport, London, United Kingdom" data-lat="51.5053" data-lon="0.0553">✈️ London City (LCY) — London</option>
                                    <option value="Manchester Airport, Manchester, United Kingdom" data-lat="53.3650" data-lon="-2.2728">✈️ Manchester (MAN) — Manchester</option>
                                    <option value="Birmingham Airport, Birmingham, United Kingdom" data-lat="52.4539" data-lon="-1.7480">✈️ Birmingham (BHX) — Birmingham</option>
                                    <option value="Edinburgh Airport, Edinburgh, United Kingdom" data-lat="55.9500" data-lon="-3.3725">✈️ Edinburgh (EDI) — Edinburgh</option>
                                    <option value="Glasgow Airport, Glasgow, United Kingdom" data-lat="55.8719" data-lon="-4.4331">✈️ Glasgow (GLA) — Glasgow</option>
                                    <option value="Bristol Airport, Bristol, United Kingdom" data-lat="51.3827" data-lon="-2.7191">✈️ Bristol (BRS) — Bristol</option>
                                </select>
                            </div>
                            <!-- Hotel selector (shown only for hotel-transfer) -->
                            <div id="hotelSelectWrapper" style="display:none;">
                                <select class="form-input" id="hotelSelect" onchange="onHotelSelect()">
                                    <option value="">-- Select Hotel --</option>
                                    <option value="The Savoy, Strand, London, United Kingdom" data-lat="51.5100" data-lon="-0.1206">🏨 The Savoy — Strand</option>
                                    <option value="The Ritz London, Piccadilly, London, United Kingdom" data-lat="51.5070" data-lon="-0.1416">🏨 The Ritz London — Piccadilly</option>
                                    <option value="Shangri-La The Shard, London, United Kingdom" data-lat="51.5045" data-lon="-0.0865">🏨 Shangri-La The Shard — Southwark</option>
                                    <option value="The Langham, 1C Portland Place, London, United Kingdom" data-lat="51.5178" data-lon="-0.1440">🏨 The Langham — Marylebone</option>
                                    <option value="Corinthia London, Whitehall Place, London, United Kingdom" data-lat="51.5067" data-lon="-0.1246">🏨 Corinthia London — Westminster</option>
                                    <option value="Park Plaza Westminster Bridge London, London, United Kingdom" data-lat="51.5008" data-lon="-0.1167">🏨 Park Plaza Westminster Bridge</option>
                                    <option value="The Dorchester, Park Lane, London, United Kingdom" data-lat="51.5078" data-lon="-0.1527">🏨 The Dorchester — Mayfair</option>
                                    <option value="The Ned London, Poultry, London, United Kingdom" data-lat="51.5134" data-lon="-0.0892">🏨 The Ned London — City of London</option>
                                    <option value="Sea Containers London, South Bank, London, United Kingdom" data-lat="51.5077" data-lon="-0.1072">🏨 Sea Containers London — South Bank</option>
                                    <option value="InterContinental London - The O2, London, United Kingdom" data-lat="51.5033" data-lon="0.0032">🏨 InterContinental The O2 — Greenwich</option>
                                </select>
                            </div>
                            <div id="returnMapContainer" class="map-picker-container" style="display:none;">
                                <div class="map-picker-wrapper">
                                    <div id="returnMap" class="map-picker"></div>
                                    <button type="button" class="map-expand-btn" onclick="toggleMapExpand('return')" title="Expand map">⛶</button>
                                </div>
                                <div class="map-picker-footer">
                                    <span class="map-coords" id="returnMapCoords">Drag marker or click to select location</span>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="confirmMapLocation('return')">✓ Confirm Location</button>
                                </div>
                            </div>
                        </div>

                        <!-- Seat Capacity (minicab only) -->
                        <div class="form-group" id="seatCapacityGroup" style="display:none;">
                            <label class="form-label">Choose Seat Capacity</label>
                            <div class="seat-capacity-grid" id="seatCapacityGrid">
                                <button type="button" class="seat-capacity-option active" data-seat="4" onclick="selectSeatCapacity(4)">
                                    <span class="seat-capacity-logo-wrap">
                                        <img src="/resources/images/logo/sedan.png" alt="Sedan 4 seats" class="seat-capacity-logo">
                                    </span>
                                    <span class="seat-capacity-title">4 Seats</span>
                                    <span class="seat-capacity-sub">Compact fare class</span>
                                </button>
                                <button type="button" class="seat-capacity-option" data-seat="7" onclick="selectSeatCapacity(7)">
                                    <span class="seat-capacity-logo-wrap">
                                        <img src="/resources/images/logo/SUV.png" alt="SUV 7 seats" class="seat-capacity-logo">
                                    </span>
                                    <span class="seat-capacity-title">7 Seats</span>
                                    <span class="seat-capacity-sub">Group fare class</span>
                                </button>
                            </div>
                            <div id="tierRecommendationText" style="margin-top:8px;color:var(--gray-500);font-size:0.85rem;"></div>
                        </div>

                        <!-- Ride Tier Selection (with-driver only — shown after locations selected) -->
                        <div class="form-group" id="rideTierGroup" style="display:none;">
                            <label class="form-label">Choose Your Ride</label>
                            <div class="ride-tier-grid" id="rideTierGrid">
                                <!-- Populated dynamically after distance is calculated -->
                                <div style="text-align:center;padding:20px;color:var(--gray-400);font-size:0.85rem;">
                                    Select pickup & destination to see ride options
                                </div>
                            </div>
                        </div>

                        <!-- Special Requests -->
                        <div class="form-group">
                            <label class="form-label">Special Requests <span style="color:var(--gray-400);font-weight:400;">(optional)</span></label>
                            <textarea class="form-textarea" id="specialRequests" placeholder="Child seat, luggage, specific pickup instructions..." rows="3"></textarea>
                        </div>

                        <!-- Trip Summary -->
                        <?php if (!$isMinicabPage): ?>
                        <div class="trip-summary" id="tripSummary" style="display:none;">
                            <div class="trip-summary-row" id="summaryDurationRow">
                                <span>Duration</span>
                                <span id="summaryDays">-</span>
                            </div>
                            <div class="trip-summary-row" id="summaryRateRow">
                                <span>Rate</span>
                                <span id="summaryRate">-</span>
                            </div>
                            <div class="trip-summary-row" id="summaryDistanceRow" style="display:none;">
                                <span>📏 Distance</span>
                                <span id="summaryDistance">-</span>
                            </div>
                            <div class="trip-summary-row" id="summaryTierRow" style="display:none;">
                                <span>� Ride Tier</span>
                                <span id="summaryTier" style="font-weight:700;color:var(--primary);">-</span>
                            </div>
                            <div class="trip-summary-row" id="summaryFareRow" style="display:none;">
                                <span>💰 Fare</span>
                                <span id="summaryFare" style="font-weight:700;color:var(--primary);">-</span>
                            </div>
                            <div class="trip-summary-row total">
                                <span>Estimated Total</span>
                                <span id="summaryTotal">-</span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!$isMinicabPage): ?>
                        <button class="btn btn-primary btn-lg btn-block" onclick="goToStep2()" id="continueBtn">
                            Continue to Payment →
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
