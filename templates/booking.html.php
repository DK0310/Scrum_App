<?php include __DIR__ . '/layout/header.html.php'; ?>
<?php $isMinicabPage = isset($bookingMode) && $bookingMode === 'minicab'; ?>

    <!-- ===== BOOKING PAGE ===== -->
    <section class="section booking-shell <?= $isMinicabPage ? 'booking-shell-minicab' : '' ?>" style="padding-top:100px;min-height:100vh;background:var(--gray-50);" id="booking">
        <div class="section-container" style="max-width:<?= $isMinicabPage ? '1240px' : '1100px' ?>;">
            
            <!-- Step Indicator -->
            <div class="booking-steps" id="bookingSteps">
                <div class="booking-step active" id="step1Indicator">
                    <span class="step-meta">
                        <span class="step-kicker">Step One</span>
                        <span class="step-label">Trip Details</span>
                    </span>
                </div>
                <div class="step-line" id="stepLine"></div>
                <div class="booking-step" id="step2Indicator">
                    <span class="step-meta">
                        <span class="step-kicker">Step Two</span>
                        <span class="step-label">Payment</span>
                    </span>
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

            <?php include __DIR__ . '/booking/step1-trip-details.html.php'; ?>
            <?php include __DIR__ . '/booking/step2-payment.html.php'; ?>

            <!-- ===== BOOKING SUCCESS ===== -->
            <div id="bookingSuccess" style="display:none;">
                <div style="text-align:center;padding:60px 20px;max-width:560px;margin:0 auto;">
                    <div style="width:80px;height:80px;border-radius:50%;background:var(--success-light);display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:2.5rem;">🎉</div>
                    <h2 style="font-size:1.5rem;font-weight:800;color:var(--gray-900);margin-bottom:8px;">Thank You!</h2>
                    <p style="color:var(--gray-500);margin-bottom:24px;line-height:1.7;" id="successMessage">Your payment has been received. Please check your email for booking details or view your orders to track the status.</p>
                    <div class="success-booking-summary" id="successSummary"></div>
                    <div style="display:flex;gap:12px;justify-content:center;margin-top:32px;flex-wrap:wrap;">
                        <a href="/orders.php" class="btn btn-primary">View My Orders</a>
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

        .booking-grid.booking-grid-minicab {
            grid-template-columns: minmax(0, 1.7fr) minmax(300px, 0.9fr);
            gap: 22px;
        }
        .booking-grid-minicab .booking-form-card {
            order: 1;
            border-radius: 24px;
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
            padding: 28px;
        }
        .booking-grid-minicab .booking-car-card {
            order: 2;
            border-radius: 20px;
            max-width: 360px;
            justify-self: end;
        }

        .minicab-summary-card {
            background: white;
            border: 1px solid var(--primary-100);
            position: sticky;
            top: 96px;
            overflow: hidden;
            box-shadow: 0 14px 32px rgba(15, 118, 110, 0.14);
        }
        .minicab-summary-hero {
            padding: 18px 18px 16px;
            background: var(--primary);
            color: white;
        }
        .minicab-summary-badge {
            display: inline-block;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            border-radius: 999px;
            padding: 6px 10px;
            background: rgba(255, 255, 255, 0.2);
            margin-bottom: 10px;
        }
        .minicab-summary-title {
            font-size: 1.12rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 6px;
        }
        .minicab-summary-sub {
            font-size: 0.78rem;
            opacity: 0.9;
        }
        .minicab-summary-body {
            padding: 12px 18px 6px;
        }
        .minicab-summary-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 9px 0;
            border-bottom: 1px dashed var(--gray-200);
            font-size: 0.79rem;
            color: var(--gray-500);
        }
        .minicab-summary-row strong {
            color: var(--gray-800);
            text-align: right;
            max-width: 60%;
            font-size: 0.84rem;
        }
        .minicab-summary-footer {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 10px 18px 12px;
        }
        .minicab-trust-pill {
            border: 1px solid var(--primary-100);
            background: var(--primary-50);
            color: var(--primary-dark);
            font-size: 0.68rem;
            font-weight: 700;
            padding: 5px 9px;
            border-radius: 999px;
        }
        .minicab-summary-actions {
            padding: 0 18px 18px;
        }
        .minicab-summary-actions .btn {
            box-shadow: 0 10px 24px rgba(15, 118, 110, 0.25);
        }

        .service-purpose-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .service-purpose-card {
            position: relative;
            aspect-ratio: 16 / 7;
            border: none;
            border-radius: 14px;
            overflow: hidden;
            cursor: pointer;
            padding: 0;
            text-align: left;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            background: var(--gray-200);
            background-size: cover;
            background-position: center;
        }
        .service-purpose-card[data-service="local"] {
            background-image: linear-gradient(145deg, rgba(15,118,110,0.25), rgba(15,118,110,0.75)), url('https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?auto=format&fit=crop&w=900&q=80');
        }
        .service-purpose-card[data-service="long-distance"] {
            background-image: linear-gradient(145deg, rgba(17,94,89,0.25), rgba(17,94,89,0.75)), url('https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=900&q=80');
        }
        .service-purpose-card[data-service="airport-transfer"] {
            background-image: linear-gradient(145deg, rgba(15,118,110,0.25), rgba(20,184,166,0.75)), url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=900&q=80');
        }
        .service-purpose-card[data-service="hotel-transfer"] {
            background-image: linear-gradient(145deg, rgba(17,94,89,0.25), rgba(15,118,110,0.75)), url('https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80');
        }
        .service-purpose-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0) 10%, rgba(0,0,0,0.62) 100%);
        }
        .service-purpose-content {
            position: absolute;
            left: 10px;
            right: 10px;
            bottom: 10px;
            color: white;
            display: flex;
            flex-direction: column;
            gap: 1px;
            z-index: 2;
        }
        .service-purpose-content strong {
            font-size: 0.88rem;
            line-height: 1.15;
        }
        .service-purpose-content small {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            opacity: 0.82;
        }
        .service-purpose-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        .service-purpose-card.active {
            outline: 3px solid var(--primary);
            outline-offset: 2px;
        }

        @media (max-width: 900px) {
            .booking-grid-minicab { grid-template-columns: 1fr; }
            .booking-grid-minicab .booking-form-card,
            .booking-grid-minicab .booking-car-card { order: initial; }
            .booking-grid-minicab .booking-car-card { max-width: none; justify-self: stretch; }
            .minicab-summary-card { position: static; }
        }
        @media (max-width: 560px) {
            .service-purpose-grid { grid-template-columns: 1fr; }
        }

        .booking-steps {
            display: flex;
            align-items: stretch;
            justify-content: center;
            gap: 12px;
            margin-bottom: 34px;
        }
        .booking-step {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 190px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid var(--gray-200);
            background: white;
            color: var(--gray-500);
            box-shadow: var(--shadow-sm);
            transition: all 0.25s ease;
        }
        .step-meta {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
            gap: 3px;
        }
        .step-kicker {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--gray-400);
            font-weight: 700;
        }
        .step-label {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--gray-700);
        }
        .booking-step.active {
            border-color: var(--primary-200);
            box-shadow: 0 10px 22px rgba(15, 118, 110, 0.16);
            background: linear-gradient(135deg, white 0%, var(--primary-50) 100%);
        }
        .booking-step.active .step-kicker,
        .booking-step.active .step-label {
            color: var(--primary-dark);
        }
        .booking-step.completed {
            border-color: #86efac;
            background: #f0fdf4;
        }
        .booking-step.completed .step-kicker,
        .booking-step.completed .step-label {
            color: #166534;
        }
        .step-line {
            width: 56px;
            align-self: center;
            height: 4px;
            border-radius: 999px;
            background: var(--gray-200);
            transition: background 0.25s ease;
        }
        .step-line.active {
            background: linear-gradient(90deg, var(--primary-300), var(--primary));
        }
        @media (max-width: 640px) {
            .booking-steps {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
                margin-bottom: 24px;
            }
            .booking-step {
                min-width: 0;
            }
            .step-line {
                width: 4px;
                height: 22px;
                margin: 0 auto;
            }
        }

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
        .seat-capacity-logo-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 30px;
        }
        .seat-capacity-logo {
            max-height: 24px;
            width: auto;
            object-fit: contain;
        }
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

        .payment-grid-modern {
            grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
            gap: 24px;
        }
        .payment-form-modern {
            border-radius: 20px;
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.08);
        }
        .payment-heading {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 18px;
        }
        .pm-chip {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            background: var(--gray-100);
            margin-bottom: 8px;
        }
        .pm-chip-logo {
            width: auto;
            height: auto;
            background: transparent;
            border-radius: 0;
            margin-bottom: 10px;
        }
        .pm-chip-logo img {
            display: block;
            height: 26px;
            width: auto;
            object-fit: contain;
        }
        .payment-method-card {
            align-items: flex-start;
            text-align: left;
            padding: 14px;
            border-radius: 14px;
            background: var(--gray-50);
        }
        .payment-method-card.active {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(15,118,110,0.15);
        }
        .promo-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--gray-800);
        }
        .payment-security-note {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--success-light);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .payment-security-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--success);
        }
        .payment-security-sub {
            font-size: 0.78rem;
            color: var(--gray-600);
        }

        .payment-summary-modern {
            border-radius: 20px;
            box-shadow: 0 16px 42px rgba(15, 23, 42, 0.1);
            top: 88px;
        }
        .payment-summary-inner {
            padding: 22px;
        }
        .payment-summary-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 14px;
        }
        .payment-route-list {
            position: relative;
            margin-bottom: 14px;
        }
        .payment-route-list::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 26px;
            bottom: 26px;
            width: 2px;
            background: var(--gray-200);
        }
        .payment-route-list .payment-detail-icon {
            width: 16px;
            text-align: center;
            color: var(--primary);
            font-size: 0.8rem;
            margin-top: 3px;
            z-index: 1;
            background: white;
        }
        .payment-detail-sub {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 2px;
        }
        .payment-price-breakdown {
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 14px;
        }
        .payment-terms-text {
            text-align: center;
            margin-top: 10px;
            font-size: 0.75rem;
            color: var(--gray-500);
            line-height: 1.45;
        }

        @media (max-width: 900px) {
            .payment-grid-modern {
                grid-template-columns: 1fr;
            }
            .payment-summary-modern {
                position: static;
            }
        }

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
