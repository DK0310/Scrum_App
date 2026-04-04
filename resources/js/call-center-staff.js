(function () {
    const API = '/api/CallCenterStaff.php';
    const ENQUIRY_API = '/api/customer-enquiry.php';
    const UK_DEFAULT_CENTER = { lat: 51.5074, lon: -0.1278 }; // London
    const MIN_PICKUP_LEAD_MINUTES = 1;
    const TIER_ORDER = ['eco', 'standard', 'premium'];
    const SEAT_ORDER = [4, 7];
    const PHONE_BOOKING_FEE = 2.0;
    const PHONE_TIER_RATES = {
        4: { eco: 2.5, standard: 3.0, premium: 4.0 },
        7: { eco: 3.0, standard: 3.5, premium: 5.0 }
    };

    let searchTimer = null;
    let autocompleteTimers = {};
    let selectedAddresses = { pickup: null, return: null };
    let availabilityMap = {};
    let pickupMapObj = null;
    let returnMapObj = null;
    let pickupMarker = null;
    let returnMarker = null;
    let selectedCustomerBalance = 0;
    let availabilityRefreshTimer = null;
    let availabilityRequestSeq = 0;

    const el = {
        form: document.getElementById('ccBookingForm'),
        search: document.getElementById('ccCustomerSearch'),
        results: document.getElementById('ccCustomerResults'),
        customerId: document.getElementById('ccCustomerId'),
        customerName: document.getElementById('ccCustomerName'),
        customerPhone: document.getElementById('ccCustomerPhone'),
        customerEmail: document.getElementById('ccCustomerEmail'),
        rideTier: document.getElementById('ccRideTier'),
        seatCapacity: document.getElementById('ccSeatCapacity'),
        serviceType: document.getElementById('ccServiceType'),
        pickupDateOnly: document.getElementById('ccPickupDateOnly'),
        pickupTimeSlot: document.getElementById('ccPickupTimeSlot'),
        pickupDate: document.getElementById('ccPickupDate'),
        pickupLocation: document.getElementById('ccPickupLocation'),
        returnLocation: document.getElementById('ccReturnLocation'),
        returnInputWrapper: document.getElementById('ccReturnInputWrapper'),
        returnMapBtn: document.getElementById('ccReturnMapBtn'),
        airportSelectWrapper: document.getElementById('ccAirportSelectWrapper'),
        hotelSelectWrapper: document.getElementById('ccHotelSelectWrapper'),
        airportSelect: document.getElementById('ccAirportSelect'),
        hotelSelect: document.getElementById('ccHotelSelect'),
        paymentMethod: document.getElementById('ccPaymentMethod'),
        paymentMethodHint: document.getElementById('ccPaymentMethodHint'),
        estimateDistance: document.getElementById('ccEstimateDistance'),
        estimateTotal: document.getElementById('ccEstimateTotal'),
        specialRequests: document.getElementById('ccSpecialRequests'),
        formStatus: document.getElementById('ccFormStatus'),
        requestsTable: document.getElementById('ccRequestsTable'),
        resetBtn: document.getElementById('ccResetBtn'),
        pickupDateHint: document.getElementById('ccPickupDateHint'),
        pickupSlotHint: document.getElementById('ccPickupSlotHint'),
        rideTierHint: document.getElementById('ccRideTierHint'),
        seatCapacityHint: document.getElementById('ccSeatCapacityHint')
        ,
        enquiryTable: document.getElementById('ccEnquiryTable'),
        replyModal: document.getElementById('ccReplyModal'),
        replyEnquiryId: document.getElementById('ccReplyEnquiryId'),
        replyContent: document.getElementById('ccReplyContent'),
        replyImage: document.getElementById('ccReplyImage'),
        replyMeta: document.getElementById('ccReplyMeta'),
        replySubmitBtn: document.getElementById('ccReplySubmitBtn'),
        createAccountForm: document.getElementById('ccCreateAccountForm'),
        accountUsername: document.getElementById('ccAccountUsername'),
        accountEmail: document.getElementById('ccAccountEmail'),
        accountPhone: document.getElementById('ccAccountPhone'),
        accountDob: document.getElementById('ccAccountDob'),
        createAccountStatus: document.getElementById('ccCreateAccountStatus'),
        createAccountSubmitBtn: document.getElementById('ccCreateAccountSubmitBtn'),
        createAccountResetBtn: document.getElementById('ccCreateAccountResetBtn'),
        createAccountSummary: document.getElementById('ccCreateAccountSummary')
        ,
        requestDetailModal: document.getElementById('ccRequestDetailModal'),
        requestDetailBody: document.getElementById('ccRequestDetailBody')
    };

    function setFormStatus(text, isError) {
        if (!el.formStatus) return;
        el.formStatus.textContent = text || '';
        el.formStatus.style.color = isError ? '#b91c1c' : '#334155';
    }

    function setCreateAccountStatus(text, isError) {
        if (!el.createAccountStatus) return;
        el.createAccountStatus.textContent = text || '';
        el.createAccountStatus.style.color = isError ? '#b91c1c' : '#334155';
    }

    async function apiGet(params) {
        const qs = new URLSearchParams(params);
        const res = await fetch(API + '?' + qs.toString());
        return res.json();
    }

    async function apiPost(body) {
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        return res.json();
    }

    async function enquiryGet(params) {
        const qs = new URLSearchParams(params);
        const res = await fetch(ENQUIRY_API + '?' + qs.toString());
        return res.json();
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function statusClass(status) {
        if (status === 'in_progress') return 'cc-badge cc-in-progress';
        if (status === 'done' || status === 'completed') return 'cc-badge cc-done';
        if (status === 'cancelled') return 'cc-badge cc-cancelled';
        return 'cc-badge cc-pending';
    }

    function normalizeDisplayTier(value) {
        const tier = normalizeTier(value);
        if (tier === 'premium') return 'Premium';
        if (tier === 'standard') return 'Standard';
        if (tier === 'eco') return 'Eco';
        return '-';
    }

    function resetForm() {
        el.customerId.value = '';
        el.form.reset();
        if (el.rideTier) el.rideTier.value = '';
        selectedCustomerBalance = 0;
        setFormStatus('');
        hideCustomerResults();
        selectedAddresses = { pickup: null, return: null };
        updateDestinationMode();
        renderPickupTimeSlots([]);
        syncPickupDateTimeFromParts();
        applyAvailabilityToOptions();
        updatePaymentMethodAvailability();
        refreshBookingEstimatePreview();
        validatePickupDateTimeRealtime();
    }

    function resetCreateAccountForm() {
        if (!el.createAccountForm) return;
        el.createAccountForm.reset();
        setCreateAccountStatus('');
        if (el.createAccountSummary) {
            el.createAccountSummary.style.display = 'none';
            el.createAccountSummary.innerHTML = '';
        }
    }

    function calculateAgeFromDob(dobValue) {
        const dob = new Date(dobValue);
        if (Number.isNaN(dob.getTime())) return -1;
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
            age -= 1;
        }
        return age;
    }

    async function submitCreateAccount(event) {
        event.preventDefault();
        if (!el.createAccountForm) return;

        const username = (el.accountUsername && el.accountUsername.value ? el.accountUsername.value : '').trim();
        const email = (el.accountEmail && el.accountEmail.value ? el.accountEmail.value : '').trim();
        const phone = (el.accountPhone && el.accountPhone.value ? el.accountPhone.value : '').trim();
        const dob = (el.accountDob && el.accountDob.value ? el.accountDob.value : '').trim();

        if (!username || !email || !phone || !dob) {
            setCreateAccountStatus('Please complete all required fields.', true);
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            setCreateAccountStatus('Invalid email format.', true);
            return;
        }

        const phoneDigits = phone.replace(/\D+/g, '');
        if (phoneDigits.length < 10 || phoneDigits.length > 15) {
            setCreateAccountStatus('Phone number must contain 10 to 15 digits.', true);
            return;
        }

        const age = calculateAgeFromDob(dob);
        if (age < 18) {
            setCreateAccountStatus('Customer must be at least 18 years old.', true);
            return;
        }

        setCreateAccountStatus('Creating account...');
        if (el.createAccountSubmitBtn) {
            el.createAccountSubmitBtn.disabled = true;
            el.createAccountSubmitBtn.textContent = 'Creating...';
        }

        try {
            const data = await apiPost({
                action: 'create_customer_account',
                username: username,
                email: email,
                phone: phone,
                dob: dob
            });

            if (!data.success) {
                setCreateAccountStatus(data.message || 'Failed to create account.', true);
                return;
            }

            setCreateAccountStatus('Customer account created successfully.', false);
            if (el.createAccountSummary) {
                const account = data.account || {};
                const warning = data.warning ? '<div style="color:#92400e;margin-top:6px;">' + escapeHtml(data.warning) + '</div>' : '';
                el.createAccountSummary.innerHTML = ''
                    + '<div><strong>Username:</strong> ' + escapeHtml(account.username || username) + '</div>'
                    + '<div><strong>Email:</strong> ' + escapeHtml(account.email || email) + '</div>'
                    + '<div><strong>Phone:</strong> ' + escapeHtml(account.phone || phone) + '</div>'
                    + '<div><strong>Temporary Password:</strong> 123456</div>'
                    + '<div><strong>Email Sent:</strong> ' + (data.email_sent ? 'Yes' : 'No') + '</div>'
                    + warning;
                el.createAccountSummary.style.display = 'block';
            }
            el.createAccountForm.reset();
        } catch (err) {
            setCreateAccountStatus('Network error. Please try again.', true);
        } finally {
            if (el.createAccountSubmitBtn) {
                el.createAccountSubmitBtn.disabled = false;
                el.createAccountSubmitBtn.textContent = 'Create Account';
            }
        }
    }

    function normalizeTier(tier) {
        const value = String(tier || '').trim().toLowerCase();
        if (value === 'luxury') return 'premium';
        return value;
    }

    function normalizeSeat(seat) {
        const n = Number(seat);
        if (n > 4) return 7;
        return 4;
    }

    function isPresetDestinationService() {
        const service = String(el.serviceType && el.serviceType.value ? el.serviceType.value : 'local').toLowerCase();
        return service === 'airport-transfer' || service === 'hotel-transfer';
    }

    function estimateSubtotalAmount() {
        const tier = normalizeTier(el.rideTier && el.rideTier.value ? el.rideTier.value : '');
        const seats = normalizeSeat(el.seatCapacity && el.seatCapacity.value ? el.seatCapacity.value : 4);
        const distanceKm = estimateDistanceKm();
        const rate = PHONE_TIER_RATES[seats] && PHONE_TIER_RATES[seats][tier] ? PHONE_TIER_RATES[seats][tier] : null;
        if (rate === null) {
            return PHONE_BOOKING_FEE;
        }
        if (distanceKm && distanceKm > 0) {
            return Math.round(((distanceKm * 0.621371 * rate) + PHONE_BOOKING_FEE) * 100) / 100;
        }
        return PHONE_BOOKING_FEE;
    }

    function refreshBookingEstimatePreview() {
        if (el.estimateDistance) {
            const d = estimateDistanceKm();
            el.estimateDistance.textContent = (d && d > 0) ? (d.toFixed(1) + ' km') : 'Select locations';
        }

        if (el.estimateTotal) {
            const total = estimateSubtotalAmount();
            el.estimateTotal.textContent = '£' + Number(total || 0).toFixed(2);
        }
    }

    function updatePaymentMethodAvailability() {
        if (!el.paymentMethod) return;
        const accountOpt = Array.from(el.paymentMethod.options).find((opt) => opt.value === 'account_balance');
        if (!accountOpt) return;

        const hasExistingCustomer = !!(el.customerId && el.customerId.value);
        const estimatedTotal = estimateSubtotalAmount();
        const canUseBalance = hasExistingCustomer && selectedCustomerBalance >= estimatedTotal;

        accountOpt.disabled = !canUseBalance;
        accountOpt.textContent = 'Account Balance' + (canUseBalance ? '' : ' (Unavailable)');

        if (el.paymentMethodHint) {
            if (!hasExistingCustomer) {
                el.paymentMethodHint.textContent = 'Account balance is disabled until you select an existing customer account.';
                el.paymentMethodHint.className = 'cc-help cc-help-error';
            } else if (!canUseBalance) {
                el.paymentMethodHint.textContent = 'Insufficient account balance for estimated total £' + estimatedTotal.toFixed(2) + '.';
                el.paymentMethodHint.className = 'cc-help cc-help-error';
            } else {
                el.paymentMethodHint.textContent = 'Account balance available. Current balance: £' + selectedCustomerBalance.toFixed(2);
                el.paymentMethodHint.className = 'cc-help cc-help-ok';
            }
        }

        if (!canUseBalance && el.paymentMethod.value === 'account_balance') {
            el.paymentMethod.value = 'cash';
        }

        refreshBookingEstimatePreview();
    }

    function applyPresetDestination(selectEl) {
        if (!selectEl) return;
        const selected = selectEl.options[selectEl.selectedIndex];
        if (!selected || !selected.value) return;

        const name = String(selected.value || '').trim();
        const lat = parseFloat(selected.getAttribute('data-lat') || '0');
        const lon = parseFloat(selected.getAttribute('data-lon') || '0');

        if (el.returnLocation) {
            el.returnLocation.value = name;
        }

        if (Number.isFinite(lat) && Number.isFinite(lon) && (lat !== 0 || lon !== 0)) {
            selectedAddresses.return = { lat: lat, lon: lon, name: name };
            updateMapCoords('return', lat, lon, name);
        } else {
            selectedAddresses.return = null;
        }

        updatePaymentMethodAvailability();
        scheduleRealtimeAvailabilityRefresh(150);
    }

    function updateDestinationMode() {
        const service = String(el.serviceType && el.serviceType.value ? el.serviceType.value : 'local').toLowerCase();
        const isAirport = service === 'airport-transfer';
        const isHotel = service === 'hotel-transfer';
        const isPreset = isAirport || isHotel;

        if (el.returnInputWrapper) {
            el.returnInputWrapper.style.display = isPreset ? 'none' : 'flex';
        }
        if (el.returnMapBtn) {
            el.returnMapBtn.style.display = isPreset ? 'none' : 'inline-flex';
        }
        if (el.airportSelectWrapper) {
            el.airportSelectWrapper.style.display = isAirport ? 'block' : 'none';
        }
        if (el.hotelSelectWrapper) {
            el.hotelSelectWrapper.style.display = isHotel ? 'block' : 'none';
        }

        const returnMapContainer = document.getElementById('returnMapContainer');
        if (isPreset && returnMapContainer) {
            returnMapContainer.style.display = 'none';
            returnMapContainer.classList.remove('expanded');
        }

        if (isAirport) {
            applyPresetDestination(el.airportSelect);
        } else if (isHotel) {
            applyPresetDestination(el.hotelSelect);
        }
    }

    function inferTier(vehicle) {
        const explicitTier = normalizeTier(
            vehicle.ride_tier || vehicle.vehicle_tier || vehicle.tier || vehicle.service_tier || ''
        );
        if (TIER_ORDER.includes(explicitTier)) {
            return explicitTier;
        }

        const categoryTier = normalizeTier(vehicle.category || '');
        if (TIER_ORDER.includes(categoryTier)) {
            return categoryTier;
        }

        const price = Number(vehicle.price_per_day || 0);
        if (price > 100) return 'premium';
        if (price > 40) return 'standard';
        return 'eco';
    }

    function buildAvailabilityMap(vehicles) {
        const map = {
            eco: { 4: 0, 7: 0 },
            standard: { 4: 0, 7: 0 },
            premium: { 4: 0, 7: 0 }
        };

        (vehicles || []).forEach((v) => {
            const status = String(v.status || '').toLowerCase();
            if (status !== 'available') return;

            const seats = normalizeSeat(v.seats);
            const tier = inferTier(v);

            map[tier][seats] = (map[tier][seats] || 0) + 1;
        });

        return map;
    }

    function buildAvailabilityMapFromMatrix(matrix) {
        const map = {
            eco: { 4: 0, 7: 0 },
            standard: { 4: 0, 7: 0 },
            premium: { 4: 0, 7: 0 }
        };

        if (!matrix || typeof matrix !== 'object') {
            return map;
        }

        const toCount = function (value) {
            const n = Number(value || 0);
            return Number.isFinite(n) && n > 0 ? n : 0;
        };

        map.eco[4] = toCount(matrix.eco && matrix.eco['4']);
        map.eco[7] = toCount(matrix.eco && matrix.eco['7']);
        map.standard[4] = toCount(matrix.standard && matrix.standard['4']);
        map.standard[7] = toCount(matrix.standard && matrix.standard['7']);
        map.premium[4] = toCount(matrix.luxury && matrix.luxury['4']);
        map.premium[7] = toCount(matrix.luxury && matrix.luxury['7']);

        return map;
    }

    function setOptionAvailability(selectEl, value, available, count) {
        if (!selectEl) return;
        const option = Array.from(selectEl.options).find((o) => String(o.value) === String(value));
        if (!option) return;
        option.disabled = !available;
        option.textContent = !available
            ? option.textContent.replace(/\s*\(.*\)$/, '') + ' (Unavailable)'
            : option.textContent.replace(/\s*\(.*\)$/, '') + (typeof count === 'number' ? ' (' + count + ')' : '');
    }

    function applyAvailabilityToOptions() {
        const map = availabilityMap;
        if (!map || !el.rideTier || !el.seatCapacity) return;

        TIER_ORDER.forEach((tier) => {
            const tierCount = (map[tier][4] || 0) + (map[tier][7] || 0);
            setOptionAvailability(el.rideTier, tier, tierCount > 0, tierCount);
        });

        const selectedTier = normalizeTier(el.rideTier.value || '');

        // Seat capacity must be selected only after ride tier is selected.
        if (!selectedTier) {
            el.seatCapacity.disabled = true;
            SEAT_ORDER.forEach((seat) => {
                const count = TIER_ORDER.reduce((sum, t) => sum + (map[t][seat] || 0), 0);
                setOptionAvailability(el.seatCapacity, seat, false, count);
            });

            if (el.rideTierHint) {
                el.rideTierHint.textContent = 'Please select a ride tier to view seat availability.';
                el.rideTierHint.className = 'cc-help';
            }

            if (el.seatCapacityHint) {
                el.seatCapacityHint.textContent = 'Please select ride tier first.';
                el.seatCapacityHint.className = 'cc-help';
            }

            updatePaymentMethodAvailability();

            return;
        }

        el.seatCapacity.disabled = false;
        SEAT_ORDER.forEach((seat) => {
            const count = selectedTier && map[selectedTier] ? (map[selectedTier][seat] || 0) : 0;
            setOptionAvailability(el.seatCapacity, seat, count > 0, count);
        });

        // Ensure current values are valid after availability update
        const selectedSeatOption = el.seatCapacity.options[el.seatCapacity.selectedIndex];
        if (selectedSeatOption && selectedSeatOption.disabled) {
            const firstEnabledSeat = Array.from(el.seatCapacity.options).find((o) => !o.disabled && o.value);
            el.seatCapacity.value = firstEnabledSeat ? firstEnabledSeat.value : '';
        }

        const tierValue = normalizeTier(el.rideTier.value || '');
        const tierCount = tierValue && map[tierValue] ? ((map[tierValue][4] || 0) + (map[tierValue][7] || 0)) : 0;
        if (el.rideTierHint) {
            if (tierCount > 0) {
                el.rideTierHint.textContent = 'Selected tier has ' + tierCount + ' available vehicle(s).';
                el.rideTierHint.className = 'cc-help cc-help-ok';
            } else {
                el.rideTierHint.textContent = 'Please choose a tier that is currently available.';
                el.rideTierHint.className = 'cc-help cc-help-error';
            }
        }

        const seat = Number(el.seatCapacity.value || 0);
        const seatCount = tierValue && map[tierValue] ? (map[tierValue][seat] || 0) : 0;
        if (el.seatCapacityHint) {
            if (seatCount > 0) {
                el.seatCapacityHint.textContent = 'Selected seat capacity has ' + seatCount + ' available vehicle(s) for this tier.';
                el.seatCapacityHint.className = 'cc-help cc-help-ok';
            } else {
                el.seatCapacityHint.textContent = 'Selected seat capacity is unavailable for this tier.';
                el.seatCapacityHint.className = 'cc-help cc-help-error';
            }
        }

        updatePaymentMethodAvailability();
    }

    function getRealtimeAvailabilityPayload() {
        const payload = {};
        const pickupValue = String(el.pickupDate && el.pickupDate.value ? el.pickupDate.value : '').trim();
        if (pickupValue !== '') {
            payload.pickup_datetime = pickupValue;
        }

        const estimatedDistanceKm = estimateDistanceKm();
        if (Number.isFinite(estimatedDistanceKm) && estimatedDistanceKm > 0) {
            payload.distance_km = Number(estimatedDistanceKm.toFixed(2));
        }

        return payload;
    }

    async function loadAvailabilityOptions() {
        const requestId = ++availabilityRequestSeq;
        try {
            const matrixRes = await fetch('/api/vehicles.php?action=tier-seat-availability', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(getRealtimeAvailabilityPayload())
            });
            const matrixData = await matrixRes.json();

            if (requestId !== availabilityRequestSeq) {
                return;
            }

            if (!matrixData.success) {
                return;
            }

            availabilityMap = buildAvailabilityMapFromMatrix(matrixData.availability || {});
            applyAvailabilityToOptions();
        } catch (err) {
            // Keep form usable with current static options if availability fetch fails.
        }
    }

    function scheduleRealtimeAvailabilityRefresh(delayMs) {
        if (availabilityRefreshTimer) {
            clearTimeout(availabilityRefreshTimer);
        }
        availabilityRefreshTimer = setTimeout(function () {
            loadAvailabilityOptions();
            loadPickupTimeSlotAvailability();
        }, delayMs || 200);
    }

    function hideCustomerResults() {
        if (!el.results) return;
        el.results.style.display = 'none';
        el.results.innerHTML = '';
    }

    function selectCustomer(customer) {
        el.customerId.value = customer.id || '';
        el.customerName.value = customer.full_name || '';
        el.customerPhone.value = customer.phone || '';
        el.customerEmail.value = customer.email || '';
        selectedCustomerBalance = Number(customer.account_balance || 0);
        hideCustomerResults();
        updatePaymentMethodAvailability();
        refreshBookingEstimatePreview();
    }

    function renderCustomerResults(customers) {
        if (!customers || customers.length === 0) {
        refreshBookingEstimatePreview();
            hideCustomerResults();
            return;
        }

        el.results.innerHTML = customers.map((c) => {
            const name = escapeHtml(c.full_name || 'Unknown');
            const email = escapeHtml(c.email || '');
            const phone = escapeHtml(c.phone || '');
            const balance = Number(c.account_balance || 0);
            return '<div class="cc-customer-item" data-id="' + escapeHtml(c.id) + '" data-name="' + name + '" data-email="' + email + '" data-phone="' + phone + '" data-balance="' + escapeHtml(String(balance)) + '">' +
                '<strong>' + name + '</strong><br><small>' + email + ' | ' + phone + ' | Balance: £' + balance.toFixed(2) + '</small></div>';
        }).join('');

        el.results.style.display = 'block';

        Array.from(el.results.querySelectorAll('.cc-customer-item')).forEach((item) => {
            item.addEventListener('click', () => {
                selectCustomer({
                    id: item.getAttribute('data-id') || '',
                    full_name: item.getAttribute('data-name') || '',
                    email: item.getAttribute('data-email') || '',
                    phone: item.getAttribute('data-phone') || '',
                    account_balance: parseFloat(item.getAttribute('data-balance') || '0') || 0
                });
            });
        });
    }

    async function searchCustomers() {
        const q = (el.search.value || '').trim();
        if (q.length < 2) {
            hideCustomerResults();
            return;
        }

        const data = await apiGet({ action: 'search_customers', q });
        if (!data.success) {
            hideCustomerResults();
            return;
        }
        renderCustomerResults(data.customers || []);
    }

    async function loadRequests() {
        el.requestsTable.innerHTML = '<div class="cc-request-card">Loading...</div>';
        const data = await apiGet({ action: 'get_my_requests', limit: 100 });
        if (!data.success) {
            el.requestsTable.innerHTML = '<div class="cc-request-card">Cannot load requests</div>';
            return;
        }

        const rows = data.requests || [];
        if (rows.length === 0) {
            el.requestsTable.innerHTML = '<div class="cc-request-card">No request yet</div>';
            return;
        }

        el.requestsTable.innerHTML = rows.map((r) => {
            const bookingId = escapeHtml(r.id || '');
            const ref = escapeHtml(r.booking_ref || bookingId);
            const customer = escapeHtml(r.customer_name || '-');
            const date = escapeHtml(formatPickupDisplay(r.pickup_date, r.pickup_time));
            const status = escapeHtml(r.status || 'pending');
            const tierLabel = normalizeDisplayTier(r.ride_tier || r.service_tier || r.tier || '');

            let actionHtml = '<button class="cc-btn cc-btn-danger" data-action="delete" data-id="' + bookingId + '">Delete</button>';
            if (status === 'pending' || status === 'in_progress') {
                actionHtml = '<button class="cc-btn cc-btn-secondary" data-action="cancel" data-id="' + bookingId + '">Cancel</button> ' + actionHtml;
            }

            return '<article class="cc-request-card" data-role="open-request" data-id="' + bookingId + '">'
                + '<div class="cc-request-head">'
                +   '<div>'
                +     '<h4 class="cc-request-title">' + customer + '</h4>'
                +     '<div class="cc-request-meta">Request ID: ' + ref + '</div>'
                +   '</div>'
                +   '<span class="' + statusClass(status) + '">' + status + '</span>'
                + '</div>'
                + '<div class="cc-request-row">'
                +   '<div>Pickup: ' + date + '</div>'
                +   '<div>Tier: ' + escapeHtml(tierLabel) + '</div>'
                + '</div>'
                + '<div class="cc-request-actions">' + actionHtml + '</div>'
                + '</article>';
        }).join('');

        Array.from(el.requestsTable.querySelectorAll('article[data-role="open-request"]')).forEach((card) => {
            card.addEventListener('click', async (evt) => {
                const target = evt.target;
                if (target instanceof Element && target.closest('button[data-action]')) {
                    return;
                }
                const bookingId = card.getAttribute('data-id') || '';
                if (!bookingId) return;
                await openRequestDetailModal(bookingId);
            });
        });

        Array.from(el.requestsTable.querySelectorAll('button[data-action]')).forEach((btn) => {
            btn.addEventListener('click', async () => {
                const action = btn.getAttribute('data-action');
                const bookingId = btn.getAttribute('data-id');
                if (!bookingId) return;

                if (action === 'cancel') {
                    await submitCancel(bookingId);
                }
                if (action === 'delete') {
                    await submitDelete(bookingId);
                }
            });
        });
    }

    function formatDateTime(value) {
        if (!value) return '-';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return String(value);
        return d.toLocaleString('en-GB', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatPickupDisplay(dateValue, timeValue) {
        const datePart = dateValue ? String(dateValue).slice(0, 10) : '';
        let timePart = String(timeValue || '').trim();

        if (!timePart) {
            const rawDate = String(dateValue || '');
            const dtMatch = rawDate.match(/[T\s](\d{2}):(\d{2})/);
            if (dtMatch) {
                const hour = Number(dtMatch[1]);
                const minute = Number(dtMatch[2]);
                if (Number.isFinite(hour) && Number.isFinite(minute)) {
                    const suffix = hour >= 12 ? 'PM' : 'AM';
                    const hour12 = (hour % 12) === 0 ? 12 : (hour % 12);
                    timePart = String(hour12).padStart(2, '0') + ':' + String(minute).padStart(2, '0') + suffix;
                }
            }
        } else {
            const parsed = timePart.match(/^(\d{1,2}):(\d{2})\s*([AaPp][Mm])?$/);
            if (parsed) {
                let hour = Number(parsed[1]);
                const minute = Number(parsed[2]);
                const meridiem = parsed[3] ? parsed[3].toUpperCase() : '';

                if (!meridiem) {
                    const suffix = hour >= 12 ? 'PM' : 'AM';
                    const hour12 = (hour % 12) === 0 ? 12 : (hour % 12);
                    timePart = String(hour12).padStart(2, '0') + ':' + String(minute).padStart(2, '0') + suffix;
                } else {
                    hour = hour === 0 ? 12 : hour;
                    timePart = String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0') + meridiem;
                }
            }
        }

        if (datePart && timePart) {
            return datePart + ' ' + timePart;
        }
        if (datePart) {
            return datePart;
        }
        if (timePart) {
            return timePart;
        }
        return '-';
    }

    function extractPickupTimeFromInput(datetimeLocalValue) {
        const raw = String(datetimeLocalValue || '').trim();
        const m = raw.match(/^(\d{4}-\d{2}-\d{2})[T\s](\d{2}):(\d{2})/);
        if (!m) {
            return '';
        }

        const hour24 = Number(m[2]);
        const minute = Number(m[3]);
        if (!Number.isFinite(hour24) || !Number.isFinite(minute)) {
            return '';
        }

        const suffix = hour24 >= 12 ? 'PM' : 'AM';
        const hour12 = (hour24 % 12) === 0 ? 12 : (hour24 % 12);
        return String(hour12).padStart(2, '0') + ':' + String(minute).padStart(2, '0') + suffix;
    }

    async function openRequestDetailModal(bookingId) {
        if (!el.requestDetailModal || !el.requestDetailBody) return;

        el.requestDetailBody.innerHTML = 'Loading details...';
        el.requestDetailModal.classList.add('open');

        try {
            const data = await apiGet({ action: 'get_request_detail', booking_id: bookingId });
            if (!data.success || !data.request) {
                el.requestDetailBody.innerHTML = '<div style="color:#b91c1c;">' + escapeHtml(data.message || 'Cannot load request detail') + '</div>';
                return;
            }

            const r = data.request;
            const tier = normalizeDisplayTier(r.ride_tier || r.service_tier || r.tier || '');
            const seats = escapeHtml(String(r.number_of_passengers || r.seat_capacity || '-'));
            const amount = '£' + Number(r.total_amount || 0).toFixed(2);
            const vehicle = escapeHtml([r.brand, r.model, r.license_plate].filter(Boolean).join(' ') || '-');

            el.requestDetailBody.innerHTML = ''
                + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">'
                +   '<div><strong>Request ID:</strong><br>' + escapeHtml(String(r.booking_ref || r.id || '-')) + '</div>'
                +   '<div><strong>Status:</strong><br>' + escapeHtml(String(r.status || '-')) + '</div>'
                +   '<div><strong>Customer:</strong><br>' + escapeHtml(String(r.customer_name || '-')) + '</div>'
                +   '<div><strong>Pickup Time:</strong><br>' + escapeHtml(formatPickupDisplay(r.pickup_date, r.pickup_time)) + '</div>'
                +   '<div><strong>Pickup:</strong><br>' + escapeHtml(String(r.pickup_location || '-')) + '</div>'
                +   '<div><strong>Destination:</strong><br>' + escapeHtml(String(r.return_location || '-')) + '</div>'
                +   '<div><strong>Tier:</strong><br>' + escapeHtml(tier) + '</div>'
                +   '<div><strong>Seats:</strong><br>' + seats + '</div>'
                +   '<div><strong>Vehicle:</strong><br>' + vehicle + '</div>'
                +   '<div><strong>Total:</strong><br>' + amount + '</div>'
                + '</div>';
        } catch (err) {
            el.requestDetailBody.innerHTML = '<div style="color:#b91c1c;">Network error while loading request detail.</div>';
        }
    }

    async function loadEnquiries() {
        if (!el.enquiryTable) return;
        el.enquiryTable.innerHTML = '<tr><td colspan="6">Loading enquiries...</td></tr>';

        try {
            const data = await enquiryGet({ action: 'list-staff-enquiries' });
            if (!data.success) {
                el.enquiryTable.innerHTML = '<tr><td colspan="6">Cannot load enquiries</td></tr>';
                return;
            }

            const rows = data.enquiries || [];
            if (rows.length === 0) {
                el.enquiryTable.innerHTML = '<tr><td colspan="6">No enquiries</td></tr>';
                return;
            }

            el.enquiryTable.innerHTML = rows.map((e) => {
                const id = escapeHtml(e.id || '');
                const created = escapeHtml(formatDateTime(e.created_at));
                const type = e.enquiry_type === 'trip' ? 'Trip' : 'General';
                const customer = escapeHtml(e.customer_name || 'Unknown');
                const status = escapeHtml(e.status || 'open');
                const preview = escapeHtml(String(e.content || '').substring(0, 100));
                const replied = !!e.reply_id || status === 'replied';

                let actionHtml = '<button class="cc-btn cc-btn-primary" data-action="reply" data-id="' + id + '">Reply</button>';
                if (replied) {
                    actionHtml = '<button class="cc-btn cc-btn-secondary" disabled>Replied</button>';
                }

                return '<tr>'
                    + '<td>' + created + '</td>'
                    + '<td>' + escapeHtml(type) + '</td>'
                    + '<td>' + customer + '</td>'
                    + '<td><span class="' + statusClass(status) + '">' + status + '</span></td>'
                    + '<td>' + preview + (String(e.content || '').length > 100 ? '...' : '') + '</td>'
                    + '<td>' + actionHtml + '</td>'
                    + '</tr>';
            }).join('');

            Array.from(el.enquiryTable.querySelectorAll('button[data-action="reply"]')).forEach((btn) => {
                btn.addEventListener('click', () => {
                    const enquiryId = btn.getAttribute('data-id') || '';
                    if (!enquiryId) return;
                    openEnquiryReplyModal(enquiryId, rows);
                });
            });
        } catch (err) {
            el.enquiryTable.innerHTML = '<tr><td colspan="6">Network error</td></tr>';
        }
    }

    function openEnquiryReplyModal(enquiryId, rows) {
        if (!el.replyModal || !el.replyEnquiryId || !el.replyContent) return;
        const row = (rows || []).find((r) => String(r.id) === String(enquiryId));
        el.replyEnquiryId.value = enquiryId;
        el.replyContent.value = '';
        if (el.replyImage) {
            el.replyImage.value = '';
        }
        if (el.replyMeta) {
            const type = row && row.enquiry_type === 'trip' ? 'Trip' : 'General';
            const customer = row ? (row.customer_name || 'Unknown') : 'Unknown';
            el.replyMeta.textContent = 'Enquiry: ' + enquiryId.substring(0, 8) + ' | Type: ' + type + ' | Customer: ' + customer;
        }
        el.replyModal.classList.add('open');
    }

    async function submitEnquiryReply() {
        if (!el.replyEnquiryId || !el.replyContent) return;

        const enquiryId = (el.replyEnquiryId.value || '').trim();
        const content = (el.replyContent.value || '').trim();
        const image = el.replyImage && el.replyImage.files ? (el.replyImage.files[0] || null) : null;

        if (!enquiryId || !content) {
            alert('Reply content is required.');
            return;
        }

        if (el.replySubmitBtn) {
            el.replySubmitBtn.disabled = true;
            el.replySubmitBtn.textContent = 'Sending...';
        }

        try {
            const formData = new FormData();
            formData.append('action', 'reply-enquiry');
            formData.append('enquiry_id', enquiryId);
            formData.append('content', content);
            if (image) {
                formData.append('image', image);
            }

            const res = await fetch(ENQUIRY_API, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (!data.success) {
                alert(data.message || 'Failed to send reply');
                return;
            }

            if (typeof closeModal === 'function') {
                closeModal('ccReplyModal');
            } else if (el.replyModal) {
                el.replyModal.classList.remove('open');
            }
            await loadEnquiries();
        } catch (err) {
            alert('Network error');
        } finally {
            if (el.replySubmitBtn) {
                el.replySubmitBtn.disabled = false;
                el.replySubmitBtn.textContent = 'Send Reply';
            }
        }
    }

    async function submitCancel(bookingId) {
        if (!confirm('Cancel this request?')) return;
        const data = await apiPost({ action: 'cancel_request', booking_id: bookingId });
        if (!data.success) {
            alert(data.message || 'Cancel failed');
            return;
        }
        await loadRequests();
    }

    async function submitDelete(bookingId) {
        if (!confirm('Delete this request?')) return;
        const data = await apiPost({ action: 'delete_request', booking_id: bookingId });
        if (!data.success) {
            alert(data.message || 'Delete failed');
            return;
        }
        await loadRequests();
    }

    function haversineDistance(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function estimateDistanceKm() {
        const p = selectedAddresses.pickup;
        const r = selectedAddresses.return;
        if (!p || !r) return null;
        return Math.round(haversineDistance(p.lat, p.lon, r.lat, r.lon) * 1.3 * 10) / 10;
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
        for (let minutes = 0; minutes < 24 * 60; minutes += 30) {
            const hour = Math.floor(minutes / 60);
            const minute = minutes % 60;
            slots.push(String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0'));
        }
        return slots;
    }

    function compareTimeValues(a, b) {
        const [ah, am] = String(a || '00:00').split(':').map(Number);
        const [bh, bm] = String(b || '00:00').split(':').map(Number);
        return (ah * 60 + am) - (bh * 60 + bm);
    }

    function getMinAllowedSlotTimeForDate(dateValue) {
        if (!dateValue) return null;
        const now = new Date();
        now.setMinutes(now.getMinutes() + MIN_PICKUP_LEAD_MINUTES, 0, 0);
        const today = now.toISOString().split('T')[0];
        if (dateValue !== today) return null;
        return String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
    }

    function syncPickupDateTimeFromParts() {
        if (!el.pickupDate || !el.pickupDateOnly || !el.pickupTimeSlot) return '';
        const dateValue = el.pickupDateOnly.value || '';
        const timeValue = el.pickupTimeSlot.value || '';
        const combined = (dateValue && timeValue) ? (dateValue + 'T' + timeValue) : '';
        el.pickupDate.value = combined;
        return combined;
    }

    function renderPickupTimeSlots(unavailableTimes) {
        if (!el.pickupDateOnly || !el.pickupTimeSlot) return;

        const selectedDate = el.pickupDateOnly.value || '';
        const previousValue = el.pickupTimeSlot.value || '';
        const blockedSet = new Set(Array.isArray(unavailableTimes) ? unavailableTimes : []);
        const minTodayTime = getMinAllowedSlotTimeForDate(selectedDate);

        let enabledCount = 0;
        const options = ['<option value="">Select time</option>'];
        buildDailyTimeSlots().forEach((slot) => {
            let disabled = blockedSet.has(slot);
            if (minTodayTime && compareTimeValues(slot, minTodayTime) < 0) {
                disabled = true;
            }

            if (!disabled) {
                enabledCount += 1;
            }

            options.push('<option value="' + slot + '"' + (disabled ? ' disabled' : '') + '>' + formatTimeSlotLabel(slot) + (disabled ? ' (Unavailable)' : '') + '</option>');
        });

        el.pickupTimeSlot.innerHTML = options.join('');
        if (previousValue && !blockedSet.has(previousValue) && (!minTodayTime || compareTimeValues(previousValue, minTodayTime) >= 0)) {
            el.pickupTimeSlot.value = previousValue;
        } else {
            el.pickupTimeSlot.value = '';
        }

        syncPickupDateTimeFromParts();

        if (el.pickupSlotHint) {
            if (!selectedDate) {
                el.pickupSlotHint.textContent = 'Choose a date to load available time slots.';
                el.pickupSlotHint.className = 'cc-help';
            } else if (enabledCount > 0) {
                el.pickupSlotHint.textContent = 'Available slots: ' + enabledCount + ' for selected date.';
                el.pickupSlotHint.className = 'cc-help cc-help-ok';
            } else {
                el.pickupSlotHint.textContent = 'No available slot for selected date. Please choose another date.';
                el.pickupSlotHint.className = 'cc-help cc-help-error';
            }
        }
    }

    async function loadPickupTimeSlotAvailability() {
        if (!el.pickupDateOnly) return;
        const dateValue = String(el.pickupDateOnly.value || '').trim();
        if (!dateValue) {
            renderPickupTimeSlots([]);
            return;
        }

        const payload = {
            date: dateValue,
            ride_tier: normalizeTier(el.rideTier && el.rideTier.value ? el.rideTier.value : ''),
            seat_capacity: normalizeSeat(el.seatCapacity && el.seatCapacity.value ? el.seatCapacity.value : 4)
        };

        const distanceKm = estimateDistanceKm();
        if (distanceKm && distanceKm > 0) {
            payload.distance_km = Number(distanceKm.toFixed(2));
        }

        try {
            const res = await fetch('/api/vehicles.php?action=unavailable-time-slots', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.success) {
                renderPickupTimeSlots([]);
                return;
            }

            renderPickupTimeSlots(data.unavailable_times || []);
        } catch (err) {
            renderPickupTimeSlots([]);
        }
    }

    async function submitRequest(event) {
        event.preventDefault();

        if (!validatePickupDateTimeRealtime()) {
            setFormStatus('Please choose a valid pickup date & time.', true);
            return;
        }

        await loadAvailabilityOptions();

        const selectedTier = normalizeTier(el.rideTier.value || '');
        const selectedSeat = normalizeSeat(el.seatCapacity ? el.seatCapacity.value : 4);
        if (!availabilityMap[selectedTier] || (availabilityMap[selectedTier][selectedSeat] || 0) <= 0) {
            setFormStatus('Selected ride tier and seat capacity are not available. Please choose another option.', true);
            return;
        }

        setFormStatus('Submitting request...');

        const payload = {
            action: 'booking_by_request',
            customer_id: el.customerId.value || null,
            customer_name: el.customerName.value,
            customer_phone: el.customerPhone.value,
            customer_email: el.customerEmail.value,
            ride_tier: selectedTier,
            seat_capacity: selectedSeat,
            service_type: el.serviceType && el.serviceType.value ? el.serviceType.value : 'local',
            pickup_date: el.pickupDate.value,
            pickup_time: extractPickupTimeFromInput(el.pickupDate.value),
            pickup_location: el.pickupLocation.value,
            return_location: el.returnLocation.value,
            payment_method: el.paymentMethod.value,
            special_requests: el.specialRequests.value,
            distance_km: estimateDistanceKm()
        };

        if (payload.payment_method === 'account_balance') {
            const estimatedTotal = estimateSubtotalAmount();
            if (!el.customerId.value) {
                setFormStatus('Account balance payment requires an existing customer account.', true);
                return;
            }
            if (selectedCustomerBalance < estimatedTotal) {
                setFormStatus('Insufficient account balance for estimated total £' + estimatedTotal.toFixed(2) + '.', true);
                return;
            }
        }

        const data = await apiPost(payload);
        if (!data.success) {
            setFormStatus(data.message || 'Submit failed', true);
            return;
        }

        const ref = (data.booking && (data.booking.booking_ref || data.booking.id)) || 'OK';
        const assignedVehicle = (data.booking && data.booking.assigned_vehicle) ? (' | Vehicle: ' + data.booking.assigned_vehicle) : '';
        setFormStatus('Request created: ' + ref + assignedVehicle);
        resetForm();
        await loadRequests();
    }

    function updateMapCoords(type, lat, lon, name) {
        const coordsEl = document.getElementById(type + 'MapCoords');
        if (!coordsEl) return;
        const short = name ? (name.length > 60 ? name.substring(0, 60) + '...' : name) : (lat.toFixed(5) + ', ' + lon.toFixed(5));
        coordsEl.textContent = '📍 ' + short;
    }

    function moveMapToLocation(type, lat, lon) {
        const map = type === 'pickup' ? pickupMapObj : returnMapObj;
        const marker = type === 'pickup' ? pickupMarker : returnMarker;
        if (!map || !marker) return;
        const latlng = L.latLng(lat, lon);
        marker.setLatLng(latlng);
        map.setView(latlng, 16, { animate: true });
    }

    async function searchNominatim(query, dropdown, input, type) {
        try {
            const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query) + '&limit=5&addressdetails=1&countrycodes=gb&viewbox=-8.9,60.9,1.8,49.8&bounded=1', {
                headers: { 'Accept-Language': 'en' }
            });
            const results = await res.json();
            if (results.length === 0) {
                dropdown.style.display = 'none';
                return;
            }

            dropdown.innerHTML = results.map((r) => {
                const parts = (r.display_name || '').split(',');
                const main = parts.slice(0, 2).join(',').trim();
                const sub = parts.slice(2).join(',').trim();
                return '<div class="autocomplete-item" data-lat="' + r.lat + '" data-lon="' + r.lon + '" data-name="' + escapeHtml(r.display_name) + '">' +
                    '<div class="ac-main">' + escapeHtml(main) + '</div>' +
                    (sub ? '<div class="ac-sub">' + escapeHtml(sub) + '</div>' : '') +
                '</div>';
            }).join('');

            dropdown.style.display = 'block';

            Array.from(dropdown.querySelectorAll('.autocomplete-item')).forEach((item) => {
                item.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    const lat = parseFloat(this.getAttribute('data-lat') || '0');
                    const lon = parseFloat(this.getAttribute('data-lon') || '0');
                    const name = this.getAttribute('data-name') || '';

                    input.value = name;
                    dropdown.style.display = 'none';
                    selectedAddresses[type] = { lat: lat, lon: lon, name: name };
                    moveMapToLocation(type, lat, lon);
                    updateMapCoords(type, lat, lon, name);
                    updatePaymentMethodAvailability();
                    refreshBookingEstimatePreview();
                    scheduleRealtimeAvailabilityRefresh(150);
                });
            });
        } catch (err) {
            dropdown.style.display = 'none';
        }
    }

    function initLeafletAutocomplete() {
        ['ccPickupLocation', 'ccReturnLocation'].forEach((id) => {
            const input = document.getElementById(id);
            if (!input) return;

            const type = id === 'ccPickupLocation' ? 'pickup' : 'return';
            let dropdown = input.parentElement.querySelector('.leaflet-autocomplete-list');
            if (!dropdown) {
                dropdown = document.createElement('div');
                dropdown.className = 'leaflet-autocomplete-list';
                dropdown.style.display = 'none';
                input.parentElement.appendChild(dropdown);
            }

            input.addEventListener('input', function () {
                selectedAddresses[type] = null;
                const query = this.value.trim();
                if (query.length < 3) {
                    dropdown.style.display = 'none';
                    return;
                }
                clearTimeout(autocompleteTimers[id]);
                autocompleteTimers[id] = setTimeout(function () {
                    searchNominatim(query, dropdown, input, type);
                }, 300);
            });

            input.addEventListener('blur', function () {
                setTimeout(function () {
                    dropdown.style.display = 'none';
                }, 200);
            });

            input.addEventListener('focus', function () {
                if (this.value.trim().length >= 3 && dropdown.innerHTML) {
                    dropdown.style.display = 'block';
                }
            });
        });
    }

    function openMapPicker(type) {
        if (type === 'return' && isPresetDestinationService()) {
            setFormStatus('For airport/hotel transfer, please select destination from dropdown list.', true);
            return;
        }

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
            const center = saved ? [saved.lat, saved.lon] : [UK_DEFAULT_CENTER.lat, UK_DEFAULT_CENTER.lon];
            const zoom = saved ? 16 : 13;

            const map = L.map(mapDiv).setView(center, zoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
                maxZoom: 19
            }).addTo(map);

            const marker = L.marker(center, { draggable: true }).addTo(map);
            map.on('click', function (e) {
                marker.setLatLng(e.latlng);
                selectedAddresses[type] = null;
                updateMapCoords(type, e.latlng.lat, e.latlng.lng, null);
            });
            marker.on('dragend', function () {
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
            setTimeout(function () { map.invalidateSize(); }, 200);
        } else {
            container.style.display = 'none';
            container.classList.remove('expanded');
        }
    }

    function toggleMapExpand(type) {
        const container = document.getElementById(type + 'MapContainer');
        if (!container) return;
        container.classList.toggle('expanded');
        const map = type === 'pickup' ? pickupMapObj : returnMapObj;
        if (map) setTimeout(function () { map.invalidateSize(); }, 300);
    }

    async function confirmMapLocation(type) {
        const marker = type === 'pickup' ? pickupMarker : returnMarker;
        const saved = selectedAddresses[type];

        if (saved && saved.name) {
            document.getElementById(type === 'pickup' ? 'ccPickupLocation' : 'ccReturnLocation').value = saved.name;
            document.getElementById(type + 'MapContainer').style.display = 'none';
            document.getElementById(type + 'MapContainer').classList.remove('expanded');
            updatePaymentMethodAvailability();
            refreshBookingEstimatePreview();
            scheduleRealtimeAvailabilityRefresh(120);
            return;
        }

        if (!marker) return;
        const pos = marker.getLatLng();
        try {
            const res = await fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + pos.lat + '&lon=' + pos.lng + '&zoom=18&addressdetails=1', {
                headers: { 'Accept-Language': 'en' }
            });
            const data = await res.json();
            const address = data.display_name || (pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6));
            document.getElementById(type === 'pickup' ? 'ccPickupLocation' : 'ccReturnLocation').value = address;
            selectedAddresses[type] = { lat: pos.lat, lon: pos.lng, name: address };
            updateMapCoords(type, pos.lat, pos.lng, address);
        } catch (err) {
            const fallback = pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6);
            document.getElementById(type === 'pickup' ? 'ccPickupLocation' : 'ccReturnLocation').value = fallback;
            selectedAddresses[type] = { lat: pos.lat, lon: pos.lng, name: fallback };
        }

        document.getElementById(type + 'MapContainer').style.display = 'none';
        document.getElementById(type + 'MapContainer').classList.remove('expanded');
        updatePaymentMethodAvailability();
        refreshBookingEstimatePreview();
        scheduleRealtimeAvailabilityRefresh(120);
    }

    function bindEvents() {
        if (el.form) {
            el.form.addEventListener('submit', submitRequest);
        }
        if (el.resetBtn) {
            el.resetBtn.addEventListener('click', resetForm);
        }
        if (el.search) {
            el.search.addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(searchCustomers, 300);
            });
        }

        if (el.rideTier) {
            el.rideTier.addEventListener('change', function () {
                applyAvailabilityToOptions();
                scheduleRealtimeAvailabilityRefresh(120);
            });
        }

        if (el.seatCapacity) {
            el.seatCapacity.addEventListener('change', function () {
                applyAvailabilityToOptions();
                updatePaymentMethodAvailability();
                refreshBookingEstimatePreview();
                scheduleRealtimeAvailabilityRefresh(120);
            });
        }

        if (el.serviceType) {
            el.serviceType.addEventListener('change', function () {
                updateDestinationMode();
                updatePaymentMethodAvailability();
                refreshBookingEstimatePreview();
                scheduleRealtimeAvailabilityRefresh(150);
            });
        }

        if (el.airportSelect) {
            el.airportSelect.addEventListener('change', function () {
                applyPresetDestination(el.airportSelect);
                scheduleRealtimeAvailabilityRefresh(150);
            });
        }

        if (el.hotelSelect) {
            el.hotelSelect.addEventListener('change', function () {
                applyPresetDestination(el.hotelSelect);
                scheduleRealtimeAvailabilityRefresh(150);
            });
        }

        if (el.pickupLocation) {
            el.pickupLocation.addEventListener('input', function () {
                updatePaymentMethodAvailability();
                refreshBookingEstimatePreview();
                scheduleRealtimeAvailabilityRefresh(250);
            });
        }

        if (el.returnLocation) {
            el.returnLocation.addEventListener('input', function () {
                updatePaymentMethodAvailability();
                refreshBookingEstimatePreview();
                scheduleRealtimeAvailabilityRefresh(250);
            });
        }

        if (el.rideTier) {
            el.rideTier.addEventListener('change', updatePaymentMethodAvailability);
        }

        if (el.paymentMethod) {
            el.paymentMethod.addEventListener('change', updatePaymentMethodAvailability);
        }

        if (el.pickupDate) {
            if (el.pickupDateOnly) {
                el.pickupDateOnly.addEventListener('focus', initPickupMinDateTime);
                el.pickupDateOnly.addEventListener('change', function () {
                    syncPickupDateTimeFromParts();
                    validatePickupDateTimeRealtime();
                    scheduleRealtimeAvailabilityRefresh(80);
                });
            }

            if (el.pickupTimeSlot) {
                el.pickupTimeSlot.addEventListener('change', function () {
                    syncPickupDateTimeFromParts();
                    validatePickupDateTimeRealtime();
                    scheduleRealtimeAvailabilityRefresh(120);
                });
            }

            el.pickupDate.addEventListener('input', function () {
                validatePickupDateTimeRealtime();
                scheduleRealtimeAvailabilityRefresh(120);
            });
            el.pickupDate.addEventListener('change', function () {
                validatePickupDateTimeRealtime();
                scheduleRealtimeAvailabilityRefresh(80);
            });
            el.pickupDate.addEventListener('blur', function () {
                validatePickupDateTimeRealtime();
                scheduleRealtimeAvailabilityRefresh(80);
            });
        }

        if (el.createAccountForm) {
            el.createAccountForm.addEventListener('submit', submitCreateAccount);
        }

        if (el.createAccountResetBtn) {
            el.createAccountResetBtn.addEventListener('click', resetCreateAccountForm);
        }

        document.addEventListener('click', function (e) {
            if (!el.results) return;
            if (!el.results.contains(e.target) && e.target !== el.search) {
                hideCustomerResults();
            }
        });
    }

    function initPickupMinDateTime() {
        if (!el.pickupDateOnly) {
            return;
        }

        const dt = new Date();
        dt.setMinutes(dt.getMinutes() + MIN_PICKUP_LEAD_MINUTES);
        const yyyy = dt.getFullYear();
        const mm = String(dt.getMonth() + 1).padStart(2, '0');
        const dd = String(dt.getDate()).padStart(2, '0');
        const minDateValue = yyyy + '-' + mm + '-' + dd;
        el.pickupDateOnly.min = minDateValue;

        if (el.pickupDateOnly.value && el.pickupDateOnly.value < minDateValue) {
            el.pickupDateOnly.value = '';
        }

        syncPickupDateTimeFromParts();
    }

    function validatePickupDateTimeRealtime() {
        if (!el.pickupDate) return true;

        // Refresh min constraint each time for true realtime validation.
        initPickupMinDateTime();

        const value = el.pickupDate.value;
        if (!value) {
            el.pickupDate.setCustomValidity('Pickup date & time is required.');
            if (el.pickupDateHint) {
                el.pickupDateHint.textContent = 'Pickup date & time is required.';
                el.pickupDateHint.className = 'cc-help cc-help-error';
            }
            return false;
        }

        const selected = new Date(value);
        const minAllowed = new Date();
        minAllowed.setMinutes(minAllowed.getMinutes() + MIN_PICKUP_LEAD_MINUTES);

        if (Number.isNaN(selected.getTime()) || selected.getTime() < minAllowed.getTime()) {
            el.pickupDate.setCustomValidity('Pickup must be at least 1 minute from now.');
            if (el.pickupDateHint) {
                el.pickupDateHint.textContent = 'Invalid time: pickup must be at least 1 minute from now.';
                el.pickupDateHint.className = 'cc-help cc-help-error';
            }
            return false;
        }

        el.pickupDate.setCustomValidity('');
        if (el.pickupDateHint) {
            el.pickupDateHint.textContent = 'Pickup time is valid.';
            el.pickupDateHint.className = 'cc-help cc-help-ok';
        }
        return true;
    }

    async function init() {
        bindEvents();
        initLeafletAutocomplete();
        initPickupMinDateTime();
        renderPickupTimeSlots([]);
        syncPickupDateTimeFromParts();
        validatePickupDateTimeRealtime();
        updateDestinationMode();
        updatePaymentMethodAvailability();
        refreshBookingEstimatePreview();
        await loadAvailabilityOptions();
        await loadPickupTimeSlotAvailability();
        await loadRequests();
        await loadEnquiries();
    }

    window.openMapPicker = openMapPicker;
    window.toggleMapExpand = toggleMapExpand;
    window.confirmMapLocation = confirmMapLocation;
    window.submitEnquiryReply = submitEnquiryReply;

    document.addEventListener('DOMContentLoaded', init);
})();
