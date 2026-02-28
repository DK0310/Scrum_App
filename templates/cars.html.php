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
        const isLoggedIn = <?= json_encode($isLoggedIn) ?>;
        const VEHICLES_API = '/api/vehicles.php';
        let allLoadedCars = [];

        // ===== FILTER STATE =====
        let filterState = {
            brand: '',
            transmission: '',
            fuel: '',
            max_price: 500,
            category: '',
            search: ''
        };

        // Init from URL params
        (function initFromURL() {
            const p = new URLSearchParams(window.location.search);
            filterState.brand = p.get('brand') || '';
            filterState.transmission = p.get('transmission') || '';
            filterState.fuel = p.get('fuel') || '';
            filterState.max_price = parseInt(p.get('max_price')) || 500;
            filterState.category = p.get('category') || '';
            filterState.search = p.get('search') || '';

            // Set active chips from URL (for static filters)
            setActiveChip('transFilters', filterState.transmission);
            setActiveChip('fuelFilters', filterState.fuel);

            // Set price range
            const range = document.getElementById('priceRange');
            range.value = filterState.max_price;
            updatePriceDisplay(filterState.max_price);

            // Show clear button if search has value
            if (filterState.search) {
                const navClear = document.getElementById('navbarSearchClear');
                if (navClear) navClear.style.display = 'flex';
                const navInput = document.getElementById('navbarSearchInput');
                if (navInput) navInput.value = filterState.search;
            }
            if (filterState.brand) {
                const navInput = document.getElementById('navbarSearchInput');
                if (navInput && !filterState.search) navInput.value = filterState.brand;
                const navClear = document.getElementById('navbarSearchClear');
                if (navClear) navClear.style.display = 'flex';
            }

            // Load dynamic filter options from DB
            loadFilterOptions();
        })();

        function setActiveChip(containerId, value) {
            document.querySelectorAll(`#${containerId} .filter-chip`).forEach(chip => {
                chip.classList.toggle('active', chip.dataset.value === value);
            });
        }

        // ===== FILTER CHIP CLICK (for static filters: transmission, fuel) =====
        document.querySelectorAll('#transFilters .filter-chip, #fuelFilters .filter-chip').forEach(chip => {
            chip.addEventListener('click', function() {
                const container = this.closest('.filter-options');
                container.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
                this.classList.add('active');

                // Update filter state
                const containerId = container.id;
                const val = this.dataset.value;
                if (containerId === 'transFilters') filterState.transmission = val;
                else if (containerId === 'fuelFilters') filterState.fuel = val;
            });
        });

        // ===== PRICE RANGE =====
        const priceRange = document.getElementById('priceRange');
        priceRange.addEventListener('input', function() {
            filterState.max_price = parseInt(this.value);
            updatePriceDisplay(this.value);
        });

        function updatePriceDisplay(val) {
            const max = 500;
            const pct = (val / max) * 100;
            document.getElementById('rangeFill').style.width = pct + '%';
            document.getElementById('priceLabel').textContent = 
                val >= max ? '$0 – $500+' : '$0 – $' + val;
        }
        updatePriceDisplay(priceRange.value);

        // ===== LOAD DYNAMIC FILTER OPTIONS FROM DB =====
        async function loadFilterOptions() {
            try {
                const res = await fetch(VEHICLES_API + '?action=filter-options');
                const data = await res.json();

                if (data.success) {
                    // Render brand chips
                    const brandContainer = document.getElementById('brandFilters');
                    const brandLoading = document.getElementById('brandLoading');
                    if (brandLoading) brandLoading.remove();

                    if (data.brands && data.brands.length > 0) {
                        data.brands.forEach(brand => {
                            const chip = document.createElement('button');
                            chip.className = 'filter-chip';
                            chip.dataset.value = brand;
                            chip.textContent = brand;
                            brandContainer.appendChild(chip);
                        });
                    }
                    // Set active from URL
                    setActiveChip('brandFilters', filterState.brand);
                    bindChipClicks(brandContainer, 'brand');

                    // Render category chips
                    const catContainer = document.getElementById('categoryFilters');
                    const catLoading = document.getElementById('categoryLoading');
                    if (catLoading) catLoading.remove();

                    if (data.categories && data.categories.length > 0) {
                        data.categories.forEach(cat => {
                            const chip = document.createElement('button');
                            chip.className = 'filter-chip';
                            chip.dataset.value = cat;
                            chip.textContent = cat.charAt(0).toUpperCase() + cat.slice(1);
                            catContainer.appendChild(chip);
                        });
                    }
                    // Set active from URL
                    setActiveChip('categoryFilters', filterState.category);
                    bindChipClicks(catContainer, 'category');
                }
            } catch (err) {
                // Remove loading text on error
                const bl = document.getElementById('brandLoading');
                const cl = document.getElementById('categoryLoading');
                if (bl) bl.textContent = 'Failed to load';
                if (cl) cl.textContent = 'Failed to load';
            }
        }

        // Bind click events for dynamically created chips
        function bindChipClicks(container, filterKey) {
            container.querySelectorAll('.filter-chip').forEach(chip => {
                chip.addEventListener('click', function() {
                    container.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                    filterState[filterKey] = this.dataset.value;
                });
            });
        }

        // ===== APPLY / RESET FILTERS =====
        function applyAllFilters() {
            const params = new URLSearchParams();
            if (filterState.search) params.set('search', filterState.search);
            if (filterState.brand) params.set('brand', filterState.brand);
            if (filterState.transmission) params.set('transmission', filterState.transmission);
            if (filterState.fuel) params.set('fuel', filterState.fuel);
            if (filterState.max_price < 500) params.set('max_price', filterState.max_price);
            if (filterState.category) params.set('category', filterState.category);
            window.location.href = 'cars.php?' + params.toString();
        }

        function resetFilters() {
            window.location.href = 'cars.php';
        }

        // ===== SEARCH WITH SUGGESTIONS (uses navbar search bar) =====
        const searchInput = document.getElementById('navbarSearchInput');
        const suggestionsBox = document.getElementById('navbarSuggestions');
        const clearBtn = document.getElementById('navbarSearchClear');
        let debounceTimer = null;

        if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.trim();
            if (clearBtn) clearBtn.style.display = q ? 'flex' : 'none';

            clearTimeout(debounceTimer);
            if (q.length < 1) {
                suggestionsBox.classList.remove('open');
                return;
            }

            debounceTimer = setTimeout(() => fetchSuggestions(q), 250);
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                suggestionsBox.classList.remove('open');
                executeSearch();
            }
        });
        }

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.navbar-search-wrapper')) {
                if (suggestionsBox) suggestionsBox.classList.remove('open');
            }
        });

        async function fetchSuggestions(query) {
            try {
                const res = await fetch(VEHICLES_API + '?action=search-suggestions&q=' + encodeURIComponent(query));
                const data = await res.json();
                if (data.success && data.suggestions.length > 0) {
                    renderSuggestions(data.suggestions, query);
                } else {
                    suggestionsBox.innerHTML = '<div class="suggestion-hint">No results found for "' + query + '"</div>';
                    suggestionsBox.classList.add('open');
                }
            } catch (err) {
                suggestionsBox.classList.remove('open');
            }
        }

        function renderSuggestions(suggestions, query) {
            const html = suggestions.map(s => {
                const icon = s.type === 'brand' 
                    ? '<div class="suggestion-icon brand-icon">🏷️</div>'
                    : '<div class="suggestion-icon vehicle-icon">🚗</div>';

                const highlighted = highlightMatch(s.label, query);
                const sub = s.sub ? '<div class="suggestion-sub">' + s.sub + '</div>' : '';

                return '<div class="suggestion-item" data-type="' + s.type + '" data-text="' + s.text + '" data-id="' + (s.id || '') + '">' +
                    icon +
                    '<div class="suggestion-text">' +
                        '<div class="suggestion-label">' + highlighted + '</div>' +
                        sub +
                    '</div>' +
                '</div>';
            }).join('');

            suggestionsBox.innerHTML = html;
            suggestionsBox.classList.add('open');

            // Bind click
            suggestionsBox.querySelectorAll('.suggestion-item').forEach(item => {
                item.addEventListener('click', function() {
                    const type = this.dataset.type;
                    const text = this.dataset.text;

                    if (searchInput) searchInput.value = text;
                    suggestionsBox.classList.remove('open');
                    if (clearBtn) clearBtn.style.display = 'flex';

                    if (type === 'brand') {
                        filterState.search = '';
                        filterState.brand = text;
                        applyAllFilters();
                    } else {
                        filterState.search = text;
                        applyAllFilters();
                    }
                });
            });
        }

        function highlightMatch(text, query) {
            const regex = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            return text.replace(regex, '<strong style="color:var(--primary);">$1</strong>');
        }

        function executeSearch() {
            filterState.search = searchInput ? searchInput.value.trim() : '';
            if (suggestionsBox) suggestionsBox.classList.remove('open');
            if (filterState.search) {
                applyAllFilters();
            }
        }

        function clearSearch() {
            if (searchInput) searchInput.value = '';
            if (clearBtn) clearBtn.style.display = 'none';
            filterState.search = '';
            if (suggestionsBox) suggestionsBox.classList.remove('open');
            if (searchInput) searchInput.focus();
        }

        // ===== LOAD CARS FROM API =====
        document.addEventListener('DOMContentLoaded', loadCars);

        async function loadCars() {
            try {
                const params = new URLSearchParams(window.location.search);
                const payload = {
                    action: 'list',
                    category: params.get('category') || '',
                    brand: params.get('brand') || '',
                    fuel: params.get('fuel') || '',
                    transmission: params.get('transmission') || '',
                    max_price: params.get('max_price') || 500,
                    location: params.get('location') || '',
                    search: params.get('search') || '',
                    limit: 50
                };

                const res = await fetch(VEHICLES_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    renderCarGrid(data.vehicles || []);
                } else {
                    renderCarGrid([]);
                }
            } catch (err) {
                document.getElementById('carGrid').innerHTML =
                    '<div style="grid-column:1/-1;text-align:center;padding:60px 20px;">' +
                        '<div style="font-size:3rem;margin-bottom:12px;">⚠️</div>' +
                        '<h3 style="color:var(--gray-700);margin-bottom:8px;">Connection Error</h3>' +
                        '<p style="color:var(--gray-500);">Failed to load cars. Please try again later.</p>' +
                    '</div>';
            }
        }

        function renderCarGrid(cars) {
            allLoadedCars = cars;
            const grid = document.getElementById('carGrid');
            const countText = document.getElementById('carCountText');

            if (cars.length === 0) {
                countText.textContent = 'No cars available yet';
                grid.innerHTML =
                    '<div style="grid-column:1/-1;text-align:center;padding:60px 20px;">' +
                        '<div style="font-size:4rem;margin-bottom:16px;">🔍</div>' +
                        '<h3 style="color:var(--gray-700);margin-bottom:8px;">No cars found</h3>' +
                        '<p style="color:var(--gray-500);margin-bottom:16px;">Try adjusting your filters or search terms.</p>' +
                        '<button class="btn btn-primary" onclick="resetFilters()">Clear All Filters</button>' +
                    '</div>';
                return;
            }

            // Build active filter summary
            const activeFilters = [];
            if (filterState.search) activeFilters.push('"' + filterState.search + '"');
            if (filterState.brand) activeFilters.push(filterState.brand);
            if (filterState.category) activeFilters.push(filterState.category);
            const filterSuffix = activeFilters.length > 0 ? ' for ' + activeFilters.join(', ') : '';

            countText.textContent = cars.length + ' car' + (cars.length !== 1 ? 's' : '') + ' available' + filterSuffix;

            grid.innerHTML = cars.map(car => {
                const images = car.images || [];
                const imageHTML = images.length > 0
                    ? '<img src="' + images[0] + '" alt="' + car.brand + ' ' + car.model + '" style="width:100%;height:100%;object-fit:cover;">'
                    : '<div class="no-image-placeholder">No Photo</div>';

                const fuelIcon = car.fuel_type === 'electric' ? '⚡' : '⛽';
                const features = (car.features || []).slice(0, 3).map(f => '<span class="car-feature">✓ ' + f + '</span>').join('');
                const rating = parseFloat(car.avg_rating) || 0;
                const stars = '★'.repeat(Math.round(rating)) + '☆'.repeat(5 - Math.round(rating));

                return '<div class="car-card" onclick="handleCarClick(\'' + car.id + '\')">' +
                    '<div class="car-card-image">' +
                        imageHTML +
                        '<button class="car-card-favorite" onclick="event.stopPropagation();toggleFavorite(this)">🤍</button>' +
                    '</div>' +
                    '<div class="car-card-body">' +
                        '<h3 class="car-card-title">' + car.brand + ' ' + car.model + ' ' + car.year + '</h3>' +
                        '<p class="car-card-subtitle">' + car.category + ' • ' + car.transmission + ' • ' + car.fuel_type + '</p>' +
                        '<div class="car-card-features">' +
                            '<span class="car-feature">👤 ' + car.seats + ' seats</span>' +
                            '<span class="car-feature">' + fuelIcon + ' ' + (car.consumption || 'N/A') + '</span>' +
                            features +
                        '</div>' +
                        '<div class="car-card-footer">' +
                            '<div class="car-card-price">' +
                                '<span class="car-price-amount">$' + car.price_per_day + '</span>' +
                                '<span class="car-price-unit">per day</span>' +
                            '</div>' +
                            '<div class="car-card-rating">' +
                                '<span class="stars">' + stars + '</span>' +
                                '<span class="count">(' + (car.total_reviews || 0) + ')</span>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            }).join('');
        }

        function handleCarClick(carId) {
            openCarDetail(carId);
        }

        // ===== CAR DETAIL MODAL =====
        async function openCarDetail(carId) {
            // Try from loaded data first
            let car = allLoadedCars.find(c => c.id === carId);

            if (!car) {
                // Fetch from API
                try {
                    const res = await fetch(VEHICLES_API, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'get', vehicle_id: carId })
                    });
                    const data = await res.json();
                    if (data.success && data.vehicle) {
                        car = data.vehicle;
                    } else {
                        showToast('Car not found.', 'error');
                        return;
                    }
                } catch (err) {
                    showToast('Failed to load car details.', 'error');
                    return;
                }
            }

            // Title & subtitle
            document.getElementById('detailTitle').textContent = car.brand + ' ' + car.model + ' ' + car.year;
            document.getElementById('detailSub').textContent = ucfirst(car.category) + ' • ' + ucfirst(car.transmission) + ' • ' + ucfirst(car.fuel_type) + (car.license_plate ? ' • ' + car.license_plate : '');

            // Price
            document.getElementById('detailPrice').textContent = '$' + Number(car.price_per_day).toLocaleString();

            // Rating
            const rating = parseFloat(car.avg_rating) || 0;
            const reviews = car.total_reviews || 0;
            const stars = '★'.repeat(Math.round(rating)) + '☆'.repeat(5 - Math.round(rating));
            document.getElementById('detailRating').innerHTML = 
                '<span style="color:#f59e0b;font-size:1.1rem;">' + stars + '</span>' +
                '<span style="font-weight:700;color:var(--gray-800);">' + rating.toFixed(1) + '</span>' +
                '<span style="color:var(--gray-500);font-size:0.85rem;">(' + reviews + ' review' + (reviews !== 1 ? 's' : '') + ')</span>';

            // Image gallery
            const images = car.images || [];
            const mainImg = document.getElementById('detailMainImage');
            const thumbsEl = document.getElementById('detailThumbs');

            if (images.length > 0) {
                mainImg.innerHTML = '<img src="' + images[0] + '" alt="' + car.brand + ' ' + car.model + '" style="width:100%;height:100%;object-fit:cover;">';
                if (images.length > 1) {
                    thumbsEl.innerHTML = images.map((img, i) => 
                        '<div class="detail-thumb ' + (i === 0 ? 'active' : '') + '" onclick="switchDetailImage(\'' + img + '\', this)">' +
                        '<img src="' + img + '" alt="Photo ' + (i+1) + '"></div>'
                    ).join('');
                } else {
                    thumbsEl.innerHTML = '';
                }
            } else {
                mainImg.innerHTML = '<span style="color:var(--gray-400);font-size:0.875rem;">No Photo Available</span>';
                thumbsEl.innerHTML = '';
            }

            // Specs grid
            const fuelIcon = car.fuel_type === 'electric' ? '🔋' : '⛽';
            document.getElementById('detailSpecs').innerHTML = 
                specItem('👤', 'Seats', car.seats) +
                specItem('⚙️', 'Transmission', ucfirst(car.transmission)) +
                specItem(fuelIcon, 'Fuel Type', ucfirst(car.fuel_type)) +
                specItem('📏', 'Engine', car.engine_size || 'N/A') +
                specItem('📊', 'Consumption', car.consumption || 'N/A') +
                specItem('🎨', 'Color', ucfirst(car.color || 'N/A')) +
                specItem('📅', 'Year', car.year) +
                specItem('📋', 'Bookings', car.total_bookings || 0);

            // Features
            const features = car.features || [];
            const featSection = document.getElementById('detailFeaturesSection');
            if (features.length > 0) {
                featSection.style.display = 'block';
                document.getElementById('detailFeatures').innerHTML = features.map(f => 
                    '<span class="detail-feature-tag">✓ ' + f.trim() + '</span>'
                ).join('');
            } else {
                featSection.style.display = 'none';
            }

            // Location
            const locSection = document.getElementById('detailLocationSection');
            const locText = [car.location_city, car.location_address].filter(Boolean).join(' — ');
            if (locText) {
                locSection.style.display = 'block';
                document.getElementById('detailLocation').innerHTML = '📍 ' + locText;
            } else {
                locSection.style.display = 'none';
            }

            // Owner
            const ownerName = car.owner_name || 'Unknown Owner';
            const initials = ownerName.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
            document.getElementById('detailOwnerAvatar').textContent = initials;
            document.getElementById('detailOwnerName').textContent = ownerName;

            // Price breakdown
            document.getElementById('detailDailyRate').textContent = '$' + Number(car.price_per_day).toLocaleString() + '/day';
            const weeklyRow = document.getElementById('detailWeeklyRow');
            const monthlyRow = document.getElementById('detailMonthlyRow');
            if (car.price_per_week && parseFloat(car.price_per_week) > 0) {
                weeklyRow.style.display = 'flex';
                document.getElementById('detailWeeklyRate').textContent = '$' + Number(car.price_per_week).toLocaleString() + '/week';
            } else {
                weeklyRow.style.display = 'none';
            }
            if (car.price_per_month && parseFloat(car.price_per_month) > 0) {
                monthlyRow.style.display = 'flex';
                document.getElementById('detailMonthlyRate').textContent = '$' + Number(car.price_per_month).toLocaleString() + '/month';
            } else {
                monthlyRow.style.display = 'none';
            }

            // Book button
            document.getElementById('detailBookBtn').setAttribute('onclick', "bookCar('" + car.id + "')");

            // Open modal
            document.getElementById('carDetailModal').classList.add('open');
        }

        function switchDetailImage(src, thumbEl) {
            document.getElementById('detailMainImage').innerHTML = '<img src="' + src + '" style="width:100%;height:100%;object-fit:cover;">';
            document.querySelectorAll('.detail-thumb').forEach(t => t.classList.remove('active'));
            if (thumbEl) thumbEl.classList.add('active');
        }

        function specItem(icon, label, value) {
            return '<div class="detail-spec-item">' +
                '<div class="detail-spec-icon">' + icon + '</div>' +
                '<div class="detail-spec-label">' + label + '</div>' +
                '<div class="detail-spec-value">' + value + '</div>' +
            '</div>';
        }

        function ucfirst(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        function bookCar(carId) {
            if (!isLoggedIn) {
                showToast('Please sign in to book a car.', 'warning');
                setTimeout(() => {
                    window.location.href = 'login.php?redirect=booking.php&car_id=' + encodeURIComponent(carId);
                }, 1000);
                return;
            }
            window.location.href = 'booking.php?car_id=' + encodeURIComponent(carId);
        }

        function toggleFavorite(btn) {
            btn.classList.toggle('active');
            btn.textContent = btn.classList.contains('active') ? '❤️' : '🤍';
            showToast(btn.classList.contains('active') ? 'Added to favorites!' : 'Removed from favorites.', 'success');
        }
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
