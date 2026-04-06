/**
 * Booking Module
 * Handles minicab booking flow (schedule only)
 * Features: step navigation, date pickers, location selection with maps, payment processing, promo codes
 */

(function initBookingModule() {
  // ===== CONFIGURATION =====
  const VEHICLES_API = '/api/vehicles.php';
  const BOOKINGS_API = '/api/bookings.php';
  const ACCOUNT_BALANCE_API = '/api/profile.php';
  const CAR_ID = window.CAR_ID || '';
  const INITIAL_PROMO = window.INITIAL_PROMO || '';
  const BOOKING_MODE = window.BOOKING_MODE || '';
  const isLoggedIn = window.isLoggedIn || false;

  // ===== STATE =====
  let carData = null;
  let selectedBookingType = 'minicab';
  let selectedPaymentMethod = 'cash';
  let accountBalanceValue = 0;
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
  const DAILY_HIRE_RATE_TABLE = {
    4: { eco: 180.00, standard: 220.00, luxury: 300.00 },
    7: { eco: 220.00, standard: 270.00, luxury: 400.00 }
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
  let tierAvailabilityTimer = null;
  const SLOT_INTERVAL_MINUTES = 30;
  const BOOKING_DRAFT_STORAGE_PREFIX = 'drivenow_booking_draft_v2';
  const BOOKING_DRAFT_MAX_AGE_MS = 6 * 60 * 60 * 1000;
  let bookingDraftSaveTimer = null;
  let bookingDraftPersistenceBound = false;
  let isRestoringBookingDraft = false;

  function getBookingDraftStorageKey() {
    const modePart = BOOKING_MODE || 'default';
    const carPart = CAR_ID || 'minicab';
    return BOOKING_DRAFT_STORAGE_PREFIX + ':' + modePart + ':' + carPart;
  }

  function getCurrentBookingStep() {
    const step2Content = document.getElementById('step2Content');
    if (step2Content && step2Content.style.display !== 'none') {
      return 2;
    }
    return 1;
  }

  function sanitizeAddressForDraft(address) {
    if (!address || typeof address !== 'object') {
      return null;
    }

    const lat = Number(address.lat);
    const lon = Number(address.lon);
    if (!Number.isFinite(lat) || !Number.isFinite(lon)) {
      return null;
    }

    return {
      lat: lat,
      lon: lon,
      name: address.name ? String(address.name) : ''
    };
  }

  function readElementValue(id) {
    const el = document.getElementById(id);
    return el ? String(el.value || '') : '';
  }

  function collectBookingDraftState() {
    return {
      version: 2,
      savedAt: Date.now(),
      step: getCurrentBookingStep(),
      selectedBookingType: selectedBookingType,
      selectedSeatCapacity: selectedSeatCapacity,
      selectedRideTier: selectedRideTier || '',
      selectedPaymentMethod: selectedPaymentMethod || 'cash',
      serviceType: readElementValue('serviceType') || 'local',
      pickupDate: readElementValue('pickupDate'),
      returnDate: readElementValue('returnDate'),
      scheduledDateOnly: readElementValue('scheduledDateOnly'),
      scheduledTimeSlot: readElementValue('scheduledTimeSlot'),
      scheduledDateTime: readElementValue('scheduledDateTime'),
      pickupLocation: readElementValue('pickupLocation'),
      returnLocation: readElementValue('returnLocation'),
      specialRequests: readElementValue('specialRequests'),
      airportSelect: readElementValue('airportSelect'),
      hotelSelect: readElementValue('hotelSelect'),
      promoCodeInput: readElementValue('promoCodeInput'),
      appliedPromoCode: appliedPromo ? String(appliedPromo.code || '') : '',
      selectedAddresses: {
        pickup: sanitizeAddressForDraft(selectedAddresses.pickup),
        return: sanitizeAddressForDraft(selectedAddresses['return'])
      },
      calculatedDistance: Number.isFinite(calculatedDistance) ? calculatedDistance : null,
      rideFare: Number.isFinite(rideFare) ? rideFare : null
    };
  }

  function saveBookingDraftState() {
    if (isRestoringBookingDraft) {
      return;
    }

    try {
      const state = collectBookingDraftState();
      sessionStorage.setItem(getBookingDraftStorageKey(), JSON.stringify(state));
    } catch (err) {
      console.warn('Unable to save booking draft state:', err);
    }
  }

  function scheduleBookingDraftSave(delayMs) {
    if (isRestoringBookingDraft) {
      return;
    }

    if (bookingDraftSaveTimer) {
      clearTimeout(bookingDraftSaveTimer);
    }
    bookingDraftSaveTimer = setTimeout(saveBookingDraftState, delayMs || 120);
  }

  function clearBookingDraftState() {
    if (bookingDraftSaveTimer) {
      clearTimeout(bookingDraftSaveTimer);
      bookingDraftSaveTimer = null;
    }

    try {
      sessionStorage.removeItem(getBookingDraftStorageKey());
    } catch (err) {
      console.warn('Unable to clear booking draft state:', err);
    }
  }

  function bindBookingDraftPersistence() {
    if (bookingDraftPersistenceBound) {
      return;
    }
    bookingDraftPersistenceBound = true;

    const trackIds = [
      'pickupDate',
      'returnDate',
      'scheduledDateOnly',
      'scheduledTimeSlot',
      'pickupLocation',
      'returnLocation',
      'specialRequests',
      'serviceType',
      'airportSelect',
      'hotelSelect',
      'promoCodeInput'
    ];

    trackIds.forEach(function(id) {
      const el = document.getElementById(id);
      if (!el) {
        return;
      }
      el.addEventListener('input', function() {
        scheduleBookingDraftSave(140);
      });
      el.addEventListener('change', function() {
        scheduleBookingDraftSave(80);
      });
    });

    document.addEventListener('visibilitychange', function() {
      if (document.visibilityState === 'hidden') {
        saveBookingDraftState();
      }
    });
    window.addEventListener('beforeunload', saveBookingDraftState);
    window.addEventListener('pagehide', saveBookingDraftState);
  }

  function parseLocalDateTimeParts(datetimeLocal) {
    const value = String(datetimeLocal || '');
    if (!value || !value.includes('T')) {
      return null;
    }

    const parts = value.split('T');
    if (parts.length !== 2) {
      return null;
    }

    const datePart = parts[0];
    const timePart = parts[1].slice(0, 5);
    if (!datePart || !timePart || !timePart.includes(':')) {
      return null;
    }

    return { date: datePart, time: timePart };
  }

  function setElementValueIfPresent(id, value) {
    const el = document.getElementById(id);
    if (!el || value === undefined || value === null) {
      return;
    }
    el.value = String(value);
  }

  function canRestoreStep2FromCurrentState() {
    if (!isLoggedIn || selectedBookingType !== 'minicab') {
      return false;
    }

    const pickupLoc = readElementValue('pickupLocation').trim();
    const scheduledVal = readElementValue('scheduledDateTime').trim();
    const returnLoc = readElementValue('returnLocation').trim();

    return !!pickupLoc && !!scheduledVal && !!returnLoc && !!selectedRideTier && Number(rideFare) > 0;
  }

  function restoreBookingDraftState() {
    const key = getBookingDraftStorageKey();
    let draft = null;

    try {
      const raw = sessionStorage.getItem(key);
      if (!raw) {
        return;
      }
      draft = JSON.parse(raw);
    } catch (err) {
      sessionStorage.removeItem(key);
      return;
    }

    if (!draft || typeof draft !== 'object') {
      sessionStorage.removeItem(key);
      return;
    }

    const savedAt = Number(draft.savedAt || 0);
    if (!savedAt || (Date.now() - savedAt) > BOOKING_DRAFT_MAX_AGE_MS) {
      sessionStorage.removeItem(key);
      return;
    }

    isRestoringBookingDraft = true;
    try {
      if (draft.selectedBookingType === 'minicab' || draft.selectedBookingType === 'with-driver') {
        selectBookingType(draft.selectedBookingType);
      }

      if (draft.serviceType) {
        const serviceTypeEl = document.getElementById('serviceType');
        if (serviceTypeEl && serviceTypeEl.querySelector('option[value="' + String(draft.serviceType).replace(/"/g, '\\"') + '"]')) {
          serviceTypeEl.value = String(draft.serviceType);
        }
        onServiceTypeChange();
      }

      const seatCapacityValue = Number(draft.selectedSeatCapacity);
      if (seatCapacityValue === 4 || seatCapacityValue === 7) {
        selectSeatCapacity(seatCapacityValue);
      }

      setElementValueIfPresent('pickupDate', draft.pickupDate || '');
      setElementValueIfPresent('returnDate', draft.returnDate || '');
      setElementValueIfPresent('scheduledDateOnly', draft.scheduledDateOnly || '');
      setElementValueIfPresent('scheduledTimeSlot', draft.scheduledTimeSlot || '');

      if ((!draft.scheduledDateOnly || !draft.scheduledTimeSlot) && draft.scheduledDateTime) {
        const dtParts = parseLocalDateTimeParts(draft.scheduledDateTime);
        if (dtParts) {
          setElementValueIfPresent('scheduledDateOnly', dtParts.date);
          setElementValueIfPresent('scheduledTimeSlot', dtParts.time);
        }
      }

      syncScheduledDateTimeFromParts();

      setElementValueIfPresent('pickupLocation', draft.pickupLocation || '');
      setElementValueIfPresent('returnLocation', draft.returnLocation || '');
      setElementValueIfPresent('specialRequests', draft.specialRequests || '');

      const restoredPickupAddress = sanitizeAddressForDraft(draft.selectedAddresses && draft.selectedAddresses.pickup);
      const restoredReturnAddress = sanitizeAddressForDraft(draft.selectedAddresses && draft.selectedAddresses['return']);
      if (restoredPickupAddress) {
        selectedAddresses.pickup = restoredPickupAddress;
        if (!readElementValue('pickupLocation') && restoredPickupAddress.name) {
          setElementValueIfPresent('pickupLocation', restoredPickupAddress.name);
        }
      }
      if (restoredReturnAddress) {
        selectedAddresses['return'] = restoredReturnAddress;
        if (!readElementValue('returnLocation') && restoredReturnAddress.name) {
          setElementValueIfPresent('returnLocation', restoredReturnAddress.name);
        }
      }

      setElementValueIfPresent('airportSelect', draft.airportSelect || '');
      setElementValueIfPresent('hotelSelect', draft.hotelSelect || '');

      if (!INITIAL_PROMO) {
        const promoRestoreCode = draft.appliedPromoCode || draft.promoCodeInput || '';
        setElementValueIfPresent('promoCodeInput', promoRestoreCode);
      }

      calculateRouteDistance();
      if (draft.selectedRideTier) {
        const tier = String(draft.selectedRideTier);
        const tierCard = document.querySelector('.ride-tier-card[data-tier="' + tier + '"]');
        if (tierCard && tierCard.getAttribute('data-disabled') !== 'true') {
          selectRideTier(tier);
        }
      }

      if (draft.selectedPaymentMethod) {
        selectPaymentMethod(String(draft.selectedPaymentMethod));
      }

      updateTripSummary();
    } finally {
      isRestoringBookingDraft = false;
    }

    if (Number(draft.step) === 2) {
      setTimeout(function() {
        if (canRestoreStep2FromCurrentState()) {
          goToStep2().catch(function(err) {
            console.warn('Unable to restore payment step from booking draft:', err);
          });
        }
      }, 220);
    }

    scheduleBookingDraftSave(120);
  }

  function getMinScheduledDateTimeValue() {
    const now = new Date();
    now.setMinutes(now.getMinutes() + 1);
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
  }

  function formatTimeSlotLabel(timeValue) {
    const parts = String(timeValue || '').split(':');
    if (parts.length !== 2) return timeValue;
    const hour24 = Number(parts[0]);
    const minute = Number(parts[1]);
    if (!Number.isFinite(hour24) || !Number.isFinite(minute)) return timeValue;
    const suffix = hour24 >= 12 ? 'PM' : 'AM';
    const hour12 = (hour24 % 12) === 0 ? 12 : (hour24 % 12);
    return String(hour12).padStart(2, '0') + ':' + String(minute).padStart(2, '0') + ' ' + suffix;
  }

  function buildDailyTimeSlots() {
    const slots = [];
    for (let minutes = 0; minutes < 24 * 60; minutes += SLOT_INTERVAL_MINUTES) {
      const hour = Math.floor(minutes / 60);
      const minute = minutes % 60;
      slots.push(String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0'));
    }
    return slots;
  }

  function getMinAllowedSlotTimeForDate(dateValue) {
    if (!dateValue) return null;
    const now = new Date();
    now.setMinutes(now.getMinutes() + 1, 0, 0);
    const today = now.toISOString().split('T')[0];
    if (dateValue !== today) return null;
    return String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
  }

  function compareTimeValues(a, b) {
    const [ah, am] = String(a || '00:00').split(':').map(Number);
    const [bh, bm] = String(b || '00:00').split(':').map(Number);
    return (ah * 60 + am) - (bh * 60 + bm);
  }

  function syncScheduledDateTimeFromParts() {
    const dateEl = document.getElementById('scheduledDateOnly');
    const timeEl = document.getElementById('scheduledTimeSlot');
    const datetimeEl = document.getElementById('scheduledDateTime');
    if (!dateEl || !timeEl || !datetimeEl) return '';

    const dateValue = dateEl.value || '';
    const timeValue = timeEl.value || '';
    const combined = (dateValue && timeValue) ? (dateValue + 'T' + timeValue) : '';
    datetimeEl.value = combined;
    return combined;
  }

  function renderScheduledTimeSlots(unavailableTimes, options) {
    const dateEl = document.getElementById('scheduledDateOnly');
    const timeEl = document.getElementById('scheduledTimeSlot');
    const hintEl = document.getElementById('scheduledTimeSlotHint');
    if (!dateEl || !timeEl) return;

    const settings = options && typeof options === 'object' ? options : {};
    const hasLoadError = settings.loadError === true;

    const selectedDate = dateEl.value || '';

    if (!selectedDate) {
      timeEl.disabled = true;
      timeEl.innerHTML = '<option value="">Select date first</option>';
      timeEl.value = '';
      syncScheduledDateTimeFromParts();

      if (hintEl) {
        hintEl.textContent = 'Choose a date to load available time slots.';
      }
      return;
    }

    const previousValue = timeEl.value || '';
    const allSlots = buildDailyTimeSlots();
    const blockedSet = new Set(Array.isArray(unavailableTimes) ? unavailableTimes : []);
    const minTodayTime = getMinAllowedSlotTimeForDate(selectedDate);

    let enabledCount = 0;
    const slotOptions = ['<option value="">Select time</option>'];
    allSlots.forEach(slot => {
      let disabled = blockedSet.has(slot);
      if (minTodayTime && compareTimeValues(slot, minTodayTime) < 0) {
        disabled = true;
      }

      if (!disabled) {
        enabledCount += 1;
      }

      slotOptions.push('<option value="' + slot + '"' + (disabled ? ' disabled' : '') + '>' + formatTimeSlotLabel(slot) + (disabled ? ' (Unavailable)' : '') + '</option>');
    });

    timeEl.innerHTML = slotOptions.join('');
    timeEl.disabled = enabledCount <= 0;

    if (previousValue && !blockedSet.has(previousValue) && (!minTodayTime || compareTimeValues(previousValue, minTodayTime) >= 0)) {
      timeEl.value = previousValue;
    } else {
      timeEl.value = '';
    }

    syncScheduledDateTimeFromParts();

    if (hintEl) {
      if (enabledCount > 0) {
        hintEl.textContent = 'Available slots: ' + enabledCount + ' for selected date.';
      } else if (hasLoadError) {
        hintEl.textContent = 'Unable to load slots right now. Please choose another date.';
      } else {
        hintEl.textContent = 'No available time slot for this date. Please choose another date.';
      }
    }
  }

  function scheduleRealtimeSlotRefresh(delayMs) {
    if (tierAvailabilityTimer) {
      clearTimeout(tierAvailabilityTimer);
    }
    tierAvailabilityTimer = setTimeout(function() {
      checkAvailableTiers(selectedSeatCapacity);
    }, delayMs || 150);
  }

  function refreshScheduledDateTimeConstraint(clearInvalidValue) {
    const scheduledDate = document.getElementById('scheduledDateOnly');
    if (!scheduledDate) return;

    const minDate = getMinScheduledDateTimeValue().split('T')[0];
    scheduledDate.min = minDate;

    if (!clearInvalidValue || !scheduledDate.value) {
      return;
    }

    if (scheduledDate.value < minDate) {
      scheduledDate.value = '';
    }

    syncScheduledDateTimeFromParts();
  }

  function getOnlineRatePerMile(tier, seatCapacity) {
    const seatRates = ONLINE_RATE_TABLE[seatCapacity] || ONLINE_RATE_TABLE[4];
    return seatRates[tier] || 0;
  }

  function getDailyHireRate(tier, seatCapacity) {
    const seatRates = DAILY_HIRE_RATE_TABLE[seatCapacity] || DAILY_HIRE_RATE_TABLE[4];
    return seatRates[tier] || 0;
  }

  function isDailyHireServiceType(serviceType) {
    return String(serviceType || '').toLowerCase() === 'daily-hire';
  }

  function getCurrentMinicabServiceType() {
    const serviceTypeEl = document.getElementById('serviceType');
    return serviceTypeEl ? String(serviceTypeEl.value || 'local').toLowerCase() : 'local';
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

  let bookingPageInitialized = false;

  // ===== INITIALIZATION =====
  async function initializeBookingPage() {
    if (bookingPageInitialized) {
      return;
    }
    bookingPageInitialized = true;

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

    const scheduledDateEl = document.getElementById('scheduledDateOnly');
    const scheduledTimeEl = document.getElementById('scheduledTimeSlot');
    if (scheduledDateEl && scheduledTimeEl) {
      refreshScheduledDateTimeConstraint(true);
      renderScheduledTimeSlots([]);

      scheduledDateEl.addEventListener('focus', function() {
        refreshScheduledDateTimeConstraint(true);
      });

      scheduledDateEl.addEventListener('change', function() {
        refreshScheduledDateTimeConstraint(false);
        renderScheduledTimeSlots([]);
        syncScheduledDateTimeFromParts();
        validatePickupDateTime();
        updateTripSummary();
        scheduleRealtimeSlotRefresh(100);
      });

      scheduledTimeEl.addEventListener('change', function() {
        syncScheduledDateTimeFromParts();
        validatePickupDateTime();
        updateTripSummary();
        checkAvailableTiers(selectedSeatCapacity);
      });

      scheduledDateEl.addEventListener('blur', function() {
        refreshScheduledDateTimeConstraint(true);
        renderScheduledTimeSlots([]);
        scheduleRealtimeSlotRefresh(120);
      });

      scheduledTimeEl.addEventListener('blur', function() {
        syncScheduledDateTimeFromParts();
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
      // Keep behavior consistent: prefilled promo from landing links should be validated immediately.
      setTimeout(function() {
        applyPromoCode();
      }, 80);
    }
    initLeafletAutocomplete();
    bindBookingDraftPersistence();
    restoreBookingDraftState();
    scheduleBookingDraftSave(160);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      initializeBookingPage().catch(function(err) {
        console.error('Booking initialization failed:', err);
      });
    });
  } else {
    initializeBookingPage().catch(function(err) {
      console.error('Booking initialization failed:', err);
    });
  }

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

      refreshScheduledDateTimeConstraint(true);
      scheduleRealtimeSlotRefresh(80);
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
    scheduleBookingDraftSave(120);
  }

  // ===== SERVICE TYPE CHANGE =====
  function onServiceTypeChange() {
    const serviceType = document.getElementById('serviceType');
    if (!serviceType) return;

    syncServiceTypeCards(serviceType.value);
    
    const isAirport = serviceType.value === 'airport-transfer';
    const isHotel = serviceType.value === 'hotel-transfer';
    const isDailyHire = serviceType.value === 'daily-hire';
    const returnInputWrapper = document.getElementById('returnLocationInputWrapper');
    const airportSelectWrapper = document.getElementById('airportSelectWrapper');
    const hotelSelectWrapper = document.getElementById('hotelSelectWrapper');
    const returnLocation = document.getElementById('returnLocation');
    const returnLocationLabel = document.getElementById('returnLocationLabel');

    if (isAirport) {
      if (returnInputWrapper) returnInputWrapper.style.display = 'none';
      if (airportSelectWrapper) airportSelectWrapper.style.display = 'block';
      if (hotelSelectWrapper) hotelSelectWrapper.style.display = 'none';
      if (returnLocationLabel) returnLocationLabel.textContent = 'Select Airport';
      if (returnLocation) returnLocation.value = '';
      selectedAddresses['return'] = null;
    } else if (isHotel) {
      if (returnInputWrapper) returnInputWrapper.style.display = 'none';
      if (airportSelectWrapper) airportSelectWrapper.style.display = 'none';
      if (hotelSelectWrapper) hotelSelectWrapper.style.display = 'block';
      if (returnLocationLabel) returnLocationLabel.textContent = 'Select Hotel';
      if (returnLocation) returnLocation.value = '';
      selectedAddresses['return'] = null;
    } else if (isDailyHire) {
      if (returnInputWrapper) returnInputWrapper.style.display = 'flex';
      if (airportSelectWrapper) airportSelectWrapper.style.display = 'none';
      if (hotelSelectWrapper) hotelSelectWrapper.style.display = 'none';
      if (returnLocationLabel) returnLocationLabel.textContent = 'Drop Off';
      if (returnLocation) returnLocation.placeholder = 'Enter drop off location';
    } else {
      if (returnInputWrapper) returnInputWrapper.style.display = 'flex';
      if (airportSelectWrapper) airportSelectWrapper.style.display = 'none';
      if (hotelSelectWrapper) hotelSelectWrapper.style.display = 'none';
      if (returnLocationLabel) returnLocationLabel.textContent = 'Destination';
      if (returnLocation) returnLocation.placeholder = 'Where do you want to go?';
    }

    if (returnLocation) {
      returnLocation.required = true;
    }

    updateRideTierVisibility();

    calculateRouteDistance();
    updateTripSummary();
    scheduleBookingDraftSave(120);
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
      scheduleBookingDraftSave(120);
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
      scheduleBookingDraftSave(120);
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
      scheduleBookingDraftSave(120);
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
      scheduleBookingDraftSave(120);
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
    scheduleRealtimeSlotRefresh(120);
    scheduleBookingDraftSave(120);
  }

  // ===== CHECK AVAILABLE TIERS =====
  async function checkAvailableTiers(seatCapacity = 4) {
    const normalizedSeatCapacity = Number(seatCapacity) >= 7 ? 7 : 4;
    const scheduledDateTime = document.getElementById('scheduledDateTime');
    const scheduledValue = scheduledDateTime ? scheduledDateTime.value : '';
    const payload = { seat_capacity: normalizedSeatCapacity };
    payload.service_type = getCurrentMinicabServiceType();

    if (scheduledValue) {
      payload.pickup_datetime = scheduledValue;
    }

    if (!isDailyHireServiceType(payload.service_type) && Number.isFinite(calculatedDistance) && calculatedDistance > 0) {
      payload.distance_km = calculatedDistance;
    }

    try {
      const response = await fetch('/api/vehicles.php?action=check-available-tiers', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const result = await response.json();
      
      if (result.success) {
        availableTiersByPassengers = result.available_tiers || {};
      }

      // Update ride tier UI based on availability and passenger count
      updateRideTierUI(normalizedSeatCapacity, availableTiersByPassengers);
      return availableTiersByPassengers;
    } catch (err) {
      console.error('Error checking available tiers:', err);
      return availableTiersByPassengers;
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
        'hotel-transfer': 'Hotel Transfer',
        'daily-hire': 'Daily Hire'
      };
      const activeService = serviceTypeEl ? serviceTypeEl.value : 'local';
      const isDailyHire = isDailyHireServiceType(activeService);
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

        if (selectedRideTier && (isDailyHire || calculatedDistance !== null)) {
          const tierLabels = { eco: '🌿 Eco', standard: '⭐ Standard', luxury: '👑 Luxury' };
          const rate = getOnlineRatePerMile(selectedRideTier, selectedSeatCapacity);
          const dailyRate = getDailyHireRate(selectedRideTier, selectedSeatCapacity);
          const distanceMiles = Number.isFinite(calculatedDistance) ? (calculatedDistance * 0.621371) : null;

          if (isDailyHire) {
            rideFare = dailyRate;
          } else {
            rideFare = Math.round(distanceMiles * rate * 100) / 100;
          }

          const summaryTier = document.getElementById('summaryTier');
          if (summaryTierRow) summaryTierRow.style.display = '';
          if (summaryTier) {
            summaryTier.textContent = isDailyHire
              ? ((tierLabels[selectedRideTier] || '') + ' · ' + selectedSeatCapacity + ' seats (£' + dailyRate.toFixed(2) + '/day)')
              : ((tierLabels[selectedRideTier] || '') + ' · ' + selectedSeatCapacity + ' seats (£' + rate.toFixed(2) + '/mile)');
          }
          
          if (summaryFareRow) summaryFareRow.style.display = '';
          const summaryFare = document.getElementById('summaryFare');
          if (summaryFare) summaryFare.textContent = '£' + rideFare.toFixed(2);

          const miniTier = document.getElementById('miniSummaryTier');
          if (miniTier) miniTier.textContent = tierLabels[selectedRideTier] || 'Select tier';
          const miniFare = document.getElementById('miniSummaryFare');
          if (miniFare) miniFare.textContent = '£' + rideFare.toFixed(2);
          const miniDistance = document.getElementById('miniSummaryDistance');
          if (miniDistance) miniDistance.textContent = isDailyHire ? 'Not required for Daily Hire' : (distanceMiles.toFixed(1) + ' miles');
          
          const summaryTotal = document.getElementById('summaryTotal');
          if (summaryTotal) summaryTotal.textContent = '£' + rideFare.toFixed(2);
        } else {
          rideFare = null;
          const summaryTotal = document.getElementById('summaryTotal');
          if (summaryTotal) {
            summaryTotal.textContent = (isDailyHire || calculatedDistance !== null) ? 'Select a ride tier' : 'Set locations first';
          }
          const miniTier = document.getElementById('miniSummaryTier');
          if (miniTier) miniTier.textContent = 'Select tier';
          const miniFare = document.getElementById('miniSummaryFare');
          if (miniFare) miniFare.textContent = (isDailyHire || calculatedDistance !== null) ? 'Select tier' : 'Set locations first';
          const miniDistance = document.getElementById('miniSummaryDistance');
          if (miniDistance) {
            miniDistance.textContent = isDailyHire
              ? 'Not required for Daily Hire'
              : (calculatedDistance !== null ? (calculatedDistance * 0.621371).toFixed(1) + ' miles' : 'Set locations first');
          }
        }
      } else {
        if (summaryDiv) summaryDiv.style.display = 'none';
        const miniTier = document.getElementById('miniSummaryTier');
        if (miniTier) miniTier.textContent = 'Select tier';
        const miniFare = document.getElementById('miniSummaryFare');
        if (miniFare) miniFare.textContent = 'Pick date & time first';
        const miniDistance = document.getElementById('miniSummaryDistance');
        if (miniDistance) miniDistance.textContent = isDailyHire ? 'Not required for Daily Hire' : (calculatedDistance !== null ? (calculatedDistance * 0.621371).toFixed(1) + ' miles' : 'Set locations first');
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

      const serviceType = document.getElementById('serviceType');
      const serviceTypeVal = serviceType ? serviceType.value : '';
      const isDailyHire = isDailyHireServiceType(serviceTypeVal);

      const returnLocation = document.getElementById('returnLocation');
      const destLoc = returnLocation ? returnLocation.value.trim() : '';
      if (!destLoc) {
        if (typeof showToast === 'function') showToast('Please enter a destination.', 'warning');
        return;
      }
      const latestTierAvailability = await checkAvailableTiers(selectedSeatCapacity);

      if (requiresManualRideTierSelection() && !selectedRideTier) {
        if (typeof showToast === 'function') showToast('Please select a ride tier (Eco, Standard, or Luxury).', 'warning');
        return;
      }
      if (selectedRideTier && latestTierAvailability && latestTierAvailability[selectedRideTier] === false) {
        if (typeof showToast === 'function') showToast('Selected ride tier is not available for this time window. Please choose another tier or time.', 'warning');
        return;
      }

      if (isDailyHire && selectedRideTier) {
        rideFare = getDailyHireRate(selectedRideTier, selectedSeatCapacity);
      }

      if (rideFare === null || rideFare <= 0) {
        if (typeof showToast === 'function') showToast('Unable to calculate fare. Please check ride tier selection.', 'warning');
        return;
      }

      if (!isDailyHire && serviceTypeVal === 'local' && Number.isFinite(calculatedDistance) && calculatedDistance > 48.28) {
        if (typeof showToast === 'function') showToast('⚠️ Local Journey must be under 30 miles. Your distance is ' + (calculatedDistance * 0.621371).toFixed(1) + ' miles. Please switch to Long Distance Journey.', 'warning');
        return;
      }
      if (!isDailyHire && serviceTypeVal === 'long-distance' && Number.isFinite(calculatedDistance) && calculatedDistance <= 48.28) {
        if (typeof showToast === 'function') showToast('⚠️ Long Distance Journey must be over 30 miles. Your distance is ' + (calculatedDistance * 0.621371).toFixed(1) + ' miles. Please switch to Local Journey.', 'warning');
        return;
      }
    }

    populatePaymentSummary();
    await refreshAccountBalanceOption();
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
    scheduleBookingDraftSave(80);
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
    scheduleBookingDraftSave(80);
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
      const serviceLabels = { 'local': 'Local Journey', 'long-distance': 'Long Distance', 'airport-transfer': 'Airport Transfer', 'hotel-transfer': 'Hotel Transfer', 'daily-hire': 'Daily Hire' };
      const serviceType = document.getElementById('serviceType');
      const serviceTypeVal = serviceType ? serviceType.value : '';
      const isDailyHire = isDailyHireServiceType(serviceTypeVal);
      const rate = getOnlineRatePerMile(selectedRideTier, selectedSeatCapacity);
      const dailyRate = getDailyHireRate(selectedRideTier, selectedSeatCapacity);

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
      if (paymentReturnLocLabel) paymentReturnLocLabel.textContent = isDailyHire ? 'Drop Off' : 'Destination';
      
      const paymentReturnLoc = document.getElementById('paymentReturnLoc');
      if (paymentReturnLoc) paymentReturnLoc.textContent = returnLoc || (isDailyHire ? 'Not specified' : '');
      
      const paymentPickupLoc = document.getElementById('paymentPickupLoc');
      if (paymentPickupLoc) paymentPickupLoc.textContent = pickupLoc;

      const paymentDailyRate = document.getElementById('paymentDailyRate');
      if (paymentDailyRate) paymentDailyRate.textContent = isDailyHire ? ('£' + dailyRate.toFixed(2) + '/day') : ('£' + rate.toFixed(2) + '/mile');
      
      const paymentDaysRow = document.getElementById('paymentDaysRow');
      if (paymentDaysRow) paymentDaysRow.style.display = '';
      
      const paymentDaysLabel = document.getElementById('paymentDaysLabel');
      if (paymentDaysLabel) paymentDaysLabel.textContent = isDailyHire ? '1 day package' : (calculatedDistance ? (calculatedDistance * 0.621371).toFixed(1) + ' miles' : '-');
      
      const paymentSubtotal = document.getElementById('paymentSubtotal');
      if (paymentSubtotal) paymentSubtotal.textContent = '£' + (rideFare || (isDailyHire ? dailyRate : 0)).toFixed(2);

      const paymentDistanceRow = document.getElementById('paymentDistanceRow');
      if (paymentDistanceRow) paymentDistanceRow.style.display = isDailyHire ? 'none' : '';
      
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
    refreshAccountBalanceOption();
  }

  function getCurrentPaymentTotal() {
    const paymentTotal = document.getElementById('paymentTotal');
    const raw = paymentTotal ? paymentTotal.textContent : '0';
    const num = Number(String(raw || '').replace(/[^0-9.\-]/g, ''));
    return Number.isFinite(num) ? Math.max(0, num) : 0;
  }

  async function refreshAccountBalanceOption() {
    const card = document.getElementById('accountBalanceMethodCard');
    const desc = document.getElementById('accountBalanceMethodDesc');
    if (!card || !desc) return;

    try {
      const res = await fetch(ACCOUNT_BALANCE_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get-balance' })
      });
      const data = await res.json();
      if (!data.success) {
        desc.textContent = 'Unavailable right now';
        card.classList.add('disabled');
        if (selectedPaymentMethod === 'account_balance') {
          selectPaymentMethod('cash');
        }
        return;
      }

      accountBalanceValue = Number(data.balance || 0);
      const total = getCurrentPaymentTotal();
      const sufficient = accountBalanceValue >= total;

      if (total <= 0) {
        desc.textContent = 'Balance: £ ' + accountBalanceValue.toFixed(2);
        card.classList.remove('disabled');
        return;
      }

      if (sufficient) {
        desc.textContent = 'Balance: £ ' + accountBalanceValue.toFixed(2);
        card.classList.remove('disabled');
      } else {
        desc.textContent = 'Balance: £ ' + accountBalanceValue.toFixed(2) + ' (insufficient)';
        card.classList.add('disabled');
        if (selectedPaymentMethod === 'account_balance') {
          selectPaymentMethod('cash');
        }
      }
    } catch (err) {
      desc.textContent = 'Unavailable right now';
      card.classList.add('disabled');
      if (selectedPaymentMethod === 'account_balance') {
        selectPaymentMethod('cash');
      }
    }
  }

  function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
  }

  // ===== PAYMENT METHOD =====
  function selectPaymentMethod(method) {
    if (method !== 'cash' && method !== 'paypal' && method !== 'account_balance') {
      return;
    }
    const targetCard = document.querySelector('.payment-method-card[data-method="' + method + '"]');
    if (targetCard && targetCard.classList.contains('disabled')) {
      if (typeof showToast === 'function') showToast('Insufficient Account Balance for this booking total.', 'warning');
      return;
    }
    selectedPaymentMethod = method;
    document.querySelectorAll('.payment-method-card').forEach(el => {
      el.classList.toggle('active', el.dataset.method === method);
    });
    scheduleBookingDraftSave(80);
  }

  // ===== PROMO CODE =====
  async function applyPromoCode() {
    const promoCodeInput = document.getElementById('promoCodeInput');
    const code = promoCodeInput ? promoCodeInput.value.trim() : '';
    if (!code) {
      if (typeof showToast === 'function') showToast('Please enter a promo code.', 'warning');
      return;
    }

    const isMinicab = selectedBookingType === 'minicab';
    let totalDaysForPromo = 1;
    if (!isMinicab) {
      const pickup = document.getElementById('pickupDate');
      const ret = document.getElementById('returnDate');
      const pickupVal = pickup ? pickup.value : '';
      const retVal = ret ? ret.value : '';
      if (pickupVal && retVal) {
        totalDaysForPromo = Math.max(1, Math.ceil((new Date(retVal) - new Date(pickupVal)) / 86400000));
      }
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
        body: JSON.stringify({ action: 'validate-promo', code, total_days: totalDaysForPromo })
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
    scheduleBookingDraftSave(100);
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
    scheduleBookingDraftSave(100);
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

        if (selectedPaymentMethod === 'account_balance') {
          const total = getCurrentPaymentTotal();
          if (accountBalanceValue < total) {
            if (typeof showToast === 'function') showToast('Insufficient Account Balance. Please top up or choose another method.', 'warning');
            if (btn) {
              btn.disabled = false;
              btn.textContent = 'Confirm & Book';
            }
            return;
          }
        }

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
      promo_code: (function() {
        const promoCodeInput = document.getElementById('promoCodeInput');
        const typedPromoCode = promoCodeInput ? promoCodeInput.value.trim() : '';
        return appliedPromo ? appliedPromo.code : typedPromoCode;
      })(),
      payment_method: selectedPaymentMethod,
      distance_km: calculatedDistance
    };

    if (selectedBookingType === 'minicab') {
      const returnLocation = document.getElementById('returnLocation');
      const serviceType = document.getElementById('serviceType');
      const serviceTypeVal = serviceType ? serviceType.value : '';
      const isDailyHire = isDailyHireServiceType(serviceTypeVal);
      payload.ride_tier = selectedRideTier;
      payload.seat_capacity = selectedSeatCapacity;
      payload.number_of_passengers = selectedSeatCapacity;
      payload.return_location = returnLocation ? returnLocation.value.trim() : '';
      payload.return_date = null;
      payload.ride_fare = isDailyHire ? getDailyHireRate(selectedRideTier, selectedSeatCapacity) : rideFare;
      payload.service_type = serviceTypeVal;
      payload.ride_timing = 'schedule';
      payload.distance_km = isDailyHire ? null : calculatedDistance;
      const scheduledDateTime = document.getElementById('scheduledDateTime');
      payload.pickup_date = scheduledDateTime ? scheduledDateTime.value : '';
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
    clearBookingDraftState();

    const tl = { 'minicab': 'Minicab', 'with-driver': 'With Driver' };
    const bookingType = booking && booking.booking_type ? booking.booking_type : selectedBookingType;
    const rideTierValue = booking && booking.ride_tier ? booking.ride_tier : selectedRideTier;
    const distanceValue = booking && booking.distance_km !== null && booking.distance_km !== undefined
      ? Number(booking.distance_km)
      : calculatedDistance;
    const bookingServiceType = booking && booking.service_type
      ? String(booking.service_type).toLowerCase()
      : getCurrentMinicabServiceType();
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
      html += '<div class="sb-row"><span>Distance</span><span>' + (bookingServiceType === 'daily-hire' ? 'Not required' : (distanceValue ? (distanceValue * 0.621371).toFixed(1) + ' miles' : '-')) + '</span></div>';
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
    return { cash: '💵 Cash', bank_transfer: '🏦 Banking', paypal: '🅿️ PayPal', credit_card: '💳 Card', account_balance: '💷 Account Balance' }[m] || m;
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
    scheduleRealtimeSlotRefresh(120);
    scheduleBookingDraftSave(100);
  }

  function renderRideTierOptions() {
    const grid = document.getElementById('rideTierGrid');
    if (!grid) return;
    const serviceTypeVal = getCurrentMinicabServiceType();
    const isDailyHire = isDailyHireServiceType(serviceTypeVal);
    
    if (calculatedDistance === null && !isDailyHire) {
      grid.innerHTML = '<div style="text-align:center;padding:20px;color:var(--gray-400);font-size:0.85rem;grid-column:1/-1;">Select pickup & destination to see ride options</div>';
      return;
    }

    const tiers = ['eco', 'standard', 'luxury'];

    grid.innerHTML = tiers.map(key => {
      const t = RIDE_TIER_CONFIG[key];
      const isActive = selectedRideTier === key ? ' active' : '';
      const rate = getOnlineRatePerMile(key, selectedSeatCapacity);
      const dailyRate = getDailyHireRate(key, selectedSeatCapacity);
      const badge = t.badge ? '<span class="ride-tier-badge" style="background:' + t.color + ';">' + t.badge + '</span>' : '';
      const desc = t.descriptions[selectedSeatCapacity] || '';

      return '<div class="ride-tier-card ' + key + isActive + '" data-tier="' + key + '" onclick="selectRideTier(\'' + key + '\')" style="border-color:' + t.color + ';">' +
        badge +
        '<span class="ride-tier-icon">' + t.icon + '</span>' +
        '<span class="ride-tier-name">' + t.name + '</span>' +
        '<span class="ride-tier-seats">👥 ' + selectedSeatCapacity + ' seats</span>' +
        '<span class="ride-tier-desc">' + escapeHtml(desc) + '</span>' +
        '<span class="ride-tier-rate">' + (isDailyHire ? ('£' + dailyRate.toFixed(2) + '/day') : ('£' + rate.toFixed(2) + '/mile')) + '</span>' +
      '</div>';
    }).join('');

    // Check available tiers
    checkAvailableTiers(selectedSeatCapacity);
  }

  function calculateRouteDistance() {
    const pickupAddr = selectedAddresses.pickup;
    const returnAddr = selectedAddresses['return'];
    const serviceTypeVal = getCurrentMinicabServiceType();
    const isDailyHire = selectedBookingType === 'minicab' && isDailyHireServiceType(serviceTypeVal);
    calculatedDistance = null;
    transferCost = null;

    const summaryDistanceRow = document.getElementById('summaryDistanceRow');
    if (summaryDistanceRow) summaryDistanceRow.style.display = 'none';

    if (isDailyHire) {
      if (selectedBookingType === 'minicab') {
        renderRideTierOptions();
      }
      updateTripSummary();
      scheduleRealtimeSlotRefresh(120);
      scheduleBookingDraftSave(120);
      return;
    }

    if (!pickupAddr || !returnAddr) {
      if (selectedBookingType === 'minicab') renderRideTierOptions();
      updateTripSummary();
      scheduleRealtimeSlotRefresh(150);
      scheduleBookingDraftSave(120);
      return;
    }
    if (selectedBookingType === 'with-driver') {
      updateTripSummary();
      scheduleBookingDraftSave(120);
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
    scheduleRealtimeSlotRefresh(120);
    scheduleBookingDraftSave(120);
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

  function invalidateExpandedMap(type, delayMs) {
    const container = document.getElementById(type + 'MapContainer');
    if (!container || !container.classList.contains('expanded')) {
      return;
    }

    const map = type === 'pickup' ? pickupMapObj : returnMapObj;
    if (!map) {
      return;
    }

    setTimeout(function() {
      map.invalidateSize();
    }, delayMs || 180);
  }

  function refreshExpandedMapsOnViewportChange() {
    invalidateExpandedMap('pickup', 180);
    invalidateExpandedMap('return', 180);
  }

  function toggleMapExpand(type) {
    const container = document.getElementById(type + 'MapContainer');
    if (container) {
      container.classList.toggle('expanded');
      const map = type === 'pickup' ? pickupMapObj : returnMapObj;
      if (map) setTimeout(() => map.invalidateSize(), 300);
    }
  }

  window.addEventListener('resize', refreshExpandedMapsOnViewportChange);
  window.addEventListener('orientationchange', function() {
    refreshExpandedMapsOnViewportChange();
    setTimeout(refreshExpandedMapsOnViewportChange, 320);
    scheduleBookingDraftSave(60);
    setTimeout(function() {
      scheduleBookingDraftSave(180);
    }, 280);
  });

  async function confirmMapLocation(type) {
    const marker = type === 'pickup' ? pickupMarker : returnMarker;
    const saved = selectedAddresses[type];
    
    if (saved && saved.name) {
      const location = document.getElementById(type + 'Location');
      if (location) location.value = saved.name;
      closeMapPicker(type);
      if (typeof showToast === 'function') showToast('📍 Location confirmed!', 'success');
      calculateRouteDistance();
      scheduleBookingDraftSave(80);
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
    scheduleBookingDraftSave(80);
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
