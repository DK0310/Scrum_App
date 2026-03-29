<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== BOOKING PAGE ===== -->
    <section class="section" style="padding-top:100px;min-height:100vh;background:var(--gray-50);" id="booking">
        <div class="section-container" style="max-width:1100px;">
            
            <!-- Step Indicator -->
            <div class="booking-steps" id="bookingSteps">
                <div class="booking-step active" id="step1Indicator">
                    <div class="step-number">1</div>
                    <div class="step-label">Trip Details</div>
                </div>
                <div class="step-line" id="stepLine"></div>
                <div class="booking-step" id="step2Indicator">
                    <div class="step-number">2</div>
                    <div class="step-label">Payment</div>
                </div>
            </div>

            <!-- Loading State -->
            <div id="bookingLoading" style="text-align:center;padding:80px 0;">
                <div style="width:40px;height:40px;border:3px solid var(--gray-200);border-top-color:var(--primary);border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 16px;"></div>
                <p style="color:var(--gray-500);">Loading vehicle details...</p>
            </div>

            <!-- No Car State -->
            <div id="bookingNoCar" style="display:none;text-align:center;padding:80px 0;">
                <div style="font-size:3rem;margin-bottom:16px;">🚗</div>
                <h3 style="color:var(--gray-700);margin-bottom:8px;">No vehicle selected</h3>
                <p style="color:var(--gray-500);margin-bottom:24px;">Select a car for hire with driver, or book a minicab instantly.</p>
                <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                    <a href="/cars.php" class="btn btn-primary">🔍 Browse Cars</a>
                    <a href="/booking.php?mode=minicab" class="btn btn-outline">🚕 Book a Minicab</a>
                </div>
            </div>

            <!-- ===== STEP 1: TRIP DETAILS ===== -->
            <div id="step1Content" style="display:none;">
                <div class="booking-grid">
                    <!-- Left: Car Info Card -->
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
                            <select class="form-input" id="serviceType" onchange="onServiceTypeChange()">
                                <option value="local">🏙️ Local Journey (under 30km)</option>
                                <option value="long-distance">🛣️ Long Distance Journey (over 30km)</option>
                                <option value="airport-transfer">✈️ Airport Transfer</option>
                                <option value="hotel-transfer">🏨 Hotel Transfer</option>
                            </select>
                            <small style="display:block;margin-top:8px;color:var(--gray-600);font-size:0.8rem;">Available seat classes for every service type: 4-seat and 7-seat.</small>
                        </div>

                        <!-- Ride Timing (minicab only) -->
                        <div class="form-group" id="rideTimingGroup" style="display:none;">
                            <label class="form-label">Ride Timing</label>
                            <div class="booking-type-grid" style="grid-template-columns: 1fr;">
                                <label class="booking-type-option active" data-timing="schedule" onclick="selectRideTiming('schedule')">
                                    <span class="booking-type-icon">📅</span>
                                    <span class="booking-type-name">Schedule</span>
                                    <span class="booking-type-desc">Pre-book your trip</span>
                                </label>
                            </div>
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
                                    <option value="Heathrow Airport, London, United Kingdom">✈️ Heathrow (LHR) — London</option>
                                    <option value="Gatwick Airport, London, United Kingdom">✈️ Gatwick (LGW) — London</option>
                                    <option value="Stansted Airport, London, United Kingdom">✈️ Stansted (STN) — London</option>
                                    <option value="Luton Airport, London, United Kingdom">✈️ Luton (LTN) — London</option>
                                    <option value="London City Airport, London, United Kingdom">✈️ London City (LCY) — London</option>
                                    <option value="Manchester Airport, Manchester, United Kingdom">✈️ Manchester (MAN) — Manchester</option>
                                    <option value="Birmingham Airport, Birmingham, United Kingdom">✈️ Birmingham (BHX) — Birmingham</option>
                                    <option value="Edinburgh Airport, Edinburgh, United Kingdom">✈️ Edinburgh (EDI) — Edinburgh</option>
                                    <option value="Glasgow Airport, Glasgow, United Kingdom">✈️ Glasgow (GLA) — Glasgow</option>
                                    <option value="Bristol Airport, Bristol, United Kingdom">✈️ Bristol (BRS) — Bristol</option>
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
                                    <span class="seat-capacity-title">🚕 4 Seats</span>
                                    <span class="seat-capacity-sub">Compact fare class</span>
                                </button>
                                <button type="button" class="seat-capacity-option" data-seat="7" onclick="selectSeatCapacity(7)">
                                    <span class="seat-capacity-title">🚐 7 Seats</span>
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

                        <button class="btn btn-primary btn-lg btn-block" onclick="goToStep2()" id="continueBtn">
                            Continue to Payment →
                        </button>
                    </div>
                </div>
            </div>

            <!-- ===== STEP 2: PAYMENT ===== -->
            <div id="step2Content" style="display:none;">
                <div class="booking-grid">
                    <!-- Left: Order Summary with Car Image -->
                    <div class="payment-summary-card">
                        <div class="payment-car-header">
                            <div class="payment-car-thumb" id="paymentCarThumb">
                                <div class="no-image-placeholder" style="height:100%;display:flex;align-items:center;justify-content:center;color:var(--gray-400);font-size:0.8rem;">No Photo</div>
                            </div>
                            <div class="payment-car-info">
                                <h3 id="paymentCarTitle" style="font-size:1.1rem;font-weight:700;color:var(--gray-900);margin-bottom:2px;"></h3>
                                <p id="paymentCarSub" style="font-size:0.8rem;color:var(--gray-500);margin-bottom:6px;"></p>
                                <span class="badge" id="paymentBookingType" style="font-size:0.75rem;"></span>
                            </div>
                        </div>

                        <div class="payment-details-list">
                            <div class="payment-detail-row">
                                <span class="payment-detail-icon">📅</span>
                                <div>
                                    <div class="payment-detail-label">Pick-up</div>
                                    <div class="payment-detail-value" id="paymentPickupDate"></div>
                                </div>
                            </div>
                            <div class="payment-detail-row" id="paymentReturnRow">
                                <span class="payment-detail-icon">📅</span>
                                <div>
                                    <div class="payment-detail-label">Return</div>
                                    <div class="payment-detail-value" id="paymentReturnDate"></div>
                                </div>
                            </div>
                            <div class="payment-detail-row">
                                <span class="payment-detail-icon">📍</span>
                                <div>
                                    <div class="payment-detail-label">Pick-up Location</div>
                                    <div class="payment-detail-value" id="paymentPickupLoc"></div>
                                </div>
                            </div>
                            <div class="payment-detail-row" id="paymentReturnLocRow">
                                <span class="payment-detail-icon">📍</span>
                                <div>
                                    <div class="payment-detail-label" id="paymentReturnLocLabel">Return Location</div>
                                    <div class="payment-detail-value" id="paymentReturnLoc"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Price Breakdown -->
                        <div class="payment-price-breakdown">
                            <div class="price-row">
                                <span>Daily Rate</span>
                                <span id="paymentDailyRate"></span>
                            </div>
                            <div class="price-row" id="paymentDaysRow">
                                <span id="paymentDaysLabel"></span>
                                <span id="paymentSubtotal"></span>
                            </div>
                            <div class="price-row" id="paymentDistanceRow" style="display:none;">
                                <span>📏 Distance</span>
                                <span id="paymentDistance"></span>
                            </div>
                            <div class="price-row" id="paymentTransferRow" style="display:none;">
                                <span>🚗 Transfer Cost</span>
                                <span id="paymentTransferCost" style="font-weight:700;color:var(--primary);"></span>
                            </div>
                            <div class="price-row promo-row" id="paymentPromoRow" style="display:none;">
                                <span style="color:var(--success);">Promo Discount</span>
                                <span style="color:var(--success);" id="paymentDiscount"></span>
                            </div>
                            <div class="price-row total-row">
                                <span>Total</span>
                                <span id="paymentTotal"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Payment Form -->
                    <div class="booking-form-card">
                        <h3 style="font-size:1.125rem;font-weight:700;color:var(--gray-900);margin-bottom:20px;">💳 Payment Method</h3>

                        <!-- Payment Methods -->
                        <div class="payment-methods-grid">
                            <div class="payment-method-card active" data-method="cash" onclick="selectPaymentMethod('cash')">
                                <span class="pm-icon">💵</span>
                                <span class="pm-name">Cash</span>
                                <span class="pm-desc">Pay on pickup</span>
                                <span class="pm-check">✓</span>
                            </div>
                            <div class="payment-method-card" data-method="bank_transfer" onclick="selectPaymentMethod('bank_transfer')">
                                <span class="pm-icon">🏦</span>
                                <span class="pm-name">Banking</span>
                                <span class="pm-desc">Bank transfer</span>
                                <span class="pm-check">✓</span>
                            </div>
                            <div class="payment-method-card" data-method="paypal" onclick="selectPaymentMethod('paypal')">
                                <span class="pm-icon">🅿️</span>
                                <span class="pm-name">PayPal</span>
                                <span class="pm-desc">Online payment</span>
                                <span class="pm-check">✓</span>
                            </div>
                            <div class="payment-method-card" data-method="credit_card" onclick="selectPaymentMethod('credit_card')">
                                <span class="pm-icon">💳</span>
                                <span class="pm-name">Card</span>
                                <span class="pm-desc">Credit / Debit</span>
                                <span class="pm-check">✓</span>
                            </div>
                        </div>

                        <!-- Promo Code Section -->
                        <div class="promo-section" id="promoSection">
                            <h4 style="font-size:0.95rem;font-weight:600;color:var(--gray-800);margin-bottom:12px;">🎟️ Promo Code</h4>
                            
                            <div class="promo-input-row" id="promoInputRow">
                                <input type="text" class="form-input" id="promoCodeInput" placeholder="Enter promo code" style="flex:1;">
                                <button class="btn btn-secondary" onclick="applyPromoCode()" id="promoApplyBtn">Apply</button>
                            </div>

                            <!-- Applied promo indicator -->
                            <div class="promo-applied" id="promoApplied" style="display:none;">
                                <div class="promo-applied-inner">
                                    <span class="promo-applied-icon">🎉</span>
                                    <div>
                                        <div class="promo-applied-code" id="promoAppliedCode"></div>
                                        <div class="promo-applied-desc" id="promoAppliedDesc"></div>
                                    </div>
                                    <button class="promo-remove-btn" onclick="removePromo()">✕</button>
                                </div>
                            </div>

                            <!-- Saved promos wallet -->
                            <div id="savedPromosSection" style="display:none;margin-top:12px;">
                                <div style="font-size:0.8rem;color:var(--gray-500);margin-bottom:8px;">Your saved promos:</div>
                                <div class="saved-promos-list" id="savedPromosList"></div>
                            </div>
                        </div>

                        <!-- Secure Notice -->
                        <div style="background:var(--success-light);border-radius:var(--radius-md);padding:14px 16px;margin-bottom:20px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span>🔒</span>
                                <div>
                                    <div style="font-size:0.85rem;font-weight:600;color:var(--success);">Secure Payment</div>
                                    <div style="font-size:0.78rem;color:var(--gray-600);">All transactions are encrypted. Your data is safe.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div style="display:flex;gap:12px;">
                            <button class="btn btn-outline" onclick="goToStep1()" style="flex:1;">← Back</button>
                            <button class="btn btn-primary btn-lg" onclick="confirmBooking()" style="flex:2;" id="confirmBtn">
                                Confirm & Book
                            </button>
                        </div>
                        <p style="text-align:center;margin-top:12px;font-size:0.78rem;color:var(--gray-400);">
                            Pickup/delivery time will be notified via email after confirmation.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ===== BOOKING SUCCESS ===== -->
            <div id="bookingSuccess" style="display:none;">
                <div style="text-align:center;padding:60px 20px;max-width:560px;margin:0 auto;">
                    <div style="width:80px;height:80px;border-radius:50%;background:var(--success-light);display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:2.5rem;">🎉</div>
                    <h2 style="font-size:1.5rem;font-weight:800;color:var(--gray-900);margin-bottom:8px;">Thank You!</h2>
                    <p style="color:var(--gray-500);margin-bottom:24px;line-height:1.7;" id="successMessage">Your payment has been received. Please check your email for booking details or view your orders to track the status.</p>
                    <div class="success-booking-summary" id="successSummary"></div>
                    <div style="display:flex;gap:12px;justify-content:center;margin-top:32px;flex-wrap:wrap;">
                        <a href="/orders.php" class="btn btn-primary">📋 View My Orders</a>
                        <a href="/cars.php" class="btn btn-outline">Browse More Cars</a>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- ===== LICENSE REQUIRED MODAL ===== -->
    <div class="license-modal-overlay" id="licenseModal" style="display:none;">
        <div class="license-modal">
            <div class="license-modal-header">
                <div class="license-modal-icon">🚫</div>
                <h3 class="license-modal-title">Driving Credentials Required</h3>
                <p class="license-modal-subtitle">To book a vehicle, you need to provide your identification information.</p>
            </div>
            <div class="license-modal-body" id="licenseModalBody">
                <!-- Dynamically populated -->
            </div>
            <div class="license-modal-actions">
                <button class="btn btn-primary" onclick="window.location.href='/profile.php'" style="flex:1;gap:8px;">
                    👤 Go to Profile
                </button>
                <button class="btn btn-secondary" onclick="closeLicenseModal()" style="flex:1;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- ===== BOOKING STYLES ===== -->
    <style>
        @keyframes spin { to { transform: rotate(360deg); } }

        .booking-steps {
            display: flex; align-items: center; justify-content: center; gap: 0; margin-bottom: 40px;
        }
        .booking-step {
            display: inline-flex; align-items: center; gap: 10px; padding: 10px 20px;
            border-radius: 999px; background: var(--gray-100); color: var(--gray-400);
            font-size: 0.875rem; font-weight: 600; transition: all 0.3s;
            line-height: 1;
        }
        .booking-step.active { background: var(--primary); color: white; }
        .booking-step.completed { background: var(--success-light); color: var(--success); }
        .step-number {
            width: 28px; height: 28px; border-radius: 50%; background: rgba(255,255,255,0.2);
            display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem;
            line-height: 1; flex-shrink: 0;
        }
        .step-label {
            line-height: 28px;
        }
        .booking-step.active .step-number { background: rgba(255,255,255,0.3); }
        .booking-step.completed .step-number { background: var(--success); color: white; }
        .step-line {
            width: 60px; height: 3px; background: var(--gray-200); margin: 0 8px; border-radius: 2px;
            transition: background 0.3s;
        }
        .step-line.active { background: var(--primary); }

        .booking-grid {
            display: grid; grid-template-columns: 380px 1fr; gap: 28px; align-items: flex-start;
        }
        @media (max-width: 900px) {
            .booking-grid { grid-template-columns: 1fr; }
        }

        .booking-car-card {
            background: white; border-radius: var(--radius-lg); overflow: hidden;
            box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); position: sticky; top: 100px;
        }
        .booking-car-image { width: 100%; height: 220px; background: var(--gray-100); overflow: hidden; }
        .booking-car-image img { width: 100%; height: 100%; object-fit: cover; }
        .booking-car-details { padding: 20px; }
        .booking-car-specs {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px;
        }
        .booking-car-specs .spec-chip {
            background: var(--gray-50); border-radius: var(--radius-sm); padding: 6px 8px;
            font-size: 0.75rem; color: var(--gray-600); text-align: center; font-weight: 500;
        }
        .booking-car-price {
            display: flex; align-items: baseline; gap: 4px; padding-top: 16px;
            border-top: 1px solid var(--gray-100);
        }

        .booking-form-card {
            background: white; border-radius: var(--radius-lg); padding: 28px;
            box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200);
        }

        .booking-type-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .booking-type-option {
            display: flex; flex-direction: column; align-items: center; gap: 4px;
            padding: 14px 8px; border: 2px solid var(--gray-200); border-radius: var(--radius-md);
            cursor: pointer; text-align: center; transition: all 0.2s; background: white;
        }
        .booking-type-option:hover { border-color: var(--primary-300); background: var(--primary-50); }
        .booking-type-option.active {
            border-color: var(--primary); background: var(--primary-50); box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }
        .booking-type-icon { font-size: 1.5rem; }
        .booking-type-name { font-size: 0.85rem; font-weight: 700; color: var(--gray-800); }
        .booking-type-desc { font-size: 0.7rem; color: var(--gray-500); }

        /* Ride Tier Cards */
        .ride-tier-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .ride-tier-card {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            padding: 16px 10px; border: 2px solid var(--gray-200); border-radius: var(--radius-md);
            cursor: pointer; text-align: center; transition: all 0.2s; background: white;
            position: relative;
        }
        .ride-tier-card:hover { border-color: var(--primary-300); background: var(--primary-50); }
        .ride-tier-card.active {
            border-color: var(--primary); background: var(--primary-50);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }
        .ride-tier-card.eco { }
        .ride-tier-card.standard { }
        .ride-tier-card.luxury { }
        .ride-tier-card.active.eco { border-color: #10b981; background: #ecfdf5; box-shadow: 0 0 0 3px rgba(16,185,129,0.15); }
        .ride-tier-card.active.standard { border-color: var(--primary); background: var(--primary-50); }
        .ride-tier-card.active.luxury { border-color: #f59e0b; background: #fffbeb; box-shadow: 0 0 0 3px rgba(245,158,11,0.15); }
        .ride-tier-icon { font-size: 1.75rem; }
        .ride-tier-name { font-size: 0.9rem; font-weight: 800; color: var(--gray-800); }
        .ride-tier-seats { font-size: 0.7rem; color: var(--gray-500); }
        .ride-tier-desc { font-size: 0.72rem; color: var(--gray-600); min-height: 32px; line-height: 1.35; }
        .ride-tier-rate { font-size: 0.75rem; color: var(--gray-500); }
        .ride-tier-price { font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-top: 2px; }
        .ride-tier-card.eco .ride-tier-price { color: #10b981; }
        .ride-tier-card.luxury .ride-tier-price { color: #f59e0b; }
        .ride-tier-badge {
            position: absolute; top: -8px; right: -8px; font-size: 0.6rem; font-weight: 700;
            padding: 2px 8px; border-radius: 999px; text-transform: uppercase;
        }
        .ride-tier-card.eco .ride-tier-badge { background: #d1fae5; color: #065f46; }
        .ride-tier-card.luxury .ride-tier-badge { background: #fef3c7; color: #92400e; }
        .seat-capacity-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .seat-capacity-option {
            border: 2px solid var(--gray-200); border-radius: var(--radius-md); background: white;
            padding: 12px 10px; text-align: center; cursor: pointer; transition: all 0.2s;
            display: flex; flex-direction: column; gap: 4px;
        }
        .seat-capacity-option:hover { border-color: var(--primary-300); background: var(--primary-50); }
        .seat-capacity-option.active { border-color: var(--primary); background: var(--primary-50); box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
        .seat-capacity-title { font-size: 0.9rem; font-weight: 700; color: var(--gray-800); }
        .seat-capacity-sub { font-size: 0.75rem; color: var(--gray-500); }
        @media (max-width: 480px) { .ride-tier-grid { grid-template-columns: 1fr; } }

        .location-input-wrapper { display: flex; gap: 8px; align-items: center; position: relative; }
        .location-input-wrapper .form-input { flex: 1; }
        .location-map-btn {
            width: 42px; height: 42px; border: 2px solid var(--gray-200); border-radius: var(--radius-md);
            background: white; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center;
            justify-content: center; transition: all 0.2s; flex-shrink: 0;
        }
        .location-map-btn:hover { border-color: var(--primary); background: var(--primary-50); }

        .map-picker-container {
            margin-top: 10px; border-radius: var(--radius-lg); border: 2px solid var(--primary-200);
            overflow: hidden; background: white; box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
        }
        .map-picker-container.expanded {
            position: fixed; top: 10px; left: 10px; right: 10px; bottom: 10px;
            z-index: 9999; margin-top: 0; border-radius: var(--radius-lg);
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
        }
        .map-picker-container.expanded .map-picker { height: calc(100% - 52px); }
        .map-picker-wrapper {
            position: relative;
        }
        .map-picker-wrapper .map-expand-btn {
            position: absolute; top: 10px; right: 10px; z-index: 1000;
            width: 36px; height: 36px; border: none; border-radius: var(--radius-sm);
            background: white; cursor: pointer; display: flex; align-items: center;
            justify-content: center; font-size: 1.1rem; transition: all 0.2s; color: var(--gray-600);
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .map-picker-wrapper .map-expand-btn:hover { background: var(--primary); color: white; }
        .map-picker-footer {
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
            padding: 10px 12px; background: var(--gray-50); border-top: 1px solid var(--gray-200);
        }
        .map-coords { font-size: 0.75rem; color: var(--gray-500); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .map-picker {
            width: 100%; height: 300px; position: relative; overflow: hidden;
            background: var(--gray-100);
        }

        .trip-summary {
            background: var(--primary-50); border-radius: var(--radius-md); padding: 16px; margin-bottom: 20px;
        }
        .trip-summary-row {
            display: flex; justify-content: space-between; font-size: 0.875rem; color: var(--gray-600); padding: 4px 0;
        }
        .trip-summary-row.total {
            border-top: 1px solid var(--primary-200); margin-top: 8px; padding-top: 10px;
            font-weight: 700; color: var(--gray-900); font-size: 1rem;
        }

        .payment-summary-card {
            background: white; border-radius: var(--radius-lg); overflow: hidden;
            box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); position: sticky; top: 100px;
        }
        .payment-car-header {
            display: flex; gap: 14px; padding: 20px; border-bottom: 1px solid var(--gray-100);
        }
        .payment-car-thumb {
            width: 100px; height: 70px; border-radius: var(--radius-md); overflow: hidden;
            background: var(--gray-100); flex-shrink: 0;
        }
        .payment-car-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .payment-car-info { flex: 1; }
        .badge {
            display: inline-block; padding: 3px 10px; border-radius: 999px; font-weight: 600;
            background: var(--primary-50); color: var(--primary);
        }

        .payment-details-list { padding: 16px 20px; }
        .payment-detail-row {
            display: flex; align-items: flex-start; gap: 10px; padding: 8px 0;
            border-bottom: 1px solid var(--gray-50);
        }
        .payment-detail-row:last-child { border-bottom: none; }
        .payment-detail-icon { font-size: 1rem; flex-shrink: 0; margin-top: 2px; }
        .payment-detail-label { font-size: 0.75rem; color: var(--gray-500); }
        .payment-detail-value { font-size: 0.875rem; font-weight: 600; color: var(--gray-800); }

        .payment-price-breakdown { padding: 16px 20px; background: var(--gray-50); }
        .price-row {
            display: flex; justify-content: space-between; font-size: 0.875rem; color: var(--gray-600); padding: 6px 0;
        }
        .price-row.total-row {
            border-top: 2px solid var(--gray-200); margin-top: 8px; padding-top: 12px;
            font-size: 1.15rem; font-weight: 800; color: var(--gray-900);
        }

        .payment-methods-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 24px; }
        .payment-method-card {
            position: relative; display: flex; flex-direction: column; align-items: center; gap: 4px;
            padding: 16px 10px; border: 2px solid var(--gray-200); border-radius: var(--radius-md);
            cursor: pointer; text-align: center; transition: all 0.2s; background: white;
        }
        .payment-method-card:hover { border-color: var(--primary-300); }
        .payment-method-card.active {
            border-color: var(--primary); background: var(--primary-50);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }
        .pm-icon { font-size: 1.5rem; }
        .pm-name { font-size: 0.85rem; font-weight: 700; color: var(--gray-800); }
        .pm-desc { font-size: 0.7rem; color: var(--gray-500); }
        .pm-check {
            position: absolute; top: 8px; right: 8px; width: 20px; height: 20px; border-radius: 50%;
            background: var(--primary); color: white; font-size: 0.7rem; display: none;
            align-items: center; justify-content: center; font-weight: 700;
        }
        .payment-method-card.active .pm-check { display: flex; }

        .promo-section {
            background: var(--gray-50); border-radius: var(--radius-md); padding: 16px; margin-bottom: 20px;
        }
        .promo-input-row { display: flex; gap: 8px; }
        .promo-applied {
            margin-top: 10px; border-radius: var(--radius-md); overflow: hidden;
            animation: promoFadeIn 0.3s ease;
        }
        @keyframes promoFadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        .promo-applied-inner {
            display: flex; align-items: center; gap: 10px; padding: 12px 14px;
            background: linear-gradient(135deg, #dcfce7 0%, #d1fae5 100%);
            border: 1px solid #86efac; border-radius: var(--radius-md);
        }
        .promo-applied-icon { font-size: 1.25rem; }
        .promo-applied-code { font-size: 0.875rem; font-weight: 700; color: #166534; }
        .promo-applied-desc { font-size: 0.78rem; color: #15803d; }
        .promo-remove-btn {
            margin-left: auto; width: 24px; height: 24px; border-radius: 50%; border: none;
            background: rgba(22,101,52,0.15); color: #166534; font-size: 0.75rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center; transition: all 0.2s;
        }
        .promo-remove-btn:hover { background: rgba(22,101,52,0.3); }

        .saved-promos-list { display: flex; flex-direction: column; gap: 6px; }
        .saved-promo-item {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            padding: 8px 12px; background: white; border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm); cursor: pointer; transition: all 0.2s;
        }
        .saved-promo-item:hover { border-color: var(--primary); background: var(--primary-50); }
        .saved-promo-code { font-size: 0.8rem; font-weight: 700; color: var(--primary); }
        .saved-promo-desc { font-size: 0.75rem; color: var(--gray-500); }
        .saved-promo-badge { font-size: 0.7rem; font-weight: 700; color: var(--success); white-space: nowrap; }

        .success-booking-summary {
            background: var(--gray-50); border-radius: var(--radius-md); padding: 20px; text-align: left; margin-top: 20px;
        }
        .success-booking-summary .sb-row {
            display: flex; justify-content: space-between; padding: 6px 0; font-size: 0.875rem; color: var(--gray-600);
        }
        .success-booking-summary .sb-row.total {
            border-top: 1px solid var(--gray-200); margin-top: 8px; padding-top: 10px;
            font-weight: 700; color: var(--gray-900);
        }
        .no-image-placeholder {
            width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
            background: var(--gray-100); color: var(--gray-400); font-size: 0.85rem;
        }

        /* License Required Modal */
        .license-modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 10000;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            animation: licenseOverlayIn 0.25s ease;
        }
        @keyframes licenseOverlayIn { from { opacity: 0; } to { opacity: 1; } }
        .license-modal {
            background: white; border-radius: 20px; width: 95%; max-width: 440px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25); overflow: hidden;
            animation: licenseModalIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes licenseModalIn { from { opacity: 0; transform: scale(0.85) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .license-modal-header {
            text-align: center; padding: 32px 28px 20px;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-bottom: 1px solid #fcd34d;
        }
        .license-modal-icon { font-size: 3rem; margin-bottom: 12px; }
        .license-modal-title { font-size: 1.2rem; font-weight: 800; color: var(--gray-900); margin-bottom: 8px; }
        .license-modal-subtitle { font-size: 0.85rem; color: var(--gray-600); line-height: 1.5; }
        .license-modal-body { padding: 20px 28px; }
        .license-missing-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 8px; }
        .license-missing-item {
            display: flex; align-items: center; gap: 12px; padding: 12px 16px;
            border-radius: var(--radius-md); font-size: 0.875rem; font-weight: 600;
        }
        .license-missing-item.missing {
            background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
        }
        .license-missing-item.expired {
            background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412;
        }
        .license-missing-item .lmi-icon { font-size: 1.25rem; flex-shrink: 0; }
        .license-missing-item .lmi-text { flex: 1; }
        .license-missing-item .lmi-status {
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
            padding: 3px 8px; border-radius: 999px; flex-shrink: 0;
        }
        .license-missing-item.missing .lmi-status { background: #fee2e2; color: #dc2626; }
        .license-missing-item.expired .lmi-status { background: #ffedd5; color: #ea580c; }
        .license-modal-actions {
            display: flex; gap: 10px; padding: 16px 28px 28px;
        }
    </style>

    <!-- OpenStreetMap + Leaflet (free, no API key needed) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .map-picker { z-index: 1; }
        /* Leaflet autocomplete dropdown */
        .leaflet-autocomplete-list {
            position: absolute; z-index: 1000; background: white; border: 1px solid var(--gray-200);
            border-radius: 8px; max-height: 220px; overflow-y: auto; box-shadow: var(--shadow-lg);
            width: 100%; top: 100%; left: 0; margin-top: 4px;
        }
        .leaflet-autocomplete-list .autocomplete-item {
            padding: 10px 14px; cursor: pointer; font-size: 0.875rem; color: var(--gray-700);
            border-bottom: 1px solid var(--gray-100); transition: background 0.15s;
        }
        .leaflet-autocomplete-list .autocomplete-item:last-child { border-bottom: none; }
        .leaflet-autocomplete-list .autocomplete-item:hover { background: var(--primary-50); color: var(--primary); }
        .leaflet-autocomplete-list .autocomplete-item .ac-main { font-weight: 600; }
        .leaflet-autocomplete-list .autocomplete-item .ac-sub { font-size: 0.75rem; color: var(--gray-400); margin-top: 2px; }
        /* Route distance badge */
        .route-distance-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; background: linear-gradient(135deg, var(--primary-50), #e8e0ff);
            border: 1px solid var(--primary-200); border-radius: 999px;
            font-size: 0.8rem; font-weight: 700; color: var(--primary);
            margin-top: 8px;
        }
    </style>

<?php include __DIR__ . '/layout/footer.html.php'; ?>

    <!-- ===== BOOKING JAVASCRIPT ===== -->
    <script>
        // Pass PHP variables to booking.js module
        window.CAR_ID = '<?= htmlspecialchars($carId) ?>';
        window.INITIAL_PROMO = '<?= htmlspecialchars($promoCode) ?>';
        window.BOOKING_MODE = '<?= htmlspecialchars($bookingMode ?? '') ?>';
        window.isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
    </script>
    <script src="/resources/js/booking.js"></script>
