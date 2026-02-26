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
                <p style="color:var(--gray-500);margin-bottom:24px;">Please select a car from our listings first.</p>
                <a href="cars.php" class="btn btn-primary">Browse Cars</a>
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
                        <div class="form-group">
                            <label class="form-label">Booking Type</label>
                            <div class="booking-type-grid">
                                <label class="booking-type-option active" data-type="self-drive" onclick="selectBookingType('self-drive')">
                                    <span class="booking-type-icon">🚗</span>
                                    <span class="booking-type-name">Self-Drive</span>
                                    <span class="booking-type-desc">Drive it yourself</span>
                                </label>
                                <label class="booking-type-option" data-type="with-driver" onclick="selectBookingType('with-driver')">
                                    <span class="booking-type-icon">👨‍✈️</span>
                                    <span class="booking-type-name">With Driver</span>
                                    <span class="booking-type-desc">Professional driver</span>
                                </label>
                                <label class="booking-type-option" data-type="airport" onclick="selectBookingType('airport')">
                                    <span class="booking-type-icon">✈️</span>
                                    <span class="booking-type-name">Airport Transfer</span>
                                    <span class="booking-type-desc">To/from airport</span>
                                </label>
                            </div>
                        </div>

                        <!-- Date Fields -->
                        <div id="dateFields">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" id="pickupDateLabel">Pick-up Date</label>
                                    <input type="date" class="form-input" id="pickupDate" min="">
                                </div>
                                <div class="form-group" id="returnDateGroup">
                                    <label class="form-label">Return Date</label>
                                    <input type="date" class="form-input" id="returnDate" min="">
                                </div>
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

                        <!-- Destination / Return Location (with-driver & airport only — hidden for self-drive) -->
                        <div class="form-group" id="returnLocationGroup" style="display:none;">
                            <label class="form-label" id="returnLocationLabel">Destination</label>
                            <div class="location-input-wrapper">
                                <input type="text" class="form-input" id="returnLocation" placeholder="Where do you want to go?" autocomplete="off">
                                <button type="button" class="location-map-btn" onclick="openMapPicker('return')" title="Choose on map">📍</button>
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

                        <!-- Airport Name (airport transfer only — shown inside destination) -->
                        <div class="form-group" id="airportGroup" style="display:none;">
                            <label class="form-label">Airport Name</label>
                            <input type="text" class="form-input" id="airportName" placeholder="e.g. Tan Son Nhat International Airport">
                        </div>

                        <!-- Special Requests -->
                        <div class="form-group">
                            <label class="form-label">Special Requests <span style="color:var(--gray-400);font-weight:400;">(optional)</span></label>
                            <textarea class="form-textarea" id="specialRequests" placeholder="Child seat, luggage, specific pickup instructions..." rows="3"></textarea>
                        </div>

                        <!-- Trip Summary -->
                        <div class="trip-summary" id="tripSummary" style="display:none;">
                            <div class="trip-summary-row">
                                <span>Duration</span>
                                <span id="summaryDays">-</span>
                            </div>
                            <div class="trip-summary-row">
                                <span>Rate</span>
                                <span id="summaryRate">-</span>
                            </div>
                            <div class="trip-summary-row" id="summaryDistanceRow" style="display:none;">
                                <span>📏 Distance</span>
                                <span id="summaryDistance">-</span>
                            </div>
                            <div class="trip-summary-row" id="summaryTransferCostRow" style="display:none;">
                                <span>🚗 Transfer Cost</span>
                                <span id="summaryTransferCost" style="font-weight:700;color:var(--primary);">-</span>
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
                    <div style="width:80px;height:80px;border-radius:50%;background:var(--success-light);display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:2.5rem;">✅</div>
                    <h2 style="font-size:1.5rem;font-weight:800;color:var(--gray-900);margin-bottom:8px;">Booking Confirmed!</h2>
                    <p style="color:var(--gray-500);margin-bottom:24px;line-height:1.7;" id="successMessage">Your booking has been submitted. We'll send pickup details to your email shortly.</p>
                    <div class="success-booking-summary" id="successSummary"></div>
                    <div style="display:flex;gap:12px;justify-content:center;margin-top:32px;">
                        <a href="cars.php" class="btn btn-outline">Browse More Cars</a>
                        <a href="index.php" class="btn btn-primary">Back to Home</a>
                    </div>
                </div>
            </div>

        </div>
    </section>

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

        .booking-type-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
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

    <!-- ===== BOOKING JAVASCRIPT ===== -->
    <script>
        const VEHICLES_API = '/api/vehicles.php';
        const BOOKINGS_API = '/api/bookings.php';
        const CAR_ID = '<?= htmlspecialchars($carId) ?>';
        const INITIAL_PROMO = '<?= htmlspecialchars($promoCode) ?>';
        const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

        let carData = null;
        let selectedBookingType = 'self-drive';
        let selectedPaymentMethod = 'cash';
        let appliedPromo = null;
        let pickupMapObj = null, returnMapObj = null;
        let pickupMarker = null, returnMarker = null;

        // ===== INIT =====
        document.addEventListener('DOMContentLoaded', async () => {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('pickupDate').min = today;
            document.getElementById('returnDate').min = today;

            document.getElementById('pickupDate').addEventListener('change', () => {
                const pickup = document.getElementById('pickupDate').value;
                if (pickup) {
                    document.getElementById('returnDate').min = pickup;
                    if (document.getElementById('returnDate').value && document.getElementById('returnDate').value < pickup) {
                        document.getElementById('returnDate').value = '';
                    }
                }
                updateTripSummary();
            });
            document.getElementById('returnDate').addEventListener('change', updateTripSummary);

            if (!CAR_ID) {
                document.getElementById('bookingLoading').style.display = 'none';
                document.getElementById('bookingNoCar').style.display = 'block';
                return;
            }

            try {
                const res = await fetch(VEHICLES_API + '?action=get&vehicle_id=' + encodeURIComponent(CAR_ID));
                const data = await res.json();
                if (data.success && data.vehicle) {
                    carData = data.vehicle;
                    renderCarInfo();
                    document.getElementById('bookingLoading').style.display = 'none';
                    document.getElementById('step1Content').style.display = 'block';
                } else {
                    document.getElementById('bookingLoading').style.display = 'none';
                    document.getElementById('bookingNoCar').style.display = 'block';
                }
            } catch (err) {
                console.error('Failed to load vehicle:', err);
                document.getElementById('bookingLoading').style.display = 'none';
                document.getElementById('bookingNoCar').style.display = 'block';
            }

            loadSavedPromos();
            if (INITIAL_PROMO) document.getElementById('promoCodeInput').value = INITIAL_PROMO;
            initLeafletAutocomplete();
        });

        // ===== RENDER CAR INFO =====
        function renderCarInfo() {
            if (!carData) return;
            const c = carData;
            document.getElementById('bookingCarTitle').textContent = c.brand + ' ' + c.model;
            document.getElementById('bookingCarSub').textContent = c.year + ' · ' + ucfirst(c.category) + ' · ' + ucfirst(c.transmission);

            const imgContainer = document.getElementById('bookingCarImage');
            if (c.images && c.images.length > 0) {
                imgContainer.innerHTML = '<img src="' + c.images[0] + '" alt="' + c.brand + ' ' + c.model + '">';
            }

            const specs = [c.seats + ' Seats', ucfirst(c.fuel_type), c.engine_size || '', c.consumption || '', ucfirst(c.color || ''), c.location_city || ''].filter(s => s);
            document.getElementById('bookingCarSpecs').innerHTML = specs.slice(0, 6).map(s => '<div class="spec-chip">' + s + '</div>').join('');
            document.getElementById('bookingCarPrice').textContent = '$' + Number(c.price_per_day).toFixed(2);
        }

        function ucfirst(str) { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; }

        // ===== BOOKING TYPE =====
        function selectBookingType(type) {
            selectedBookingType = type;
            document.querySelectorAll('.booking-type-option').forEach(el => el.classList.toggle('active', el.dataset.type === type));

            const isSelfDrive = type === 'self-drive';
            const isWithDriver = type === 'with-driver';
            const isAirport = type === 'airport';

            // Date fields
            document.getElementById('returnDateGroup').style.display = isAirport ? 'none' : '';
            document.getElementById('pickupDateLabel').textContent = isAirport ? 'Transfer Date' : 'Pick-up Date';

            // Pickup label
            document.getElementById('pickupLocationLabel').textContent = isSelfDrive ? 'Vehicle Pick-up Location' : 'Pick-up Location';
            document.getElementById('pickupLocation').placeholder = isSelfDrive 
                ? 'Where do you want to pick up the car?' 
                : 'Where should we pick you up?';

            // Return/Destination location — hidden for self-drive, shown for with-driver & airport
            document.getElementById('returnLocationGroup').style.display = isSelfDrive ? 'none' : 'block';
            if (isWithDriver) {
                document.getElementById('returnLocationLabel').textContent = 'Destination';
                document.getElementById('returnLocation').placeholder = 'Where do you want to go?';
            } else if (isAirport) {
                document.getElementById('returnLocationLabel').textContent = 'Drop-off Location';
                document.getElementById('returnLocation').placeholder = 'Airport or drop-off point...';
            }

            // Airport name field — only for airport transfer
            document.getElementById('airportGroup').style.display = isAirport ? 'block' : 'none';

            // Clear return location when switching to self-drive
            if (isSelfDrive) {
                document.getElementById('returnLocation').value = '';
            }

            calculateRouteDistance();
            updateTripSummary();
        }

        // ===== TRIP SUMMARY =====
        function updateTripSummary() {
            if (!carData) return;
            const summaryDiv = document.getElementById('tripSummary');
            const pickup = document.getElementById('pickupDate').value;
            const ret = document.getElementById('returnDate').value;
            const ppd = Number(carData.price_per_day);

            if (selectedBookingType === 'airport') {
                if (pickup) {
                    summaryDiv.style.display = 'block';
                    document.getElementById('summaryDays').textContent = '1 day (transfer)';
                    document.getElementById('summaryRate').textContent = '$' + ppd.toFixed(2) + '/day';
                    
                    // Calculate total with transfer cost
                    let total = ppd;
                    if (transferCost !== null) {
                        total = transferCost; // Airport transfer: distance-based cost replaces daily rate
                    }
                    document.getElementById('summaryTotal').textContent = '$' + total.toFixed(2);
                } else { summaryDiv.style.display = 'none'; }
                return;
            }

            // Hide distance/transfer rows for non-airport (they appear from calculateRouteDistance for with-driver)
            if (selectedBookingType === 'self-drive') {
                document.getElementById('summaryDistanceRow').style.display = 'none';
                document.getElementById('summaryTransferCostRow').style.display = 'none';
            }

            if (pickup && ret) {
                const diff = Math.max(1, Math.ceil((new Date(ret) - new Date(pickup)) / 86400000));
                summaryDiv.style.display = 'block';
                document.getElementById('summaryDays').textContent = diff + ' day' + (diff > 1 ? 's' : '');
                document.getElementById('summaryRate').textContent = '$' + ppd.toFixed(2) + '/day';
                document.getElementById('summaryTotal').textContent = '$' + (diff * ppd).toFixed(2);
            } else { summaryDiv.style.display = 'none'; }
        }

        // ===== STEP NAVIGATION =====
        function goToStep2() {
            if (!isLoggedIn) { showToast('Please log in to continue booking.', 'warning'); return; }
            const pickup = document.getElementById('pickupDate').value;
            const pickupLoc = document.getElementById('pickupLocation').value.trim();
            if (!pickup) { showToast('Please select a pick-up date.', 'warning'); return; }
            if (!pickupLoc) { showToast('Please enter a pick-up location.', 'warning'); return; }

            if (selectedBookingType === 'self-drive') {
                // Self-drive: only needs pickup location + dates
                const ret = document.getElementById('returnDate').value;
                if (!ret) { showToast('Please select a return date.', 'warning'); return; }
                if (ret < pickup) { showToast('Return date must be after pick-up date.', 'warning'); return; }
            } else if (selectedBookingType === 'with-driver') {
                // With driver: needs pickup + destination + dates
                const ret = document.getElementById('returnDate').value;
                if (!ret) { showToast('Please select a return date.', 'warning'); return; }
                if (ret < pickup) { showToast('Return date must be after pick-up date.', 'warning'); return; }
                const destLoc = document.getElementById('returnLocation').value.trim();
                if (!destLoc) { showToast('Please enter a destination.', 'warning'); return; }
            } else if (selectedBookingType === 'airport') {
                // Airport: needs pickup + drop-off location + airport name
                const dropoffLoc = document.getElementById('returnLocation').value.trim();
                if (!dropoffLoc) { showToast('Please enter the drop-off location.', 'warning'); return; }
                if (!document.getElementById('airportName').value.trim()) { showToast('Please enter the airport name.', 'warning'); return; }
            }

            populatePaymentSummary();
            document.getElementById('step1Content').style.display = 'none';
            document.getElementById('step2Content').style.display = 'block';
            document.getElementById('step1Indicator').classList.remove('active');
            document.getElementById('step1Indicator').classList.add('completed');
            document.getElementById('step2Indicator').classList.add('active');
            document.getElementById('stepLine').classList.add('active');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function goToStep1() {
            document.getElementById('step2Content').style.display = 'none';
            document.getElementById('step1Content').style.display = 'block';
            document.getElementById('step2Indicator').classList.remove('active');
            document.getElementById('step1Indicator').classList.remove('completed');
            document.getElementById('step1Indicator').classList.add('active');
            document.getElementById('stepLine').classList.remove('active');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // ===== POPULATE PAYMENT SUMMARY =====
        function populatePaymentSummary() {
            if (!carData) return;
            const c = carData;
            const ppd = Number(c.price_per_day);
            const pickup = document.getElementById('pickupDate').value;
            const ret = document.getElementById('returnDate').value;
            const pickupLoc = document.getElementById('pickupLocation').value.trim();
            const returnLoc = document.getElementById('returnLocation').value.trim();

            document.getElementById('paymentCarTitle').textContent = c.brand + ' ' + c.model;
            document.getElementById('paymentCarSub').textContent = c.year + ' · ' + ucfirst(c.category);
            const typeLabels = { 'self-drive': 'Self-Drive', 'with-driver': 'With Driver', 'airport': 'Airport Transfer' };
            document.getElementById('paymentBookingType').textContent = typeLabels[selectedBookingType] || selectedBookingType;

            const thumb = document.getElementById('paymentCarThumb');
            if (c.images && c.images.length > 0) thumb.innerHTML = '<img src="' + c.images[0] + '" alt="' + c.brand + '">';

            document.getElementById('paymentPickupDate').textContent = formatDate(pickup);
            if (selectedBookingType === 'self-drive') {
                // Self-drive: no destination, return to same location
                document.getElementById('paymentReturnRow').style.display = '';
                document.getElementById('paymentReturnDate').textContent = formatDate(ret);
                document.getElementById('paymentReturnLocRow').style.display = 'none';
            } else if (selectedBookingType === 'with-driver') {
                // With driver: show destination
                document.getElementById('paymentReturnRow').style.display = '';
                document.getElementById('paymentReturnDate').textContent = formatDate(ret);
                document.getElementById('paymentReturnLocRow').style.display = '';
                document.getElementById('paymentReturnLocLabel').textContent = 'Destination';
                document.getElementById('paymentReturnLoc').textContent = returnLoc;
            } else if (selectedBookingType === 'airport') {
                // Airport: no return date, show drop-off + airport
                document.getElementById('paymentReturnRow').style.display = 'none';
                document.getElementById('paymentReturnLocRow').style.display = '';
                document.getElementById('paymentReturnLocLabel').textContent = 'Drop-off / Airport';
                document.getElementById('paymentReturnLoc').textContent = returnLoc + ' (' + document.getElementById('airportName').value.trim() + ')';
            }
            document.getElementById('paymentPickupLoc').textContent = pickupLoc;

            let totalDays = 1;
            if (selectedBookingType !== 'airport' && pickup && ret) {
                totalDays = Math.max(1, Math.ceil((new Date(ret) - new Date(pickup)) / 86400000));
            }
            const subtotal = totalDays * ppd;
            document.getElementById('paymentDailyRate').textContent = '$' + ppd.toFixed(2) + '/day';
            document.getElementById('paymentDaysLabel').textContent = totalDays + ' day' + (totalDays > 1 ? 's' : '');
            document.getElementById('paymentSubtotal').textContent = '$' + subtotal.toFixed(2);

            // Distance + transfer cost
            if (calculatedDistance !== null && selectedBookingType !== 'self-drive') {
                document.getElementById('paymentDistanceRow').style.display = '';
                document.getElementById('paymentDistance').textContent = calculatedDistance.toFixed(1) + ' km';
            } else {
                document.getElementById('paymentDistanceRow').style.display = 'none';
            }

            if (transferCost !== null && selectedBookingType === 'airport') {
                const ratePerKm = getRatePerKm(c.category);
                document.getElementById('paymentTransferRow').style.display = '';
                document.getElementById('paymentTransferCost').textContent = '$' + transferCost.toFixed(2) + ' ($' + ratePerKm + '/km)';
                // For airport: daily rate row is hidden, transfer cost replaces it
                document.getElementById('paymentDaysRow').style.display = 'none';
            } else {
                document.getElementById('paymentTransferRow').style.display = 'none';
                document.getElementById('paymentDaysRow').style.display = '';
            }

            updatePaymentTotal();
        }

        function updatePaymentTotal() {
            if (!carData) return;
            const ppd = Number(carData.price_per_day);
            const pickup = document.getElementById('pickupDate').value;
            const ret = document.getElementById('returnDate').value;
            let totalDays = 1;
            if (selectedBookingType !== 'airport' && pickup && ret) {
                totalDays = Math.max(1, Math.ceil((new Date(ret) - new Date(pickup)) / 86400000));
            }

            // For airport transfer with distance calculated, use transfer cost
            let subtotal;
            if (selectedBookingType === 'airport' && transferCost !== null) {
                subtotal = transferCost;
            } else {
                subtotal = totalDays * ppd;
            }

            let discount = 0;
            if (appliedPromo) {
                discount = appliedPromo.discount_type === 'percentage'
                    ? Math.round(subtotal * appliedPromo.discount_value / 100 * 100) / 100
                    : Math.min(appliedPromo.discount_value, subtotal);
                document.getElementById('paymentPromoRow').style.display = '';
                document.getElementById('paymentDiscount').textContent = '-$' + discount.toFixed(2);
            } else {
                document.getElementById('paymentPromoRow').style.display = 'none';
            }
            document.getElementById('paymentTotal').textContent = '$' + Math.max(0, subtotal - discount).toFixed(2);
        }

        function formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
        }

        // ===== PAYMENT METHOD =====
        function selectPaymentMethod(method) {
            selectedPaymentMethod = method;
            document.querySelectorAll('.payment-method-card').forEach(el => el.classList.toggle('active', el.dataset.method === method));
        }

        // ===== PROMO CODE =====
        async function applyPromoCode() {
            const code = document.getElementById('promoCodeInput').value.trim();
            if (!code) { showToast('Please enter a promo code.', 'warning'); return; }

            const btn = document.getElementById('promoApplyBtn');
            btn.disabled = true; btn.textContent = '...';

            try {
                const res = await fetch(BOOKINGS_API, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'validate-promo', code })
                });
                const data = await res.json();
                if (data.success) {
                    appliedPromo = data.promo;
                    showPromoApplied();
                    updatePaymentTotal();
                    showToast('✅ Promo applied: ' + data.promo.title, 'success');
                    savePromoToWallet(data.promo.code);
                } else {
                    showToast('❌ ' + data.message, 'error');
                }
            } catch (err) { showToast('Failed to validate promo code.', 'error'); }

            btn.disabled = false; btn.textContent = 'Apply';
        }

        function showPromoApplied() {
            document.getElementById('promoInputRow').style.display = 'none';
            document.getElementById('promoApplied').style.display = 'block';
            document.getElementById('promoAppliedCode').textContent = appliedPromo.code;
            document.getElementById('promoAppliedDesc').textContent = appliedPromo.discount_type === 'percentage'
                ? appliedPromo.discount_value + '% discount applied'
                : '$' + Number(appliedPromo.discount_value).toFixed(2) + ' discount applied';
        }

        function removePromo() {
            appliedPromo = null;
            document.getElementById('promoApplied').style.display = 'none';
            document.getElementById('promoInputRow').style.display = 'flex';
            document.getElementById('promoCodeInput').value = '';
            updatePaymentTotal();
            showToast('Promo code removed.', 'info');
        }

        // ===== SAVED PROMOS WALLET =====
        function savePromoToWallet(code) {
            let saved = JSON.parse(localStorage.getItem('drivenow_saved_promos') || '[]');
            if (!saved.includes(code.toUpperCase())) {
                saved.push(code.toUpperCase());
                localStorage.setItem('drivenow_saved_promos', JSON.stringify(saved));
            }
        }

        async function loadSavedPromos() {
            const saved = JSON.parse(localStorage.getItem('drivenow_saved_promos') || '[]');
            if (saved.length === 0) return;

            try {
                const res = await fetch(BOOKINGS_API, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'active-promos' })
                });
                const data = await res.json();
                if (!data.success || !data.promos) return;

                const available = data.promos.filter(p => saved.includes(p.code.toUpperCase()));
                if (available.length === 0) return;

                document.getElementById('savedPromosList').innerHTML = available.map(p => {
                    const dt = p.discount_type === 'percentage' ? p.discount_value + '% OFF' : '$' + p.discount_value + ' OFF';
                    return '<div class="saved-promo-item" onclick="useSavedPromo(\'' + p.code + '\')">' +
                        '<div><div class="saved-promo-code">' + p.code + '</div>' +
                        '<div class="saved-promo-desc">' + (p.title || '') + '</div></div>' +
                        '<span class="saved-promo-badge">' + dt + '</span></div>';
                }).join('');
                document.getElementById('savedPromosSection').style.display = 'block';
            } catch (err) { console.error('Failed to load promos:', err); }
        }

        function useSavedPromo(code) {
            document.getElementById('promoCodeInput').value = code;
            applyPromoCode();
        }

        // ===== CONFIRM BOOKING =====
        async function confirmBooking() {
            const btn = document.getElementById('confirmBtn');
            btn.disabled = true; btn.textContent = 'Processing...';

            const pickupLoc = document.getElementById('pickupLocation').value.trim();
            const returnLoc = document.getElementById('returnLocation').value.trim();

            const payload = {
                action: 'create',
                vehicle_id: CAR_ID,
                booking_type: selectedBookingType,
                pickup_date: document.getElementById('pickupDate').value,
                return_date: selectedBookingType === 'airport' ? null : document.getElementById('returnDate').value,
                pickup_location: pickupLoc,
                return_location: selectedBookingType === 'self-drive' ? pickupLoc : (returnLoc || pickupLoc),
                airport_name: selectedBookingType === 'airport' ? document.getElementById('airportName').value.trim() : null,
                special_requests: document.getElementById('specialRequests').value.trim(),
                promo_code: appliedPromo ? appliedPromo.code : '',
                payment_method: selectedPaymentMethod
            };

            try {
                const res = await fetch(BOOKINGS_API, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showBookingSuccess(data.booking);
                    addNotification('Booking Confirmed', 'Your ' + carData.brand + ' ' + carData.model + ' booking has been submitted!', 'booking');
                } else {
                    showToast('❌ ' + data.message, 'error');
                }
            } catch (err) {
                showToast('Failed to create booking. Please try again.', 'error');
            }
            btn.disabled = false; btn.textContent = 'Confirm & Book';
        }

        function showBookingSuccess(booking) {
            document.getElementById('step2Content').style.display = 'none';
            document.getElementById('bookingSteps').style.display = 'none';
            document.getElementById('bookingSuccess').style.display = 'block';

            const tl = { 'self-drive': 'Self-Drive', 'with-driver': 'With Driver', 'airport': 'Airport Transfer' };
            let html = '<div class="sb-row"><span>Vehicle</span><span>' + carData.brand + ' ' + carData.model + '</span></div>';
            html += '<div class="sb-row"><span>Type</span><span>' + (tl[selectedBookingType] || selectedBookingType) + '</span></div>';
            html += '<div class="sb-row"><span>Duration</span><span>' + booking.total_days + ' day' + (booking.total_days > 1 ? 's' : '') + '</span></div>';
            html += '<div class="sb-row"><span>Subtotal</span><span>$' + parseFloat(booking.subtotal).toFixed(2) + '</span></div>';
            if (booking.discount > 0) {
                html += '<div class="sb-row" style="color:var(--success);"><span>Discount (' + booking.promo_applied + ')</span><span>-$' + parseFloat(booking.discount).toFixed(2) + '</span></div>';
            }
            html += '<div class="sb-row total"><span>Total</span><span>$' + parseFloat(booking.total).toFixed(2) + '</span></div>';
            html += '<div class="sb-row"><span>Payment</span><span>' + formatPM(booking.payment_method) + '</span></div>';
            html += '<div class="sb-row"><span>Status</span><span style="color:var(--warning);font-weight:600;">⏳ Pending</span></div>';
            document.getElementById('successSummary').innerHTML = html;
        }

        function formatPM(m) {
            return { cash: '💵 Cash', bank_transfer: '🏦 Banking', paypal: '🅿️ PayPal', credit_card: '💳 Card' }[m] || m;
        }

        // ===== OPENSTREETMAP + LEAFLET =====
        let autocompleteTimers = {};

        let selectedAddresses = { pickup: null, return: null };
        let routeLine = null; // Polyline between pickup & destination
        let calculatedDistance = null; // in km
        let transferCost = null; // $ for airport transfer

        // Rate per km by category
        function getRatePerKm(category) {
            if (!category) return 1;
            const cat = category.toLowerCase();
            if (['sedan', 'suv', 'electric'].includes(cat)) return 1;
            if (['minibus', 'van', 'sport'].includes(cat)) return 2;
            if (cat === 'luxury') return 5;
            return 1;
        }

        // Haversine distance calculation
        function haversineDistance(lat1, lon1, lat2, lon2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        }

        // Calculate & display distance between pickup and destination
        function calculateRouteDistance() {
            const pickupAddr = selectedAddresses.pickup;
            const returnAddr = selectedAddresses['return'];
            calculatedDistance = null;
            transferCost = null;

            // Hide distance rows by default
            document.getElementById('summaryDistanceRow').style.display = 'none';
            document.getElementById('summaryTransferCostRow').style.display = 'none';

            if (!pickupAddr || !returnAddr) { updateTripSummary(); return; }
            if (selectedBookingType === 'self-drive') { updateTripSummary(); return; }

            const dist = haversineDistance(pickupAddr.lat, pickupAddr.lon, returnAddr.lat, returnAddr.lon);
            // Multiply by 1.3 for road factor (straight line → actual road estimate)
            calculatedDistance = Math.round(dist * 1.3 * 10) / 10;

            // Show distance
            document.getElementById('summaryDistanceRow').style.display = '';
            document.getElementById('summaryDistance').textContent = calculatedDistance.toFixed(1) + ' km (est.)';

            // For airport transfer, calculate transfer cost
            if (selectedBookingType === 'airport' && carData) {
                const ratePerKm = getRatePerKm(carData.category);
                transferCost = Math.round(calculatedDistance * ratePerKm * 100) / 100;
                document.getElementById('summaryTransferCostRow').style.display = '';
                document.getElementById('summaryTransferCost').textContent = '$' + transferCost.toFixed(2) + ' ($' + ratePerKm + '/km)';
            }

            updateTripSummary();
        }
        
        function initLeafletAutocomplete() {
            ['pickupLocation', 'returnLocation'].forEach(id => {
                const input = document.getElementById(id);
                if (!input) return;

                const type = id === 'pickupLocation' ? 'pickup' : 'return';

                let dropdown = input.parentElement.querySelector('.leaflet-autocomplete-list');
                if (!dropdown) {
                    dropdown = document.createElement('div');
                    dropdown.className = 'leaflet-autocomplete-list';
                    dropdown.style.display = 'none';
                    input.parentElement.appendChild(dropdown);
                }

                input.addEventListener('input', function () {
                    selectedAddresses[type] = null;
                    const query = this.value.trim();
                    if (query.length < 3) { dropdown.style.display = 'none'; return; }
                    clearTimeout(autocompleteTimers[id]);
                    autocompleteTimers[id] = setTimeout(() => searchNominatim(query, dropdown, input, type, false), 350);
                });

                input.addEventListener('blur', () => setTimeout(() => dropdown.style.display = 'none', 200));
                input.addEventListener('focus', function () {
                    if (this.value.trim().length >= 3 && dropdown.innerHTML) dropdown.style.display = 'block';
                });
            });
        }

        async function searchNominatim(query, dropdown, input, type, isMapSearch) {
            try {
                const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query) + '&limit=5&addressdetails=1&countrycodes=vn', {
                    headers: { 'Accept-Language': 'vi,en' }
                });
                const results = await res.json();
                if (results.length === 0) { dropdown.style.display = 'none'; return; }

                dropdown.innerHTML = results.map(r => {
                    const parts = r.display_name.split(',');
                    const main = parts.slice(0, 2).join(',').trim();
                    const sub = parts.slice(2).join(',').trim();
                    return '<div class="autocomplete-item" data-lat="' + r.lat + '" data-lon="' + r.lon + '" data-name="' + r.display_name.replace(/"/g, '&quot;') + '">' +
                        '<div class="ac-main">' + main + '</div>' +
                        (sub ? '<div class="ac-sub">' + sub + '</div>' : '') +
                        '</div>';
                }).join('');
                dropdown.style.display = 'block';

                dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                    item.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        const lat = parseFloat(this.dataset.lat);
                        const lon = parseFloat(this.dataset.lon);
                        const name = this.dataset.name;
                        
                        input.value = name;
                        dropdown.style.display = 'none';

                        selectedAddresses[type] = { lat, lon, name };

                        // Move marker + map to the selected location (map stays open!)
                        moveMapToLocation(type, lat, lon);
                        updateMapCoords(type, lat, lon, name);

                        calculateRouteDistance();
                        updateTripSummary();
                    });
                });
            } catch (err) { console.error('Nominatim search error:', err); }
        }

        function updateMapCoords(type, lat, lon, name) {
            const coordsEl = document.getElementById(type + 'MapCoords');
            if (coordsEl) {
                const short = name ? (name.length > 60 ? name.substring(0, 60) + '...' : name) : (lat.toFixed(5) + ', ' + lon.toFixed(5));
                coordsEl.textContent = '📍 ' + short;
            }
        }

        function moveMapToLocation(type, lat, lon) {
            const map = type === 'pickup' ? pickupMapObj : returnMapObj;
            const marker = type === 'pickup' ? pickupMarker : returnMarker;
            
            if (map && marker) {
                const latlng = L.latLng(lat, lon);
                marker.setLatLng(latlng);
                map.setView(latlng, 16, { animate: true });
            }
        }

        function openMapPicker(type) {
            const container = document.getElementById(type + 'MapContainer');
            if (container.style.display === 'none') {
                container.style.display = 'block';
                const mapDiv = document.getElementById(type + 'Map');

                // Destroy existing map
                if (type === 'pickup' && pickupMapObj) { pickupMapObj.remove(); pickupMapObj = null; }
                if (type === 'return' && returnMapObj) { returnMapObj.remove(); returnMapObj = null; }

                const saved = selectedAddresses[type];
                const center = saved ? [saved.lat, saved.lon] : [10.8231, 106.6297];
                const zoom = saved ? 16 : 13;

                const map = L.map(mapDiv).setView(center, zoom);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    maxZoom: 19
                }).addTo(map);

                const marker = L.marker(center, { draggable: true }).addTo(map);
                
                // Click on map to move marker
                map.on('click', e => {
                    marker.setLatLng(e.latlng);
                    selectedAddresses[type] = null; // Clear saved (user picking manually)
                    updateMapCoords(type, e.latlng.lat, e.latlng.lng, null);
                });
                marker.on('dragend', () => {
                    const pos = marker.getLatLng();
                    selectedAddresses[type] = null;
                    updateMapCoords(type, pos.lat, pos.lng, null);
                });

                if (type === 'pickup') { pickupMapObj = map; pickupMarker = marker; }
                else { returnMapObj = map; returnMarker = marker; }

                // Update coords display
                if (saved) updateMapCoords(type, saved.lat, saved.lon, saved.name);

                setTimeout(() => map.invalidateSize(), 200);
            } else {
                container.style.display = 'none';
                container.classList.remove('expanded');
            }
        }

        function closeMapPicker(type) {
            const container = document.getElementById(type + 'MapContainer');
            container.style.display = 'none';
            container.classList.remove('expanded');
        }

        function toggleMapExpand(type) {
            const container = document.getElementById(type + 'MapContainer');
            container.classList.toggle('expanded');
            // Re-render map after resize
            const map = type === 'pickup' ? pickupMapObj : returnMapObj;
            if (map) setTimeout(() => map.invalidateSize(), 300);
        }

        async function confirmMapLocation(type) {
            const marker = type === 'pickup' ? pickupMarker : returnMarker;
            const saved = selectedAddresses[type];
            
            if (saved && saved.name) {
                document.getElementById(type + 'Location').value = saved.name;
                closeMapPicker(type);
                showToast('📍 Location confirmed!', 'success');
                calculateRouteDistance();
                return;
            }

            if (!marker) { closeMapPicker(type); return; }
            const pos = marker.getLatLng();

            const btn = document.getElementById(type + 'MapContainer').querySelector('.map-picker-footer .btn');
            if (btn) { btn.disabled = true; btn.textContent = 'Loading...'; }

            try {
                const res = await fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + pos.lat + '&lon=' + pos.lng + '&zoom=18&addressdetails=1', {
                    headers: { 'Accept-Language': 'vi,en' }
                });
                const data = await res.json();
                const address = data.display_name || (pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6));
                document.getElementById(type + 'Location').value = address;
                selectedAddresses[type] = { lat: pos.lat, lon: pos.lng, name: address };
                updateMapCoords(type, pos.lat, pos.lng, address);
            } catch (err) {
                document.getElementById(type + 'Location').value = pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6);
                selectedAddresses[type] = { lat: pos.lat, lon: pos.lng, name: null };
            }

            if (btn) { btn.disabled = false; btn.textContent = '✓ Confirm Location'; }
            closeMapPicker(type);
            showToast('📍 Location confirmed!', 'success');
            calculateRouteDistance();
        }
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
