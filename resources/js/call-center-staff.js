(function () {
    const API = '/api/CallCenterStaff.php';

    let searchTimer = null;
    let autocompleteTimers = {};
    let selectedAddresses = { pickup: null, return: null };
    let pickupMapObj = null;
    let returnMapObj = null;
    let pickupMarker = null;
    let returnMarker = null;

    const el = {
        form: document.getElementById('ccBookingForm'),
        search: document.getElementById('ccCustomerSearch'),
        results: document.getElementById('ccCustomerResults'),
        customerId: document.getElementById('ccCustomerId'),
        customerName: document.getElementById('ccCustomerName'),
        customerPhone: document.getElementById('ccCustomerPhone'),
        customerEmail: document.getElementById('ccCustomerEmail'),
        rideTier: document.getElementById('ccRideTier'),
        pickupDate: document.getElementById('ccPickupDate'),
        pickupLocation: document.getElementById('ccPickupLocation'),
        returnLocation: document.getElementById('ccReturnLocation'),
        paymentMethod: document.getElementById('ccPaymentMethod'),
        specialRequests: document.getElementById('ccSpecialRequests'),
        formStatus: document.getElementById('ccFormStatus'),
        requestsTable: document.getElementById('ccRequestsTable'),
        resetBtn: document.getElementById('ccResetBtn')
    };

    function setFormStatus(text, isError) {
        if (!el.formStatus) return;
        el.formStatus.textContent = text || '';
        el.formStatus.style.color = isError ? '#b91c1c' : '#334155';
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

    function resetForm() {
        el.customerId.value = '';
        el.form.reset();
        setFormStatus('');
        hideCustomerResults();
        selectedAddresses = { pickup: null, return: null };
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
        hideCustomerResults();
    }

    function renderCustomerResults(customers) {
        if (!customers || customers.length === 0) {
            hideCustomerResults();
            return;
        }

        el.results.innerHTML = customers.map((c) => {
            const name = escapeHtml(c.full_name || 'Unknown');
            const email = escapeHtml(c.email || '');
            const phone = escapeHtml(c.phone || '');
            return '<div class="cc-customer-item" data-id="' + escapeHtml(c.id) + '" data-name="' + name + '" data-email="' + email + '" data-phone="' + phone + '">' +
                '<strong>' + name + '</strong><br><small>' + email + ' | ' + phone + '</small></div>';
        }).join('');

        el.results.style.display = 'block';

        Array.from(el.results.querySelectorAll('.cc-customer-item')).forEach((item) => {
            item.addEventListener('click', () => {
                selectCustomer({
                    id: item.getAttribute('data-id') || '',
                    full_name: item.getAttribute('data-name') || '',
                    email: item.getAttribute('data-email') || '',
                    phone: item.getAttribute('data-phone') || ''
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
        el.requestsTable.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
        const data = await apiGet({ action: 'get_my_requests', limit: 100 });
        if (!data.success) {
            el.requestsTable.innerHTML = '<tr><td colspan="5">Cannot load requests</td></tr>';
            return;
        }

        const rows = data.requests || [];
        if (rows.length === 0) {
            el.requestsTable.innerHTML = '<tr><td colspan="5">No request yet</td></tr>';
            return;
        }

        el.requestsTable.innerHTML = rows.map((r) => {
            const bookingId = escapeHtml(r.id || '');
            const ref = escapeHtml(r.booking_ref || bookingId);
            const customer = escapeHtml(r.customer_name || '-');
            const date = escapeHtml(r.pickup_date || '-');
            const status = escapeHtml(r.status || 'pending');

            let actionHtml = '<button class="cc-btn cc-btn-danger" data-action="delete" data-id="' + bookingId + '">Delete</button>';
            if (status === 'pending' || status === 'in_progress') {
                actionHtml = '<button class="cc-btn cc-btn-secondary" data-action="cancel" data-id="' + bookingId + '">Cancel</button> ' + actionHtml;
            }

            return '<tr>' +
                '<td>' + ref + '</td>' +
                '<td>' + customer + '</td>' +
                '<td>' + date + '</td>' +
                '<td><span class="' + statusClass(status) + '">' + status + '</span></td>' +
                '<td>' + actionHtml + '</td>' +
                '</tr>';
        }).join('');

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

    async function submitRequest(event) {
        event.preventDefault();
        setFormStatus('Submitting request...');

        const payload = {
            action: 'booking_by_request',
            customer_id: el.customerId.value || null,
            customer_name: el.customerName.value,
            customer_phone: el.customerPhone.value,
            customer_email: el.customerEmail.value,
            ride_tier: el.rideTier.value,
            pickup_date: el.pickupDate.value,
            pickup_location: el.pickupLocation.value,
            return_location: el.returnLocation.value,
            payment_method: el.paymentMethod.value,
            special_requests: el.specialRequests.value,
            distance_km: estimateDistanceKm()
        };

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
            const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query) + '&limit=5&addressdetails=1', {
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
            const center = saved ? [saved.lat, saved.lon] : [10.8231, 106.6297];
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

        document.addEventListener('click', function (e) {
            if (!el.results) return;
            if (!el.results.contains(e.target) && e.target !== el.search) {
                hideCustomerResults();
            }
        });
    }

    function initPickupMinDateTime() {
        const dt = new Date();
        dt.setMinutes(dt.getMinutes() + 30);
        const yyyy = dt.getFullYear();
        const mm = String(dt.getMonth() + 1).padStart(2, '0');
        const dd = String(dt.getDate()).padStart(2, '0');
        const hh = String(dt.getHours()).padStart(2, '0');
        const mi = String(dt.getMinutes()).padStart(2, '0');
        el.pickupDate.min = yyyy + '-' + mm + '-' + dd + 'T' + hh + ':' + mi;
    }

    async function init() {
        bindEvents();
        initLeafletAutocomplete();
        initPickupMinDateTime();
        await loadRequests();
    }

    window.openMapPicker = openMapPicker;
    window.toggleMapExpand = toggleMapExpand;
    window.confirmMapLocation = confirmMapLocation;

    document.addEventListener('DOMContentLoaded', init);
})();
