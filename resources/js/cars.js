/**
 * Cars Filter & Listing Module
 * Handles vehicle filtering, search, grid rendering, and detail modal
 */

(function initCarsModule() {
  const isLoggedIn = window.isLoggedIn || false;
  const VEHICLES_API = '/api/vehicles.php';
  let allLoadedCars = [];

  const USER_ROLE = window.USER_ROLE || 'user';
  const NORMALIZED_USER_ROLE = String(USER_ROLE || 'user').toLowerCase().replace(/[-_\s]/g, '');
  const CAN_VIEW_VEHICLE_DETAIL = true;
  const NON_CUSTOMER_ROLES = new Set(['admin', 'controlstaff', 'callcenterstaff', 'driver']);
  const CAN_SELF_SELECT_VEHICLE_BOOKING = NON_CUSTOMER_ROLES.has(NORMALIZED_USER_ROLE);

  // ===== FILTER STATE =====
  let filterState = {
    brand: '',
    seats: '',
    max_price: 500,
    tier: '',
    search: ''
  };

  // Init from URL params
  function initFromURL() {
    const p = new URLSearchParams(window.location.search);

    filterState.brand = p.get('brand') || '';
    filterState.seats = p.get('seats') || '';
    filterState.max_price = parseInt(p.get('max_price')) || 500;
    filterState.tier = p.get('tier') || '';
    filterState.search = p.get('search') || '';

    // Set active chips from URL (for static filters)
    setActiveChip('tierFilters', filterState.tier);
    setActiveChip('seatFilters', filterState.seats);

    // Set price range
    const range = document.getElementById('priceRange');
    if (range) {
      range.value = filterState.max_price;
      updatePriceDisplay(filterState.max_price);
    }

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
  }

  function setActiveChip(containerId, value) {
    const container = document.getElementById(containerId);
    if (container) {
      container.querySelectorAll('.filter-chip').forEach(chip => {
        chip.classList.toggle('active', chip.dataset.value === value);
      });
    }
  }

  // ===== FILTER CHIP CLICK (for static filters: tier, seats) =====
  function bindStaticChips() {
    document.querySelectorAll('#tierFilters .filter-chip, #seatFilters .filter-chip').forEach(chip => {
      chip.disabled = false;
      chip.addEventListener('click', function(e) {
        e.preventDefault();
        const container = this.closest('.tier-switch, .seat-switch, .filter-options');
        if (container) {
          container.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
          this.classList.add('active');

          // Update filter state
          const containerId = container.id;
          const val = this.dataset.value;
          if (containerId === 'tierFilters') filterState.tier = val;
          else if (containerId === 'seatFilters') filterState.seats = val;
        }
      });
    });
  }

  function bindBrandSelect() {
    const brandSelect = document.getElementById('brandSelect');
    if (!brandSelect) return;
    brandSelect.disabled = false;

    brandSelect.addEventListener('change', function() {
      filterState.brand = this.value || '';
    });
  }

  // ===== PRICE RANGE =====
  function bindPriceRange() {
    const priceRange = document.getElementById('priceRange');
    if (priceRange) {
      priceRange.addEventListener('input', function() {
        filterState.max_price = parseInt(this.value);
        updatePriceDisplay(this.value);
      });
      updatePriceDisplay(priceRange.value);
    }
  }

  function updatePriceDisplay(val) {
    const max = 500;
    const pct = (val / max) * 100;
    const rangeFill = document.getElementById('rangeFill');
    const priceLabel = document.getElementById('priceLabel');
    
    if (rangeFill) rangeFill.style.width = pct + '%';
    if (priceLabel) {
      priceLabel.textContent = val >= max ? '£0 – £500+' : '£0 – £' + val;
    }
  }

  // ===== LOAD DYNAMIC FILTER OPTIONS FROM DB =====
  async function loadFilterOptions() {
    try {
      const res = await fetch(VEHICLES_API + '?action=filter-options');
      const data = await res.json();

      if (data.success) {
        // Render brand options
        const brandSelect = document.getElementById('brandSelect');
        if (brandSelect && data.brands && data.brands.length > 0) {
          const safeBrands = data.brands.filter(Boolean);
          brandSelect.innerHTML = '<option value="">All Brands</option>' + safeBrands.map(brand =>
            '<option value="' + escapeHtml(String(brand)) + '">' + escapeHtml(String(brand)) + '</option>'
          ).join('');

          if (filterState.brand) {
            brandSelect.value = filterState.brand;
          }
        }
      }
    } catch (err) {
      const brandSelect = document.getElementById('brandSelect');
      if (brandSelect && brandSelect.options.length <= 1) {
        const fallback = document.createElement('option');
        fallback.value = '';
        fallback.textContent = 'Failed to load brands';
        brandSelect.appendChild(fallback);
      }
    }
  }

  // ===== SEARCH WITH SUGGESTIONS (uses navbar search bar) =====
  let debounceTimer = null;

  function bindSearchFunctionality() {
    const searchInput = document.getElementById('navbarSearchInput');
    const suggestionsBox = document.getElementById('navbarSuggestions');
    const clearBtn = document.getElementById('navbarSearchClear');

    if (searchInput) {
      searchInput.addEventListener('input', function() {
        const q = this.value.trim();
        if (clearBtn) clearBtn.style.display = q ? 'flex' : 'none';

        clearTimeout(debounceTimer);
        if (q.length < 1) {
          if (suggestionsBox) suggestionsBox.classList.remove('open');
          return;
        }

        debounceTimer = setTimeout(() => fetchSuggestions(q), 250);
      });

      searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          if (suggestionsBox) suggestionsBox.classList.remove('open');
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
  }

  async function fetchSuggestions(query) {
    const suggestionsBox = document.getElementById('navbarSuggestions');
    if (!suggestionsBox) return;

    try {
      const res = await fetch(VEHICLES_API + '?action=search-suggestions&q=' + encodeURIComponent(query));
      const data = await res.json();
      if (data.success && data.suggestions.length > 0) {
        renderSuggestions(data.suggestions, query);
      } else {
        suggestionsBox.innerHTML = '<div class="suggestion-hint">No results found for "' + escapeHtml(query) + '"</div>';
        suggestionsBox.classList.add('open');
      }
    } catch (err) {
      suggestionsBox.classList.remove('open');
    }
  }

  function renderSuggestions(suggestions, query) {
    const suggestionsBox = document.getElementById('navbarSuggestions');
    if (!suggestionsBox) return;

    const html = suggestions.map(s => {
      const icon = s.type === 'brand' 
        ? '<div class="suggestion-icon brand-icon">🏷️</div>'
        : '<div class="suggestion-icon vehicle-icon">🚗</div>';

      const highlighted = highlightMatch(s.label, query);
      const sub = s.sub ? '<div class="suggestion-sub">' + escapeHtml(s.sub) + '</div>' : '';

      return '<div class="suggestion-item" data-type="' + s.type + '" data-text="' + escapeHtml(s.text) + '" data-id="' + (s.id || '') + '">' +
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
        const searchInput = document.getElementById('navbarSearchInput');
        const clearBtn = document.getElementById('navbarSearchClear');

        if (searchInput) searchInput.value = text;
        suggestionsBox.classList.remove('open');
        if (clearBtn) clearBtn.style.display = 'flex';

        if (type === 'brand') {
          filterState.search = '';
          filterState.brand = text;
          const brandSelect = document.getElementById('brandSelect');
          if (brandSelect) brandSelect.value = text;
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
    const searchInput = document.getElementById('navbarSearchInput');
    const suggestionsBox = document.getElementById('navbarSuggestions');
    
    filterState.search = searchInput ? searchInput.value.trim() : '';
    if (suggestionsBox) suggestionsBox.classList.remove('open');
    if (filterState.search) {
      applyAllFilters();
    }
  }

  // ===== APPLY / RESET FILTERS =====
  function applyAllFilters() {
    const params = new URLSearchParams();
    if (filterState.search) params.set('search', filterState.search);
    if (filterState.brand) params.set('brand', filterState.brand);
    if (filterState.tier) params.set('tier', filterState.tier);
    if (filterState.seats) params.set('seats', filterState.seats);
    if (filterState.max_price < 500) params.set('max_price', filterState.max_price);
    window.location.href = '/cars.php?' + params.toString();
  }

  function resetFilters() {
    window.location.href = '/cars.php';
  }

  // ===== LOAD CARS FROM API =====
  async function loadCars() {
    try {
      const params = new URLSearchParams(window.location.search);

      const payload = {
        action: 'list',
        tier: params.get('tier') || '',
        seats: params.get('seats') || '',
        brand: params.get('brand') || '',
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
      const grid = document.getElementById('carGrid');
      if (grid) {
        grid.innerHTML =
          '<div style="grid-column:1/-1;text-align:center;padding:60px 20px;">' +
            '<div style="font-size:3rem;margin-bottom:12px;">⚠️</div>' +
            '<h3 style="color:var(--gray-700);margin-bottom:8px;">Connection Error</h3>' +
            '<p style="color:var(--gray-500);">Failed to load cars. Please try again later.</p>' +
          '</div>';
      }
    }
  }

  function renderCarGrid(cars) {
    allLoadedCars = cars;
    const grid = document.getElementById('carGrid');
    const countText = document.getElementById('carCountText');

    if (!grid) return;

    if (cars.length === 0) {
      if (countText) countText.textContent = 'No cars available yet';
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
    if (filterState.tier) activeFilters.push(filterState.tier + ' tier');
    if (filterState.seats) activeFilters.push(filterState.seats + ' seats');
    const filterSuffix = activeFilters.length > 0 ? ' for ' + activeFilters.join(', ') : '';

    if (countText) {
      countText.textContent = cars.length + ' car' + (cars.length !== 1 ? 's' : '') + ' found' + filterSuffix;
    }

    grid.innerHTML = cars.map(car => {
      const images = car.images || [];
      const hasValidImage = images.length > 0 && images[0] && (images[0].startsWith('http') || images[0].startsWith('/api/'));
      const displaySeats = normalizeSeatTier(car.seats);
      const plate = (car.license_plate || '').trim() || 'No plate';
      const imageHTML = hasValidImage
        ? '<img src="' + images[0] + '" alt="' + escapeHtml(car.brand + ' ' + car.model) + '" style="width:100%;height:100%;object-fit:cover;" onerror="this.onerror=null;this.src=\'/resources/images/logo/logo.png\';"><div class="no-image-placeholder" style="display:none;">No Photo</div>'
        : '<img src="/resources/images/logo/logo.png" alt="Vehicle" style="width:100%;height:100%;object-fit:cover;">';

      const rating = parseFloat(car.avg_rating) || 0;

      // Customer-facing availability status: only "available" is bookable.
      const isAvailable = isVehicleAvailableForCustomer(car.status);
      const statusBadge = isAvailable
        ? '<span class="car-status-badge available">Available</span>'
        : '<span class="car-status-badge not-available">Not Available</span>';

      const tierBadge = '<span class="car-tier-badge" style="background:' + getTierBgColor(car.service_tier) + ';color:' + getTierTextColor(car.service_tier) + ';">' +
        escapeHtml((car.service_tier || 'standard').toUpperCase()) + ' TIER</span>';

      return '<div class="car-card' + (isAvailable ? '' : ' car-rented') + '" onclick="handleCarClick(\'' + car.id + '\')" style="cursor:pointer;">' +
        '<div class="car-card-image">' +
          imageHTML +
          statusBadge +
          '<button class="car-card-favorite" onclick="event.stopPropagation();toggleFavorite(this)"><span class="material-symbols-outlined">favorite_border</span></button>' +
        '</div>' +
        '<div class="car-card-body">' +
          '<div class="car-card-head">' +
            '<h3 class="car-card-title">' + escapeHtml(car.brand + ' ' + car.model + ' ' + car.year) + '</h3>' +
            '<div class="car-card-rating"><span class="material-symbols-outlined">star</span><span>' + rating.toFixed(1) + '</span></div>' +
          '</div>' +
          '<div class="car-card-meta">' +
            '<span class="car-meta-chip"><span class="material-symbols-outlined">event_seat</span><span>' + displaySeats + ' seats</span></span>' +
            '<span class="car-meta-chip"><span class="material-symbols-outlined">badge</span><span>' + escapeHtml(plate) + '</span></span>' +
          '</div>' +
          '<div class="car-card-footer">' +
            tierBadge +
          '</div>' +
        '</div>' +
      '</div>';
    }).join('');
  }

  function normalizeSeatTier(seats) {
    const n = parseInt(seats, 10) || 0;
    return n >= 7 ? 7 : 4;
  }

  function getTierColor(tier) {
    const colors = {
      'eco': '#10b981',
      'standard': '#0f766e',
      'luxury': '#f59e0b'
    };
    return colors[tier] || '#0f766e';
  }

  function getTierBgColor(tier) {
    const key = String(tier || 'standard').toLowerCase();
    if (key === 'eco') return '#d1fae5';
    if (key === 'luxury' || key === 'premium') return '#ffdbd1';
    return '#dde4e2';
  }

  function getTierTextColor(tier) {
    const key = String(tier || 'standard').toLowerCase();
    if (key === 'eco') return '#065f46';
    if (key === 'luxury' || key === 'premium') return '#723522';
    return '#414847';
  }

  function getTierIcon(tier) {
    const icons = {
      'eco': '🌿',
      'standard': '⭐',
      'luxury': '👑'
    };
    return icons[tier] || '⭐';
  }

  function formatServiceTierLabel(tier) {
    const key = String(tier || 'standard').toLowerCase();
    if (key === 'eco') return 'Economy';
    if (key === 'luxury' || key === 'premium') return 'Luxury';
    return 'Standard';
  }

  function getTierQualityInfo(tier) {
    const key = String(tier || 'standard').toLowerCase();
    if (key === 'eco') {
      return {
        score: '4.1',
        summary: 'Economy tier focuses on value, clean rides, and reliable essentials for daily trips.',
        indicators: ['Clean interior', 'Reliable comfort', 'Budget-friendly service']
      };
    }
    if (key === 'luxury' || key === 'premium') {
      return {
        score: '4.8',
        summary: 'Luxury tier provides premium comfort, advanced amenities, and top-rated service quality.',
        indicators: ['Premium cabin', 'Enhanced amenities', 'Top-rated experience']
      };
    }
    return {
      score: '4.5',
      summary: 'Standard tier balances comfort and value with consistent quality for most customers.',
      indicators: ['Balanced comfort', 'Consistent quality', 'Great value']
    };
  }

  function normalizeCategoryKey(category, fuelType, tier) {
    const c = String(category || '').trim().toLowerCase();
    const fuel = String(fuelType || '').trim().toLowerCase();
    const normalizedTier = String(tier || '').trim().toLowerCase();

    if (c === 'hybrid' || fuel === 'hybrid') return 'hybrid';
    if (c === 'electric' || fuel === 'electric') return 'electric';
    if (c === 'suv') return 'suv';
    if (c === 'luxury' || normalizedTier === 'luxury' || normalizedTier === 'premium') return 'luxury';
    return 'sedan';
  }

  function getCategoryProfiles() {
    return {
      sedan: {
        label: 'Sedan',
        seats: '4-5 seats',
        luggage: '280-420 lbs',
        defaultAmenityFocus: 'Balanced comfort',
        coreAmenities: ['A/C', 'Phone charging', 'Comfort seats']
      },
      suv: {
        label: 'SUV',
        seats: '4-7 seats',
        luggage: '420-620 lbs',
        defaultAmenityFocus: 'Family and group flexibility',
        coreAmenities: ['Large trunk', 'Rear climate vents', 'All-weather support']
      },
      luxury: {
        label: 'Luxury',
        seats: '4-7 seats',
        luggage: '320-500 lbs',
        defaultAmenityFocus: 'Premium comfort and exclusivity',
        coreAmenities: ['Premium cabin', 'Ambient lighting', 'Advanced infotainment']
      },
      electric: {
        label: 'Electric',
        seats: '4-7 seats',
        luggage: '250-450 lbs',
        defaultAmenityFocus: 'Quiet and eco-friendly travel',
        coreAmenities: ['Zero-emission ride', 'Smart driver assist', 'Modern digital cockpit']
      },
      hybrid: {
        label: 'Hybrid',
        seats: '4-7 seats',
        luggage: '280-460 lbs',
        defaultAmenityFocus: 'Efficiency with flexibility',
        coreAmenities: ['Fuel-efficient drivetrain', 'Regenerative braking', 'Smooth city cruising']
      }
    };
  }

  function getVehicleLuggageCapacityLbs(car) {
    const raw = car && (car.luggage_capacity_lbs ?? car.capacity);
    if (raw === null || raw === undefined || raw === '') return null;
    const parsed = parseInt(raw, 10);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
  }

  function formatLuggageCapacityLbs(value) {
    const parsed = parseInt(value, 10);
    if (!Number.isFinite(parsed) || parsed <= 0) return 'N/A';
    return parsed + ' lbs';
  }

  function buildAmenityFocusText(features, profile) {
    const normalized = Array.isArray(features)
      ? features.map(function(item) { return String(item || '').trim(); }).filter(Boolean)
      : [];
    if (normalized.length > 0) {
      return normalized.slice(0, 2).join(' + ');
    }
    return profile.defaultAmenityFocus;
  }

  function renderCategoryComparisonTable(activeCategoryKey) {
    const profiles = getCategoryProfiles();
    const order = ['sedan', 'suv', 'luxury', 'electric', 'hybrid'];
    const rows = order.map(function(key) {
      const profile = profiles[key];
      const isActive = key === activeCategoryKey;

      return '<tr class="' + (isActive ? 'active-row' : '') + '">' +
        '<td>' + escapeHtml(profile.label) + (isActive ? ' (Current)' : '') + '</td>' +
        '<td>' + escapeHtml(profile.seats) + '</td>' +
        '<td>' + escapeHtml(profile.luggage) + '</td>' +
        '<td>' + escapeHtml(profile.coreAmenities.join(', ')) + '</td>' +
      '</tr>';
    }).join('');

    return '<table class="detail-comparison-table">' +
      '<thead><tr><th>Category</th><th>Typical Seats</th><th>Luggage Capacity</th><th>Amenity Highlights</th></tr></thead>' +
      '<tbody>' + rows + '</tbody>' +
    '</table>';
  }

  function isVehicleAvailableForCustomer(status) {
    return String(status || 'available').toLowerCase() === 'available';
  }

  function formatVehicleStatusLabel(status) {
    const key = String(status || 'unavailable').toLowerCase();
    if (key === 'rented') return 'rented';
    if (key === 'maintenance') return 'in maintenance';
    if (key === 'inactive') return 'inactive';
    return key;
  }

  async function notifyVehicleAvailability(vehicleId) {
    if (!isLoggedIn) {
      if (typeof showToast === 'function') showToast('Please sign in to subscribe for availability alerts.', 'warning');
      if (typeof showAuthModal === 'function') {
        showAuthModal('login');
      }
      return;
    }

    try {
      const res = await fetch(VEHICLES_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'notify-me', vehicle_id: vehicleId })
      });
      const data = await res.json();
      if (data.success) {
        if (typeof showToast === 'function') showToast(data.message || 'Subscribed. We will notify you when this vehicle is available.', 'success');
      } else {
        if (typeof showToast === 'function') showToast(data.message || 'Unable to subscribe for notification.', 'error');
      }
    } catch (e) {
      if (typeof showToast === 'function') showToast('Network error. Please try again.', 'error');
    }
  }

  function handleCarClick(carId) {
    openCarDetail(carId);
  }

  // ===== CAR DETAIL MODAL =====
  async function openCarDetail(carId) {
    let car = allLoadedCars.find(c => c.id === carId);

    if (!car) {
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
          if (typeof showToast === 'function') showToast('Car not found.', 'error');
          return;
        }
      } catch (err) {
        if (typeof showToast === 'function') showToast('Failed to load car details.', 'error');
        return;
      }
    }

    // Title & subtitle
    const detailTitle = document.getElementById('detailTitle');
    const detailSub = document.getElementById('detailSub');
    const categoryKey = normalizeCategoryKey(car.category, car.fuel_type, car.service_tier);
    const categoryProfiles = getCategoryProfiles();
    const activeCategoryProfile = categoryProfiles[categoryKey] || categoryProfiles.sedan;
    const features = Array.isArray(car.features) ? car.features : [];
    if (detailTitle) detailTitle.textContent = car.brand + ' ' + car.model + ' ' + car.year;
    if (detailSub) detailSub.textContent = activeCategoryProfile.label + ' • ' + ucfirst(car.transmission) + ' • ' + ucfirst(car.fuel_type) + (car.license_plate ? ' • ' + car.license_plate : '');

    // Service Tier + availability
    const detailPrice = document.getElementById('detailPrice');
    const detailAvailabilityLabel = document.getElementById('detailAvailabilityLabel');
    const tierLabel = formatServiceTierLabel(car.service_tier);
    const isAvailable = isVehicleAvailableForCustomer(car.status);
    if (detailPrice) {
      const tierColor = getTierColor((car.service_tier || '').toLowerCase());
      const tierIcon = getTierIcon(car.service_tier);
      detailPrice.innerHTML = '<span style="color:' + tierColor + ';font-weight:700;font-size:1.1rem;">' + tierIcon + ' ' + tierLabel + '</span>';
    }
    if (detailAvailabilityLabel) {
      detailAvailabilityLabel.textContent = isAvailable ? 'Available for booking' : 'Not Available';
    }

    // Rating
    const detailRating = document.getElementById('detailRating');
    if (detailRating) {
      const rating = parseFloat(car.avg_rating) || 0;
      const reviews = car.total_reviews || 0;
      const stars = '★'.repeat(Math.round(rating)) + '☆'.repeat(5 - Math.round(rating));
      detailRating.innerHTML = 
        '<span style="color:#f59e0b;font-size:1.1rem;">' + stars + '</span>' +
        '<span style="font-weight:700;color:var(--gray-800);">' + rating.toFixed(1) + '</span>' +
        '<span style="color:var(--gray-500);font-size:0.85rem;">(' + reviews + ' review' + (reviews !== 1 ? 's' : '') + ')</span>';
    }

    const detailQualitySection = document.getElementById('detailQualitySection');
    if (detailQualitySection) {
      const quality = getTierQualityInfo(car.service_tier);
      detailQualitySection.innerHTML =
        '<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px;">' +
          '<div style="font-size:0.9rem;font-weight:700;color:var(--gray-800);">Service Quality</div>' +
          '<span class="quality-pill">Quality Score ' + quality.score + '/5</span>' +
        '</div>' +
        '<div style="font-size:0.85rem;color:var(--gray-700);margin-bottom:8px;">' + escapeHtml(quality.summary) + '</div>' +
        '<div style="display:flex;gap:8px;flex-wrap:wrap;">' +
          quality.indicators.map(function(indicator) {
            return '<span class="detail-feature-tag">' + escapeHtml(indicator) + '</span>';
          }).join('') +
        '</div>';
    }

    const detailCategoryBadge = document.getElementById('detailCategoryBadge');
    const detailLuggageCapacityLbs = document.getElementById('detailLuggageCapacityLbs');
    const detailAmenityFocus = document.getElementById('detailAmenityFocus');
    const detailCategoryComparison = document.getElementById('detailCategoryComparison');
    if (detailCategoryBadge) detailCategoryBadge.textContent = activeCategoryProfile.label;
    if (detailLuggageCapacityLbs) detailLuggageCapacityLbs.textContent = activeCategoryProfile.luggage;
    if (detailAmenityFocus) detailAmenityFocus.textContent = buildAmenityFocusText(features, activeCategoryProfile);
    if (detailCategoryComparison) {
      detailCategoryComparison.innerHTML = renderCategoryComparisonTable(categoryKey);
    }

    // Image gallery
    const images = car.images || [];
    const mainImg = document.getElementById('detailMainImage');
    const thumbsEl = document.getElementById('detailThumbs');

    if (mainImg) {
      if (images.length > 0 && images[0] && (images[0].startsWith('http') || images[0].startsWith('/api/'))) {
        mainImg.innerHTML = '<img src="' + images[0] + '" alt="' + escapeHtml(car.brand + ' ' + car.model) + '" style="width:100%;height:100%;object-fit:cover;" onerror="this.onerror=null;this.src=\'/resources/images/logo/logo.png\';">';
        if (thumbsEl && images.length > 1) {
          thumbsEl.innerHTML = images.filter(img => img && (img.startsWith('http') || img.startsWith('/api/'))).map((img, i) => 
            '<div class="detail-thumb ' + (i === 0 ? 'active' : '') + '" onclick="switchDetailImage(\'' + img + '\', this)">' +
            '<img src="' + img + '" alt="Photo ' + (i+1) + '" onerror="this.onerror=null;this.src=\'/resources/images/logo/logo.png\';"></div>'
          ).join('');
        } else if (thumbsEl) {
          thumbsEl.innerHTML = '';
        }
      } else {
        mainImg.innerHTML = '<img src="/resources/images/logo/logo.png" alt="Vehicle" style="width:100%;height:100%;object-fit:cover;">';
        if (thumbsEl) thumbsEl.innerHTML = '';
      }
    }

    // Specs grid
    const detailSpecs = document.getElementById('detailSpecs');
    if (detailSpecs) {
      detailSpecs.innerHTML = 
        specItem('🏷️', 'Category', activeCategoryProfile.label) +
        specItem('👤', 'Seats', car.seats) +
        specItem('🧳', 'Luggage', activeCategoryProfile.luggage) +
        specItem('🎨', 'Color', ucfirst(car.color || 'N/A')) +
        specItem('📅', 'Year', car.year) +
        specItem('📋', 'Bookings', car.total_bookings || 0);
    }

    // Features
    const featSection = document.getElementById('detailFeaturesSection');
    const detailFeatures = document.getElementById('detailFeatures');
    if (featSection && detailFeatures) {
      if (features.length > 0) {
        featSection.style.display = 'block';
        detailFeatures.innerHTML = features.map(f => 
          '<span class="detail-feature-tag">✓ ' + escapeHtml(f.trim()) + '</span>'
        ).join('');
      } else {
        featSection.style.display = 'none';
      }
    }

    // Location
    const locSection = document.getElementById('detailLocationSection');
    const detailLocation = document.getElementById('detailLocation');
    if (locSection && detailLocation) {
      const locText = [car.location_city, car.location_address].filter(Boolean).join(' — ');
      if (locText) {
        locSection.style.display = 'block';
        detailLocation.innerHTML = '📍 ' + escapeHtml(locText);
      } else {
        locSection.style.display = 'none';
      }
    }

    // Hide price breakdown (no longer applicable - using service tiers instead)
    const detailDailyRate = document.getElementById('detailDailyRate');
    if (detailDailyRate && detailDailyRate.parentElement) {
      detailDailyRate.parentElement.style.display = 'none';
    }

    const weeklyRow = document.getElementById('detailWeeklyRow');
    if (weeklyRow) {
      weeklyRow.style.display = 'none';
    }

    const monthlyRow = document.getElementById('detailMonthlyRow');
    if (monthlyRow) {
      monthlyRow.style.display = 'none';
    }

    // Open modal
    const modal = document.getElementById('carDetailModal');
    if (modal) modal.classList.add('open');
  }

  function switchDetailImage(src, thumbEl) {
    const detailMainImage = document.getElementById('detailMainImage');
    if (detailMainImage) {
      detailMainImage.innerHTML = '<img src="' + src + '" style="width:100%;height:100%;object-fit:cover;" onerror="this.onerror=null;this.src=\'/resources/images/logo/logo.png\';">';
    }
    document.querySelectorAll('.detail-thumb').forEach(t => t.classList.remove('active'));
    if (thumbEl) thumbEl.classList.add('active');
  }

  function specItem(icon, label, value) {
    return '<div class="detail-spec-item">' +
      '<div class="detail-spec-icon">' + icon + '</div>' +
      '<div class="detail-spec-label">' + label + '</div>' +
      '<div class="detail-spec-value">' + escapeHtml(value) + '</div>' +
    '</div>';
  }

  function ucfirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  function bookCar(carId) {
    if (!CAN_SELF_SELECT_VEHICLE_BOOKING) {
      if (typeof showToast === 'function') {
        showToast('Customers cannot self-select a vehicle. Please contact Call Center staff to book.', 'warning');
      }
      return;
    }

    if (!isLoggedIn) {
      if (typeof showToast === 'function') showToast('Please sign in to book a car.', 'warning');
      if (typeof showAuthModal === 'function') {
        showAuthModal('login');
      }
      return;
    }
    // Check if car is available
    const car = allLoadedCars.find(c => c.id === carId);
    if (car && !isVehicleAvailableForCustomer(car.status)) {
      if (typeof showToast === 'function') showToast('This vehicle is not available for booking right now.', 'warning');
      return;
    }
    window.location.href = '/booking.php?car_id=' + encodeURIComponent(carId);
  }

  function toggleFavorite(btn) {
    const icon = btn.querySelector('.material-symbols-outlined');
    btn.classList.toggle('active');
    if (icon) {
      icon.textContent = btn.classList.contains('active') ? 'favorite' : 'favorite_border';
      icon.style.fontVariationSettings = btn.classList.contains('active')
        ? "'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24"
        : "'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24";
    }
    if (typeof showToast === 'function') {
      showToast(btn.classList.contains('active') ? 'Added to favorites!' : 'Removed from favorites.', 'success');
    }
  }

  function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
  }

  // ===== INITIALIZATION =====
  document.addEventListener('DOMContentLoaded', function() {
    initFromURL();
    bindStaticChips();
    bindBrandSelect();
    bindPriceRange();
    bindSearchFunctionality();
    loadCars();
    
    // Listen for vehicle availability updates from booking completion
    window.addEventListener('vehicleAvailabilityUpdated', function(e) {
      // Reload cars list when a vehicle becomes available
      if (e.detail && e.detail.vehicle_status === 'available') {
        console.log('Vehicle ' + e.detail.vehicle_id + ' is now available, refreshing cars list...');
        setTimeout(() => loadCars(), 300);
      }
    });
  });

  // Export public functions to global scope
  window.setActiveChip = setActiveChip;
  window.updatePriceDisplay = updatePriceDisplay;
  window.applyAllFilters = applyAllFilters;
  window.resetFilters = resetFilters;
  window.clearSearch = function() {
    const searchInput = document.getElementById('navbarSearchInput');
    const clearBtn = document.getElementById('navbarSearchClear');
    const suggestionsBox = document.getElementById('navbarSuggestions');
    
    if (searchInput) searchInput.value = '';
    if (clearBtn) clearBtn.style.display = 'none';
    filterState.search = '';
    if (suggestionsBox) suggestionsBox.classList.remove('open');
    if (searchInput) searchInput.focus();
  };
  window.handleCarClick = handleCarClick;
  window.openCarDetail = openCarDetail;
  window.switchDetailImage = switchDetailImage;
  window.bookCar = bookCar;
  window.toggleFavorite = toggleFavorite;
  window.loadCars = loadCars;
})();
