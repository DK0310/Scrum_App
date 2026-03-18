<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== ALL CARS + FILTER SYSTEM ===== -->
    <section class="section featured-section" id="cars" style="padding-top:100px;">
        <div class="section-container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">All Cars</h2>
                    <p class="section-subtitle" id="carCountText">Loading cars...</p>
                </div>
            </div>

            <!-- Filter System -->
            <div class="filter-section" id="filterSection" style="margin-bottom:32px;">
                <div class="filter-grid">
                    <div class="filter-group">
                        <div class="filter-group-title">Category</div>
                        <div class="filter-options" id="categoryFilters">
                            <button class="filter-chip active" data-value="">All</button>
                            <span class="filter-loading" id="categoryLoading">Loading...</span>
                        </div>
                    </div>
                    <div class="filter-group">
                        <div class="filter-group-title">Brand</div>
                        <div class="filter-options" id="brandFilters">
                            <button class="filter-chip active" data-value="">All</button>
                            <span class="filter-loading" id="brandLoading">Loading...</span>
                        </div>
                    </div>
                    <div class="filter-group">
                        <div class="filter-group-title">Transmission</div>
                        <div class="filter-options" id="transFilters">
                            <button class="filter-chip active" data-value="">All</button>
                            <button class="filter-chip" data-value="automatic">Automatic</button>
                            <button class="filter-chip" data-value="manual">Manual</button>
                        </div>
                    </div>
                    <div class="filter-group">
                        <div class="filter-group-title">Fuel Type</div>
                        <div class="filter-options" id="fuelFilters">
                            <button class="filter-chip active" data-value="">All</button>
                            <button class="filter-chip" data-value="petrol">Petrol</button>
                            <button class="filter-chip" data-value="diesel">Diesel</button>
                            <button class="filter-chip" data-value="electric">Electric</button>
                            <button class="filter-chip" data-value="hybrid">Hybrid</button>
                        </div>
                    </div>
                    <div class="filter-group">
                        <div class="filter-group-title">Price Range <span id="priceLabel" style="color:var(--primary);font-weight:800;">$0 – $500+</span></div>
                        <div class="filter-range">
                            <div class="range-slider-wrapper">
                                <input type="range" min="0" max="500" value="500" step="10" id="priceRange">
                                <div class="range-fill" id="rangeFill"></div>
                            </div>
                            <div class="filter-price-display">
                                <span>$0</span>
                                <span>$500+</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="filter-actions">
                    <button class="btn btn-outline filter-reset-btn" onclick="resetFilters()">↩ Reset</button>
                    <button class="btn btn-primary filter-apply-btn" onclick="applyAllFilters()">🔍 Apply Filters</button>
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
        @keyframes spin { to { transform: rotate(360deg); } }
        .no-image-placeholder {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
            background: var(--gray-100); color: var(--gray-400);
            font-size: 0.875rem; font-weight: 500;
        }

        /* ===== FILTER OVERRIDES ===== */
        .filter-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr 1fr; gap: 24px; }
        .filter-group-title { font-size: 0.8rem; font-weight: 700; color: var(--gray-600); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.05em; display: flex; justify-content: space-between; align-items: center; }
        .filter-chip {
            padding: 6px 14px; border-radius: var(--radius-full);
            font-size: 0.8rem; font-weight: 500; cursor: pointer;
            border: 1.5px solid var(--gray-200); background: white; color: var(--gray-600);
            transition: all 0.2s ease;
        }
        .filter-chip:hover { border-color: var(--primary); color: var(--primary); }
        .filter-chip.active {
            border-color: var(--primary) !important; background: var(--primary) !important;
            color: white !important; font-weight: 600;
        }

        /* Price Range */
        .range-slider-wrapper { position: relative; width: 100%; height: 24px; display: flex; align-items: center; }

        /* Vehicle Status Badge */
        .car-status-badge {
            position: absolute; top: 10px; left: 10px; z-index: 3;
            padding: 4px 12px; border-radius: 999px; font-size: 0.7rem; font-weight: 700;
            letter-spacing: 0.3px; text-transform: uppercase; backdrop-filter: blur(4px);
        }
        .car-status-badge.available {
            background: rgba(16, 185, 129, 0.9); color: white;
        }
        .car-status-badge.rented {
            background: rgba(239, 68, 68, 0.9); color: white;
        }
        .car-card.car-rented { opacity: 0.75; }
        .car-card.car-rented .car-card-image img { filter: grayscale(30%); }

        .range-slider-wrapper input[type="range"] {
            -webkit-appearance: none; appearance: none; width: 100%; height: 6px;
            background: var(--gray-200); border-radius: 3px; outline: none; position: relative; z-index: 2;
        }
        .range-slider-wrapper input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none; width: 20px; height: 20px; border-radius: 50%;
            background: var(--primary); cursor: pointer; border: 3px solid white;
            box-shadow: 0 2px 6px rgba(37,99,235,0.3);
        }
        .range-fill {
            position: absolute; left: 0; top: 50%; transform: translateY(-50%);
            height: 6px; background: var(--primary); border-radius: 3px; z-index: 1; pointer-events: none;
        }

        /* Filter Actions */
        .filter-actions {
            display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;
            padding-top: 16px; border-top: 1px solid var(--gray-100);
        }
        .filter-reset-btn { font-size: 0.875rem; padding: 8px 20px; }
        .filter-apply-btn { font-size: 0.875rem; padding: 8px 24px; }
        .filter-loading { font-size: 0.75rem; color: var(--gray-400); font-style: italic; }

        @media (max-width: 1024px) {
            .filter-grid { grid-template-columns: 1fr 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .filter-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .filter-grid { grid-template-columns: 1fr; }
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
