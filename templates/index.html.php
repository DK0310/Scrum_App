<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== HERO SECTION ===== -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">⚡ #1 Car Rental Platform Worldwide</div>
                <h1 class="hero-title">
                    Find Your Perfect <span>Drive</span> Today
                </h1>
                <p class="hero-description">
                    Book premium cars from trusted owners worldwide. Self-drive or with driver, online or by phone — your journey starts here.
                </p>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <a href="cars.php" class="btn btn-primary btn-lg">🔍 Browse Cars</a>
                    <a href="#how-it-works" class="btn btn-outline btn-lg" style="border-color:rgba(255,255,255,0.3);color:white;">Learn More →</a>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <div class="hero-stat-number">50K+</div>
                        <div class="hero-stat-label">Cars Available</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-number">120+</div>
                        <div class="hero-stat-label">Countries</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-number">2M+</div>
                        <div class="hero-stat-label">Happy Customers</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-number">4.9★</div>
                        <div class="hero-stat-label">Average Rating</div>
                    </div>
                </div>
            </div>
            <div class="hero-image">
                <!-- Slideshow container -->
                <div class="hero-slideshow" id="heroSlideshow">
                    <div class="hero-slides-wrapper" id="heroSlidesWrapper">
                        <!-- Fallback when no slides in DB -->
                        <div class="hero-slide active" id="heroFallbackSlide">
                            <div class="hero-car-visual" style="font-size:0;width:100%;height:100%;background:linear-gradient(135deg,var(--primary-50),var(--gray-100));border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;">
                                <span style="font-size:1rem;color:var(--gray-400);font-weight:600;">Your Dream Car Awaits</span>
                            </div>
                        </div>
                    </div>
                    <!-- Slide indicators -->
                    <div class="hero-slide-indicators" id="heroIndicators"></div>
                    <!-- Slide caption -->
                    <div class="hero-slide-caption" id="heroCaption" style="display:none;">
                        <div class="hero-slide-title" id="heroCaptionTitle"></div>
                        <div class="hero-slide-subtitle" id="heroCaptionSub"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== CAR CATEGORIES ===== -->
    <section class="section">
        <div class="section-container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Browse by Category</h2>
                    <p class="section-subtitle">Choose from a wide range of vehicle categories</p>
                </div>
                <a href="cars.php" class="section-link">All Categories →</a>
            </div>
            <div class="category-grid">
                <div class="category-card" onclick="filterByCategory('sedan')">
                    <div class="category-name">Sedan</div>
                </div>
                <div class="category-card" onclick="filterByCategory('suv')">
                    <div class="category-name">SUV</div>
                </div>
                <div class="category-card" onclick="filterByCategory('luxury')">
                    <div class="category-name">Luxury</div>
                </div>
                <div class="category-card" onclick="filterByCategory('electric')">
                    <div class="category-name">Electric</div>
                </div>
                <div class="category-card" onclick="filterByCategory('van')">
                    <div class="category-name">Van / Minibus</div>
                </div>
                <div class="category-card" onclick="filterByCategory('sports')">
                    <div class="category-name">Sports</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== FEATURED CARS (loaded from DB) ===== -->
    <section class="section featured-section" id="cars">
        <div class="section-container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">⭐ Featured Cars</h2>
                    <p class="section-subtitle">Premium vehicles from our trusted owners</p>
                </div>
                <a href="cars.php" class="section-link">View All Cars →</a>
            </div>

            <div class="car-grid" id="carGrid">
                <!-- Cars loaded dynamically from API -->
                <div style="grid-column:1/-1;text-align:center;padding:40px 20px;">
                    <div style="width:36px;height:36px;border:3px solid var(--gray-200);border-top-color:var(--primary);border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px;"></div>
                    <p style="color:var(--gray-500);font-size:0.875rem;">Loading featured cars...</p>
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
        /* Search bar styles */
        .car-search-wrapper { position: relative; }
        .car-search-bar {
            display: flex; align-items: center; gap: 12px;
            background: white; border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg); padding: 8px 8px 8px 20px;
            transition: var(--transition);
        }
        .car-search-bar:focus-within { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(37,99,235,0.12); }
        .car-search-icon { font-size: 1.25rem; flex-shrink: 0; }
        .car-search-input {
            flex: 1; border: none; outline: none; font-size: 1rem;
            font-family: var(--font); color: var(--gray-800); background: transparent; padding: 8px 0;
        }
        .car-search-input::placeholder { color: var(--gray-400); }
        .car-search-btn { border-radius: var(--radius) !important; padding: 10px 24px !important; flex-shrink: 0; }
        .car-suggestions {
            position: absolute; top: calc(100% + 6px); left: 0; right: 0;
            background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow-xl);
            border: 1px solid var(--gray-100); z-index: 100;
            display: none; max-height: 360px; overflow-y: auto;
        }
        .car-suggestions.open { display: block; }
        .suggestion-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 20px; cursor: pointer; transition: var(--transition);
            border-bottom: 1px solid var(--gray-50);
        }
        .suggestion-item:last-child { border-bottom: none; }
        .suggestion-item:hover { background: var(--primary-50); }
        .suggestion-icon {
            width: 36px; height: 36px; border-radius: var(--radius);
            display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;
        }
        .suggestion-icon.brand-icon { background: var(--primary-50); color: var(--primary); }
        .suggestion-icon.vehicle-icon { background: var(--gray-100); color: var(--gray-600); }
        .suggestion-text { flex: 1; }
        .suggestion-label { font-size: 0.938rem; font-weight: 600; color: var(--gray-800); }
        .suggestion-sub { font-size: 0.75rem; color: var(--gray-500); margin-top: 2px; }
    </style>

    <!-- ===== HOW IT WORKS ===== -->
    <section class="section" id="how-it-works">
        <div class="section-container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">How It Works</h2>
                    <p class="section-subtitle">Rent a car in 4 simple steps — online or by phone</p>
                </div>
            </div>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <div class="step-icon">🔍</div>
                    <h3 class="step-title">Search & Filter</h3>
                    <p class="step-description">Browse thousands of cars. Filter by type, price, location, brand, and more.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">2</div>
                    <div class="step-icon">📋</div>
                    <h3 class="step-title">Book Online or Call</h3>
                    <p class="step-description">Book instantly online, via phone, or enquire with our customer service team.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">3</div>
                    <div class="step-icon">💳</div>
                    <h3 class="step-title">Pay Securely</h3>
                    <p class="step-description">Pay with cash, bank transfer, credit card, or digital wallet. All payments secured.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">4</div>
                    <div class="step-icon">🚗</div>
                    <h3 class="step-title">Pick Up & Drive</h3>
                    <p class="step-description">Pick up your car and enjoy your ride. GPS tracking available for car owners.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== PROMOTIONS PREVIEW ===== -->
    <section class="section" style="background:var(--gray-100);">
        <div class="section-container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">🎉 Promotions & Deals</h2>
                    <p class="section-subtitle">Save big with our exclusive offers and promo codes</p>
                </div>
                <a href="promotions.php" class="section-link">All Promotions →</a>
            </div>
            <div class="promo-grid">
                <div class="promo-card" onclick="applyPromo('WEEKEND20')">
                    <div class="promo-discount">20% OFF</div>
                    <div class="promo-title">Weekend Special</div>
                    <div class="promo-description">Book any car for the weekend and save 20%. Valid until March 31.</div>
                    <div class="promo-code">WEEKEND20</div>
                </div>
                <div class="promo-card accent" onclick="applyPromo('FIRST50')">
                    <div class="promo-discount">$50 OFF</div>
                    <div class="promo-title">First Ride Bonus</div>
                    <div class="promo-description">New users get $50 off their first booking. Sign up today!</div>
                    <div class="promo-code">FIRST50</div>
                </div>
                <div class="promo-card dark" onclick="applyPromo('LONGTERM30')">
                    <div class="promo-discount">30% OFF</div>
                    <div class="promo-title">Long-term Rental</div>
                    <div class="promo-description">Book for 30+ days and get 30% discount. Perfect for corporate use.</div>
                    <div class="promo-code">LONGTERM30</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== REVIEWS PREVIEW ===== -->
    <section class="section">
        <div class="section-container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">⭐ Customer Reviews</h2>
                    <p class="section-subtitle">See what our customers are saying about their experience</p>
                </div>
                <a href="reviews.php" class="section-link">All Reviews →</a>
            </div>
            <div class="review-grid">
                <div class="review-card">
                    <div class="review-stars">★★★★★</div>
                    <p class="review-text">"Best car rental experience ever! The booking process was seamless, and the car was in perfect condition."</p>
                    <div class="review-author">
                        <div class="review-avatar">JD</div>
                        <div class="review-author-info">
                            <div class="review-author-name">James Davis</div>
                            <div class="review-author-trip">BMW 5 Series • New York → Boston</div>
                        </div>
                    </div>
                </div>
                <div class="review-card">
                    <div class="review-stars">★★★★★</div>
                    <p class="review-text">"The face recognition login is super cool! And the AI chatbot helped me find the perfect car for my family trip."</p>
                    <div class="review-author">
                        <div class="review-avatar">AL</div>
                        <div class="review-author-info">
                            <div class="review-author-name">Anna Lee</div>
                            <div class="review-author-trip">Honda CR-V • Tokyo → Osaka</div>
                        </div>
                    </div>
                </div>
                <div class="review-card">
                    <div class="review-stars">★★★★☆</div>
                    <p class="review-text">"Great selection of electric vehicles. The GPS tracking feature gave me peace of mind when renting out my car."</p>
                    <div class="review-author">
                        <div class="review-avatar">MP</div>
                        <div class="review-author-info">
                            <div class="review-author-name">Marco Polo</div>
                            <div class="review-author-trip">Tesla Model Y • Milan → Rome</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

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

        /* ===== HERO SLIDESHOW ===== */
        .hero-slideshow {
            width: 100%; max-width: 560px; aspect-ratio: 16/10;
            border-radius: var(--radius-xl); overflow: hidden;
            position: relative; border: 1px solid rgba(255,255,255,0.15);
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
        }
        .hero-slides-wrapper { position: relative; width: 100%; height: 100%; }
        .hero-slide {
            position: absolute; inset: 0;
            opacity: 0; transition: opacity 0.8s ease-in-out;
        }
        .hero-slide.active { opacity: 1; z-index: 1; }
        .hero-slide img { width: 100%; height: 100%; object-fit: cover; }
        .hero-slide-indicators {
            position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%);
            display: flex; gap: 8px; z-index: 5;
        }
        .hero-indicator {
            width: 10px; height: 10px; border-radius: 50%;
            background: rgba(255,255,255,0.4); border: none; cursor: pointer;
            transition: all 0.3s; padding: 0;
        }
        .hero-indicator.active {
            background: white; width: 28px; border-radius: 5px;
        }
        .hero-slide-caption {
            position: absolute; bottom: 40px; left: 16px; right: 16px;
            z-index: 5; color: white; text-shadow: 0 2px 8px rgba(0,0,0,0.6);
        }
        .hero-slide-title { font-size: 1.1rem; font-weight: 700; }
        .hero-slide-subtitle { font-size: 0.8rem; opacity: 0.85; margin-top: 2px; }
        .hero-slideshow::after {
            content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 50%;
            background: linear-gradient(transparent, rgba(0,0,0,0.4));
            border-radius: 0 0 var(--radius-xl) var(--radius-xl);
            pointer-events: none; z-index: 2;
        }
    </style>

    <!-- ===== HOMEPAGE JAVASCRIPT ===== -->
    <script>
        const isLoggedIn = <?= json_encode($isLoggedIn ?? false) ?>;
        const VEHICLES_API = '/api/vehicles.php';
        const ADMIN_API = '/api/admin.php';
        let featuredCars = [];
        let heroSlides = [];
        let heroCurrentIndex = 0;
        let heroTimer = null;

        // Load featured cars AND hero slides on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadFeaturedCars();
            loadHeroSlides();
        });

        // ===== HERO SLIDESHOW =====
        async function loadHeroSlides() {
            try {
                const res = await fetch(ADMIN_API + '?action=hero-slides-public');
                const data = await res.json();
                if (data.success && data.slides && data.slides.length > 0) {
                    heroSlides = data.slides;
                    renderHeroSlideshow();
                    startHeroTimer();
                }
                // If no slides, fallback placeholder remains visible
            } catch (e) {
                // Fallback stays visible
            }
        }

        function renderHeroSlideshow() {
            const wrapper = document.getElementById('heroSlidesWrapper');
            const indicators = document.getElementById('heroIndicators');
            const caption = document.getElementById('heroCaption');

            // Remove fallback
            const fallback = document.getElementById('heroFallbackSlide');
            if (fallback) fallback.remove();

            // Create slide elements
            wrapper.innerHTML = heroSlides.map((s, i) => 
                '<div class="hero-slide ' + (i === 0 ? 'active' : '') + '" data-index="' + i + '">' +
                '<img src="' + s.image_url + '" alt="' + (s.title || 'Hero Slide') + '">' +
                '</div>'
            ).join('');

            // Create indicators
            if (heroSlides.length > 1) {
                indicators.innerHTML = heroSlides.map((_, i) => 
                    '<button class="hero-indicator ' + (i === 0 ? 'active' : '') + '" onclick="goToHeroSlide(' + i + ')"></button>'
                ).join('');
            }

            // Show caption for first slide
            updateHeroCaption(0);
        }

        function updateHeroCaption(index) {
            const caption = document.getElementById('heroCaption');
            const slide = heroSlides[index];
            if (slide && (slide.title || slide.subtitle)) {
                caption.style.display = 'block';
                document.getElementById('heroCaptionTitle').textContent = slide.title || '';
                document.getElementById('heroCaptionSub').textContent = slide.subtitle || '';
            } else {
                caption.style.display = 'none';
            }
        }

        function goToHeroSlide(index) {
            if (index === heroCurrentIndex) return;
            const slides = document.querySelectorAll('.hero-slide');
            const indicators = document.querySelectorAll('.hero-indicator');

            slides.forEach(s => s.classList.remove('active'));
            indicators.forEach(i => i.classList.remove('active'));

            slides[index]?.classList.add('active');
            indicators[index]?.classList.add('active');

            heroCurrentIndex = index;
            updateHeroCaption(index);

            // Reset timer
            resetHeroTimer();
        }

        function nextHeroSlide() {
            const next = (heroCurrentIndex + 1) % heroSlides.length;
            goToHeroSlide(next);
        }

        function startHeroTimer() {
            if (heroSlides.length <= 1) return;
            heroTimer = setInterval(nextHeroSlide, 5000);
        }

        function resetHeroTimer() {
            clearInterval(heroTimer);
            startHeroTimer();
        }

        async function loadFeaturedCars() {
            try {
                const res = await fetch(VEHICLES_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'list', limit: 4 })
                });
                const data = await res.json();

                if (data.success && data.vehicles && data.vehicles.length > 0) {
                    featuredCars = data.vehicles;
                    renderFeaturedCars(data.vehicles);
                } else {
                    renderEmptyFeatured();
                }
            } catch (err) {
                renderEmptyFeatured();
            }
        }

        function renderFeaturedCars(cars) {
            const grid = document.getElementById('carGrid');
            grid.innerHTML = cars.map(car => {
                const images = car.images || [];
                const imageHTML = images.length > 0
                    ? `<img src="${images[0]}" alt="${car.brand} ${car.model}" style="width:100%;height:100%;object-fit:cover;">`
                    : `<div class="no-image-placeholder">No Photo</div>`;
                const fuelIcon = car.fuel_type === 'electric' ? '🔋' : '⛽';
                const features = (car.features || []).slice(0, 3).map(f => `<span class="car-feature">✓ ${f}</span>`).join('');
                const rating = parseFloat(car.avg_rating) || 0;
                const stars = '★'.repeat(Math.round(rating)) + '☆'.repeat(5 - Math.round(rating));

                return `
                <div class="car-card" onclick="openCarDetail('${car.id}')">
                    <div class="car-card-image">
                        ${imageHTML}
                        <button class="car-card-favorite" onclick="event.stopPropagation();toggleFavorite(this)">🤍</button>
                    </div>
                    <div class="car-card-body">
                        <h3 class="car-card-title">${car.brand} ${car.model} ${car.year}</h3>
                        <p class="car-card-subtitle">${car.category} • ${car.transmission} • ${car.fuel_type}</p>
                        <div class="car-card-features">
                            <span class="car-feature">👤 ${car.seats} seats</span>
                            <span class="car-feature">${fuelIcon} ${car.consumption || 'N/A'}</span>
                            ${features}
                        </div>
                        <div class="car-card-footer">
                            <div class="car-card-price">
                                <span class="car-price-amount">$${car.price_per_day}</span>
                                <span class="car-price-unit">per day</span>
                            </div>
                            <div class="car-card-rating">
                                <span class="stars">${stars}</span>
                                <span class="count">(${car.total_reviews || 0})</span>
                            </div>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        function renderEmptyFeatured() {
            const grid = document.getElementById('carGrid');
            grid.innerHTML = `
                <div style="grid-column:1/-1;text-align:center;padding:60px 20px;">
                    <div style="font-size:3rem;margin-bottom:12px;">🚗</div>
                    <h3 style="color:var(--gray-700);margin-bottom:8px;">No cars listed yet</h3>
                    <p style="color:var(--gray-500);">Be the first car owner to list your vehicle on DriveNow!</p>
                </div>`;
        }

        // ===== SEARCH =====
        function filterByCategory(category) {
            window.location.href = 'cars.php?category=' + encodeURIComponent(category);
        }

        // ===== FAVORITES =====
        function toggleFavorite(btn) {
            btn.classList.toggle('active');
            btn.textContent = btn.classList.contains('active') ? '❤️' : '🤍';
            showToast(btn.classList.contains('active') ? 'Added to favorites!' : 'Removed from favorites.', 'success');
        }

        // ===== CAR DETAILS =====
        async function openCarDetail(id) {
            // Try from loaded featured cars first
            let car = featuredCars.find(c => c.id === id);

            if (!car) {
                // Fetch from API
                try {
                    const res = await fetch(VEHICLES_API, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'get', vehicle_id: id })
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

        // ===== PROMOTIONS =====
        function applyPromo(code) {
            showToast('Promo code "' + code + '" copied! Apply it during booking.', 'success');
            window.location.href = 'booking.php?promo=' + encodeURIComponent(code);
        }
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
