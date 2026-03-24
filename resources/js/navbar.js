/**
 * Navbar Module - DriveNow
 * Handles: Navbar scroll detection, side menu toggle, universal search
 */

// ===== NAVBAR SCROLL DETECTION =====
window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 10);
});

// ===== SIDE MENU TOGGLE =====
function toggleSideMenu() {
    const menu = document.getElementById('sideMenu');
    const toggle = document.getElementById('sideMenuToggle');
    const overlay = document.getElementById('sideMenuOverlay');
    const isOpen = menu.classList.toggle('open');
    toggle.classList.toggle('active', isOpen);
    if (overlay) overlay.classList.toggle('active', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
}

function closeSideMenu() {
    const menu = document.getElementById('sideMenu');
    const toggle = document.getElementById('sideMenuToggle');
    const overlay = document.getElementById('sideMenuOverlay');
    menu.classList.remove('open');
    toggle.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
    document.body.style.overflow = '';
}

// Legacy aliases for backward compat
function toggleMobileMenu() { toggleSideMenu(); }
function closeMobileMenu() { closeSideMenu(); }

// Close side menu with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSideMenu();
});

// ===== NAVBAR SEARCH (universal, works on all pages) =====
(function initNavbarSearch() {
    const navInput = document.getElementById('navbarSearchInput');
    const navSug = document.getElementById('navbarSuggestions');
    const navClear = document.getElementById('navbarSearchClear');
    if (!navInput) return;

    let navDebounce = null;

    // Show/hide clear button based on value
    if (navInput.value.trim()) {
        navClear.style.display = 'flex';
    }

    // Only bind universal handlers if NOT on cars page (cars page has its own)
    if (document.getElementById('carGrid')) return;

    navInput.addEventListener('input', function() {
        const q = this.value.trim();
        navClear.style.display = q ? 'flex' : 'none';
        clearTimeout(navDebounce);
        if (q.length < 1) { navSug.classList.remove('open'); return; }
        navDebounce = setTimeout(() => navbarFetchSuggestions(q), 250);
    });

    navInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            navSug.classList.remove('open');
            const q = navInput.value.trim();
            if (q) {
                window.location.href = '/api/cars.php?search=' + encodeURIComponent(q);
            } else {
                window.location.href = '/api/cars.php';
            }
        }
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.navbar-search-wrapper')) {
            navSug.classList.remove('open');
        }
    });

    async function navbarFetchSuggestions(query) {
        try {
            const res = await fetch('/api/vehicles.php?action=search-suggestions&q=' + encodeURIComponent(query));
            const data = await res.json();
            if (data.success && data.suggestions.length > 0) {
                navSug.innerHTML = data.suggestions.map(s => {
                    const icon = s.type === 'brand' ? '🏷️' : '🚗';
                    return '<div class="suggestion-item" data-type="' + s.type + '" data-text="' + s.text + '">' +
                        '<div class="suggestion-icon ' + (s.type === 'brand' ? 'brand-icon' : 'vehicle-icon') + '">' + icon + '</div>' +
                        '<div class="suggestion-text"><div class="suggestion-label">' + s.label + '</div>' +
                        (s.sub ? '<div class="suggestion-sub">' + s.sub + '</div>' : '') +
                        '</div></div>';
                }).join('');
                navSug.classList.add('open');

                navSug.querySelectorAll('.suggestion-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const type = this.dataset.type;
                        const text = this.dataset.text;
                        navSug.classList.remove('open');
                        if (type === 'brand') {
                            window.location.href = '/api/cars.php?brand=' + encodeURIComponent(text);
                        } else {
                            window.location.href = '/api/cars.php?search=' + encodeURIComponent(text);
                        }
                    });
                });
            } else {
                navSug.innerHTML = '<div class="suggestion-hint" style="font-size:0.75rem;color:var(--gray-400);padding:10px 20px;background:var(--gray-50);">No results for "' + query + '"</div>';
                navSug.classList.add('open');
            }
        } catch(e) { navSug.classList.remove('open'); }
    }
})();

// Global navbar clear search function
function navbarClearSearch() {
    const navInput = document.getElementById('navbarSearchInput');
    const navClear = document.getElementById('navbarSearchClear');
    const navSug = document.getElementById('navbarSuggestions');
    if (navInput) { navInput.value = ''; navInput.focus(); }
    if (navClear) navClear.style.display = 'none';
    if (navSug) navSug.classList.remove('open');
    // If on cars page, also clear the filter
    if (typeof clearSearch === 'function') clearSearch();
}

// ===== SMOOTH SCROLL FOR SIDE MENU NAV =====
document.querySelectorAll('.side-menu-item').forEach(link => {
    link.addEventListener('click', (e) => {
        const href = link.getAttribute('href');
        // Close side menu on any nav click
        closeSideMenu();
        if (href.startsWith('#') || href.includes('#')) {
            const hash = href.includes('#') ? '#' + href.split('#')[1] : href;
            e.preventDefault();
            const target = document.querySelector(hash);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else if (href.includes('index.php#')) {
                window.location.href = href;
            }
        }
    });
});
