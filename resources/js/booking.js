/**
 * Booking Module
 * Handles minicab booking flow (schedule only)
 * Features: step navigation, date pickers, location selection with maps, payment processing, promo codes
 */

(function initBookingModule() {
  // ===== CONFIGURATION =====
  const VEHICLES_API = '/api/vehicles.php';
  const BOOKINGS_API = '/api/bookings.php';
  const CAR_ID = window.CAR_ID || '';
  const INITIAL_PROMO = window.INITIAL_PROMO || '';
  const BOOKING_MODE = window.BOOKING_MODE || '';
  const isLoggedIn = window.isLoggedIn || false;

  // ===== STATE =====
  let carData = null;
  let selectedBookingType = 'minicab';
  let selectedPaymentMethod = 'cash';
  let appliedPromo = null;
  let pickupMapObj = null, returnMapObj = null;
  let pickupMarker = null, returnMarker = null;
  let selectedRideTier = null; // 'eco', 'standard', 'luxury'
  let autoAssignedRideTier = false;
  let selectedSeatCapacity = 4;
  let rideFare = null;
  let lockedBookingType = null;

  // Location tracking
  let autocompleteTimers = {};
  let selectedAddresses = { pickup: null, return: null };
  let calculatedDistance = null;
  let transferCost = null;

  const ONLINE_RATE_TABLE = {
    4: { eco: 2.00, standard: 2.50, luxury: 3.50 },
    7: { eco: 3.00, standard: 3.50, luxury: 4.50 }
  };

  // Ride tier configuration for UI
  const RIDE_TIER_CONFIG = {
    'eco': {
      name: 'Eco',
      icon: '🌿',
      color: '#10b981',
      badge: 'Best Value',
      descriptions: {
        4: 'Affordable city rides with essential comfort.',
        7: 'Budget-friendly option for larger groups.'
      }
    },
    'standard': {
      name: 'Standard',
      icon: '⭐',
      color: '#0f766e',
      badge: 'Popular',
      descriptions: {
        4: 'Balanced comfort, luggage room, and smoother ride.',
        7: 'Family-ready cabin for medium-size groups.'
      }
    },
    'luxury': {
      name: 'Luxury',
      icon: '👑',
      color: '#f59e0b',
      badge: 'Premium',
      descriptions: {
        4: 'Executive quality with premium interior and quiet ride.',
        7: 'High-end MPV class for premium group travel.'
      }
    }
  };

  let availableTiersByPassengers = {};  // Track which tiers are available per passenger count

  function getOnlineRatePerMile(tier, seatCapacity) {
    const seatRates = ONLINE_RATE_TABLE[seatCapacity] || ONLINE_RATE_TABLE[4];
    return seatRates[tier] || 0;
  }

  function getMinicabSeatLogo(seatCapacity) {
    return seatCapacity === 7
      ? '/resources/images/logo/SUV.png'
      : '/resources/images/logo/sedan.png';
  }

  function requiresManualRideTierSelection() {
    return selectedBookingType === 'minicab';
  }

  function updateRideTierVisibility() {
    const rideTierGroup = document.getElementById('rideTierGroup');
    if (!rideTierGroup) return;

    if (selectedBookingType !== 'minicab') {
      rideTierGroup.style.display = 'none';
      autoAssignedRideTier = false;
      return;
    }

    const shouldShowTier = requiresManualRideTierSelection();
    rideTierGroup.style.display = shouldShowTier ? 'block' : 'none';

    if (shouldShowTier) {
      if (autoAssignedRideTier) {
        selectedRideTier = null;
        rideFare = null;
      }
      autoAssignedRideTier = false;
      return;
    }

    selectedRideTier = 'standard';
    autoAssignedRideTier = true;
  }

  function updateSeatCapacityInfo() {
    const info = document.getElementById('tierRecommendationText');
    if (info) {
      info.textContent = 'Selected fare class: ' + selectedSeatCapacity + '-seat vehicle. Rates update automatically for each tier.';
    }
  }

  // ===== INITIALIZATION =====
  document.addEventListener('DOMContentLoaded', async function() {
    const paypalHandled = await processPayPalCallback();
    if (paypalHandled) {
      return;
    }

    const today = new Date().toISOString().split('T')[0];
    const pickupDateEl = document.getElementById('pickupDate');
    const returnDateEl = document.getElementById('returnDate');
    
    if (pickupDateEl) {
      pickupDateEl.min = today;
      pickupDateEl.addEventListener('change', function() {
        const pickup = this.value;
        if (pickup && returnDateEl) {
          returnDateEl.min = pickup;
          if (returnDateEl.value && returnDateEl.value < pickup) {
            returnDateEl.value = '';
          }
        }
        updateTripSummary();
      });
    }
    
    if (returnDateEl) {
      returnDateEl.min = today;
      returnDateEl.addEventListener('change', updateTripSummary);
    }

    const scheduledDTEl = document.getElementById('scheduledDateTime');
    if (scheduledDTEl) {
      scheduledDTEl.addEventListener('change', function() {
        validatePickupDateTime();
        updateTripSummary();
      });
    }

    // Single workflow: minicab + schedule only
    lockedBookingType = 'minicab';
    const typeGroup = document.getElementById('bookingTypeGroup');
    if (typeGroup) typeGroup.style.display = 'none';
    const loadingEl = document.getElementById('bookingLoading');
    if (loadingEl) loadingEl.style.display = 'none';
    const step1El = document.getElementById('step1Content');
    if (step1El) step1El.style.display = 'block';
    selectBookingType('minicab');

    loadSavedPromos();
    if (INITIAL_PROMO) {
      const promoInput = document.getElementById('promoCodeInput');
      if (promoInput) promoInput.value = INITIAL_PROMO;
    }
    initLeafletAutocomplete();
  });

  async function processPayPalCallback() {
    const params = new URLSearchParams(window.location.search);
    const paypalState = (params.get('paypal') || '').toLowerCase();
    if (!paypalState) return false;

    const orderId = params.get('token') || '';
    const payerId = params.get('PayerID') || '';

    if (paypalState === 'cancel') {
      if (orderId) {
        try {
          await fetch(BOOKINGS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'paypal-cancel', order_id: orderId })
          });
        } catch (err) {
          console.error('PayPal cancel notify failed:', err);
        }
      }

      if (typeof showToast === 'function') {
        showToast('PayPal payment was cancelled. You can choose another payment method.', 'warning');
      }
      clearPayPalQueryParams();
      return false;
    }

    if (paypalState === 'return' && orderId) {
      try {
        const res = await fetch(BOOKINGS_API, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'paypal-capture',
            order_id: orderId,
            payer_id: payerId,
          })
        });
        const data = await res.json();

        if (data.success) {
          if (typeof showToast === 'function') {
            showToast('✅ PayPal payment captured successfully.', 'success');
          }

          clearPayPalQueryParams();
          showBookingSuccess(data.booking || {
            booking_type: 'minicab',
            total_days: 1,
            subtotal: 0,
            discount: 0,
            total: 0,
            promo_applied: '',
            payment_method: 'paypal',
            distance_km: null,
            ride_tier: null,
          });

          const successMessage = document.getElementById('successMessage');
          if (successMessage) {
            successMessage.textContent = 'Your PayPal payment has been captured successfully. You can track your trip status in My Orders.';
          }

          return true;
        } else if (typeof showToast === 'function') {
          showToast('❌ ' + (data.message || 'PayPal capture failed.'), 'error');
        }
      } catch (err) {
        console.error('PayPal capture error:', err);
        if (typeof showToast === 'function') {
          showToast('❌ Failed to verify PayPal payment. Please contact support.', 'error');
        }
      }

      clearPayPalQueryParams();
      return false;
    }

    return false;
  }

  function clearPayPalQueryParams() {
    const params = new URLSearchParams(window.location.search);
    params.delete('paypal');
    params.delete('token');
    params.delete('PayerID');

    const qs = params.toString();
    const newUrl = window.location.pathname + (qs ? ('?' + qs) : '');
    window.history.replaceState({}, '', newUrl);
  }

  // ===== REAL-TIME DATETIME VALIDATION =====
  function validatePickupDateTime() {
    const scheduledDTEl = document.getElementById('scheduledDateTime');
    const errorEl = document.getElementById('pickupDateTimeError');
    
    // Get datetime value - prefer scheduledDateTime, but also check pickupDate
    let datetimeValue = '';
    if (scheduledDTEl && scheduledDTEl.value) {
      datetimeValue = scheduledDTEl.value;
    }
    
    // If no value in scheduledDateTime, it shouldn't happen on step 1, but might happen on step 2
    // In that case, just allow (it was already validated on step 1)
    if (!datetimeValue) {
      if (errorEl) errorEl.style.display = 'none';
      return true; // Allow empty on step 2 (already validated on step 1)
    }

    // Validate it's not in the past
    // Parse datetime-local value as LOCAL time (NOT UTC)
    // datetime-local format: "2026-03-23T14:32" must be parsed as local time, not UTC
    try {
      const [datePart, timePart] = datetimeValue.split('T');
      const [year, month, day] = datePart.split('-');
      const [hours, minutes] = timePart.split(':');
      const pickupTime = new Date(parseInt(year), parseInt(month) - 1, parseInt(day), parseInt(hours), parseInt(minutes));
      
      const now = new Date();
      const minAllowedTime = new Date(now.getTime() + 60 * 1000); // At least 1 minute in future

      if (pickupTime < minAllowedTime) {
        if (errorEl) {
          errorEl.style.display = 'block';
          errorEl.innerHTML = '❌ <strong>Pickup time must be at least 1 minute in the future.</strong>';
        }
        return false;
      }
    } catch (e) {
      if (errorEl) {
        errorEl.style.display = 'block';
        errorEl.innerHTML = '❌ <strong>Invalid date/time format.</strong>';
      }
      return false;
    }

    if (errorEl) errorEl.style.display = 'none';
    return true;
  }

  // ===== RENDER CAR INFO =====
  function renderCarInfo() {
    if (!carData) return;
    const c = carData;
    
    const titleEl = document.getElementById('bookingCarTitle');
    if (titleEl) titleEl.textContent = c.brand + ' ' + c.model;
    
    const subEl = document.getElementById('bookingCarSub');
    if (subEl) subEl.textContent = c.year + ' · ' + ucfirst(c.category) + ' · ' + ucfirst(c.transmission);

    const imgContainer = document.getElementById('bookingCarImage');
    if (imgContainer) {
      if (c.images && c.images.length > 0) {
        const imgUrl = c.images[0];
        if (imgUrl && (imgUrl.startsWith('http') || imgUrl.startsWith('/api/'))) {
          imgContainer.innerHTML = '<img src="' + imgUrl + '" alt="' + escapeHtml(c.brand + ' ' + c.model) + '" onerror="this.parentElement.innerHTML=\'<div class=no-image-placeholder style=height:100%;display:flex;align-items:center;justify-content:center;color:var(--gray-400)>No Photo</div>\'">';
        } else {
          imgContainer.innerHTML = '<div class="no-image-placeholder" style="height:100%;display:flex;align-items:center;justify-content:center;color:var(--gray-400);">No Photo</div>';
        }
      } else {
        imgContainer.innerHTML = '<div class="no-image-placeholder" style="height:100%;display:flex;align-items:center;justify-content:center;color:var(--gray-400);">No Photo</div>';
      }
    }

    const specs = [c.seats + ' Seats', ucfirst(c.fuel_type), c.engine_size || '', c.consumption || '', ucfirst(c.color || ''), c.location_city || ''].filter(s => s);
    const specsEl = document.getElementById('bookingCarSpecs');
    if (specsEl) {
      specsEl.innerHTML = specs.slice(0, 6).map(s => '<div class="spec-chip">' + escapeHtml(s) + '</div>').join('');
    }
    
    const priceEl = document.getElementById('bookingCarPrice');
    if (priceEl) priceEl.textContent = '£0.00'; // Price based on tier/distance
  }

  function ucfirst(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
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

  // ===== BOOKING TYPE =====
  function selectBookingType(type) {
    selectedBookingType = type;
    selectedRideTier = null;
    rideFare = null;
    
    document.querySelectorAll('.booking-type-option[data-type]').forEach(el => {
      el.classList.toggle('active', el.dataset.type === type);
    });

    const isMinicab = type === 'minicab';
    const isWithDriver = type === 'with-driver';

    // Date fields
    const returnDateGroup = document.getElementById('returnDateGroup');
    const pickupDateGroup = document.getElementById('pickupDateGroup');
    if (returnDateGroup) returnDateGroup.style.display = isMinicab ? 'none' : '';
    if (pickupDateGroup) pickupDateGroup.style.display = isMinicab ? 'none' : '';
    
    const scheduledDTGroup = document.getElementById('scheduledDateTimeGroup');
    if (scheduledDTGroup) scheduledDTGroup.style.display = 'none';
    
    if (isMinicab) {
      const pickupDateGroup = document.getElementById('pickupDateGroup');
      const returnDateGroup = document.getElementById('returnDateGroup');
      const scheduledDateTimeGroup = document.getElementById('scheduledDateTimeGroup');

      if (pickupDateGroup) pickupDateGroup.style.display = 'none';
      if (returnDateGroup) returnDateGroup.style.display = 'none';
      if (scheduledDateTimeGroup) scheduledDateTimeGroup.style.display = 'block';

      const now = new Date();
      const year = now.getFullYear();
      const month = String(now.getMonth() + 1).padStart(2, '0');
      const day = String(now.getDate()).padStart(2, '0');
      const hours = String(now.getHours()).padStart(2, '0');
      const minutes = String(now.getMinutes()).padStart(2, '0');
      const minDT = `${year}-${month}-${day}T${hours}:${minutes}`;

      const scheduledDateTime = document.getElementById('scheduledDateTime');
      if (scheduledDateTime) scheduledDateTime.min = minDT;
    }

    // Pickup label
    const pickupLocationLabel = document.getElementById('pickupLocationLabel');
    const pickupLocation = document.getElementById('pickupLocation');
    if (pickupLocationLabel) {
      pickupLocationLabel.textContent = isMinicab ? 'Pick-up Location' : 'Vehicle Pick-up Location';
    }
    if (pickupLocation) {
      pickupLocation.placeholder = isMinicab ? 'Where should we pick you up?' : 'Where do you want to pick up the car?';
    }

    // Destination location
    const returnLocationGroup = document.getElementById('returnLocationGroup');
    if (returnLocationGroup) returnLocationGroup.style.display = isMinicab ? 'block' : 'none';
    
    if (isMinicab) {
      const returnLocationLabel = document.getElementById('returnLocationLabel');
      const returnLocation = document.getElementById('returnLocation');
      if (returnLocationLabel) returnLocationLabel.textContent = 'Destination';
      if (returnLocation) returnLocation.placeholder = 'Where do you want to go?';
    }

    // Service type — only for minicab
    const serviceTypeGroup = document.getElementById('serviceTypeGroup');
    if (serviceTypeGroup) serviceTypeGroup.style.display = isMinicab ? 'block' : 'none';

    // Reset airport selector state
    if (isMinicab) {
      onServiceTypeChange();
    } else {
      const returnLocationInputWrapper = document.getElementById('returnLocationInputWrapper');
      const airportSelectWrapper = document.getElementById('airportSelectWrapper');
      const hotelSelectWrapper = document.getElementById('hotelSelectWrapper');
      if (returnLocationInputWrapper) returnLocationInputWrapper.style.display = 'flex';
      if (airportSelectWrapper) airportSelectWrapper.style.display = 'none';
      if (hotelSelectWrapper) hotelSelectWrapper.style.display = 'none';
    }

    // Ride tier selection — only for minicab
    updateRideTierVisibility();

    // Seat capacity — only for minicab
    const seatCapacityGroup = document.getElementById('seatCapacityGroup');
    if (seatCapacityGroup) seatCapacityGroup.style.display = isMinicab ? 'block' : 'none';
    
    if (isMinicab) {
      updateSeatCapacityInfo();
      renderRideTierOptions();
    }

    // Car card visibility
    const carCard = document.querySelector('#step1Content .booking-car-card');
    const bookingGrid = document.querySelector('#step1Content .booking-grid');
    if (isMinicab) {
      const lockToMinicabLayout = BOOKING_MODE === 'minicab';
      if (carCard) carCard.style.display = lockToMinicabLayout ? '' : 'none';
      if (bookingGrid) bookingGrid.style.gridTemplateColumns = lockToMinicabLayout ? '' : '1fr';
    } else {
      if (carCard && carData) carCard.style.display = '';
      if (bookingGrid && carData) bookingGrid.style.gridTemplateColumns = '380px 1fr';
    }

    // Clear return location when switching to with-driver
    if (isWithDriver) {
      const returnLocation = document.getElementById('returnLocation');
      if (returnLocation) returnLocation.value = '';
    }

    calculateRouteDistance();
    updateTripSummary();
  }

  // ===== SERVICE TYPE CHANGE =====
  function onServiceTypeChange() {
    const serviceType = document.getElementById('serviceType');
    if (!serviceType) return;

    syncServiceTypeCards(serviceType.value);
    
    const isAirport = serviceType.value === 'airport-transfer';
    const isHotel = serviceType.value === 'hotel-transfer';
    const returnInputWrapper = document.getElementById('returnLocationInputWrapper');
    const airportSelectWrapper = document.getElementById('airportSelectWrapper');
    const hotelSelectWrapper = document.getElementById('hotelSelectWrapper');

    if (isAirport) {
      if (returnInputWrapper) returnInputWrapper.style.display = 'none';
      if (airportSelectWrapper) airportSelectWrapper.style.display = 'block';
      if (hotelSelectWrapper) hotelSelectWrapper.style.display = 'none';
      const returnLocationLabel = document.getElementById('returnLocationLabel');
      if (returnLocationLabel) returnLocationLabel.textContent = 'Select Airport';
      const returnLocation = document.getElementById('returnLocation');
      if (returnLocation) returnLocation.value = '';
      selectedAddresses['return'] = null;
    } else if (isHotel) {
      if (returnInputWrapper) returnInputWrapper.style.display = 'none';
      if (airportSelectWrapper) airportSelectWrapper.style.display = 'none';
      if (hotelSelectWrapper) hotelSelectWrapper.style.display = 'block';
      const returnLocationLabel = document.getElementById('returnLocationLabel');
      if (returnLocationLabel) returnLocationLabel.textContent = 'Select Hotel';
      const returnLocation = document.getElementById('returnLocation');
      if (returnLocation) returnLocation.value = '';
      selectedAddresses['return'] = null;
    } else {
      if (returnInputWrapper) returnInputWrapper.style.display = 'flex';
      if (airportSelectWrapper) airportSelectWrapper.style.display = 'none';
      if (hotelSelectWrapper) hotelSelectWrapper.style.display = 'none';
      const returnLocationLabel = document.getElementById('returnLocationLabel');
      if (returnLocationLabel) returnLocationLabel.textContent = 'Destination';
      const returnLocation = document.getElementById('returnLocation');
      if (returnLocation) returnLocation.placeholder = 'Where do you want to go?';
    }

    updateRideTierVisibility();

    calculateRouteDistance();
    updateTripSummary();
  }

  function syncServiceTypeCards(activeType) {
    document.querySelectorAll('.service-purpose-card').forEach(card => {
      card.classList.toggle('active', card.dataset.service === activeType);
    });
  }

  function selectServiceTypeCard(type) {
    const serviceType = document.getElementById('serviceType');
    if (!serviceType) return;
    serviceType.value = type;
    onServiceTypeChange();
  }

  // ===== AIRPORT SELECT =====
  function onAirportSelect() {
    const airportSelect = document.getElementById('airportSelect');
    if (!airportSelect) return;
    
    const selected = airportSelect.value;
    const returnLocation = document.getElementById('returnLocation');
    
    if (!selected) {
      if (returnLocation) returnLocation.value = '';
      selectedAddresses['return'] = null;
      updateRideTierVisibility();
      calculateRouteDistance();
      return;
    }
    
    if (returnLocation) returnLocation.value = selected;
    updateRideTierVisibility();

    const selectedOption = airportSelect.options[airportSelect.selectedIndex];
    const optionLat = selectedOption ? Number(selectedOption.dataset.lat) : NaN;
    const optionLon = selectedOption ? Number(selectedOption.dataset.lon) : NaN;
    if (!Number.isNaN(optionLat) && !Number.isNaN(optionLon)) {
      selectedAddresses['return'] = { lat: optionLat, lon: optionLon, name: selected };
      calculateRouteDistance();
      updateTripSummary();
      return;
    }

    searchDestinationLocation(selected, 'Airport geocode error:');
  }

  function onHotelSelect() {
    const hotelSelect = document.getElementById('hotelSelect');
    if (!hotelSelect) return;

    const selected = hotelSelect.value;
    const returnLocation = document.getElementById('returnLocation');

    if (!selected) {
      if (returnLocation) returnLocation.value = '';
      selectedAddresses['return'] = null;
      updateRideTierVisibility();
      calculateRouteDistance();
      return;
    }

    if (returnLocation) returnLocation.value = selected;
    updateRideTierVisibility();

    const selectedOption = hotelSelect.options[hotelSelect.selectedIndex];
    const optionLat = selectedOption ? Number(selectedOption.dataset.lat) : NaN;
    const optionLon = selectedOption ? Number(selectedOption.dataset.lon) : NaN;
    if (!Number.isNaN(optionLat) && !Number.isNaN(optionLon)) {
      selectedAddresses['return'] = { lat: optionLat, lon: optionLon, name: selected };
      calculateRouteDistance();
      updateTripSummary();
      return;
    }

    searchDestinationLocation(selected, 'Hotel geocode error:');
  }

  async function searchDestinationLocation(destinationName, errorPrefix) {
    try {
      const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(destinationName) + '&limit=1&countrycodes=gb', {
        headers: { 'Accept-Language': 'en' }
      });
      const results = await res.json();
      if (results.length > 0) {
        const r = results[0];
        selectedAddresses['return'] = { lat: parseFloat(r.lat), lon: parseFloat(r.lon), name: destinationName };
        calculateRouteDistance();
        updateTripSummary();
      } else {
        selectedAddresses['return'] = null;
        calculateRouteDistance();
      }
    } catch (err) {
      console.error(errorPrefix, err);
    }
  }

  // ===== TIER RECOMMENDATION =====
  function updateTierRecommendation() {
    updateSeatCapacityInfo();
    checkAvailableTiers(selectedSeatCapacity);
  }

  function selectSeatCapacity(capacity) {
    const next = Number(capacity);
    if (next !== 4 && next !== 7) return;

    selectedSeatCapacity = next;
    document.querySelectorAll('.seat-capacity-option').forEach(el => {
      el.classList.toggle('active', Number(el.getAttribute('data-seat')) === selectedSeatCapacity);
    });

    renderRideTierOptions();
    updateSeatCapacityInfo();
    updateTripSummary();
  }

  // ===== CHECK AVAILABLE TIERS =====
  async function checkAvailableTiers(passengers = 1) {
    try {
      const response = await fetch('/api/vehicles.php?action=check-available-tiers', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ passengers: passengers })
      });

      const result = await response.json();
      
      if (result.success) {
        availableTiersByPassengers = result.available_tiers || {};
      }

      // Update ride tier UI based on availability and passenger count
      updateRideTierUI(passengers, availableTiersByPassengers);
    } catch (err) {
      console.error('Error checking available tiers:', err);
    }
  }

  // ===== UPDATE RIDE TIER UI =====
  function updateRideTierUI(passengers, availableTiers) {
    const tierCards = document.querySelectorAll('.ride-tier-card');
    
    tierCards.forEach(card => {
      const tierType = card.getAttribute('data-tier');
      const tierConfig = RIDE_TIER_CONFIG[tierType];
      
      if (!tierConfig) return;

      let isDisabled = false;
      let disableReason = '';

      // Check: No vehicles available for this tier
      if (!availableTiers[tierType]) {
        isDisabled = true;
        disableReason = 'No vehicles available';
      }

      // Update card UI
      if (isDisabled) {
        card.style.opacity = '0.5';
        card.style.pointerEvents = 'none';
        card.style.cursor = 'not-allowed';
        card.setAttribute('data-disabled', 'true');
        
        card.title = disableReason;

        // Add disabled indicator
        let disabledLabel = card.querySelector('.tier-disabled-label');
        if (!disabledLabel) {
          disabledLabel = document.createElement('div');
          disabledLabel.className = 'tier-disabled-label';
          disabledLabel.style.cssText = `
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.7); color: white; padding: 8px 12px;
            border-radius: 6px; font-size: 0.75rem; font-weight: 600; z-index: 10;
            white-space: nowrap;
          `;
          card.style.position = 'relative';
          card.appendChild(disabledLabel);
        }
        disabledLabel.textContent = disableReason;

        // Deselect if this tier was selected
        if (selectedRideTier === tierType) {
          selectedRideTier = null;
          rideFare = null;
          card.classList.remove('active');
        }
      } else {
        card.style.opacity = '1';
        card.style.pointerEvents = 'auto';
        card.style.cursor = 'pointer';
        card.removeAttribute('data-disabled');
        
        let disabledLabel = card.querySelector('.tier-disabled-label');
        if (disabledLabel) {
          disabledLabel.remove();
        }
      }
    });
  }

  // ===== TRIP SUMMARY =====
  function updateTripSummary() {
    const summaryDiv = document.getElementById('tripSummary');
    const isMinicab = selectedBookingType === 'minicab';
    if (!summaryDiv && !isMinicab) return;
    
    const pickup = document.getElementById('pickupDate');
    const ret = document.getElementById('returnDate');
    const pickupVal = pickup ? pickup.value : '';
    const retVal = ret ? ret.value : '';

    const summaryTierRow = document.getElementById('summaryTierRow');
    const summaryFareRow = document.getElementById('summaryFareRow');
    if (summaryTierRow) summaryTierRow.style.display = 'none';
    if (summaryFareRow) summaryFareRow.style.display = 'none';

    if (isMinicab) {
      const serviceTypeEl = document.getElementById('serviceType');
      const serviceLabels = {
        'local': 'Local Journey',
        'long-distance': 'Long Journey',
        'airport-transfer': 'Airport Transfer',
        'hotel-transfer': 'Hotel Transfer'
      };
      const activeService = serviceTypeEl ? serviceTypeEl.value : 'local';
      const miniService = document.getElementById('miniSummaryService');
      if (miniService) miniService.textContent = serviceLabels[activeService] || 'Local Journey';

      if (summaryDiv) summaryDiv.style.display = 'none';

      const hasPickupTime = !!document.getElementById('scheduledDateTime').value;
      if (hasPickupTime) {
        if (summaryDiv) summaryDiv.style.display = 'block';
        const summaryDurationRow = document.getElementById('summaryDurationRow');
        const summaryRateRow = document.getElementById('summaryRateRow');
        if (summaryDurationRow) summaryDurationRow.style.display = 'none';
        if (summaryRateRow) summaryRateRow.style.display = 'none';

        if (selectedRideTier && calculatedDistance !== null) {
          const tierLabels = { eco: '🌿 Eco', standard: '⭐ Standard', luxury: '👑 Luxury' };
          const rate = getOnlineRatePerMile(selectedRideTier, selectedSeatCapacity);
          
          // Convert distance from km to miles (1 km = 0.621371 miles)
          const distanceMiles = calculatedDistance * 0.621371;
          rideFare = Math.round(distanceMiles * rate * 100) / 100;

          const summaryTier = document.getElementById('summaryTier');
          if (summaryTierRow) summaryTierRow.style.display = '';
          if (summaryTier) summaryTier.textContent = (tierLabels[selectedRideTier] || '') + ' · ' + selectedSeatCapacity + ' seats (£' + rate.toFixed(2) + '/mile)';
          
          if (summaryFareRow) summaryFareRow.style.display = '';
          const summaryFare = document.getElementById('summaryFare');
          if (summaryFare) summaryFare.textContent = '£' + rideFare.toFixed(2);

          const miniTier = document.getElementById('miniSummaryTier');
          if (miniTier) miniTier.textContent = tierLabels[selectedRideTier] || 'Select tier';
          const miniFare = document.getElementById('miniSummaryFare');
          if (miniFare) miniFare.textContent = '£' + rideFare.toFixed(2);
          const miniDistance = document.getElementById('miniSummaryDistance');
          if (miniDistance) miniDistance.textContent = distanceMiles.toFixed(1) + ' miles';
          
          const summaryTotal = document.getElementById('summaryTotal');
          if (summaryTotal) summaryTotal.textContent = '£' + rideFare.toFixed(2);
        } else {
          rideFare = null;
          const summaryTotal = document.getElementById('summaryTotal');
          if (summaryTotal) {
            summaryTotal.textContent = calculatedDistance !== null ? 'Select a ride tier' : 'Set locations first';
          }
          const miniTier = document.getElementById('miniSummaryTier');
          if (miniTier) miniTier.textContent = 'Select tier';
          const miniFare = document.getElementById('miniSummaryFare');
          if (miniFare) miniFare.textContent = calculatedDistance !== null ? 'Select tier' : 'Set locations first';
          const miniDistance = document.getElementById('miniSummaryDistance');
          if (miniDistance) miniDistance.textContent = calculatedDistance !== null ? (calculatedDistance * 0.621371).toFixed(1) + ' miles' : 'Set locations first';
        }
      } else {
        if (summaryDiv) summaryDiv.style.display = 'none';
        const miniTier = document.getElementById('miniSummaryTier');
        if (miniTier) miniTier.textContent = 'Select tier';
        const miniFare = document.getElementById('miniSummaryFare');
        if (miniFare) miniFare.textContent = 'Pick date & time first';
        const miniDistance = document.getElementById('miniSummaryDistance');
        if (miniDistance) miniDistance.textContent = calculatedDistance !== null ? (calculatedDistance * 0.621371).toFixed(1) + ' miles' : 'Set locations first';
      }
      return;
    }

    // With-driver mode
    if (!carData) return;
    const ppd = Number(carData.price_per_day);

    const summaryDistanceRow = document.getElementById('summaryDistanceRow');
    if (summaryDistanceRow) summaryDistanceRow.style.display = 'none';
    
    const summaryDurationRow = document.getElementById('summaryDurationRow');
    const summaryRateRow = document.getElementById('summaryRateRow');
    if (summaryDurationRow) summaryDurationRow.style.display = '';
    if (summaryRateRow) summaryRateRow.style.display = '';

    if (pickupVal && retVal) {
      const diff = Math.max(1, Math.ceil((new Date(retVal) - new Date(pickupVal)) / 86400000));
      summaryDiv.style.display = 'block';
      const summaryDays = document.getElementById('summaryDays');
      const summaryRate = document.getElementById('summaryRate');
      const summaryTotal = document.getElementById('summaryTotal');
      if (summaryDays) summaryDays.textContent = diff + ' day' + (diff > 1 ? 's' : '');
      if (summaryRate) summaryRate.textContent = '£' + ppd.toFixed(2) + '/day';
      if (summaryTotal) summaryTotal.textContent = '£' + (diff * ppd).toFixed(2);
    } else {
      summaryDiv.style.display = 'none';
    }
  }

  // ===== STEP NAVIGATION =====
  async function goToStep2() {
    if (!isLoggedIn) {
      if (typeof showToast === 'function') showToast('Please log in to continue booking.', 'warning');
      return;
    }
    
    const pickupLocation = document.getElementById('pickupLocation');
    const pickupLoc = pickupLocation ? pickupLocation.value.trim() : '';
    if (!pickupLoc) {
      if (typeof showToast === 'function') showToast('Please enter a pick-up location.', 'warning');
      return;
    }

    if (selectedBookingType === 'with-driver') {
      const pickup = document.getElementById('pickupDate');
      const ret = document.getElementById('returnDate');
      const pickupVal = pickup ? pickup.value : '';
      const retVal = ret ? ret.value : '';
      
      if (!pickupVal) {
        if (typeof showToast === 'function') showToast('Please select a pick-up date.', 'warning');
        return;
      }
      if (!retVal) {
        if (typeof showToast === 'function') showToast('Please select a return date.', 'warning');
        return;
      }
      if (retVal < pickupVal) {
        if (typeof showToast === 'function') showToast('Return date must be after pick-up date.', 'warning');
        return;
      }
      if (!carData) {
        if (typeof showToast === 'function') showToast('Please select a vehicle first.', 'warning');
        return;
      }
    } else if (selectedBookingType === 'minicab') {
      const scheduledDT = document.getElementById('scheduledDateTime');
      const scheduledVal = scheduledDT ? scheduledDT.value : '';
      if (!scheduledVal) {
        if (typeof showToast === 'function') showToast('Please select a scheduled pick-up date and time.', 'warning');
        return;
      }
      // Validate scheduled time is in the future
      try {
        // Parse datetime-local value as LOCAL time (NOT UTC)
        const [datePart, timePart] = scheduledVal.split('T');
        const [year, month, day] = datePart.split('-');
        const [hours, minutes] = timePart.split(':');
        const scheduledTime = new Date(parseInt(year), parseInt(month) - 1, parseInt(day), parseInt(hours), parseInt(minutes));

        const now = new Date();
        const minAllowedTime = new Date(now.getTime() + 60 * 1000);

        if (scheduledTime < minAllowedTime) {
          if (typeof showToast === 'function') showToast('⚠️ Scheduled pick-up time must be at least 1 minute in the future. Please select a later time.', 'warning');
          return;
        }
      } catch (e) {
        if (typeof showToast === 'function') showToast('Invalid date and time format. Please check your input.', 'warning');
        return;
      }
      const pickupDate = document.getElementById('pickupDate');
      if (pickupDate) pickupDate.value = scheduledVal.split('T')[0];

      const returnLocation = document.getElementById('returnLocation');
      const destLoc = returnLocation ? returnLocation.value.trim() : '';
      if (!destLoc) {
        if (typeof showToast === 'function') showToast('Please enter a destination.', 'warning');
        return;
      }
      if (requiresManualRideTierSelection() && !selectedRideTier) {
        if (typeof showToast === 'function') showToast('Please select a ride tier (Eco, Standard, or Luxury).', 'warning');
        return;
      }
      if (rideFare === null || rideFare <= 0) {
        if (typeof showToast === 'function') showToast('Unable to calculate fare. Please set both locations.', 'warning');
        return;
      }

      const serviceType = document.getElementById('serviceType');
      const serviceTypeVal = serviceType ? serviceType.value : '';
      if (serviceTypeVal === 'local' && calculatedDistance > 48.28) {
        if (typeof showToast === 'function') showToast('⚠️ Local Journey must be under 30 miles. Your distance is ' + (calculatedDistance * 0.621371).toFixed(1) + ' miles. Please switch to Long Distance Journey.', 'warning');
        return;
      }
      if (serviceTypeVal === 'long-distance' && calculatedDistance <= 48.28) {
        if (typeof showToast === 'function') showToast('⚠️ Long Distance Journey must be over 30 miles. Your distance is ' + (calculatedDistance * 0.621371).toFixed(1) + ' miles. Please switch to Local Journey.', 'warning');
        return;
      }
    }

    populatePaymentSummary();
    const step1Content = document.getElementById('step1Content');
    const step2Content = document.getElementById('step2Content');
    const step1Indicator = document.getElementById('step1Indicator');
    const step2Indicator = document.getElementById('step2Indicator');
    const stepLine = document.getElementById('stepLine');
    
    if (step1Content) step1Content.style.display = 'none';
    if (step2Content) step2Content.style.display = 'block';
    if (step1Indicator) {
      step1Indicator.classList.remove('active');
      step1Indicator.classList.add('completed');
    }
    if (step2Indicator) step2Indicator.classList.add('active');
    if (stepLine) stepLine.classList.add('active');
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function goToStep1() {
    const step2Content = document.getElementById('step2Content');
    const step1Content = document.getElementById('step1Content');
    const step2Indicator = document.getElementById('step2Indicator');
    const step1Indicator = document.getElementById('step1Indicator');
    const stepLine = document.getElementById('stepLine');
    
    if (step2Content) step2Content.style.display = 'none';
    if (step1Content) step1Content.style.display = 'block';
    if (step2Indicator) step2Indicator.classList.remove('active');
    if (step1Indicator) {
      step1Indicator.classList.remove('completed');
      step1Indicator.classList.add('active');
    }
    if (stepLine) stepLine.classList.remove('active');
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // ===== POPULATE PAYMENT SUMMARY =====
  function populatePaymentSummary() {
    const pickup = document.getElementById('pickupDate');
    const ret = document.getElementById('returnDate');
    const pickupLocation = document.getElementById('pickupLocation');
    const returnLocation = document.getElementById('returnLocation');
    
    const pickupVal = pickup ? pickup.value : '';
    const retVal = ret ? ret.value : '';
    const pickupLoc = pickupLocation ? pickupLocation.value.trim() : '';
    const returnLoc = returnLocation ? returnLocation.value.trim() : '';

    if (selectedBookingType === 'minicab') {
      const tierLabels = { eco: '🌿 Eco', standard: '⭐ Standard', luxury: '👑 Luxury' };
      const serviceLabels = { 'local': 'Local Journey', 'long-distance': 'Long Distance', 'airport-transfer': 'Airport Transfer', 'hotel-transfer': 'Hotel Transfer' };
      const serviceType = document.getElementById('serviceType');
      const serviceTypeVal = serviceType ? serviceType.value : '';
      const rate = getOnlineRatePerMile(selectedRideTier, selectedSeatCapacity);

      const paymentCarTitle = document.getElementById('paymentCarTitle');
      if (paymentCarTitle) paymentCarTitle.textContent = tierLabels[selectedRideTier] || 'Minicab';
      
      const paymentCarSub = document.getElementById('paymentCarSub');
      if (paymentCarSub) paymentCarSub.textContent = (serviceLabels[serviceTypeVal] || 'Auto-assigned vehicle') + ' · ' + selectedSeatCapacity + ' seats';
      
      const paymentBookingType = document.getElementById('paymentBookingType');
      if (paymentBookingType) paymentBookingType.textContent = 'Minicab – ' + (tierLabels[selectedRideTier] || '');

      const thumb = document.getElementById('paymentCarThumb');
      if (thumb) {
        const logoUrl = getMinicabSeatLogo(selectedSeatCapacity);
        thumb.innerHTML = '<img src="' + logoUrl + '" alt="Minicab ' + selectedSeatCapacity + '-seat" style="width:100%;height:100%;object-fit:contain;background:white;padding:6px;" onerror="this.parentElement.innerHTML=\'<div class=no-image-placeholder style=height:100%;display:flex;align-items:center;justify-content:center;color:var(--gray-400);font-size:0.8rem>Logo unavailable</div>\'">';
      }

      const paymentPickupDate = document.getElementById('paymentPickupDate');
      if (paymentPickupDate) {
        const scheduled = document.getElementById('scheduledDateTime');
        const scheduledVal = scheduled ? scheduled.value : '';
        paymentPickupDate.textContent = scheduledVal
          ? new Date(scheduledVal).toLocaleString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
          : formatDate(pickupVal);
      }

      const paymentReturnRow = document.getElementById('paymentReturnRow');
      if (paymentReturnRow) paymentReturnRow.style.display = 'none';
      
      const paymentReturnLocRow = document.getElementById('paymentReturnLocRow');
      if (paymentReturnLocRow) paymentReturnLocRow.style.display = '';
      
      const paymentReturnLocLabel = document.getElementById('paymentReturnLocLabel');
      if (paymentReturnLocLabel) paymentReturnLocLabel.textContent = 'Destination';
      
      const paymentReturnLoc = document.getElementById('paymentReturnLoc');
      if (paymentReturnLoc) paymentReturnLoc.textContent = returnLoc;
      
      const paymentPickupLoc = document.getElementById('paymentPickupLoc');
      if (paymentPickupLoc) paymentPickupLoc.textContent = pickupLoc;

      const paymentDailyRate = document.getElementById('paymentDailyRate');
      if (paymentDailyRate) paymentDailyRate.textContent = '£' + rate.toFixed(2) + '/mile';
      
      const paymentDaysRow = document.getElementById('paymentDaysRow');
      if (paymentDaysRow) paymentDaysRow.style.display = '';
      
      const paymentDaysLabel = document.getElementById('paymentDaysLabel');
      if (paymentDaysLabel) paymentDaysLabel.textContent = calculatedDistance ? (calculatedDistance * 0.621371).toFixed(1) + ' miles' : '-';
      
      const paymentSubtotal = document.getElementById('paymentSubtotal');
      if (paymentSubtotal) paymentSubtotal.textContent = '£' + (rideFare || 0).toFixed(2);

      const paymentDistanceRow = document.getElementById('paymentDistanceRow');
      if (paymentDistanceRow) paymentDistanceRow.style.display = '';
      
      const paymentDistance = document.getElementById('paymentDistance');
      if (paymentDistance) paymentDistance.textContent = calculatedDistance ? (calculatedDistance * 0.621371).toFixed(1) + ' miles' : '-';
      
      const paymentTransferRow = document.getElementById('paymentTransferRow');
      if (paymentTransferRow) paymentTransferRow.style.display = 'none';

      updatePaymentTotal();
      return;
    }

    // With-driver mode
    if (!carData) return;
    const c = carData;
    const ppd = 0; // No longer using price per day for with-driver

    const paymentCarTitle = document.getElementById('paymentCarTitle');
    if (paymentCarTitle) paymentCarTitle.textContent = c.brand + ' ' + c.model;
    
    const paymentCarSub = document.getElementById('paymentCarSub');
    if (paymentCarSub) paymentCarSub.textContent = c.year + ' · ' + ucfirst(c.category);
    
    const paymentBookingType = document.getElementById('paymentBookingType');
    if (paymentBookingType) paymentBookingType.textContent = 'With Driver';

    const thumb = document.getElementById('paymentCarThumb');
    if (thumb) {
      if (c.images && c.images.length > 0 && c.images[0] && (c.images[0].startsWith('http') || c.images[0].startsWith('/api/'))) {
        thumb.innerHTML = '<img src="' + c.images[0] + '" alt="' + escapeHtml(c.brand) + '" onerror="this.parentElement.innerHTML=\'<div style=width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--gray-400);font-size:0.8rem>No Photo</div>\'">';
      } else {
        thumb.innerHTML = '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--gray-400);font-size:0.8rem;">No Photo</div>';
      }
    }

    const paymentPickupDate = document.getElementById('paymentPickupDate');
    if (paymentPickupDate) paymentPickupDate.textContent = formatDate(pickupVal);
    
    const paymentReturnRow = document.getElementById('paymentReturnRow');
    if (paymentReturnRow) paymentReturnRow.style.display = '';
    
    const paymentReturnDate = document.getElementById('paymentReturnDate');
    if (paymentReturnDate) paymentReturnDate.textContent = formatDate(retVal);
    
    const paymentReturnLocRow = document.getElementById('paymentReturnLocRow');
    if (paymentReturnLocRow) paymentReturnLocRow.style.display = 'none';
    
    const paymentPickupLoc = document.getElementById('paymentPickupLoc');
    if (paymentPickupLoc) paymentPickupLoc.textContent = pickupLoc;

    let totalDays = 1;
    if (pickupVal && retVal) {
      totalDays = Math.max(1, Math.ceil((new Date(retVal) - new Date(pickupVal)) / 86400000));
    }
    const subtotal = totalDays * ppd;
    
    const paymentDailyRate = document.getElementById('paymentDailyRate');
    if (paymentDailyRate) paymentDailyRate.textContent = '£' + ppd.toFixed(2) + '/day';
    
    const paymentDaysLabel = document.getElementById('paymentDaysLabel');
    if (paymentDaysLabel) paymentDaysLabel.textContent = totalDays + ' day' + (totalDays > 1 ? 's' : '');
    
    const paymentSubtotal = document.getElementById('paymentSubtotal');
    if (paymentSubtotal) paymentSubtotal.textContent = '£' + subtotal.toFixed(2);

    const paymentDistanceRow = document.getElementById('paymentDistanceRow');
    if (paymentDistanceRow) paymentDistanceRow.style.display = 'none';
    
    const paymentTransferRow = document.getElementById('paymentTransferRow');
    if (paymentTransferRow) paymentTransferRow.style.display = 'none';
    
    const paymentDaysRow = document.getElementById('paymentDaysRow');
    if (paymentDaysRow) paymentDaysRow.style.display = '';

    updatePaymentTotal();
  }

  function updatePaymentTotal() {
    let subtotal;

    if (selectedBookingType === 'minicab') {
      subtotal = rideFare || 0;
    } else {
      if (!carData) return;
      const ppd = Number(carData.price_per_day);
      const pickup = document.getElementById('pickupDate');
      const ret = document.getElementById('returnDate');
      const pickupVal = pickup ? pickup.value : '';
      const retVal = ret ? ret.value : '';
      let totalDays = 1;
      if (pickupVal && retVal) {
        totalDays = Math.max(1, Math.ceil((new Date(retVal) - new Date(pickupVal)) / 86400000));
      }
      subtotal = totalDays * ppd;
    }

    let discount = 0;
    if (appliedPromo) {
      discount = appliedPromo.discount_type === 'percentage'
        ? Math.round(subtotal * appliedPromo.discount_value / 100 * 100) / 100
        : Math.min(appliedPromo.discount_value, subtotal);
      const paymentPromoRow = document.getElementById('paymentPromoRow');
      if (paymentPromoRow) paymentPromoRow.style.display = '';
      const paymentDiscount = document.getElementById('paymentDiscount');
      if (paymentDiscount) paymentDiscount.textContent = '-£' + discount.toFixed(2);
    } else {
      const paymentPromoRow = document.getElementById('paymentPromoRow');
      if (paymentPromoRow) paymentPromoRow.style.display = 'none';
    }
    const paymentTotal = document.getElementById('paymentTotal');
    if (paymentTotal) paymentTotal.textContent = '£' + Math.max(0, subtotal - discount).toFixed(2);
  }

  function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
  }

  // ===== PAYMENT METHOD =====
  function selectPaymentMethod(method) {
    if (method !== 'cash' && method !== 'paypal') {
      return;
    }
    selectedPaymentMethod = method;
    document.querySelectorAll('.payment-method-card').forEach(el => {
      el.classList.toggle('active', el.dataset.method === method);
    });
  }

  // ===== PROMO CODE =====
  async function applyPromoCode() {
    const promoCodeInput = document.getElementById('promoCodeInput');
    const code = promoCodeInput ? promoCodeInput.value.trim() : '';
    if (!code) {
      if (typeof showToast === 'function') showToast('Please enter a promo code.', 'warning');
      return;
    }

    const btn = document.getElementById('promoApplyBtn');
    if (btn) {
      btn.disabled = true;
      btn.textContent = '...';
    }

    try {
      const res = await fetch(BOOKINGS_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'validate-promo', code })
      });
      const data = await res.json();
      if (data.success) {
        appliedPromo = data.promo;
        showPromoApplied();
        updatePaymentTotal();
        if (typeof showToast === 'function') showToast('✅ Promo applied: ' + data.promo.title, 'success');
        savePromoToWallet(data.promo.code);
      } else {
        if (typeof showToast === 'function') showToast('❌ ' + data.message, 'error');
      }
    } catch (err) {
      if (typeof showToast === 'function') showToast('Failed to validate promo code.', 'error');
    }

    if (btn) {
      btn.disabled = false;
      btn.textContent = 'Apply';
    }
  }

  function showPromoApplied() {
    const promoInputRow = document.getElementById('promoInputRow');
    const promoApplied = document.getElementById('promoApplied');
    const promoAppliedCode = document.getElementById('promoAppliedCode');
    const promoAppliedDesc = document.getElementById('promoAppliedDesc');
    
    if (promoInputRow) promoInputRow.style.display = 'none';
    if (promoApplied) promoApplied.style.display = 'block';
    if (promoAppliedCode) promoAppliedCode.textContent = appliedPromo.code;
    if (promoAppliedDesc) {
      promoAppliedDesc.textContent = appliedPromo.discount_type === 'percentage'
        ? appliedPromo.discount_value + '% discount applied'
        : '£' + Number(appliedPromo.discount_value).toFixed(2) + ' discount applied';
    }
  }

  function removePromo() {
    appliedPromo = null;
    const promoApplied = document.getElementById('promoApplied');
    const promoInputRow = document.getElementById('promoInputRow');
    const promoCodeInput = document.getElementById('promoCodeInput');
    
    if (promoApplied) promoApplied.style.display = 'none';
    if (promoInputRow) promoInputRow.style.display = 'flex';
    if (promoCodeInput) promoCodeInput.value = '';
    
    updatePaymentTotal();
    if (typeof showToast === 'function') showToast('Promo code removed.', 'info');
  }

  // ===== SAVED PROMOS WALLET =====
  function savePromoToWallet(code) {
    let saved = JSON.parse(localStorage.getItem('drivenow_saved_promos') || '[]');
    if (!saved.includes(code.toUpperCase())) {
      saved.push(code.toUpperCase());
      localStorage.setItem('drivenow_saved_promos', JSON.stringify(saved));
    }
  }

  async function loadSavedPromos() {
    const saved = JSON.parse(localStorage.getItem('drivenow_saved_promos') || '[]');
    if (saved.length === 0) return;

    try {
      const res = await fetch(BOOKINGS_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'active-promos' })
      });
      const data = await res.json();
      if (!data.success || !data.promos) return;

      const available = data.promos.filter(p => saved.includes(p.code.toUpperCase()));
      if (available.length === 0) return;

      const savedPromosList = document.getElementById('savedPromosList');
      if (savedPromosList) {
        savedPromosList.innerHTML = available.map(p => {
          const dt = p.discount_type === 'percentage' ? p.discount_value + '% OFF' : '£' + p.discount_value + ' OFF';
          return '<div class="saved-promo-item" onclick="useSavedPromo(\'' + escapeHtml(p.code) + '\')">' +
            '<div><div class="saved-promo-code">' + escapeHtml(p.code) + '</div>' +
            '<div class="saved-promo-desc">' + (p.title || '') + '</div></div>' +
            '<span class="saved-promo-badge">' + dt + '</span></div>';
        }).join('');
      }
      
      const savedPromosSection = document.getElementById('savedPromosSection');
      if (savedPromosSection) savedPromosSection.style.display = 'block';
    } catch (err) {
      console.error('Failed to load promos:', err);
    }
  }

  function useSavedPromo(code) {
    const promoCodeInput = document.getElementById('promoCodeInput');
    if (promoCodeInput) promoCodeInput.value = code;
    applyPromoCode();
  }

  // ===== CONFIRM BOOKING =====
  async function confirmBooking() {
    const btn = document.getElementById('confirmBtn');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Processing...';
    }

    // Note: pickup datetime was already validated on goToStep2()
    // Don't re-validate here with a new "now" time, as user may have spent time on payment page

    const pickupLocation = document.getElementById('pickupLocation');
    const pickupLoc = pickupLocation ? pickupLocation.value.trim() : '';

    // Convert datetime-local to UTC ISO format for backend storage 
    const convertToUTCISO = function(datetimeLocalValue) {
      if (!datetimeLocalValue) return '';
      // Parse datetime-local as LOCAL time (NOT UTC)
      // datetime-local format: "2026-03-23T14:32" must be parsed as local time, not UTC
      const [datePart, timePart] = datetimeLocalValue.split('T');
      const [year, month, day] = datePart.split('-');
      const [hours, minutes] = timePart.split(':');
      const localDate = new Date(parseInt(year), parseInt(month) - 1, parseInt(day), parseInt(hours), parseInt(minutes));

      if (Number.isNaN(localDate.getTime())) return '';
      // Date stores epoch internally; toISOString() already returns the same instant in UTC.
      // Do NOT apply timezone offset manually, otherwise time will be shifted twice.
      return localDate.toISOString();
    };

    // Extract and format time as "08:00AM", "12:00PM", etc.
    const extractPickupTime = function(datetimeLocalValue) {
      if (!datetimeLocalValue) return '';
      // Format: "2026-03-28T08:30" -> extract "08:30" and convert to "08:30AM"
      const timePart = datetimeLocalValue.substring(11, 16); // "08:30"
      const [hours, minutes] = timePart.split(':');
      const hour = parseInt(hours, 10);
      const ampm = hour >= 12 ? 'PM' : 'AM';
      const displayHour = hour % 12 || 12; // Convert 24h to 12h (0 -> 12, 13 -> 1, etc.)
      return String(displayHour).padStart(2, '0') + ':' + minutes + ampm;
    };

    const payload = {
      action: 'create',
      booking_type: selectedBookingType,
      pickup_date: document.getElementById('pickupDate').value || '',
      pickup_location: pickupLoc,
      special_requests: document.getElementById('specialRequests').value.trim(),
      promo_code: appliedPromo ? appliedPromo.code : '',
      payment_method: selectedPaymentMethod,
      distance_km: calculatedDistance
    };

    if (selectedBookingType === 'minicab') {
      const returnLocation = document.getElementById('returnLocation');
      payload.ride_tier = selectedRideTier;
      payload.seat_capacity = selectedSeatCapacity;
      payload.number_of_passengers = selectedSeatCapacity;
      payload.return_location = returnLocation ? returnLocation.value.trim() : '';
      payload.return_date = null;
      payload.ride_fare = rideFare;
      const serviceType = document.getElementById('serviceType');
      payload.service_type = serviceType ? serviceType.value : '';
      payload.ride_timing = 'schedule';
      const scheduledDateTime = document.getElementById('scheduledDateTime');
      payload.pickup_date = convertToUTCISO(scheduledDateTime ? scheduledDateTime.value : '');
      payload.pickup_time = extractPickupTime(scheduledDateTime ? scheduledDateTime.value : '');
    } else {
      payload.vehicle_id = CAR_ID;
      const returnDate = document.getElementById('returnDate');
      payload.return_date = returnDate ? returnDate.value : '';
      payload.return_location = pickupLoc;
    }

    try {
      const res = await fetch(BOOKINGS_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        if (selectedPaymentMethod === 'paypal') {
          const approvalUrl = data.paypal && data.paypal.approval_url ? data.paypal.approval_url : '';
          if (!approvalUrl) {
            if (typeof showToast === 'function') {
              showToast('❌ PayPal checkout could not be started. Please try again.', 'error');
            }
          } else {
            window.location.href = approvalUrl;
            return;
          }
        }

        showBookingSuccess(data.booking);
        const vehicleName = selectedBookingType === 'minicab' 
          ? 'minicab trip' 
          : (carData ? carData.brand + ' ' + carData.model : 'vehicle');
        if (typeof addNotification === 'function') {
          addNotification('Booking Confirmed', 'Your ' + vehicleName + ' booking has been submitted!', 'booking');
        }
      } else {
        if (typeof showToast === 'function') showToast('❌ ' + data.message, 'error');
      }
    } catch (err) {
      if (typeof showToast === 'function') showToast('Failed to create booking. Please try again.', 'error');
    }
    
    if (btn) {
      btn.disabled = false;
      btn.textContent = 'Confirm & Book';
    }
  }

  function showBookingSuccess(booking) {
    const step2Content = document.getElementById('step2Content');
    const bookingSteps = document.getElementById('bookingSteps');
    const bookingSuccess = document.getElementById('bookingSuccess');
    
    if (step2Content) step2Content.style.display = 'none';
    if (bookingSteps) bookingSteps.style.display = 'none';
    if (bookingSuccess) bookingSuccess.style.display = 'block';

    const tl = { 'minicab': 'Minicab', 'with-driver': 'With Driver' };
    const bookingType = booking && booking.booking_type ? booking.booking_type : selectedBookingType;
    const rideTierValue = booking && booking.ride_tier ? booking.ride_tier : selectedRideTier;
    const distanceValue = booking && booking.distance_km !== null && booking.distance_km !== undefined
      ? Number(booking.distance_km)
      : calculatedDistance;
    const subtotal = Number(booking && booking.subtotal !== undefined ? booking.subtotal : 0);
    const discount = Number(booking && booking.discount !== undefined ? booking.discount : 0);
    const total = Number(booking && booking.total !== undefined ? booking.total : subtotal - discount);
    const totalDays = Number(booking && booking.total_days !== undefined ? booking.total_days : 1);
    const paymentMethod = booking && booking.payment_method ? booking.payment_method : selectedPaymentMethod;
    const promoApplied = booking && booking.promo_applied ? booking.promo_applied : '';

    let vehicleName = '';
    if (bookingType === 'minicab') {
      const tierLabels = { eco: '🌿 Eco', standard: '⭐ Standard', luxury: '👑 Luxury' };
      vehicleName = 'Vehicle will be assigned when your trip starts' + (tierLabels[rideTierValue] ? ' · ' + tierLabels[rideTierValue] : '');
    } else {
      vehicleName = carData ? carData.brand + ' ' + carData.model : 'Vehicle';
    }

    let html = '<div class="sb-row"><span>Vehicle</span><span>' + escapeHtml(vehicleName) + '</span></div>';
    html += '<div class="sb-row"><span>Type</span><span>' + (tl[bookingType] || bookingType) + '</span></div>';
    if (bookingType === 'minicab') {
      html += '<div class="sb-row"><span>Distance</span><span>' + (distanceValue ? (distanceValue * 0.621371).toFixed(1) + ' miles' : '-') + '</span></div>';
    } else {
      html += '<div class="sb-row"><span>Duration</span><span>' + totalDays + ' day' + (totalDays > 1 ? 's' : '') + '</span></div>';
    }
    html += '<div class="sb-row"><span>Subtotal</span><span>£' + subtotal.toFixed(2) + '</span></div>';
    if (discount > 0) {
      html += '<div class="sb-row" style="color:var(--success);"><span>Discount (' + escapeHtml(promoApplied || 'Promo') + ')</span><span>-£' + discount.toFixed(2) + '</span></div>';
    }
    html += '<div class="sb-row total"><span>Total</span><span>£' + total.toFixed(2) + '</span></div>';
    html += '<div class="sb-row"><span>Payment</span><span>' + formatPaymentMethod(paymentMethod) + '</span></div>';
    html += '<div class="sb-row"><span>Status</span><span style="color:var(--warning);font-weight:600;">⏳ Pending</span></div>';
    
    const successSummary = document.getElementById('successSummary');
    if (successSummary) successSummary.innerHTML = html;
  }

  function formatPaymentMethod(m) {
    return { cash: '💵 Cash', bank_transfer: '🏦 Banking', paypal: '🅿️ PayPal', credit_card: '💳 Card' }[m] || m;
  }

  // ===== LEAFLET AUTOCOMPLETE =====
  function haversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  function selectRideTier(tier) {
    const tierConfig = RIDE_TIER_CONFIG[tier];
    if (!tierConfig) return;
    autoAssignedRideTier = false;
    selectedRideTier = tier;
    document.querySelectorAll('.ride-tier-card').forEach(el => {
      el.classList.toggle('active', el.dataset.tier === tier);
    });
    updateTripSummary();
  }

  function renderRideTierOptions() {
    const grid = document.getElementById('rideTierGrid');
    if (!grid) return;
    
    if (calculatedDistance === null) {
      grid.innerHTML = '<div style="text-align:center;padding:20px;color:var(--gray-400);font-size:0.85rem;grid-column:1/-1;">Select pickup & destination to see ride options</div>';
      return;
    }

    const tiers = ['eco', 'standard', 'luxury'];

    grid.innerHTML = tiers.map(key => {
      const t = RIDE_TIER_CONFIG[key];
      const isActive = selectedRideTier === key ? ' active' : '';
      const rate = getOnlineRatePerMile(key, selectedSeatCapacity);
      const badge = t.badge ? '<span class="ride-tier-badge" style="background:' + t.color + ';">' + t.badge + '</span>' : '';
      const desc = t.descriptions[selectedSeatCapacity] || '';

      return '<div class="ride-tier-card ' + key + isActive + '" data-tier="' + key + '" onclick="selectRideTier(\'' + key + '\')" style="border-color:' + t.color + ';">' +
        badge +
        '<span class="ride-tier-icon">' + t.icon + '</span>' +
        '<span class="ride-tier-name">' + t.name + '</span>' +
        '<span class="ride-tier-seats">👥 ' + selectedSeatCapacity + ' seats</span>' +
        '<span class="ride-tier-desc">' + escapeHtml(desc) + '</span>' +
        '<span class="ride-tier-rate">£' + rate.toFixed(2) + '/mile</span>' +
      '</div>';
    }).join('');

    // Check available tiers
    checkAvailableTiers(selectedSeatCapacity);
  }

  function calculateRouteDistance() {
    const pickupAddr = selectedAddresses.pickup;
    const returnAddr = selectedAddresses['return'];
    calculatedDistance = null;
    transferCost = null;

    const summaryDistanceRow = document.getElementById('summaryDistanceRow');
    if (summaryDistanceRow) summaryDistanceRow.style.display = 'none';

    if (!pickupAddr || !returnAddr) {
      if (selectedBookingType === 'minicab') renderRideTierOptions();
      updateTripSummary();
      return;
    }
    if (selectedBookingType === 'with-driver') {
      updateTripSummary();
      return;
    }

    const dist = haversineDistance(pickupAddr.lat, pickupAddr.lon, returnAddr.lat, returnAddr.lon);
    calculatedDistance = Math.round(dist * 1.3 * 10) / 10;

    if (summaryDistanceRow) summaryDistanceRow.style.display = '';
    const summaryDistance = document.getElementById('summaryDistance');
    if (summaryDistance) summaryDistance.textContent = (calculatedDistance * 0.621371).toFixed(1) + ' miles (est.)';

    if (selectedBookingType === 'minicab') {
      renderRideTierOptions();
    }

    updateTripSummary();
  }

  function initLeafletAutocomplete() {
    ['pickupLocation', 'returnLocation'].forEach(id => {
      const input = document.getElementById(id);
      if (!input) return;

      const type = id === 'pickupLocation' ? 'pickup' : 'return';

      let dropdown = input.parentElement.querySelector('.leaflet-autocomplete-list');
      if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.className = 'leaflet-autocomplete-list';
        dropdown.style.display = 'none';
        input.parentElement.appendChild(dropdown);
      }

      input.addEventListener('input', function() {
        selectedAddresses[type] = null;
        const query = this.value.trim();
        if (query.length < 3) {
          dropdown.style.display = 'none';
          return;
        }
        clearTimeout(autocompleteTimers[id]);
        autocompleteTimers[id] = setTimeout(() => searchNominatim(query, dropdown, input, type, false), 350);
      });

      input.addEventListener('blur', () => setTimeout(() => {
        dropdown.style.display = 'none';
      }, 200));

      input.addEventListener('focus', function() {
        if (this.value.trim().length >= 3 && dropdown.innerHTML) dropdown.style.display = 'block';
      });
    });
  }

  async function searchNominatim(query, dropdown, input, type, isMapSearch) {
    try {
      const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query) + '&limit=5&addressdetails=1&countrycodes=gb', {
        headers: { 'Accept-Language': 'en' }
      });
      const results = await res.json();
      if (results.length === 0) {
        dropdown.style.display = 'none';
        return;
      }

      dropdown.innerHTML = results.map(r => {
        const parts = r.display_name.split(',');
        const main = parts.slice(0, 2).join(',').trim();
        const sub = parts.slice(2).join(',').trim();
        return '<div class="autocomplete-item" data-lat="' + r.lat + '" data-lon="' + r.lon + '" data-name="' + r.display_name.replace(/"/g, '&quot;') + '">' +
          '<div class="ac-main">' + escapeHtml(main) + '</div>' +
          (sub ? '<div class="ac-sub">' + escapeHtml(sub) + '</div>' : '') +
        '</div>';
      }).join('');
      dropdown.style.display = 'block';

      dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
        item.addEventListener('mousedown', function(e) {
          e.preventDefault();
          const lat = parseFloat(this.dataset.lat);
          const lon = parseFloat(this.dataset.lon);
          const name = this.dataset.name;
          
          input.value = name;
          dropdown.style.display = 'none';

          selectedAddresses[type] = { lat, lon, name };

          moveMapToLocation(type, lat, lon);
          updateMapCoords(type, lat, lon, name);

          calculateRouteDistance();
          updateTripSummary();
        });
      });
    } catch (err) {
      console.error('Nominatim search error:', err);
    }
  }

  function updateMapCoords(type, lat, lon, name) {
    const coordsEl = document.getElementById(type + 'MapCoords');
    if (coordsEl) {
      const short = name ? (name.length > 60 ? name.substring(0, 60) + '...' : name) : (lat.toFixed(5) + ', ' + lon.toFixed(5));
      coordsEl.textContent = '📍 ' + short;
    }
  }

  function moveMapToLocation(type, lat, lon) {
    const map = type === 'pickup' ? pickupMapObj : returnMapObj;
    const marker = type === 'pickup' ? pickupMarker : returnMarker;
    
    if (map && marker) {
      const latlng = L.latLng(lat, lon);
      marker.setLatLng(latlng);
      map.setView(latlng, 16, { animate: true });
    }
  }

  function openMapPicker(type) {
    const container = document.getElementById(type + 'MapContainer');
    if (!container) return;
    
    if (container.style.display === 'none') {
      container.style.display = 'block';
      const mapDiv = document.getElementById(type + 'Map');

      if (type === 'pickup' && pickupMapObj) {
        pickupMapObj.remove();
        pickupMapObj = null;
      }
      if (type === 'return' && returnMapObj) {
        returnMapObj.remove();
        returnMapObj = null;
      }

      const saved = selectedAddresses[type];
      // Default map center for UK bookings (London)
      const center = saved ? [saved.lat, saved.lon] : [51.5074, -0.1278];
      const zoom = saved ? 16 : 13;

      const map = L.map(mapDiv).setView(center, zoom);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
      }).addTo(map);

      const marker = L.marker(center, { draggable: true }).addTo(map);
      
      map.on('click', e => {
        marker.setLatLng(e.latlng);
        selectedAddresses[type] = null;
        updateMapCoords(type, e.latlng.lat, e.latlng.lng, null);
      });
      
      marker.on('dragend', () => {
        const pos = marker.getLatLng();
        selectedAddresses[type] = null;
        updateMapCoords(type, pos.lat, pos.lng, null);
      });

      if (type === 'pickup') {
        pickupMapObj = map;
        pickupMarker = marker;
      } else {
        returnMapObj = map;
        returnMarker = marker;
      }

      if (saved) updateMapCoords(type, saved.lat, saved.lon, saved.name);

      setTimeout(() => map.invalidateSize(), 200);
    } else {
      container.style.display = 'none';
      container.classList.remove('expanded');
    }
  }

  function closeMapPicker(type) {
    const container = document.getElementById(type + 'MapContainer');
    if (container) {
      container.style.display = 'none';
      container.classList.remove('expanded');
    }
  }

  function toggleMapExpand(type) {
    const container = document.getElementById(type + 'MapContainer');
    if (container) {
      container.classList.toggle('expanded');
      const map = type === 'pickup' ? pickupMapObj : returnMapObj;
      if (map) setTimeout(() => map.invalidateSize(), 300);
    }
  }

  async function confirmMapLocation(type) {
    const marker = type === 'pickup' ? pickupMarker : returnMarker;
    const saved = selectedAddresses[type];
    
    if (saved && saved.name) {
      const location = document.getElementById(type + 'Location');
      if (location) location.value = saved.name;
      closeMapPicker(type);
      if (typeof showToast === 'function') showToast('📍 Location confirmed!', 'success');
      calculateRouteDistance();
      return;
    }

    if (!marker) {
      closeMapPicker(type);
      return;
    }
    
    const pos = marker.getLatLng();
    const container = document.getElementById(type + 'MapContainer');
    const btn = container ? container.querySelector('.map-picker-footer .btn') : null;
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Loading...';
    }

    try {
      const res = await fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + pos.lat + '&lon=' + pos.lng + '&zoom=18&addressdetails=1', {
        headers: { 'Accept-Language': 'en' }
      });
      const data = await res.json();
      const address = data.display_name || (pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6));
      const location = document.getElementById(type + 'Location');
      if (location) location.value = address;
      selectedAddresses[type] = { lat: pos.lat, lon: pos.lng, name: address };
      updateMapCoords(type, pos.lat, pos.lng, address);
    } catch (err) {
      const location = document.getElementById(type + 'Location');
      if (location) location.value = pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6);
      selectedAddresses[type] = { lat: pos.lat, lon: pos.lng, name: null };
    }

    if (btn) {
      btn.disabled = false;
      btn.textContent = '✓ Confirm Location';
    }
    closeMapPicker(type);
    if (typeof showToast === 'function') showToast('📍 Location confirmed!', 'success');
    calculateRouteDistance();
  }

  // ===== EXPORT FUNCTIONS =====
  window.selectBookingType = selectBookingType;
  window.onServiceTypeChange = onServiceTypeChange;
  window.selectServiceTypeCard = selectServiceTypeCard;
  window.onAirportSelect = onAirportSelect;
  window.onHotelSelect = onHotelSelect;
  window.updateTierRecommendation = updateTierRecommendation;
  window.goToStep2 = goToStep2;
  window.goToStep1 = goToStep1;
  window.selectPaymentMethod = selectPaymentMethod;
  window.applyPromoCode = applyPromoCode;
  window.removePromo = removePromo;
  window.useSavedPromo = useSavedPromo;
  window.confirmBooking = confirmBooking;
  window.selectRideTier = selectRideTier;
  window.selectSeatCapacity = selectSeatCapacity;
  window.openMapPicker = openMapPicker;
  window.closeMapPicker = closeMapPicker;
  window.toggleMapExpand = toggleMapExpand;
  window.confirmMapLocation = confirmMapLocation;
  window.calculateRouteDistance = calculateRouteDistance;
})();
