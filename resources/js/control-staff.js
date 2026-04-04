(function () {
    const API = '/api/ControlStaff.php';

    let ordersCache = [];
    let vehiclesCache = [];
    let driversCache = [];
    let availableVehiclesCache = [];

    const el = {
        tabs: document.querySelectorAll('.ctrl-tab'),
        ordersPanel: document.getElementById('ordersPanel'),
        vehiclesPanel: document.getElementById('vehiclesPanel'),
        assignDriverPanel: document.getElementById('assignDriverPanel'),

        orderFilter: document.getElementById('ctrlOrderStatusFilter'),
        reloadOrdersBtn: document.getElementById('ctrlReloadOrders'),
        orderSearch: document.getElementById('ctrlOrderSearch'),
        ordersTable: document.getElementById('ctrlOrdersTable'),
        orderMsg: document.getElementById('ctrlOrderStatusMsg'),
        orderModal: document.getElementById('ctrlOrderModal'),
        orderModalBody: document.getElementById('ctrlOrderModalBody'),
        orderModalTitle: document.getElementById('ctrlOrderModalTitle'),
        orderModalClose: document.getElementById('ctrlOrderModalClose'),

        vehicleId: document.getElementById('ctrlVehicleId'),
        brand: document.getElementById('ctrlBrand'),
        model: document.getElementById('ctrlModel'),
        year: document.getElementById('ctrlYear'),
        license: document.getElementById('ctrlLicense'),
        category: document.getElementById('ctrlCategory'),
        serviceTier: document.getElementById('ctrlServiceTier'),
        seats: document.getElementById('ctrlSeats'),
        luggageCapacity: document.getElementById('ctrlLuggageCapacity'),
        color: document.getElementById('ctrlColor'),
        vehicleImage: document.getElementById('ctrlVehicleImage'),
        saveVehicleBtn: document.getElementById('ctrlSaveVehicle'),
        resetVehicleBtn: document.getElementById('ctrlResetVehicle'),
        vehicleSearch: document.getElementById('ctrlVehicleSearch'),
        vehicleTierFilter: document.getElementById('ctrlVehicleTierFilter'),
        vehicleSeatsFilter: document.getElementById('ctrlVehicleSeatsFilter'),
        vehiclesTable: document.getElementById('ctrlVehiclesTable'),
        vehicleMsg: document.getElementById('ctrlVehicleStatusMsg'),

        driverFilter: document.getElementById('ctrlDriverStatusFilter'),
        reloadDriversBtn: document.getElementById('ctrlReloadDrivers'),
        driverSearch: document.getElementById('ctrlDriverSearch'),
        driversTable: document.getElementById('ctrlDriversTable'),
        driverMsg: document.getElementById('ctrlDriverStatusMsg'),

        kpiOrders: document.getElementById('ctrlKpiOrders'),
        kpiDrivers: document.getElementById('ctrlKpiDrivers'),
        kpiVehicles: document.getElementById('ctrlKpiVehicles')
    };

    function updateKpis() {
        if (el.kpiOrders) {
            el.kpiOrders.textContent = String(ordersCache.length || 0);
        }
        if (el.kpiDrivers) {
            el.kpiDrivers.textContent = String(driversCache.length || 0);
        }
        if (el.kpiVehicles) {
            el.kpiVehicles.textContent = String(vehiclesCache.length || 0);
        }
    }

    function setOrderMsg(text, isError) {
        el.orderMsg.textContent = text || '';
        el.orderMsg.style.color = isError ? '#b91c1c' : '#334155';
    }

    function setVehicleMsg(text, isError) {
        el.vehicleMsg.textContent = text || '';
        el.vehicleMsg.style.color = isError ? '#b91c1c' : '#334155';
    }

    function setDriverMsg(text, isError) {
        if (!el.driverMsg) return;
        el.driverMsg.textContent = text || '';
        el.driverMsg.style.color = isError ? '#b91c1c' : '#334155';
    }

    function statusBadgeClass(status) {
        if (status === 'in_progress') return 'ctrl-badge ctrl-in-progress';
        if (status === 'done' || status === 'completed') return 'ctrl-badge ctrl-done';
        if (status === 'cancelled' || status === 'canceled') return 'ctrl-badge ctrl-pending';
        return 'ctrl-badge ctrl-pending';
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizedQuery(value) {
        return String(value || '').trim().toLowerCase();
    }

    function bestMatchScore(query, candidates) {
        if (!query) return 0;
        let best = Number.POSITIVE_INFINITY;

        for (const c of candidates) {
            const text = normalizedQuery(c);
            if (!text) continue;
            if (text.startsWith(query)) {
                best = Math.min(best, 0);
                continue;
            }
            const idx = text.indexOf(query);
            if (idx >= 0) {
                best = Math.min(best, 10 + idx);
            }
        }

        return Number.isFinite(best) ? best : -1;
    }

    async function apiGet(params) {
        const qs = new URLSearchParams(params);
        qs.set('_ts', String(Date.now()));
        const res = await fetch(API + '?' + qs.toString(), {
            cache: 'no-store',
            headers: {
                'Cache-Control': 'no-cache'
            }
        });
        const raw = await res.text();
        if (!res.ok) {
            throw new Error('HTTP ' + res.status + ': ' + raw.substring(0, 200));
        }
        try {
            return JSON.parse(raw);
        } catch (err) {
            throw new Error('Invalid JSON response: ' + raw.substring(0, 200));
        }
    }

    async function apiPost(body) {
        const url = API;
        console.log('POST to:', url, 'body:', body);
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const raw = await res.text();
        console.log('Response status:', res.status, 'body:', raw.substring(0, 500));
        if (!res.ok) {
            throw new Error('HTTP ' + res.status + ': ' + raw.substring(0, 200));
        }
        try {
            return JSON.parse(raw);
        } catch (err) {
            throw new Error('Invalid JSON response: ' + raw.substring(0, 200));
        }
    }

    function switchTab(target) {
        el.tabs.forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-tab') === target);
        });
        el.ordersPanel.classList.toggle('active', target === 'orders');
        el.vehiclesPanel.classList.toggle('active', target === 'vehicles');
        if (el.assignDriverPanel) {
            el.assignDriverPanel.classList.toggle('active', target === 'assign-driver');
        }

        if (target === 'assign-driver') {
            loadDrivers();
        }
    }

    function normalizeDisplayStatus(status) {
        if (status === 'done') return 'completed';
        return status || 'pending';
    }

    function getNextStatusAction(status) {
        if (status === 'pending') {
            return { nextStatus: 'in_progress', label: 'Confirm' };
        }
        return null;
    }

    async function loadOrders() {
        const status = el.orderFilter.value || 'all';
        setOrderMsg('Loading orders...');

        try {
            const data = await apiGet({ action: 'get_orders', status, limit: 200 });
            if (!data.success) {
                setOrderMsg(data.message || 'Cannot load orders', true);
                return;
            }

            ordersCache = data.orders || [];
            renderOrdersTable();
            updateKpis();
            setOrderMsg('Loaded ' + ordersCache.length + ' order(s).');
        } catch (err) {
            setOrderMsg('Error loading orders: ' + (err?.message || 'Unknown error'), true);
            console.error('Load orders error:', err);
        }
    }

    function renderOrdersTable() {
        const rows = getFilteredOrders();
        if (!rows.length) {
            el.ordersTable.innerHTML = '<tr><td colspan="6">No matching orders found</td></tr>';
            return;
        }

        el.ordersTable.innerHTML = rows.map((o) => {
            const id = escapeHtml(o.id || '');
            const customer = escapeHtml(o.customer_name || o.user_name || o.renter_name || 'Customer');
            const pickupDate = escapeHtml(formatPickupDatetime(o.pickup_date, o.pickup_time));
            const total = Number(o.total_amount || 0).toFixed(2);
            const status = normalizeDisplayStatus(o.status);
            let actionHtml = '<div class="ctrl-order-actions">' +
                '<button class="ctrl-btn ctrl-btn-muted" data-role="detail-order" data-id="' + id + '">View</button>' +
            '</div>';
            if (status === 'pending') {
                actionHtml =
                    '<div class="ctrl-order-actions">' +
                        '<button class="ctrl-btn ctrl-btn-muted" data-role="detail-order" data-id="' + id + '">View</button>' +
                        '<button class="ctrl-btn ctrl-btn-primary" data-role="advance-order" data-id="' + id + '" data-next-status="in_progress">Confirm</button>' +
                        '<button class="ctrl-btn ctrl-btn-danger" data-role="reject-order" data-id="' + id + '">Reject</button>' +
                    '</div>';
            }

            return '<tr>' +
                '<td>' + id + '</td>' +
                '<td>' + customer + '</td>' +
                '<td>' + pickupDate + '</td>' +
                '<td>$' + total + '</td>' +
                '<td class="ctrl-order-status-cell">' +
                    '<span class="' + statusBadgeClass(status) + '">' + escapeHtml(status) + '</span>' +
                '</td>' +
                '<td class="ctrl-order-actions-cell">' +
                    actionHtml +
                '</td>' +
                '</tr>';
        }).join('');

        bindOrderRowActions();
    }

    function getFilteredOrders() {
        const q = normalizedQuery(el.orderSearch ? el.orderSearch.value : '');
        if (!q) {
            return ordersCache.slice();
        }

        return ordersCache
            .map((o) => {
                const score = bestMatchScore(q, [
                    o.id,
                    o.customer_name,
                    o.user_name,
                    o.renter_name,
                    o.pickup_date,
                    o.status
                ]);
                return { o, score };
            })
            .filter((x) => x.score >= 0)
            .sort((a, b) => a.score - b.score)
            .map((x) => x.o);
    }

    function bindOrderRowActions() {
        const buttons = el.ordersTable.querySelectorAll('button[data-role="advance-order"]');
        console.log('Found ' + buttons.length + ' advance-order buttons');
        
        Array.from(buttons).forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                const status = btn.getAttribute('data-next-status');
                const btnText = btn.textContent.trim();
                console.log('Advance order clicked - id:', id, 'next status:', status);
                if (!id || !status) {
                    console.log('Missing id or status');
                    return;
                }

                // Check if already processing
                if (btn.disabled) {
                    return; // Prevent duplicate clicks
                }

                // Show confirmation alert with action-specific message
                let confirmMessage = `Are you sure you want to ${btnText.toLowerCase()} this order?`;
                if (status === 'in_progress') {
                    confirmMessage = '⚠️ Start Trip Confirmation\n\n' +
                        'Are you sure you want to start this trip?\n\n' +
                        '📧 An invoice will be automatically sent to the customer.\n' +
                        'Please ensure all trip details are correct before proceeding.';
                }

                if (!confirm(confirmMessage)) {
                    return; // User cancelled
                }

                // Disable button to prevent duplicate submissions
                btn.disabled = true;
                const originalText = btn.textContent;
                btn.textContent = 'Processing...';
                btn.style.opacity = '0.6';

                try {
                    console.log('Posting update_order_status:', { booking_id: id, status: status });
                    const data = await apiPost({ action: 'update_order_status', booking_id: id, status: status });
                    console.log('API response:', data);
                    if (!data.success) {
                        setOrderMsg(data.message || 'Failed to update order', true);
                        await loadOrders();
                        return;
                    }
                    setOrderMsg('Order status updated.');
                    await loadOrders();
                    await loadVehicles();
                } catch (err) {
                    setOrderMsg('Error: ' + (err?.message || 'Unknown error'), true);
                    console.error('Advance order error:', err);
                    btn.disabled = false;
                    btn.textContent = originalText;
                    btn.style.opacity = '1';
                }
            });
        });

        Array.from(el.ordersTable.querySelectorAll('button[data-role="reject-order"]')).forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (btn.disabled) return;
                const id = btn.getAttribute('data-id');
                if (!id) return;
                if (!confirm('Reject this pending order? The customer will see it as cancelled.')) return;

                btn.disabled = true;
                const previousText = btn.textContent;
                btn.textContent = 'Rejecting...';

                try {
                    const data = await apiPost({ action: 'reject_order', booking_id: id });
                    if (!data.success) {
                        setOrderMsg(data.message || 'Reject failed', true);
                        return;
                    }

                    // Realtime feedback: reflect cancellation immediately in current table data.
                    const target = ordersCache.find((o) => String(o.id) === String(id));
                    if (target) {
                        target.status = 'cancelled';
                    }
                    renderOrdersTable();

                    setOrderMsg('Order rejected.');
                    await loadOrders();
                    await loadVehicles();
                } catch (err) {
                    setOrderMsg('Reject failed: ' + (err && err.message ? err.message : 'Unknown error'), true);
                } finally {
                    btn.disabled = false;
                    btn.textContent = previousText;
                }
            });
        });

        Array.from(el.ordersTable.querySelectorAll('button[data-role="detail-order"]')).forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                if (!id) return;

                try {
                    const data = await apiGet({ action: 'get_order', order_id: id });
                    if (!data.success) {
                        setOrderMsg(data.message || 'Cannot load order detail', true);
                        return;
                    }

                    openOrderDetailModal(data.order || {});
                } catch (err) {
                    setOrderMsg('Error loading order detail: ' + (err && err.message ? err.message : 'Unknown error'), true);
                }
            });
        });
    }

    function formatPickupDatetime(pickupDate, pickupTime) {
        if (!pickupDate) return '-';
        try {
            const toAmPm = (timeValue) => {
                const raw = String(timeValue || '').trim();
                if (!raw) return '';

                const m = raw.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/i);
                if (!m) return raw;

                let hour = parseInt(m[1], 10);
                const minute = String(m[2] || '00').padStart(2, '0');
                const meridiem = (m[3] || '').toUpperCase();

                if (meridiem === 'PM' && hour < 12) hour += 12;
                if (meridiem === 'AM' && hour === 12) hour = 0;

                const ampm = hour >= 12 ? 'PM' : 'AM';
                let displayHour = hour % 12;
                if (displayHour === 0) displayHour = 12;
                return String(displayHour).padStart(2, '0') + ':' + minute + ampm;
            };

            // If pickup_time is available, combine date with the correct time
            if (pickupTime) {
                // Parse UTC datetime from database to get date only
                const date = new Date(pickupDate + 'Z'); // Append Z to indicate UTC
                const options = { year: 'numeric', month: 'short', day: 'numeric' };
                const dateStr = date.toLocaleString('en-US', options); // e.g., "28 Mar 2026"
                return dateStr + ', ' + toAmPm(pickupTime); // e.g., "28 Mar 2026, 10:00AM"
            }
            
            // Fallback to original behavior if no pickup_time
            const date = new Date(pickupDate + 'Z'); // Append Z to indicate UTC
            // Format in user's local timezone (no specific timezone forced)
            const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: undefined };
            return date.toLocaleString('en-US', options);
        } catch (e) {
            return escapeHtml(pickupDate);
        }
    }

    function openOrderDetailModal(order) {
        if (!el.orderModal || !el.orderModalBody) return;

        const customerName = escapeHtml(order.customer_name || order.user_name || order.renter_name || 'Customer');
        const normalizedStatus = normalizeDisplayStatus(order.status || 'pending');
        const status = escapeHtml(normalizedStatus);
        const canShowVehicle = (normalizedStatus === 'in_progress' || normalizedStatus === 'completed');
        const vehicleText = canShowVehicle
            ? escapeHtml([order.brand, order.model, order.license_plate].filter(Boolean).join(' ') || '-')
            : 'Pending assignment';
        const pickupLocation = escapeHtml(order.pickup_location || '-');
        const pickupDateTime = formatPickupDatetime(order.pickup_date, order.pickup_time);
        const pickupText = pickupLocation + ' @ ' + pickupDateTime;
        const destinationText = escapeHtml(order.return_location || '-');
        const amountText = '£' + Number(order.total_amount || 0).toFixed(2);
        const requestedTier = order.ride_tier || order.service_tier || '';
        const serviceTier = escapeHtml(requestedTier ? toTitleCase(normalizeTier(requestedTier)) : '-');
        const bookedSeats = order.number_of_passengers || order.seat_capacity || order.seats || '-';
        const seatsText = escapeHtml(String(bookedSeats)) + (String(bookedSeats) === '-' ? '' : ' seats');
        const pickupTimeText = escapeHtml((function(timeValue) {
            const raw = String(timeValue || '').trim();
            if (!raw) return '-';

            const m = raw.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/i);
            if (!m) return raw;

            let hour = parseInt(m[1], 10);
            const minute = String(m[2] || '00').padStart(2, '0');
            const meridiem = (m[3] || '').toUpperCase();

            if (meridiem === 'PM' && hour < 12) hour += 12;
            if (meridiem === 'AM' && hour === 12) hour = 0;

            const ampm = hour >= 12 ? 'PM' : 'AM';
            let displayHour = hour % 12;
            if (displayHour === 0) displayHour = 12;
            return String(displayHour).padStart(2, '0') + ':' + minute + ampm;
        })(order.pickup_time));

        el.orderModalTitle.textContent = 'Order #' + (order.id || '');
        el.orderModalBody.innerHTML =
            '<div class="ctrl-modal-grid">' +
                '<div class="ctrl-kv"><div class="ctrl-k">Customer</div><div class="ctrl-v">' + customerName + '</div></div>' +
                '<div class="ctrl-kv"><div class="ctrl-k">Status</div><div class="ctrl-v">' + status + '</div></div>' +
                '<div class="ctrl-kv"><div class="ctrl-k">Pickup</div><div class="ctrl-v">' + pickupText + '</div></div>' +
                '<div class="ctrl-kv"><div class="ctrl-k">Pickup Time</div><div class="ctrl-v">' + pickupTimeText + '</div></div>' +
                '<div class="ctrl-kv"><div class="ctrl-k">Destination</div><div class="ctrl-v">' + destinationText + '</div></div>' +
                '<div class="ctrl-kv"><div class="ctrl-k">Vehicle</div><div class="ctrl-v">' + vehicleText + '</div></div>' +
                '<div class="ctrl-kv"><div class="ctrl-k">Service Tier</div><div class="ctrl-v">' + serviceTier + '</div></div>' +
                '<div class="ctrl-kv"><div class="ctrl-k">Seats</div><div class="ctrl-v">' + seatsText + '</div></div>' +
                '<div class="ctrl-kv"><div class="ctrl-k">Amount</div><div class="ctrl-v">' + amountText + '</div></div>' +
                '<div class="ctrl-kv" style="grid-column:1/-1;"><div class="ctrl-k">Notes</div><div class="ctrl-v">' + escapeHtml(order.special_requests || '-') + '</div></div>' +
            '</div>';

        el.orderModal.classList.add('open');
    }

    function closeOrderDetailModal() {
        if (!el.orderModal) return;
        el.orderModal.classList.remove('open');
    }

    function resetVehicleForm() {
        el.vehicleId.value = '';
        el.brand.value = '';
        el.model.value = '';
        el.year.value = '';
        el.license.value = '';
        el.category.value = 'sedan';
        el.serviceTier.value = 'standard';
        el.seats.value = '5';
        if (el.luggageCapacity) el.luggageCapacity.value = '';
        el.color.value = '';
        if (el.vehicleImage) {
            el.vehicleImage.value = '';
        }
        setVehicleMsg('');
    }

    function fillVehicleForm(vehicle) {
        el.vehicleId.value = vehicle.id || '';
        el.brand.value = vehicle.brand || '';
        el.model.value = vehicle.model || '';
        el.year.value = vehicle.year || '';
        el.license.value = vehicle.license_plate || '';
        el.category.value = (vehicle.category || 'sedan').toLowerCase();
        el.serviceTier.value = normalizeTier(vehicle.service_tier || 'standard');
        el.seats.value = vehicle.seats || 5;
        if (el.luggageCapacity) {
            const capacity = Number(vehicle.luggage_capacity_lbs || vehicle.capacity || 0);
            el.luggageCapacity.value = Number.isFinite(capacity) && capacity > 0 ? String(capacity) : '';
        }
        el.color.value = vehicle.color || '';
        if (el.vehicleImage) {
            el.vehicleImage.value = '';
        }
    }

    async function uploadVehicleImage(file, vehicleId) {
        const formData = new FormData();
        formData.append('image', file);
        if (vehicleId) {
            formData.append('vehicle_id', vehicleId);
        }

        const res = await fetch('/api/vehicles.php?action=upload-image', {
            method: 'POST',
            body: formData
        });

        const raw = await res.text();
        let data;
        try {
            data = JSON.parse(raw);
        } catch (err) {
            throw new Error('Upload endpoint returned invalid JSON: ' + raw.substring(0, 200));
        }
        if (!data.success) {
            throw new Error(data.message || 'Image upload failed');
        }
        return data;
    }

    async function loadVehicles() {
        el.vehiclesTable.innerHTML = '<tr><td colspan="7">Loading...</td></tr>';

        const data = await apiGet({ action: 'get_vehicles' });
        if (!data.success) {
            el.vehiclesTable.innerHTML = '<tr><td colspan="7">Cannot load vehicles</td></tr>';
            setVehicleMsg(data.message || 'Cannot load vehicles', true);
            return;
        }

        vehiclesCache = data.vehicles || [];
        renderVehiclesTable();
        updateKpis();
    }

    function renderVehiclesTable() {
        const rows = getFilteredVehicles();
        if (!rows.length) {
            el.vehiclesTable.innerHTML = '<tr><td colspan="7">No matching vehicles</td></tr>';
            return;
        }

        el.vehiclesTable.innerHTML = rows.map((v) => {
            const id = escapeHtml(v.id || '');
            const vm = escapeHtml((v.brand || '') + ' ' + (v.model || ''));
            const plate = escapeHtml(v.license_plate || '-');
            const seat = escapeHtml(formatCustomerSeats(v.seats));
            const cat = escapeHtml(v.category || '-');
            const tier = renderTierChip(v.service_tier || 'standard');
            const status = renderVehicleStatusBadge(v.status || 'unavailable');
            const locked = !!v.is_locked_in_progress;
            const lockReason = escapeHtml(v.lock_reason || 'Vehicle is linked to an in-progress order');
            const editBtn = locked
                ? '<button class="ctrl-btn ctrl-btn-muted" data-role="edit-vehicle" data-id="' + id + '" disabled title="' + lockReason + '" style="opacity:.55;cursor:not-allowed;">Edit</button>'
                : '<button class="ctrl-btn ctrl-btn-muted" data-role="edit-vehicle" data-id="' + id + '">Edit</button>';
            const deleteBtn = locked
                ? '<button class="ctrl-btn ctrl-btn-muted" data-role="delete-vehicle" data-id="' + id + '" disabled title="' + lockReason + '" style="opacity:.55;cursor:not-allowed;">Delete</button>'
                : '<button class="ctrl-btn ctrl-btn-danger" data-role="delete-vehicle" data-id="' + id + '">Delete</button>';

            return '<tr>' +
                '<td>' + vm + '</td>' +
                '<td>' + plate + '</td>' +
                '<td>' + seat + '</td>' +
                '<td>' + cat + '</td>' +
                '<td>' + tier + '</td>' +
                '<td>' + status + '</td>' +
                '<td>' +
                    editBtn + ' ' +
                    deleteBtn +
                '</td>' +
                '</tr>';
        }).join('');

        Array.from(el.vehiclesTable.querySelectorAll('button[data-role="edit-vehicle"]')).forEach((btn) => {
            btn.addEventListener('click', () => {
                if (btn.disabled) {
                    setVehicleMsg(btn.title || 'Vehicle is linked to an in-progress order and cannot be edited now.', true);
                    return;
                }
                const id = btn.getAttribute('data-id');
                const vehicle = vehiclesCache.find((v) => String(v.id) === String(id));
                if (!vehicle) return;
                fillVehicleForm(vehicle);
                switchTab('vehicles');
                setVehicleMsg('Editing vehicle: ' + (vehicle.brand || '') + ' ' + (vehicle.model || ''));
            });
        });

        Array.from(el.vehiclesTable.querySelectorAll('button[data-role="delete-vehicle"]')).forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (btn.disabled) {
                    setVehicleMsg(btn.title || 'Vehicle is linked to an in-progress order and cannot be deleted now.', true);
                    return;
                }
                const id = btn.getAttribute('data-id');
                if (!id) return;
                if (!confirm('Delete this vehicle?')) return;

                const data = await apiPost({ action: 'delete_vehicle', vehicle_id: id });
                if (!data.success) {
                    setVehicleMsg(data.message || 'Delete failed', true);
                    return;
                }
                setVehicleMsg('Vehicle deleted successfully.');
                await loadVehicles();
            });
        });
    }

    function getFilteredVehicles() {
        const q = normalizedQuery(el.vehicleSearch ? el.vehicleSearch.value : '');
        const tierFilter = normalizedQuery(el.vehicleTierFilter ? el.vehicleTierFilter.value : 'all');
        const seatsFilter = normalizedQuery(el.vehicleSeatsFilter ? el.vehicleSeatsFilter.value : 'all');

        let rows = vehiclesCache.slice();

        if (tierFilter && tierFilter !== 'all') {
            rows = rows.filter((v) => normalizeTier(v.service_tier || 'standard') === tierFilter);
        }

        if (seatsFilter && seatsFilter !== 'all') {
            rows = rows.filter((v) => formatCustomerSeats(v.seats) === seatsFilter);
        }

        if (!q) {
            return rows;
        }

        return rows
            .map((v) => {
                const score = bestMatchScore(q, [
                    v.id,
                    v.brand,
                    v.model,
                    v.license_plate,
                    v.category,
                    v.service_tier,
                    formatCustomerSeats(v.seats)
                ]);
                return { v, score };
            })
            .filter((x) => x.score >= 0)
            .sort((a, b) => a.score - b.score)
            .map((x) => x.v);
    }

    function formatCustomerSeats(rawSeats) {
        const totalSeats = parseInt(rawSeats, 10);
        if (!Number.isFinite(totalSeats) || totalSeats <= 0) {
            return '-';
        }
        if (totalSeats === 5) {
            return '4';
        }
        if (totalSeats === 7) {
            return '7';
        }
        return String(totalSeats);
    }

    function driverStatusBadgeClass(status) {
        if (status === 'dispatched') return 'ctrl-badge ctrl-in-progress';
        return 'ctrl-badge ctrl-pending';
    }

    function driverServiceStateBadgeClass(state) {
        if (state === 'on_service') return 'ctrl-badge ctrl-service-on';
        return 'ctrl-badge ctrl-service-free';
    }

    function toTitleCase(value) {
        return String(value || '')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (m) => m.toUpperCase());
    }

    function normalizeTier(value) {
        const tier = String(value || '').trim().toLowerCase();
        if (tier === 'premium') {
            return 'luxury';
        }
        if (tier === 'eco' || tier === 'standard' || tier === 'luxury') {
            return tier;
        }
        return 'standard';
    }

    function renderTierChip(value) {
        const tier = normalizeTier(value);
        return '<span class="ctrl-tier ctrl-tier-' + tier + '">' + escapeHtml(toTitleCase(tier)) + '</span>';
    }

    function normalizeVehicleStatus(value) {
        const status = String(value || '').trim().toLowerCase();
        if (!status) return 'default';
        if (status === 'in_progress' || status === 'in use') return 'in-use';
        if (status === 'booked' || status === 'reserved') return 'booked';
        return status.replace(/_/g, '-');
    }

    function renderVehicleStatusBadge(value) {
        const key = normalizeVehicleStatus(value);
        const text = toTitleCase(String(value || key).replace(/-/g, ' '));
        const valid = ['available', 'in-use', 'booked', 'maintenance', 'unavailable'];
        const cls = valid.includes(key) ? key : 'default';
        return '<span class="ctrl-vstatus ctrl-vstatus-' + cls + '">' + escapeHtml(text) + '</span>';
    }

    async function loadAvailableVehicles() {
        const data = await apiGet({ action: 'get_available_vehicles' });
        if (!data.success) {
            throw new Error(data.message || 'Cannot load available vehicles');
        }
        availableVehiclesCache = data.vehicles || [];
    }

    async function loadDrivers() {
        if (!el.driversTable) return;
        const status = el.driverFilter ? el.driverFilter.value : 'all';
        setDriverMsg('Loading drivers...');

        try {
            await loadAvailableVehicles();
            const data = await apiGet({ action: 'get_drivers', status: status || 'all' });
            if (!data.success) {
                setDriverMsg(data.message || 'Cannot load drivers', true);
                return;
            }

            driversCache = data.drivers || [];
            renderDriversTable();
            updateKpis();
            setDriverMsg('Loaded ' + driversCache.length + ' driver(s).');
        } catch (err) {
            setDriverMsg('Error loading drivers: ' + (err && err.message ? err.message : 'Unknown error'), true);
        }
    }

    function vehicleOptionsHtml(selectedId) {
        if (!availableVehiclesCache.length) {
            return '<option value="">No available vehicle</option>';
        }

        const base = '<option value="">Select vehicle</option>';
        const rows = availableVehiclesCache.map((v) => {
            const vid = String(v.id || '');
            const isSelected = selectedId && vid === selectedId ? ' selected' : '';
            const label = [v.brand, v.model, v.license_plate].filter(Boolean).join(' - ');
            return '<option value="' + escapeHtml(vid) + '"' + isSelected + '>' + escapeHtml(label) + '</option>';
        }).join('');

        return base + rows;
    }

    function renderDriversTable() {
        if (!el.driversTable) return;

        const rows = getFilteredDrivers();
        if (!rows.length) {
            el.driversTable.innerHTML = '<tr><td colspan="6">No matching drivers found</td></tr>';
            return;
        }

        el.driversTable.innerHTML = rows.map((d) => {
            const id = escapeHtml(d.id || '');
            const name = escapeHtml(d.full_name || 'Driver');
            const email = escapeHtml(d.email || '-');
            const phone = escapeHtml(d.phone || '-');
            const status = (d.status === 'dispatched') ? 'dispatched' : 'pending';
            const serviceState = (d.service_state === 'on_service') ? 'on_service' : 'free';
            const assignedVehicle = d.assigned_vehicle_id
                ? escapeHtml([d.brand, d.model, d.license_plate].filter(Boolean).join(' - '))
                : '-';
            const canUnassign = (d.can_unassign !== false);
            const unassignLockReason = escapeHtml(d.unassign_lock_reason || 'Cannot unassign while vehicle is serving an in-progress order.');

            let actionHtml = '';
            if (status === 'pending') {
                actionHtml = '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">' +
                    '<select class="ctrl-select" data-role="dispatch-vehicle-select" data-driver-id="' + id + '" style="min-width:220px;">' + vehicleOptionsHtml('') + '</select>' +
                    '<button class="ctrl-btn ctrl-btn-primary" data-role="dispatch-driver" data-id="' + id + '">Dispatch</button>' +
                    '</div>';
            } else {
                actionHtml = canUnassign
                    ? '<button class="ctrl-btn ctrl-btn-danger" data-role="unassign-driver" data-id="' + id + '">Unassign</button>'
                    : '<button class="ctrl-btn ctrl-btn-muted" data-role="unassign-driver" data-id="' + id + '" disabled title="' + unassignLockReason + '" style="opacity:.55;cursor:not-allowed;">Unassign</button>';
            }

            return '<tr>' +
                '<td>' + name + '</td>' +
                '<td>' + email + '<br><small>' + phone + '</small></td>' +
                '<td>' + assignedVehicle + '</td>' +
                '<td><span class="' + driverStatusBadgeClass(status) + '">' + escapeHtml(status) + '</span></td>' +
                '<td><span class="' + driverServiceStateBadgeClass(serviceState) + '">' + escapeHtml(serviceState.replace('_', ' ')) + '</span></td>' +
                '<td>' + actionHtml + '</td>' +
                '</tr>';
        }).join('');

        bindDriverRowActions();
    }

    function getFilteredDrivers() {
        const q = normalizedQuery(el.driverSearch ? el.driverSearch.value : '');
        if (!q) {
            return driversCache.slice();
        }

        return driversCache
            .map((d) => {
                const assignedVehicle = [d.brand, d.model, d.license_plate].filter(Boolean).join(' ');
                const score = bestMatchScore(q, [
                    d.id,
                    d.full_name,
                    d.email,
                    d.phone,
                    assignedVehicle,
                    d.status
                ]);
                return { d, score };
            })
            .filter((x) => x.score >= 0)
            .sort((a, b) => a.score - b.score)
            .map((x) => x.d);
    }

    function bindDriverRowActions() {
        if (!el.driversTable) return;

        Array.from(el.driversTable.querySelectorAll('button[data-role="dispatch-driver"]')).forEach((btn) => {
            btn.addEventListener('click', async () => {
                const driverId = btn.getAttribute('data-id') || '';
                if (!driverId) return;

                const select = el.driversTable.querySelector('select[data-role="dispatch-vehicle-select"][data-driver-id="' + driverId + '"]');
                const vehicleId = select ? String(select.value || '').trim() : '';
                if (!vehicleId) {
                    setDriverMsg('Please select an available vehicle before dispatch.', true);
                    return;
                }

                btn.disabled = true;
                const original = btn.textContent;
                btn.textContent = 'Dispatching...';

                try {
                    const data = await apiPost({ action: 'dispatch_driver', driver_id: driverId, vehicle_id: vehicleId });
                    if (!data.success) {
                        setDriverMsg(data.message || 'Dispatch failed', true);
                        return;
                    }

                    setDriverMsg('Driver dispatched successfully.');
                    await loadDrivers();
                    await loadVehicles();
                } catch (err) {
                    setDriverMsg('Error dispatching driver: ' + (err && err.message ? err.message : 'Unknown error'), true);
                } finally {
                    btn.disabled = false;
                    btn.textContent = original;
                }
            });
        });

        Array.from(el.driversTable.querySelectorAll('button[data-role="unassign-driver"]')).forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (btn.disabled) {
                    setDriverMsg(btn.title || 'Cannot unassign while vehicle is serving an in-progress order.', true);
                    return;
                }
                const driverId = btn.getAttribute('data-id') || '';
                if (!driverId) return;

                if (!confirm('Unassign this driver from the current vehicle?')) {
                    return;
                }

                btn.disabled = true;
                const original = btn.textContent;
                btn.textContent = 'Unassigning...';

                try {
                    const data = await apiPost({ action: 'unassign_driver', driver_id: driverId });
                    if (!data.success) {
                        setDriverMsg(data.message || 'Unassign failed', true);
                        return;
                    }

                    setDriverMsg('Driver unassigned successfully.');
                    await loadDrivers();
                    await loadVehicles();
                } catch (err) {
                    setDriverMsg('Error unassigning driver: ' + (err && err.message ? err.message : 'Unknown error'), true);
                } finally {
                    btn.disabled = false;
                    btn.textContent = original;
                }
            });
        });
    }

    async function saveVehicle() {
        if (el.saveVehicleBtn) {
            el.saveVehicleBtn.disabled = true;
        }

        const vehicleId = el.vehicleId.value.trim();
        const seatValue = Number(el.seats.value || 5);
        const rawLuggageValue = el.luggageCapacity ? String(el.luggageCapacity.value || '').trim() : '';

        const payload = {
            brand: el.brand.value.trim(),
            model: el.model.value.trim(),
            year: Number(el.year.value || 0),
            license_plate: el.license.value.trim(),
            category: el.category.value.trim() || 'sedan',
            service_tier: (el.serviceTier.value || 'standard').trim(),
            seats: seatValue,
            luggage_capacity_lbs: el.luggageCapacity ? Number(el.luggageCapacity.value || 0) : 0,
            color: el.color.value.trim()
        };

        if (rawLuggageValue === '') {
            if (vehicleId) {
                payload.luggage_capacity_lbs = null;
            } else {
                delete payload.luggage_capacity_lbs;
            }
        } else if (!Number.isFinite(payload.luggage_capacity_lbs) || payload.luggage_capacity_lbs <= 0) {
            setVehicleMsg('Luggage capacity must be a positive number in lbs.', true);
            if (el.saveVehicleBtn) {
                el.saveVehicleBtn.disabled = false;
            }
            return;
        } else {
            payload.luggage_capacity_lbs = Math.round(payload.luggage_capacity_lbs);
        }

        if (!payload.brand || !payload.model || payload.year < 1990 || !payload.license_plate) {
            setVehicleMsg('Please fill required fields correctly.', true);
            if (el.saveVehicleBtn) {
                el.saveVehicleBtn.disabled = false;
            }
            return;
        }

        if (!['eco', 'standard', 'luxury'].includes(payload.service_tier)) {
            setVehicleMsg('Vehicle tier must be eco, standard, or luxury.', true);
            if (el.saveVehicleBtn) {
                el.saveVehicleBtn.disabled = false;
            }
            return;
        }

        if (![5, 7].includes(payload.seats)) {
            setVehicleMsg('Seats must be 5 or 7.', true);
            if (el.saveVehicleBtn) {
                el.saveVehicleBtn.disabled = false;
            }
            return;
        }

        const imageFile = (el.vehicleImage && el.vehicleImage.files && el.vehicleImage.files.length > 0)
            ? el.vehicleImage.files[0]
            : null;

        try {
            let data;

            if (vehicleId) {
                data = await apiPost({
                    action: 'edit_vehicle',
                    vehicle_id: vehicleId,
                    ...payload
                });

                if (!data.success) {
                    setVehicleMsg(data.message || 'Save failed', true);
                    return;
                }

                if (imageFile) {
                    await uploadVehicleImage(imageFile, vehicleId);
                }
            } else {
                const imageIds = [];
                if (imageFile) {
                    const uploadData = await uploadVehicleImage(imageFile, '');
                    if (uploadData.image_id) {
                        imageIds.push(uploadData.image_id);
                    }
                }

                data = await apiPost({
                    action: 'add_vehicle',
                    ...payload,
                    image_ids: imageIds
                });

                if (!data.success) {
                    setVehicleMsg(data.message || 'Save failed', true);
                    return;
                }
            }

            setVehicleMsg(vehicleId ? 'Vehicle updated.' : 'Vehicle added.');
            resetVehicleForm();
            await loadVehicles();
        } catch (err) {
            setVehicleMsg((err && err.message) ? err.message : 'Save failed', true);
        } finally {
            if (el.saveVehicleBtn) {
                el.saveVehicleBtn.disabled = false;
            }
        }
    }

    function bindEvents() {
        el.tabs.forEach((btn) => {
            btn.addEventListener('click', () => switchTab(btn.getAttribute('data-tab')));
        });

        if (el.reloadOrdersBtn) {
            el.reloadOrdersBtn.addEventListener('click', loadOrders);
        }

        if (el.orderFilter) {
            el.orderFilter.addEventListener('change', loadOrders);
        }

        if (el.orderSearch) {
            el.orderSearch.addEventListener('input', renderOrdersTable);
        }

        if (el.reloadDriversBtn) {
            el.reloadDriversBtn.addEventListener('click', loadDrivers);
        }

        if (el.driverFilter) {
            el.driverFilter.addEventListener('change', loadDrivers);
        }

        if (el.driverSearch) {
            el.driverSearch.addEventListener('input', renderDriversTable);
        }

        if (el.vehicleSearch) {
            el.vehicleSearch.addEventListener('input', renderVehiclesTable);
        }

        if (el.vehicleTierFilter) {
            el.vehicleTierFilter.addEventListener('change', renderVehiclesTable);
        }

        if (el.vehicleSeatsFilter) {
            el.vehicleSeatsFilter.addEventListener('change', renderVehiclesTable);
        }

        if (el.saveVehicleBtn) {
            el.saveVehicleBtn.addEventListener('click', saveVehicle);
        }

        if (el.resetVehicleBtn) {
            el.resetVehicleBtn.addEventListener('click', resetVehicleForm);
        }

        if (el.orderModalClose) {
            el.orderModalClose.addEventListener('click', closeOrderDetailModal);
        }
        if (el.orderModal) {
            el.orderModal.addEventListener('click', (e) => {
                if (e.target === el.orderModal) {
                    closeOrderDetailModal();
                }
            });
        }
    }

    async function init() {
        bindEvents();
        await loadOrders();
        await loadVehicles();
        await loadDrivers();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
