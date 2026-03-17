/**
 * Cars Filter & Listing Module
 * Handles vehicle filtering, search, grid rendering, and detail modal
 */

(function initCarsModule() {
  const isLoggedIn = window.isLoggedIn || false;
  const VEHICLES_API = '/api/vehicles.php';
  let allLoadedCars = [];

  const USER_ROLE = window.USER_ROLE || 'user';
  const CAN_VIEW_VEHICLE_DETAIL = (USER_ROLE === 'staff' || USER_ROLE === 'admin');

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
  function initFromURL() {
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

  // ===== FILTER CHIP CLICK (for static filters: transmission, fuel) =====
  function bindStaticChips() {
    document.querySelectorAll('#transFilters .filter-chip, #fuelFilters .filter-chip').forEach(chip => {
      chip.addEventListener('click', function() {
        const container = this.closest('.filter-options');
        if (container) {
          container.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
          this.classList.add('active');

          // Update filter state
          const containerId = container.id;
          const val = this.dataset.value;
          if (containerId === 'transFilters') filterState.transmission = val;
          else if (containerId === 'fuelFilters') filterState.fuel = val;
        }
      });
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
      priceLabel.textContent = val >= max ? '$0 – $500+' : '$0 – $' + val;
    }
  }

  // ===== LOAD DYNAMIC FILTER OPTIONS FROM DB =====
  async function loadFilterOptions() {
    try {
      const res = await fetch(VEHICLES_API + '?action=filter-options');
      const data = await res.json();

      if (data.success) {
        // Render brand chips
        const brandContainer = document.getElementById('brandFilters');
        if (brandContainer) {
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
        }

        // Render category chips
        const catContainer = document.getElementById('categoryFilters');
        if (catContainer) {
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
    if (filterState.transmission) params.set('transmission', filterState.transmission);
    if (filterState.fuel) params.set('fuel', filterState.fuel);
    if (filterState.max_price < 500) params.set('max_price', filterState.max_price);
    if (filterState.category) params.set('category', filterState.category);
    window.location.href = 'cars.php?' + params.toString();
  }

  function resetFilters() {
    window.location.href = 'cars.php';
  }

  // ===== LOAD CARS FROM API =====
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
    if (filterState.category) activeFilters.push(filterState.category);
    const filterSuffix = activeFilters.length > 0 ? ' for ' + activeFilters.join(', ') : '';

    if (countText) {
      countText.textContent = cars.length + ' car' + (cars.length !== 1 ? 's' : '') + ' found' + filterSuffix;
    }

    grid.innerHTML = cars.map(car => {
      const images = car.images || [];
      const hasValidImage = images.length > 0 && images[0] && (images[0].startsWith('http') || images[0].startsWith('/api/'));
      const imageHTML = hasValidImage
        ? '<img src="' + images[0] + '" alt="' + escapeHtml(car.brand + ' ' + car.model) + '" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><div class="no-image-placeholder" style="display:none;">No Photo</div>'
        : '<div class="no-image-placeholder">No Photo</div>';

      const fuelIcon = car.fuel_type === 'electric' ? '⚡' : '⛽';
      const features = (car.features || []).slice(0, 3).map(f => '<span class="car-feature">✓ ' + escapeHtml(f) + '</span>').join('');
      const rating = parseFloat(car.avg_rating) || 0;
      const stars = '★'.repeat(Math.round(rating)) + '☆'.repeat(5 - Math.round(rating));

      // Vehicle status badge
      const isAvailable = (car.status || 'available') === 'available';
      const statusBadge = isAvailable
        ? '<span class="car-status-badge available">✓ Available</span>'
        : '<span class="car-status-badge rented">🔒 Rented</span>';

      return '<div class="car-card' + (isAvailable ? '' : ' car-rented') + '" onclick="handleCarClick(\'' + car.id + '\')">' +
        '<div class="car-card-image">' +
          imageHTML +
          statusBadge +
          '<button class="car-card-favorite" onclick="event.stopPropagation();toggleFavorite(this)">🤍</button>' +
        '</div>' +
        '<div class="car-card-body">' +
          '<h3 class="car-card-title">' + escapeHtml(car.brand + ' ' + car.model + ' ' + car.year) + '</h3>' +
          '<p class="car-card-subtitle">' + escapeHtml(car.category + ' • ' + car.transmission + ' • ' + car.fuel_type) + '</p>' +
          '<div class="car-card-features">' +
            '<span class="car-feature">👤 ' + car.seats + ' seats</span>' +
            '<span class="car-feature">' + fuelIcon + ' ' + escapeHtml(car.consumption || 'N/A') + '</span>' +
            features +
          '</div>' +
          '<div class="car-card-footer">' +
            '<div class="car-card-price">' +
              '<span class="car-price-amount">$' + Number(car.price_per_day).toLocaleString() + '</span>' +
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
    if (!CAN_VIEW_VEHICLE_DETAIL) {
      bookCar(carId);
      return;
    }
    openCarDetail(carId);
  }

  // ===== CAR DETAIL MODAL =====
  async function openCarDetail(carId) {
    if (!CAN_VIEW_VEHICLE_DETAIL) {
      return;
    }

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
    if (detailTitle) detailTitle.textContent = car.brand + ' ' + car.model + ' ' + car.year;
    if (detailSub) detailSub.textContent = ucfirst(car.category) + ' • ' + ucfirst(car.transmission) + ' • ' + ucfirst(car.fuel_type) + (car.license_plate ? ' • ' + car.license_plate : '');

    // Price
    const detailPrice = document.getElementById('detailPrice');
    if (detailPrice) detailPrice.textContent = '$' + Number(car.price_per_day).toLocaleString();

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

    // Image gallery
    const images = car.images || [];
    const mainImg = document.getElementById('detailMainImage');
    const thumbsEl = document.getElementById('detailThumbs');

    if (mainImg) {
      if (images.length > 0 && images[0] && (images[0].startsWith('http') || images[0].startsWith('/api/'))) {
        mainImg.innerHTML = '<img src="' + images[0] + '" alt="' + escapeHtml(car.brand + ' ' + car.model) + '" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML=\'<span style=color:var(--gray-400);font-size:0.875rem>No Photo Available</span>\'">';
        if (thumbsEl && images.length > 1) {
          thumbsEl.innerHTML = images.filter(img => img && (img.startsWith('http') || img.startsWith('/api/'))).map((img, i) => 
            '<div class="detail-thumb ' + (i === 0 ? 'active' : '') + '" onclick="switchDetailImage(\'' + img + '\', this)">' +
            '<img src="' + img + '" alt="Photo ' + (i+1) + '"></div>'
          ).join('');
        } else if (thumbsEl) {
          thumbsEl.innerHTML = '';
        }
      } else {
        mainImg.innerHTML = '<span style="color:var(--gray-400);font-size:0.875rem;">No Photo Available</span>';
        if (thumbsEl) thumbsEl.innerHTML = '';
      }
    }

    // Specs grid
    const detailSpecs = document.getElementById('detailSpecs');
    if (detailSpecs) {
      const fuelIcon = car.fuel_type === 'electric' ? '🔋' : '⛽';
      detailSpecs.innerHTML = 
        specItem('👤', 'Seats', car.seats) +
        specItem('⚙️', 'Transmission', ucfirst(car.transmission)) +
        specItem(fuelIcon, 'Fuel Type', ucfirst(car.fuel_type)) +
        specItem('📏', 'Engine', car.engine_size || 'N/A') +
        specItem('📊', 'Consumption', car.consumption || 'N/A') +
        specItem('🎨', 'Color', ucfirst(car.color || 'N/A')) +
        specItem('📅', 'Year', car.year) +
        specItem('📋', 'Bookings', car.total_bookings || 0);
    }

    // Features
    const features = car.features || [];
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

    // Owner
    const ownerName = car.owner_name || 'Unknown Owner';
    const initials = ownerName.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
    const detailOwnerAvatar = document.getElementById('detailOwnerAvatar');
    const detailOwnerName = document.getElementById('detailOwnerName');
    if (detailOwnerAvatar) detailOwnerAvatar.textContent = initials;
    if (detailOwnerName) detailOwnerName.textContent = ownerName;

    // Price breakdown
    const detailDailyRate = document.getElementById('detailDailyRate');
    if (detailDailyRate) detailDailyRate.textContent = '$' + Number(car.price_per_day).toLocaleString() + '/day';

    const weeklyRow = document.getElementById('detailWeeklyRow');
    const detailWeeklyRate = document.getElementById('detailWeeklyRate');
    if (weeklyRow && detailWeeklyRate) {
      if (car.price_per_week && parseFloat(car.price_per_week) > 0) {
        weeklyRow.style.display = 'flex';
        detailWeeklyRate.textContent = '$' + Number(car.price_per_week).toLocaleString() + '/week';
      } else {
        weeklyRow.style.display = 'none';
      }
    }

    const monthlyRow = document.getElementById('detailMonthlyRow');
    const detailMonthlyRate = document.getElementById('detailMonthlyRate');
    if (monthlyRow && detailMonthlyRate) {
      if (car.price_per_month && parseFloat(car.price_per_month) > 0) {
        monthlyRow.style.display = 'flex';
        detailMonthlyRate.textContent = '$' + Number(car.price_per_month).toLocaleString() + '/month';
      } else {
        monthlyRow.style.display = 'none';
      }
    }

    // Book button
    const bookBtn = document.getElementById('detailBookBtn');
    const statusNotice = document.getElementById('detailStatusNotice');
    if (bookBtn && statusNotice) {
      bookBtn.setAttribute('onclick', "bookCar('" + car.id + "')");
      if ((car.status || 'available') === 'available') {
        bookBtn.disabled = false;
        bookBtn.style.opacity = '1';
        bookBtn.textContent = '📋 Book This Car';
        statusNotice.style.display = 'none';
      } else {
        bookBtn.disabled = true;
        bookBtn.style.opacity = '0.5';
        bookBtn.textContent = '🔒 Currently Rented';
        statusNotice.style.display = 'block';
      }
    }

    // Open modal
    const modal = document.getElementById('carDetailModal');
    if (modal) modal.classList.add('open');
  }

  function switchDetailImage(src, thumbEl) {
    const detailMainImage = document.getElementById('detailMainImage');
    if (detailMainImage) {
      detailMainImage.innerHTML = '<img src="' + src + '" style="width:100%;height:100%;object-fit:cover;">';
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
    if (!isLoggedIn) {
      if (typeof showToast === 'function') showToast('Please sign in to book a car.', 'warning');
      setTimeout(() => {
        window.location.href = 'login.php?redirect=booking.php&car_id=' + encodeURIComponent(carId);
      }, 1000);
      return;
    }
    // Check if car is available
    const car = allLoadedCars.find(c => c.id === carId);
    if (car && car.status !== 'available') {
      if (typeof showToast === 'function') showToast('This vehicle is currently rented and not available for booking.', 'warning');
      return;
    }
    window.location.href = 'booking.php?car_id=' + encodeURIComponent(carId);
  }

  function toggleFavorite(btn) {
    btn.classList.toggle('active');
    btn.textContent = btn.classList.contains('active') ? '❤️' : '🤍';
    if (typeof showToast === 'function') {
      showToast(btn.classList.contains('active') ? 'Added to favorites!' : 'Removed from favorites.', 'success');
    }
  }

  function escapeHtml(text) {
    if (!text) return '';
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
  }

  // ===== INITIALIZATION =====
  document.addEventListener('DOMContentLoaded', function() {
    initFromURL();
    bindStaticChips();
    bindPriceRange();
    bindSearchFunctionality();
    loadCars();
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
