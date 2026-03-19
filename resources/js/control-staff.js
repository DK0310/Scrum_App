(function () {
    const API = '/api/ControlStaff.php';

    let ordersCache = [];
    let vehiclesCache = [];

    const el = {
        tabs: document.querySelectorAll('.ctrl-tab'),
        ordersPanel: document.getElementById('ordersPanel'),
        vehiclesPanel: document.getElementById('vehiclesPanel'),

        orderFilter: document.getElementById('ctrlOrderStatusFilter'),
        reloadOrdersBtn: document.getElementById('ctrlReloadOrders'),
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
        color: document.getElementById('ctrlColor'),
        vehicleImage: document.getElementById('ctrlVehicleImage'),
        saveVehicleBtn: document.getElementById('ctrlSaveVehicle'),
        resetVehicleBtn: document.getElementById('ctrlResetVehicle'),
        vehiclesTable: document.getElementById('ctrlVehiclesTable'),
        vehicleMsg: document.getElementById('ctrlVehicleStatusMsg')
    };

    function setOrderMsg(text, isError) {
        el.orderMsg.textContent = text || '';
        el.orderMsg.style.color = isError ? '#b91c1c' : '#334155';
    }

    function setVehicleMsg(text, isError) {
        el.vehicleMsg.textContent = text || '';
        el.vehicleMsg.style.color = isError ? '#b91c1c' : '#334155';
    }

    function statusBadgeClass(status) {
        if (status === 'in_progress') return 'ctrl-badge ctrl-in-progress';
        if (status === 'done' || status === 'completed') return 'ctrl-badge ctrl-done';
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

    async function apiGet(params) {
        const qs = new URLSearchParams(params);
        const res = await fetch(API + '?' + qs.toString());
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
    }

    function normalizeDisplayStatus(status) {
        if (status === 'done') return 'completed';
        return status || 'pending';
    }

    function getNextStatusAction(status) {
        if (status === 'pending') {
            return { nextStatus: 'in_progress', label: 'Start Trip' };
        }
        if (status === 'in_progress') {
            return { nextStatus: 'completed', label: 'Mark Completed' };
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
            setOrderMsg('Loaded ' + ordersCache.length + ' order(s).');
        } catch (err) {
            setOrderMsg('Error loading orders: ' + (err?.message || 'Unknown error'), true);
            console.error('Load orders error:', err);
        }
    }

    function renderOrdersTable() {
        if (!ordersCache.length) {
            el.ordersTable.innerHTML = '<tr><td colspan="5">No orders found</td></tr>';
            return;
        }

        el.ordersTable.innerHTML = ordersCache.map((o) => {
            const id = escapeHtml(o.id || '');
            const customer = escapeHtml(o.customer_name || o.user_name || o.renter_name || 'Customer');
            const pickupDate = escapeHtml(o.pickup_date || '-');
            const total = Number(o.total_amount || 0).toFixed(2);
            const status = normalizeDisplayStatus(o.status);
            const nextAction = getNextStatusAction(status);
            const nextActionHtml = nextAction
                ? '<button class="ctrl-btn ctrl-btn-primary" data-role="advance-order" data-id="' + id + '" data-next-status="' + nextAction.nextStatus + '">' + escapeHtml(nextAction.label) + '</button>'
                : '<button class="ctrl-btn ctrl-btn-muted" disabled>No further action</button>';

            return '<tr>' +
                '<td>' + id + '</td>' +
                '<td>' + customer + '</td>' +
                '<td>' + pickupDate + '</td>' +
                '<td>$' + total + '</td>' +
                '<td class="ctrl-order-status-cell">' +
                    '<div class="ctrl-order-status-wrap">' +
                        '<span class="' + statusBadgeClass(status) + '">' + escapeHtml(status) + '</span>' +
                        nextActionHtml +
                        '<div class="ctrl-order-actions">' +
                            '<button class="ctrl-btn ctrl-btn-muted" data-role="detail-order" data-id="' + id + '">View</button>' +
                            '<button class="ctrl-btn ctrl-btn-danger" data-role="delete-order" data-id="' + id + '">Delete</button>' +
                        '</div>' +
                    '</div>' +
                '</td>' +
                '</tr>';
        }).join('');

        bindOrderRowActions();
    }

    function bindOrderRowActions() {
        const buttons = el.ordersTable.querySelectorAll('button[data-role="advance-order"]');
        console.log('Found ' + buttons.length + ' advance-order buttons');
        
        Array.from(buttons).forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                const status = btn.getAttribute('data-next-status');
                console.log('Advance order clicked - id:', id, 'next status:', status);
                if (!id || !status) {
                    console.log('Missing id or status');
                    return;
                }

                btn.disabled = true;
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
                }
            });
        });

        Array.from(el.ordersTable.querySelectorAll('button[data-role="detail-order"]')).forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                if (!id) return;

                const data = await apiGet({ action: 'get_order', order_id: id });
                if (!data.success) {
                    setOrderMsg(data.message || 'Cannot load order detail', true);
                    return;
                }

                const o = data.order || {};
                openOrderDetailModal(o);
            });
        });

        Array.from(el.ordersTable.querySelectorAll('button[data-role="delete-order"]')).forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                if (!id) return;
                if (!confirm('Delete this order permanently?')) return;

                const data = await apiPost({ action: 'delete_order', booking_id: id });
                if (!data.success) {
                    setOrderMsg(data.message || 'Delete failed', true);
                    return;
                }

                setOrderMsg('Order deleted.');
                await loadOrders();
                await loadVehicles();
            });
        });
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
        const pickupText = escapeHtml((order.pickup_location || '-') + ' @ ' + (order.pickup_date || '-'));
        const destinationText = escapeHtml(order.return_location || '-');
        const amountText = '$' + Number(order.total_amount || 0).toFixed(2);

        el.orderModalTitle.textContent = 'Order #' + (order.id || '');
        el.orderModalBody.innerHTML =
            '<div class="ctrl-modal-grid">' +
                '<div class="ctrl-kv"><div class="ctrl-k">Customer</div><div class="ctrl-v">' + customerName + '</div></div>' +
                '<div class="ctrl-kv"><div class="ctrl-k">Status</div><div class="ctrl-v">' + status + '</div></div>' +
                '<div class="ctrl-kv"><div class="ctrl-k">Pickup</div><div class="ctrl-v">' + pickupText + '</div></div>' +
                '<div class="ctrl-kv"><div class="ctrl-k">Destination</div><div class="ctrl-v">' + destinationText + '</div></div>' +
                '<div class="ctrl-kv"><div class="ctrl-k">Vehicle</div><div class="ctrl-v">' + vehicleText + '</div></div>' +
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
        el.serviceTier.value = vehicle.service_tier || 'standard';
        el.seats.value = vehicle.seats || 5;
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
        el.vehiclesTable.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';

        const data = await apiGet({ action: 'get_vehicles' });
        if (!data.success) {
            el.vehiclesTable.innerHTML = '<tr><td colspan="6">Cannot load vehicles</td></tr>';
            setVehicleMsg(data.message || 'Cannot load vehicles', true);
            return;
        }

        vehiclesCache = data.vehicles || [];
        renderVehiclesTable();
    }

    function renderVehiclesTable() {
        if (!vehiclesCache.length) {
            el.vehiclesTable.innerHTML = '<tr><td colspan="6">No vehicles</td></tr>';
            return;
        }

        el.vehiclesTable.innerHTML = vehiclesCache.map((v) => {
            const id = escapeHtml(v.id || '');
            const vm = escapeHtml((v.brand || '') + ' ' + (v.model || ''));
            const plate = escapeHtml(v.license_plate || '-');
            const cat = escapeHtml(v.category || '-');
            const tier = escapeHtml((v.service_tier || 'standard').toString());
            const status = escapeHtml(v.status || '-');

            return '<tr>' +
                '<td>' + vm + '</td>' +
                '<td>' + plate + '</td>' +
                '<td>' + cat + '</td>' +
                '<td>' + tier + '</td>' +
                '<td>' + status + '</td>' +
                '<td>' +
                    '<button class="ctrl-btn ctrl-btn-muted" data-role="edit-vehicle" data-id="' + id + '">Edit</button> ' +
                    '<button class="ctrl-btn ctrl-btn-danger" data-role="delete-vehicle" data-id="' + id + '">Delete</button>' +
                '</td>' +
                '</tr>';
        }).join('');

        Array.from(el.vehiclesTable.querySelectorAll('button[data-role="edit-vehicle"]')).forEach((btn) => {
            btn.addEventListener('click', () => {
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

    async function saveVehicle() {
        if (el.saveVehicleBtn) {
            el.saveVehicleBtn.disabled = true;
        }

        const payload = {
            brand: el.brand.value.trim(),
            model: el.model.value.trim(),
            year: Number(el.year.value || 0),
            license_plate: el.license.value.trim(),
            category: el.category.value.trim() || 'sedan',
            service_tier: (el.serviceTier.value || 'standard').trim(),
            seats: Number(el.seats.value || 5),
            color: el.color.value.trim()
        };

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

        const vehicleId = el.vehicleId.value.trim();
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
    }

    document.addEventListener('DOMContentLoaded', init);
})();
