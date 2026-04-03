<?php include __DIR__ . '/layout/header.html.php'; ?>

    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <!-- ===== ALL CARS + FILTER SYSTEM ===== -->
    <section class="section featured-section" id="cars" style="padding-top:100px;background:#f8fafa;">
        <div class="section-container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">All Cars</h2>
                    <p class="section-subtitle" id="carCountText">Loading cars...</p>
                </div>
            </div>

            <!-- Filter System -->
            <div class="filter-section" id="filterSection" style="margin-bottom:42px;">
                <div class="filter-layout">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <div class="filter-group-title">Service Tier</div>
                            <div class="tier-switch" id="tierFilters">
                                <button class="filter-chip active" data-value="">All</button>
                                <button class="filter-chip" data-value="eco">Eco</button>
                                <button class="filter-chip" data-value="standard">Standard</button>
                                <button class="filter-chip" data-value="luxury">Luxury</button>
                            </div>
                        </div>
                        <div class="filter-group">
                            <div class="filter-group-title">Brand</div>
                            <div class="brand-select-wrap">
                                <select id="brandSelect" class="brand-select">
                                    <option value="">All Brands</option>
                                </select>
                                <span class="material-symbols-outlined brand-select-icon" aria-hidden="true">expand_more</span>
                            </div>
                        </div>
                        <div class="filter-group">
                            <div class="filter-group-title">Capacity</div>
                            <div class="seat-switch" id="seatFilters">
                                <button class="filter-chip active" data-value="">All</button>
                                <button class="filter-chip" data-value="4">4 Seats</button>
                                <button class="filter-chip" data-value="7">7 Seats</button>
                            </div>
                        </div>
                        <!--<div class="filter-group">
                            <div class="filter-group-title">Daily Rate (GBP) <span id="priceLabel" style="color:var(--primary);font-weight:800;">£0 – £500+</span></div>
                            <div class="filter-range">
                                <div class="range-slider-wrapper">
                                    <input type="range" min="0" max="500" value="500" step="10" id="priceRange">
                                    <div class="range-fill" id="rangeFill"></div>
                                </div>
                                <div class="filter-price-display">
                                    <span>£0</span>
                                    <span>£500+</span>
                                </div>
                            </div>
                        </div>-->
                    </div>

                    <div class="filter-actions">
                        <button class="btn btn-outline filter-reset-btn" onclick="resetFilters()">Reset</button>
                        <button class="btn btn-primary filter-apply-btn" onclick="applyAllFilters()">Apply Filters</button>
                    </div>
                </div>
            </div>

            <!-- Car Grid (loaded dynamically from API) -->
            <div class="car-grid" id="carGrid">
                <div style="grid-column:1/-1;text-align:center;padding:60px 20px;">
                    <div style="width:40px;height:40px;border:3px solid var(--gray-200);border-top-color:var(--primary);border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 16px;"></div>
                    <p style="color:var(--gray-500);">Loading available cars...</p>
                </div>
            </div>
        </div>
    </section>

    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            vertical-align: middle;
        }

        #cars .section-title {
            font-family: 'Manrope', sans-serif;
            font-size: 2.4rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
            color: #191c1d;
        }

        #cars .section-subtitle {
            font-family: 'Inter', sans-serif;
            font-size: 0.82rem;
            font-weight: 600;
            color: #3e4946;
            letter-spacing: 0.01em;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
        .no-image-placeholder {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
            background: var(--gray-100); color: var(--gray-400);
            font-size: 0.875rem; font-weight: 500;
        }

        .filter-section {
            background: #f2f4f4;
            border-radius: 14px;
            border: 1px solid rgba(190, 201, 197, 0.4);
            padding: 22px;
        }

        .filter-layout {
            display: flex;
            align-items: flex-end;
            gap: 18px;
            flex-wrap: wrap;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(160px, 1fr));
            gap: 16px;
            flex: 1;
            min-width: 0;
        }

        .filter-group-title {
            font-size: 0.64rem;
            font-weight: 800;
            color: #3e4946;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Inter', sans-serif;
        }

        .tier-switch,
        .seat-switch {
            display: flex;
            gap: 6px;
            background: #e1e3e3;
            border-radius: 10px;
            padding: 4px;
        }

        .tier-switch {
            display: inline-flex;
            width: auto;
            max-width: 100%;
            flex-wrap: nowrap;
        }

        .tier-switch .filter-chip {
            flex: 0 0 auto;
            min-width: 56px;
            white-space: nowrap;
        }

        .seat-switch {
            background: transparent;
            padding: 0;
        }

        .seat-switch .filter-chip {
            flex: 1;
        }

        .filter-chip {
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            border: 1px solid transparent;
            background: transparent;
            color: #5b6563;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
            min-width: 0;
            text-align: center;
        }

        .seat-switch .filter-chip {
            background: #e1e3e3;
            border-color: #e1e3e3;
        }

        .filter-chip:hover { color: var(--primary); }

        .filter-chip.active {
            border-color: var(--primary) !important;
            background: var(--primary) !important;
            color: white !important;
        }

        .brand-select-wrap {
            position: relative;
        }

        .brand-select {
            width: 100%;
            border: none;
            border-radius: 9px;
            background: #e1e3e3;
            padding: 10px 34px 10px 12px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #303a38;
            appearance: none;
            font-family: 'Inter', sans-serif;
        }

        .brand-select:focus {
            outline: 1px solid #84d5c5;
        }

        .brand-select-icon {
            position: absolute;
            right: 9px;
            top: 50%;
            transform: translateY(-50%);
            color: #52625e;
            pointer-events: none;
            font-size: 18px;
        }

        .range-slider-wrapper { position: relative; width: 100%; height: 24px; display: flex; align-items: center; }
        .filter-price-display {
            display: flex;
            justify-content: space-between;
            margin-top: 2px;
            color: #546460;
            font-size: 0.63rem;
            font-weight: 700;
            letter-spacing: 0.08em;
        }

        /* Vehicle Status Badge */
        .car-status-badge {
            position: absolute;
            top: 14px;
            left: 14px;
            z-index: 3;
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 0.74rem;
            line-height: 1;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }
        .car-status-badge.available {
            background: #d1fae5;
            color: #065f46;
        }
        .car-status-badge.rented {
            background: #fee2e2;
            color: #991b1b;
        }
        .car-card.car-rented { opacity: 0.8; }
        .car-card.car-rented .car-card-image img { filter: grayscale(20%); }

        .range-slider-wrapper input[type="range"] {
            -webkit-appearance: none; appearance: none; width: 100%; height: 6px;
            background: var(--gray-200); border-radius: 3px; outline: none; position: relative; z-index: 2;
        }
        .range-slider-wrapper input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none; width: 20px; height: 20px; border-radius: 50%;
            background: var(--primary); cursor: pointer; border: 2px solid white;
            box-shadow: 0 2px 8px rgba(4, 107, 94, 0.25);
        }
        .range-fill {
            position: absolute; left: 0; top: 50%; transform: translateY(-50%);
            height: 6px; background: var(--primary); border-radius: 3px; z-index: 1; pointer-events: none;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            width: 100%;
            justify-content: flex-end;
        }

        .filter-reset-btn,
        .filter-apply-btn {
            font-size: 0.74rem;
            font-weight: 800;
            border-radius: 8px;
            padding: 10px 18px;
            letter-spacing: 0.01em;
        }

        .filter-reset-btn {
            color: var(--primary);
            border-color: transparent;
            background: transparent;
        }

        .filter-apply-btn {
            box-shadow: 0 8px 16px rgba(4, 107, 94, 0.18);
        }

        .car-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 28px;
        }

        .car-card {
            background: #fff;
            border: 1px solid rgba(190, 201, 197, 0.35);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.28s ease;
        }

        .car-card:hover {
            box-shadow: 0 16px 30px rgba(0, 79, 69, 0.12);
            transform: translateY(-2px);
        }

        .car-card-image {
            position: relative;
            aspect-ratio: 4 / 3;
            height: auto !important;
            min-height: 220px;
            overflow: hidden;
            background: #e1e3e3;
            display: block !important;
            line-height: 0;
        }

        .car-card-image img {
            position: absolute;
            inset: 0;
            width: 100% !important;
            height: 100% !important;
            min-width: 100%;
            min-height: 100%;
            max-width: none !important;
            display: block !important;
            object-fit: cover !important;
            object-position: center center;
            transition: transform 0.7s ease;
        }

        .car-card-image .no-image-placeholder {
            position: absolute;
            inset: 0;
        }

        .car-card:hover .car-card-image img {
            transform: scale(1.05);
        }

        .car-card-favorite {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 34px;
            height: 34px;
            border-radius: 999px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.9);
            color: #5f6665;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }

        .car-card-favorite .material-symbols-outlined {
            font-size: 19px;
        }

        .car-card-favorite.active {
            color: #ba1a1a;
        }

        .car-card-body {
            padding: 18px;
        }

        .car-card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 12px;
        }

        .car-card-title {
            margin: 0;
            font-family: 'Manrope', sans-serif;
            font-size: 1.08rem;
            font-weight: 800;
            line-height: 1.2;
            color: #191c1d;
        }

        .car-card-rating {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            font-size: 0.74rem;
            font-weight: 800;
            color: #191c1d;
        }

        .car-card-rating .material-symbols-outlined {
            font-size: 15px;
            color: #eab308;
            font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24;
        }

        .car-card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .car-meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #3e4946;
            background: #eceeee;
            font-size: 0.69rem;
            font-weight: 700;
            border-radius: 6px;
            padding: 5px 8px;
        }

        .car-meta-chip .material-symbols-outlined {
            font-size: 14px;
        }

        .car-card-footer {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            border-top: 1px solid rgba(190, 201, 197, 0.35);
            padding-top: 14px;
        }

        .car-tier-badge {
            font-size: 0.56rem !important;
            font-weight: 800 !important;
            letter-spacing: 0.12em;
            text-transform: uppercase !important;
            padding: 5px 10px !important;
            border-radius: 999px !important;
        }

        @media (max-width: 1024px) {
            .filter-grid { grid-template-columns: 1fr 1fr; }
            .car-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 768px) {
            .filter-layout { flex-direction: column; align-items: stretch; }
            .filter-grid { grid-template-columns: 1fr; }
            .filter-actions { justify-content: stretch; }
            .filter-reset-btn,
            .filter-apply-btn { flex: 1; }
            .car-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 480px) {
            .filter-section { padding: 16px; }
            .car-card-body { padding: 14px; }
        }
    </style>

    <!-- ===== CAR DETAIL MODAL ===== -->
    <div class="modal-overlay" id="carDetailModal">
        <div class="modal" style="max-width:800px;max-height:92vh;overflow-y:auto;padding:0;">
            <!-- Image Gallery -->
            <div class="detail-gallery" id="detailGallery">
                <div class="detail-gallery-main" id="detailMainImage">
                    <span style="color:var(--gray-400);">No Photo</span>
                </div>
                <div class="detail-gallery-thumbs" id="detailThumbs"></div>
                <button class="modal-close" onclick="closeModal('carDetailModal')" style="position:absolute;top:12px;right:12px;z-index:5;background:rgba(0,0,0,0.5);color:white;border:none;width:36px;height:36px;border-radius:50%;font-size:1.1rem;cursor:pointer;">✕</button>
            </div>
            <!-- Details Body -->
            <div style="padding:28px 32px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
                    <div>
                        <h2 class="detail-car-title" id="detailTitle" style="font-size:1.5rem;font-weight:800;color:var(--gray-900);margin-bottom:4px;"></h2>
                        <p class="detail-car-sub" id="detailSub" style="font-size:0.875rem;color:var(--gray-500);"></p>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:1.75rem;font-weight:800;color:var(--primary);" id="detailPrice"></div>
                        <div style="font-size:0.8rem;color:var(--gray-500);">per day</div>
                    </div>
                </div>

                <!-- Rating -->
                <div id="detailRating" style="display:flex;align-items:center;gap:8px;margin-bottom:20px;"></div>

                <!-- Specs Grid -->
                <div class="detail-specs-grid" id="detailSpecs" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px;"></div>

                <!-- Features -->
                <div id="detailFeaturesSection" style="margin-bottom:24px;display:none;">
                    <h4 style="font-size:0.85rem;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">Features</h4>
                    <div id="detailFeatures" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
                </div>

                <!-- Location -->
                <div id="detailLocationSection" style="margin-bottom:24px;display:none;">
                    <h4 style="font-size:0.85rem;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">Location</h4>
                    <div id="detailLocation" style="font-size:0.938rem;color:var(--gray-700);"></div>
                </div>

                <!-- Owner Info -->
                <div id="detailOwnerSection" style="display:flex;align-items:center;gap:14px;padding:16px;background:var(--gray-50);border-radius:var(--radius-md);margin-bottom:24px;">
                    <div id="detailOwnerAvatar" style="width:44px;height:44px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0;"></div>
                    <div>
                        <div style="font-weight:600;color:var(--gray-800);" id="detailOwnerName"></div>
                        <div style="font-size:0.8rem;color:var(--gray-500);" id="detailOwnerLabel">Vehicle Owner</div>
                    </div>
                </div>

                <!-- Price Breakdown -->
                <div id="detailPriceSection" style="padding:16px;background:var(--primary-50);border-radius:var(--radius-md);margin-bottom:24px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <span style="font-size:0.875rem;color:var(--gray-600);">Daily Rate</span>
                        <span style="font-weight:700;color:var(--gray-800);" id="detailDailyRate"></span>
                    </div>
                    <div id="detailWeeklyRow" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;display:none;">
                        <span style="font-size:0.875rem;color:var(--gray-600);">Weekly Rate</span>
                        <span style="font-weight:700;color:var(--gray-800);" id="detailWeeklyRate"></span>
                    </div>
                    <div id="detailMonthlyRow" style="display:flex;justify-content:space-between;align-items:center;display:none;">
                        <span style="font-size:0.875rem;color:var(--gray-600);">Monthly Rate</span>
                        <span style="font-weight:700;color:var(--gray-800);" id="detailMonthlyRate"></span>
                    </div>
                </div>

                <!-- Actions -->
                <div style="display:flex;gap:12px;">
                    <button class="btn btn-outline" style="flex:1;" onclick="closeModal('carDetailModal')">Close</button>
                    <button class="btn btn-primary" style="flex:2;" id="detailBookBtn" onclick="bookCar('')">📋 Book This Car</button>
                </div>
                <div id="detailStatusNotice" style="display:none;margin-top:10px;padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:var(--radius-md);text-align:center;">
                    <span style="font-size:0.85rem;color:#991b1b;font-weight:600;">🔒 This vehicle is currently rented</span>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Car Detail Modal Styles */
        .detail-gallery { position: relative; width: 100%; background: var(--gray-900); }
        .detail-gallery-main {
            width: 100%; height: 320px; display: flex; align-items: center; justify-content: center;
            overflow: hidden;
        }
        .detail-gallery-main img { width: 100%; height: 100%; object-fit: cover; }
        .detail-gallery-thumbs {
            display: flex; gap: 6px; padding: 8px 12px; background: rgba(0,0,0,0.6);
            overflow-x: auto; position: absolute; bottom: 0; left: 0; right: 0;
        }
        .detail-gallery-thumbs:empty { display: none; }
        .detail-thumb {
            width: 56px; height: 40px; border-radius: 6px; overflow: hidden; cursor: pointer;
            border: 2px solid transparent; flex-shrink: 0; opacity: 0.6; transition: all 0.2s;
        }
        .detail-thumb:hover, .detail-thumb.active { opacity: 1; border-color: white; }
        .detail-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .detail-spec-item {
            padding: 14px 10px; background: var(--gray-50); border-radius: var(--radius-md); text-align: center;
        }
        .detail-spec-icon { font-size: 1.25rem; margin-bottom: 4px; }
        .detail-spec-label { font-size: 0.7rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px; }
        .detail-spec-value { font-size: 0.875rem; font-weight: 700; color: var(--gray-800); }
        .detail-feature-tag {
            padding: 6px 14px; border-radius: var(--radius-full); font-size: 0.8rem;
            font-weight: 500; background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-200);
        }
        @media (max-width: 640px) {
            .detail-gallery-main { height: 220px; }
            #detailSpecs { grid-template-columns: repeat(2, 1fr) !important; }
        }
    </style>

    <!-- ===== CARS PAGE JAVASCRIPT ===== -->
    <script>
        // Pass PHP variables to JavaScript module
        window.isLoggedIn = <?= json_encode($isLoggedIn) ?>;
        window.USER_ROLE = <?= json_encode($userRole ?? 'user') ?>;
    </script>
    <script src="/resources/js/cars.js"></script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
