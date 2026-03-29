// ===== HOMEPAGE MODULE =====
// Manages homepage functionality: available vehicles grid, hero slides, reviews

const VEHICLES_API = '/api/vehicles.php';
const ADMIN_API = '/api/admin.php';

// ===== UTILITIES =====
function vehicleName(v) {
    const brand = (v.brand || '').trim();
    const model = (v.model || '').trim();
    return [brand, model].filter(Boolean).join(' ').trim() || 'Vehicle';
}

function money(v) {
    const n = Number(v);
    if (!Number.isFinite(n)) return '';
    return '£' + n.toLocaleString(undefined, { maximumFractionDigits: 0 });
}

function escapeHtml(str) {
    return String(str)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeHtmlAttr(str) {
    return escapeHtml(str).replaceAll('`', '&#096;');
}

// ===== VEHICLE CARD RENDERING =====
function cardVehicle(v) {
    const name = vehicleName(v);
    const plate = (v.license_plate || '-');
    const tier = (v.service_tier || v.tier || v.category || 'Standard').trim();
    const tierLabel = tier.charAt(0).toUpperCase() + tier.slice(1).toLowerCase();
    const img = v.vehicle_image;

    const imgHtml = img
        ? `<img src="${escapeHtmlAttr(img)}" alt="${escapeHtmlAttr(name)}" style="width:100%;height:150px;object-fit:cover;display:block;">`
        : `<div class="no-image-placeholder" style="height:150px;">No Image</div>`;

    return `
        <div class="card" style="padding:0;overflow:hidden;border-radius:14px;">
            <div style="background:var(--gray-100);">${imgHtml}</div>
            <div style="padding:14px 14px 12px;">
                <div style="font-weight:800;color:var(--gray-900);font-size:0.98rem;line-height:1.25;">${escapeHtml(name)}</div>
                <div style="margin-top:8px;display:flex;justify-content:space-between;gap:10px;align-items:center;">
                    <div style="color:var(--primary);font-weight:900;font-size:0.9rem;">${escapeHtml(tierLabel)}</div>
                    <div style="font-size:0.82rem;color:var(--gray-600);font-weight:700;">${escapeHtml(plate)}</div>
                </div>
                <div style="margin-top:10px;">
                    <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:var(--success-light);color:var(--success);font-weight:800;font-size:0.75rem;">Available</span>
                </div>
            </div>
        </div>
    `;
}

// ===== VEHICLE LOADING =====
async function loadAvailableVehicles() {
    const grid = document.getElementById('availableVehiclesGrid');
    try {
        const url = new URL(VEHICLES_API, window.location.origin);
        url.searchParams.set('action', 'public-list');

        const res = await fetch(url.toString());
        const data = await res.json();

        if (!data.success) throw new Error(data.message || 'Failed to load vehicles');

        const vehicles = Array.isArray(data.vehicles) ? data.vehicles : [];

        if (vehicles.length === 0) {
            grid.innerHTML = `
                <div style="grid-column:1/-1;text-align:center;padding:26px 18px;color:var(--gray-500);">
                    No available vehicles found.
                </div>
            `;
            return;
        }

        grid.innerHTML = vehicles.map(cardVehicle).join('');
    } catch (e) {
        grid.innerHTML = `
            <div style="grid-column:1/-1;text-align:center;padding:26px 18px;color:var(--danger);">
                Failed to load vehicles.
            </div>
        `;
    }
}

// ===== HERO SLIDES LOADING (for homepage slideshow) =====
// Global variables for slide navigation
let heroSlidesData = [];
let heroSlideIndex = 0;
let heroAutoRotateInterval = null;

async function loadHeroSlides() {
    try {
        const res = await fetch('/api/admin.php?action=hero-slides-public');
        const data = await res.json();

        if (!data.success || !Array.isArray(data.slides) || data.slides.length === 0) {
            // No slides available, hide slideshow
            const slideshow = document.getElementById('heroSlideshow');
            if (slideshow) slideshow.style.display = 'none';
            return;
        }

        heroSlidesData = data.slides;
        const wrapper = document.getElementById('heroSlidesWrapper');
        if (!wrapper) return;

        // Create slide elements
        const htmlSlides = heroSlidesData.map((slide, idx) => {
            const imageUrl = slide.image_url || '/api/admin.php?action=hero-slide-image&id=' + slide.id;
            return `
                <div class="hero-slide ${idx === 0 ? 'active' : ''}" style="position:absolute;inset:0;opacity:${idx === 0 ? 1 : 0};transition:opacity 0.8s ease-in-out;">
                    <img src="${escapeHtmlAttr(imageUrl)}" alt="${escapeHtml(slide.title || 'Slide')}" 
                         style="width:100%;height:100%;object-fit:cover;">
                </div>
            `;
        }).join('');

        wrapper.innerHTML = htmlSlides;

        // Display first slide caption
        heroSlideIndex = 0;
        updateHeroCaption();

        // Setup slideshow auto-carousel (only if more than 1 slide)
        if (heroSlidesData.length > 1) {
            // Show navigation buttons
            const prevBtn = document.getElementById('heroPrevBtn');
            const nextBtn = document.getElementById('heroNextBtn');
            if (prevBtn) prevBtn.style.display = 'none'; // Show on hover
            if (nextBtn) nextBtn.style.display = 'none'; // Show on hover

            // Start auto-rotation
            startHeroAutoRotate();
        }
    } catch (e) {
        console.error('Failed to load hero slides:', e);
    }
}

function updateHeroCaption() {
    if (heroSlidesData.length === 0) return;
    
    const slide = heroSlidesData[heroSlideIndex];
    const captionDiv = document.getElementById('heroCaption');
    const titleDiv = document.getElementById('heroCaptionTitle');
    const subtitleDiv = document.getElementById('heroCaptionSub');

    if (captionDiv && titleDiv && subtitleDiv) {
        if (slide.title || slide.subtitle) {
            titleDiv.innerHTML = escapeHtml(slide.title || 'Welcome');
            subtitleDiv.innerHTML = escapeHtml(slide.subtitle || '');
            captionDiv.style.display = 'block';
        } else {
            captionDiv.style.display = 'none';
        }
    }
}

function heroSlideNav(direction) {
    heroSlideIndex = (heroSlideIndex + direction + heroSlidesData.length) % heroSlidesData.length;
    updateHeroSlideDisplay();
    updateHeroCaption();
    
    // Reset auto-rotation timer
    clearInterval(heroAutoRotateInterval);
    startHeroAutoRotate();
}

function updateHeroSlideDisplay() {
    const wrapper = document.getElementById('heroSlidesWrapper');
    if (!wrapper) return;

    const slides = wrapper.querySelectorAll('.hero-slide');
    slides.forEach((slide, idx) => {
        if (idx === heroSlideIndex) {
            slide.classList.add('active');
            slide.style.opacity = '1';
        } else {
            slide.classList.remove('active');
            slide.style.opacity = '0';
        }
    });
}

function startHeroAutoRotate() {
    heroAutoRotateInterval = setInterval(() => {
        if (heroSlidesData.length > 1) {
            heroSlideIndex = (heroSlideIndex + 1) % heroSlidesData.length;
            updateHeroSlideDisplay();
            updateHeroCaption();
        }
    }, 4000); // Change slide every 4 seconds
}

// ===== GLOBAL ACTIONS (for onclick handlers in templates) =====

/**
 * Filter vehicles by category
 * Redirect to /cars.php with category parameter
 */
function filterByCategory(category) {
    window.location.href = '/cars.php?category=' + encodeURIComponent(category);
}

/**
 * Apply promotion code
 * Save to localStorage and redirect to booking
 */
function applyPromo(code) {
    // Save promo to wallet in localStorage
    let saved = JSON.parse(localStorage.getItem('drivenow_saved_promos') || '[]');
    if (!saved.includes(code.toUpperCase())) {
        saved.push(code.toUpperCase());
        localStorage.setItem('drivenow_saved_promos', JSON.stringify(saved));
    }

    if (typeof showToast === 'function') {
        showToast('🎟️ Promo "' + code + '" saved to your wallet! Redirecting to booking...', 'success');
    }
    setTimeout(() => {
        window.location.href = '/booking.php?promo=' + encodeURIComponent(code);
    }, 1000);
}

/**
 * Book a specific car
 * Check login first, then redirect to booking page
 */
function bookCar(carId) {
    const isLoggedIn = window.isLoggedIn || false;
    
    if (!isLoggedIn) {
        if (typeof showToast === 'function') showToast('Please sign in to book a car.', 'warning');
        setTimeout(() => {
            window.location.href = '/login.php?redirect=/booking.php&car_id=' + encodeURIComponent(carId);
        }, 1000);
        return;
    }
    
    window.location.href = '/booking.php?car_id=' + encodeURIComponent(carId);
}

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', () => {
    loadAvailableVehicles();

    // Keep existing homepage loaders (hero, reviews) if present
    if (typeof loadHeroSlides === 'function') loadHeroSlides();
    if (typeof loadHomeReviews === 'function') loadHomeReviews();

    // Export functions to global scope for onclick handlers
    window.filterByCategory = filterByCategory;
    window.applyPromo = applyPromo;
    window.bookCar = bookCar;
    window.loadHomeVehicles = loadAvailableVehicles;
    
    // Listen for vehicle availability updates from booking completion
    window.addEventListener('vehicleAvailabilityUpdated', function(e) {
        // Reload available vehicles when a booking completes
        if (e.detail && e.detail.vehicle_status === 'available') {
            console.log('Vehicle ' + e.detail.vehicle_id + ' is now available, refreshing list...');
            setTimeout(() => loadAvailableVehicles(), 300);
        }
    });
});
