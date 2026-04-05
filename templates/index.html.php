<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== HERO SECTION ===== -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">#1 Car Rental Platform Worldwide</div>
                <h1 class="hero-title">
                    Find Your Perfect <span>Drive</span> Today
                </h1>
                <p class="hero-description">
                    Book minicabs or hire cars with professional drivers. Local journeys, airport transfers, hotel pickups — your ride starts here.
                </p>
                <div class="hero-actions">
                    <a href="/booking.php?mode=minicab" class="btn btn-lg" style="background:var(--primary);color:white;border:1px solid var(--primary-100);box-shadow:0 10px 30px rgba(15,118,110,0.35);">🚕 Book a Minicab</a>
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
                    <!-- Navigation buttons -->
                    <button class="hero-nav-btn hero-nav-prev" id="heroPrevBtn" onclick="heroSlideNav(-1)" aria-label="Previous slide">
                        <span>❮</span>
                    </button>
                    <button class="hero-nav-btn hero-nav-next" id="heroNextBtn" onclick="heroSlideNav(1)" aria-label="Next slide">
                        <span>❯</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== MINICAB SPOTLIGHT ===== -->
    <section class="section spotlight-section">
        <div class="section-container">
            <div class="spotlight-grid">
                <div class="spotlight-content">
                    <div class="spotlight-label">
                        <span class="spotlight-pill">Spotlight</span>
                        <span class="spotlight-rule"></span>
                    </div>
                    <h2 class="spotlight-title">Need A Ride <span>In Minutes?</span></h2>
                    <p class="spotlight-subtitle">Choose pickup point, destination, and ride tier. Our system auto-matches an available driver and confirms instantly.</p>
                    <div class="spotlight-actions">
                        <a href="/booking.php?mode=minicab" class="btn btn-lg spotlight-primary">Start Booking Now</a>
                        <a href="/customer-enquiry.php" class="btn btn-lg spotlight-secondary">Ask For Assistance</a>
                    </div>
                </div>
                <div class="spotlight-cards">
                    <div class="spotlight-card">
                        <div>
                            <div class="spotlight-metric">~3 min</div>
                            <div class="spotlight-label-text">Average Dispatch Time</div>
                        </div>
                        <div class="spotlight-icon">⏱</div>
                    </div>
                    <div class="spotlight-card">
                        <div>
                            <div class="spotlight-metric">24/7</div>
                            <div class="spotlight-label-text">Service Availability</div>
                        </div>
                        <div class="spotlight-icon">📅</div>
                    </div>
                    <div class="spotlight-card spotlight-card-dark">
                        <div class="spotlight-card-head">
                            <span class="spotlight-card-title">Ride Tiers</span>
                            <span class="spotlight-card-icon">🚗</span>
                        </div>
                        <div class="spotlight-tier-row">
                            <span class="spotlight-tier">Eco</span>
                            <span class="spotlight-tier">Standard</span>
                            <span class="spotlight-tier spotlight-tier-strong">Luxury</span>
                        </div>
                        <p class="spotlight-card-note">Precision matched vehicles for every journey profile.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== CAR CATEGORIES ===== -->
    <section class="section">
        <div class="section-container">
            <div class="section-header section-header-centered">
                <div>
                    <span class="section-eyebrow">Exclusive Access</span>
                    <h2 class="section-title">Browse by Category</h2>
                    <p class="section-subtitle">Select your experience</p>
                </div>
            </div>
            <div class="category-grid category-grid-redesign">
                <div class="category-card category-card-redesign" onclick="filterByCategory('eco')">
                    <div class="category-icon"><img src="/resources/images/logo/piggy-bank.png" alt="Eco"></div>
                    <div class="category-name">Eco</div>
                    <p class="category-desc">Sustainable luxury for the modern commuter.</p>
                    <span class="category-link">Explore Collection →</span>
                </div>
                <div class="category-card category-card-redesign" onclick="filterByCategory('standard')">
                    <div class="category-icon"><img src="/resources/images/logo/car.png" alt="Standard"></div>
                    <div class="category-name">Standard</div>
                    <p class="category-desc">Versatile reliability without compromise.</p>
                    <span class="category-link">Explore Collection →</span>
                </div>
                <div class="category-card category-card-redesign" onclick="filterByCategory('luxury')">
                    <div class="category-icon"><img src="/resources/images/logo/crown.png" alt="Luxury"></div>
                    <div class="category-name">Luxury</div>
                    <p class="category-desc">Unrivaled prestige for special journeys.</p>
                    <span class="category-link">Explore Collection →</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== FEATURED CARS (loaded from DB) ===== -->
    <section class="section featured-section" id="cars">
        <div class="section-container">
            <div class="section-header section-header-vehicles section-header-space">
                <div>
                    <span class="section-eyebrow">Premium Fleet</span>
                    <h2 class="section-title">Available Vehicles</h2>
                    <p class="section-subtitle">Instant booking available for our premium fleet across your city.</p>
                </div>
                <a href="/cars.php" class="section-link">All Cars →</a>
            </div>
            

            <div class="car-grid car-grid-redesign" id="availableVehiclesGrid">
                <div class="vehicles-loading">
                    <div class="vehicles-spinner"></div>
                    <p>Loading available vehicles...</p>
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

        .spotlight-section { padding-top: 30px; padding-bottom: 30px; }
        .spotlight-grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
            gap: 28px;
            align-items: center;
        }
        .spotlight-label { display: flex; align-items: center; gap: 12px; }
        .spotlight-pill {
            background: var(--primary-50);
            color: var(--primary-dark);
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            padding: 6px 12px;
            border-radius: 999px;
        }
        .spotlight-rule { flex: 1; height: 1px; background: var(--gray-200); }
        .spotlight-title {
            font-size: clamp(2.2rem, 4vw, 3.4rem);
            font-weight: 900;
            line-height: 1.1;
            margin: 14px 0 10px;
        }
        .spotlight-title span { color: var(--primary); font-style: italic; }
        .spotlight-subtitle { font-size: 1rem; color: var(--gray-600); max-width: 520px; }
        .spotlight-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 18px; }
        .spotlight-primary {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: white;
            border: none;
            font-weight: 800;
            box-shadow: 0 12px 30px rgba(15, 118, 110, 0.25);
        }
        .spotlight-secondary { background: white; color: var(--primary-dark); border: 1px solid var(--gray-200); }
        .spotlight-cards { display: grid; gap: 16px; }
        .spotlight-card {
            background: white;
            border: 1px solid var(--gray-100);
            border-radius: 18px;
            padding: 20px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            box-shadow: var(--shadow-md);
        }
        .spotlight-card-dark {
            background: var(--primary);
            color: white;
            border-color: transparent;
            display: block;
        }
        .spotlight-metric { font-size: 2rem; font-weight: 900; color: var(--primary-dark); }
        .spotlight-label-text { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.14em; color: var(--gray-500); font-weight: 700; }
        .spotlight-icon {
            width: 44px; height: 44px; border-radius: 12px;
            background: var(--primary-50); display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }
        .spotlight-card-head { display: flex; justify-content: space-between; align-items: center; }
        .spotlight-card-title { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.18em; opacity: 0.8; font-weight: 700; }
        .spotlight-card-icon { font-size: 1.3rem; }
        .spotlight-tier-row { display: flex; flex-wrap: wrap; gap: 10px; margin: 16px 0 8px; }
        .spotlight-tier {
            padding: 6px 14px; border-radius: 10px; background: rgba(255,255,255,0.15);
            font-size: 0.8rem; font-weight: 700; color: white;
        }
        .spotlight-tier-strong { background: white; color: var(--primary-dark); }
        .spotlight-card-note { font-size: 0.75rem; opacity: 0.8; }

        .category-grid-redesign {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }
        .category-card-redesign {
            background: var(--gray-50);
            border: 1px solid var(--gray-100);
            border-radius: 28px;
            padding: 28px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 10px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .category-card-redesign:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
        .category-icon {
            width: 56px; height: 56px; border-radius: 16px; background: white;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
            box-shadow: var(--shadow-sm);
        }
        .category-icon img {
            width: 30px;
            height: 30px;
            object-fit: contain;
            display: block;
        }
        .category-desc { color: var(--gray-500); font-size: 0.9rem; }
        .category-link { color: var(--primary); font-weight: 700; font-size: 0.85rem; }

        .car-grid-redesign {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 22px;
        }
        .vehicles-loading {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px 20px;
            color: var(--gray-500);
        }
        .vehicles-spinner {
            width: 36px; height: 36px; border: 3px solid var(--gray-200);
            border-top-color: var(--primary); border-radius: 50%;
            animation: spin 0.8s linear infinite; margin: 0 auto 12px;
        }

        .section-header-centered { text-align: center; justify-content: center; }
        .section-header-centered .section-subtitle { margin: 0 auto; max-width: 620px; }
        .section-header-space {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 16px;
        }
        .section-header-space > div {
            grid-column: 2;
            text-align: center;
            margin: 0;
        }
        .section-header-space .section-link {
            grid-column: 3;
            justify-self: end;
        }
        .section-eyebrow { display: block; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.2em; text-transform: uppercase; color: var(--primary); margin-bottom: 8px; }

        .steps-grid-redesign {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }
        .step-card-redesign {
            position: relative;
            padding: 26px;
            border-radius: 18px;
            background: var(--gray-50);
            border: 1px solid var(--gray-100);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .step-card-redesign:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
        .step-card-redesign .step-icon {
            width: 56px; height: 56px; border-radius: 16px;
            background: var(--primary-50); color: var(--primary-dark);
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
            margin-bottom: 18px;
        }
        .step-card-redesign .step-index {
            position: absolute; top: 18px; right: 18px;
            font-size: 2.5rem; font-weight: 900; color: rgba(15, 118, 110, 0.08);
        }

        .section-promotions { background: var(--gray-100); }
        .promo-grid-redesign {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }
        .promo-card-redesign {
            position: relative;
            border-radius: 28px;
            overflow: hidden;
            min-height: 320px;
            display: flex;
            align-items: flex-end;
            background: var(--primary);
            color: white;
        }
        .promo-weekend { background: linear-gradient(140deg, #004f45, #0f766e); }
        .promo-welcome { background: linear-gradient(140deg, #3b1d6a, #7b2cbf); }
        .promo-longterm { background: linear-gradient(140deg, #0f172a, #1e293b); }
        .promo-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0) 40%, rgba(0,0,0,0.65) 100%);
        }
        .promo-content {
            position: relative;
            padding: 26px;
            background: rgba(255,255,255,0.1);
            margin: 16px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.2);
            width: calc(100% - 32px);
        }
        .promo-pill {
            display: inline-block; padding: 6px 12px; border-radius: 999px;
            background: rgba(255,255,255,0.2); font-size: 0.7rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase;
            margin-bottom: 12px;
        }
        .promo-pill-alt { background: rgba(225, 190, 231, 0.9); color: #4a148c; }
        .promo-pill-neutral { background: rgba(226, 232, 240, 0.9); color: #0f172a; }
        .promo-btn { width: 100%; margin-top: 14px; border: none; }

        .reviews-section { background: var(--gray-50); }
        .review-grid-redesign {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
            margin-top: 30px;
        }
        .review-card-redesign {
            background: white;
            border-radius: 28px;
            padding: 28px;
            box-shadow: var(--shadow-md);
        }
        .review-card-highlight {
            background: var(--primary);
            color: white;
            border-radius: 28px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(15, 118, 110, 0.25);
        }
        .review-card-highlight .review-stars { color: #fcd34d; }
        .review-card-highlight .review-text { color: rgba(255,255,255,0.96); }
        .review-card-highlight .review-author-name { color: #ffffff; }
        .review-card-highlight .review-author-trip { color: rgba(255,255,255,0.82); }
        .review-metrics {
            margin-top: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            border-top: 1px solid var(--gray-200);
            padding-top: 20px;
        }
        .review-metric { text-align: center; }
        .review-metric-value { font-size: 1.5rem; font-weight: 900; color: var(--primary); }
        .review-metric-label { font-size: 0.8rem; color: var(--gray-500); }

        @media (max-width: 900px) {
            .spotlight-grid { grid-template-columns: 1fr; }
            .section-header-space {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            .section-header-space .section-link {
                justify-self: auto;
            }
        }
    </style>

    <!-- ===== HOW IT WORKS ===== -->
    <section class="section" id="how-it-works">
        <div class="section-container">
            <div class="section-header section-header-centered">
                <div>
                    <span class="section-eyebrow">Precision Workflow</span>
                    <h2 class="section-title">How It Works</h2>
                    <p class="section-subtitle">Experience a seamless transition from selection to the driver's seat with our curated 4-step process.</p>
                </div>
            </div>
            <div class="steps-grid steps-grid-redesign">
                <div class="step-card step-card-redesign">
                    <div class="step-icon">🔍</div>
                    <h3 class="step-title">Browse Suited Vehicles</h3>
                    <p class="step-description">Browse our elite fleet with granular filters for performance, luxury, and utility.</p>
                    <div class="step-index">01</div>
                </div>
                <div class="step-card step-card-redesign">
                    <div class="step-icon">📅</div>
                    <h3 class="step-title">Book Online or Call</h3>
                    <p class="step-description">Instant confirmation through our digital concierge or a dedicated personal agent.</p>
                    <div class="step-index">02</div>
                </div>
                <div class="step-card step-card-redesign">
                    <div class="step-icon">🔒</div>
                    <h3 class="step-title">Pay Securely</h3>
                    <p class="step-description">Encrypted transactional layer supporting all major premium credit providers.</p>
                    <div class="step-index">03</div>
                </div>
                <div class="step-card step-card-redesign">
                    <div class="step-icon">🔑</div>
                    <h3 class="step-title">Pick Up & Drive</h3>
                    <p class="step-description">Your vehicle awaits at your chosen location, detailed and ready for departure.</p>
                    <div class="step-index">04</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== PROMOTIONS PREVIEW ===== -->
    <section class="section section-promotions">
        <div class="section-container">
            <div class="section-header section-header-space">
                <div>
                    <span class="section-eyebrow">Seasonal Benefits</span>
                    <h2 class="section-title">Promotions and Deals</h2>
                </div>
                <a href="/promotions.php" class="section-link">All Promotions →</a>
            </div>
            <div class="promo-grid promo-grid-redesign">
                <div class="promo-card promo-card-redesign promo-weekend" onclick="applyPromo('WEEKEND20')">
                    <div class="promo-overlay"></div>
                    <div class="promo-content">
                        <span class="promo-pill">Limited Offer</span>
                        <h3 class="promo-title">20% OFF Weekend Special</h3>
                        <p class="promo-description">Elevate your weekend escape with our curated performance collection.</p>
                        <button class="btn btn-primary promo-btn">Claim Privilege</button>
                    </div>
                </div>
                <div class="promo-card promo-card-redesign promo-welcome" onclick="applyPromo('FIRST50')">
                    <div class="promo-overlay"></div>
                    <div class="promo-content">
                        <span class="promo-pill promo-pill-alt">Welcome Gift</span>
                        <h3 class="promo-title">$50 OFF First Ride Bonus</h3>
                        <p class="promo-description">A sophisticated introduction to EliteDrive. Applied at checkout.</p>
                        <button class="btn btn-primary promo-btn">Unlock Bonus</button>
                    </div>
                </div>
                <div class="promo-card promo-card-redesign promo-longterm" onclick="applyPromo('LONGTERM30')">
                    <div class="promo-overlay"></div>
                    <div class="promo-content">
                        <span class="promo-pill promo-pill-neutral">Executive Tier</span>
                        <h3 class="promo-title">30% OFF Long-term Rental</h3>
                        <p class="promo-description">Extended excellence for journeys that require more than a moment.</p>
                        <button class="btn btn-primary promo-btn">Inquire Now</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== REVIEWS PREVIEW ===== -->
    <section class="section reviews-section">
        <div class="section-container">
            <div class="section-header section-header-space">
                <div>
                    <span class="section-eyebrow">Trust & Excellence</span>
                    <h2 class="section-title">Experience Refined Precision</h2>
                    <p class="section-subtitle">Join thousands of discerning travelers who trust our concierge for their most critical journeys.</p>
                </div>
                <a href="/reviews.php" class="section-link">View All Feedback →</a>
            </div>
            <div class="review-grid review-grid-redesign" id="homeReviewGrid">
                <!-- Fallback static reviews (replaced dynamically if DB reviews exist) -->
                <div class="review-card review-card-redesign">
                    <div class="review-stars">★★★★★</div>
                    <p class="review-text">"The precision of service is unmatched. From the flight tracking to the pristine vehicle state, every detail was handled with elite care."</p>
                    <div class="review-author">
                        <div class="review-avatar">JD</div>
                        <div class="review-author-info">
                            <div class="review-author-name">James D.</div>
                            <div class="review-author-trip">Corporate Executive</div>
                        </div>
                    </div>
                </div>
                <div class="review-card review-card-highlight">
                    <div class="review-stars">★★★★★</div>
                    <p class="review-text">"The chauffeurs aren't just drivers; they are logistical specialists. My commute has become my most productive hour of the day."</p>
                    <div class="review-author">
                        <div class="review-avatar">SM</div>
                        <div class="review-author-info">
                            <div class="review-author-name">Sarah M.</div>
                            <div class="review-author-trip">Tech Entrepreneur</div>
                        </div>
                    </div>
                </div>
                <div class="review-card review-card-redesign">
                    <div class="review-stars">★★★★★</div>
                    <p class="review-text">"Flawless long-distance booking. The app is intuitive and the car was waiting exactly where specified. Real luxury is simplicity."</p>
                    <div class="review-author">
                        <div class="review-avatar">RK</div>
                        <div class="review-author-info">
                            <div class="review-author-name">Robert K.</div>
                            <div class="review-author-trip">Frequent Traveler</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="review-metrics">
                <div class="review-metric"><div class="review-metric-value">99.8%</div><div class="review-metric-label">On-time Arrival</div></div>
                <div class="review-metric"><div class="review-metric-value">15k+</div><div class="review-metric-label">Monthly Journeys</div></div>
                <div class="review-metric"><div class="review-metric-value">4.9/5</div><div class="review-metric-label">Customer Rating</div></div>
                <div class="review-metric"><div class="review-metric-value">24/7</div><div class="review-metric-label">Concierge Support</div></div>
            </div>
        </div>
    </section>

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
        /* Navigation buttons */
        .hero-nav-btn {
            position: absolute; top: 50%; transform: translateY(-50%);
            width: 44px; height: 44px; border-radius: 50%;
            background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);
            color: white; font-size: 1.2rem; cursor: pointer;
            display: none; align-items: center; justify-content: center;
            z-index: 4; transition: all 0.3s ease;
            backdrop-filter: blur(4px);
        }
        .hero-nav-btn:hover {
            background: rgba(255,255,255,0.35); transform: translateY(-50%) scale(1.1);
        }
        .hero-nav-btn span { display: flex; align-items: center; justify-content: center; }
        .hero-nav-prev { left: 12px; }
        .hero-nav-next { right: 12px; }
        .hero-slideshow:hover .hero-nav-btn { display: flex; }
        .hero-slideshow::after {
            content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 50%;
            background: linear-gradient(transparent, rgba(0,0,0,0.4));
            border-radius: 0 0 var(--radius-xl) var(--radius-xl);
            pointer-events: none; z-index: 2;
        }
    </style>

    <?php include __DIR__ . '/partials/vehicle-detail-modal.html.php'; ?>

    <script>
        window.isLoggedIn = <?= json_encode($isLoggedIn ?? false) ?>;
        window.USER_ROLE = <?= json_encode($userRole ?? 'user') ?>;
    </script>
    <script src="/resources/js/cars.js"></script>
    <script src="/resources/js/home.js"></script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>                                                            
