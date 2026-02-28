<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== MY ORDERS PAGE ===== -->
    <section class="section" style="padding-top:100px;min-height:100vh;background:var(--gray-50);" id="orders">
        <div class="section-container" style="max-width:1000px;">
            <div class="section-header" style="margin-bottom:32px;">
                <div>
                    <h2 class="section-title">📋 My Orders</h2>
                    <p class="section-subtitle">Track and manage your bookings</p>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="order-tabs" id="orderTabs">
                <button class="order-tab active" data-status="all" onclick="filterOrders('all')">All</button>
                <button class="order-tab" data-status="pending" onclick="filterOrders('pending')">⏳ Pending</button>
                <button class="order-tab" data-status="confirmed" onclick="filterOrders('confirmed')">✅ Confirmed</button>
                <button class="order-tab" data-status="in_progress" onclick="filterOrders('in_progress')">🚗 In Progress</button>
                <button class="order-tab" data-status="completed" onclick="filterOrders('completed')">✔️ Completed</button>
                <button class="order-tab" data-status="cancelled" onclick="filterOrders('cancelled')">❌ Cancelled</button>
            </div>

            <!-- Loading -->
            <div id="ordersLoading" style="text-align:center;padding:60px 0;">
                <div style="width:40px;height:40px;border:3px solid var(--gray-200);border-top-color:var(--primary);border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 16px;"></div>
                <p style="color:var(--gray-500);">Loading your orders...</p>
            </div>

            <!-- Empty State -->
            <div id="ordersEmpty" style="display:none;text-align:center;padding:60px 0;">
                <div style="font-size:3rem;margin-bottom:16px;">📭</div>
                <h3 style="color:var(--gray-700);margin-bottom:8px;">No orders yet</h3>
                <p style="color:var(--gray-500);margin-bottom:24px;">Start by browsing our car listings and make your first booking!</p>
                <a href="cars.php" class="btn btn-primary" style="display:inline-block;width:auto;padding:12px 32px;">Browse Cars</a>
            </div>

            <!-- Orders List -->
            <div id="ordersList" style="display:none;"></div>
        </div>
    </section>

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }

        .order-tabs {
            display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px;
            padding: 4px; background: white; border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200);
        }
        .order-tab {
            padding: 10px 18px; border: none; border-radius: var(--radius-md);
            background: transparent; font-size: 0.85rem; font-weight: 600;
            color: var(--gray-500); cursor: pointer; transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .order-tab:hover { background: var(--gray-100); color: var(--gray-700); }
        .order-tab.active { background: var(--primary); color: white; }

        .order-card {
            background: white; border-radius: var(--radius-lg); overflow: hidden;
            box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200);
            margin-bottom: 16px; transition: all 0.2s;
        }
        .order-card:hover { box-shadow: var(--shadow-md); }

        .order-card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 24px; border-bottom: 1px solid var(--gray-100);
        }
        .order-card-left { display: flex; align-items: center; gap: 16px; }
        .order-car-thumb {
            width: 80px; height: 56px; border-radius: var(--radius-md); overflow: hidden;
            background: var(--gray-100); flex-shrink: 0;
        }
        .order-car-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .order-car-info h4 { font-size: 1rem; font-weight: 700; color: var(--gray-900); margin-bottom: 2px; }
        .order-car-info p { font-size: 0.8rem; color: var(--gray-500); }

        .order-status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 999px; font-size: 0.78rem; font-weight: 700;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-in_progress { background: #e0e7ff; color: #3730a3; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        .order-card-body {
            padding: 16px 24px;
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px;
        }
        .order-detail-item {
            display: flex; flex-direction: column; gap: 2px;
        }
        .order-detail-label { font-size: 0.75rem; color: var(--gray-400); font-weight: 500; }
        .order-detail-value { font-size: 0.875rem; font-weight: 600; color: var(--gray-800); }

        .order-card-footer {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 24px; background: var(--gray-50); border-top: 1px solid var(--gray-100);
        }
        .order-total { font-size: 1.1rem; font-weight: 800; color: var(--gray-900); }
        .order-actions { display: flex; gap: 8px; }

        /* Owner section */
        .owner-renter-info {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; background: var(--primary-50); border-radius: var(--radius-md);
            margin: 0 24px 16px; font-size: 0.85rem;
        }
        .owner-renter-info span { font-weight: 600; color: var(--primary); }

        @media (max-width: 600px) {
            .order-card-header { flex-direction: column; gap: 12px; align-items: flex-start; }
            .order-card-body { grid-template-columns: 1fr 1fr; }
        }
    </style>

    <script>
        const BOOKINGS_API = '/api/bookings.php';
        const VEHICLES_API = '/api/vehicles.php';
        const USER_ROLE = '<?= htmlspecialchars($userRole) ?>';
        let allOrders = [];
        let currentFilter = 'all';

        document.addEventListener('DOMContentLoaded', loadOrders);

        async function loadOrders() {
            document.getElementById('ordersLoading').style.display = 'block';
            document.getElementById('ordersEmpty').style.display = 'none';
            document.getElementById('ordersList').style.display = 'none';

            try {
                const res = await fetch(BOOKINGS_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'my-orders' })
                });
                const text = await res.text();
                console.log('Orders API raw response:', text);
                let data;
                try { data = JSON.parse(text); } catch(e) { console.error('JSON parse error:', e, text); throw e; }

                document.getElementById('ordersLoading').style.display = 'none';

                if (data.success && data.orders && data.orders.length > 0) {
                    allOrders = data.orders;
                    renderOrders(allOrders);
                    document.getElementById('ordersList').style.display = 'block';
                } else {
                    document.getElementById('ordersEmpty').style.display = 'block';
                }
            } catch (err) {
                console.error('Failed to load orders:', err);
                document.getElementById('ordersLoading').style.display = 'none';
                document.getElementById('ordersEmpty').style.display = 'block';
            }
        }

        function filterOrders(status) {
            currentFilter = status;
            document.querySelectorAll('.order-tab').forEach(t => t.classList.toggle('active', t.dataset.status === status));

            const filtered = status === 'all' ? allOrders : allOrders.filter(o => o.status === status);
            if (filtered.length === 0) {
                document.getElementById('ordersList').innerHTML = '<div style="text-align:center;padding:40px 0;color:var(--gray-400);">No orders with this status.</div>';
            } else {
                renderOrders(filtered);
            }
        }

        function renderOrders(orders) {
            const container = document.getElementById('ordersList');
            container.innerHTML = orders.map(order => {
                const statusLabels = {
                    pending: '⏳ Pending',
                    confirmed: '✅ Confirmed',
                    in_progress: '🚗 In Progress',
                    completed: '✔️ Completed',
                    cancelled: '❌ Cancelled'
                };
                const typeLabels = {
                    'self-drive': 'Self-Drive',
                    'with-driver': 'With Driver',
                    'airport': 'Airport Transfer'
                };
                const pmLabels = {
                    cash: '💵 Cash',
                    bank_transfer: '🏦 Banking',
                    paypal: '🅿️ PayPal',
                    credit_card: '💳 Card'
                };

                const carName = (order.brand || '') + ' ' + (order.model || '');
                const thumbUrl = order.thumbnail_url || '';
                const thumbHtml = thumbUrl
                    ? '<img src="' + thumbUrl + '" alt="' + carName + '">'
                    : '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--gray-400);font-size:0.7rem;">No Photo</div>';

                let actionsHtml = '';

                // Owner actions: Confirm → Delivery (in_progress) → Done (completed)
                if (USER_ROLE === 'owner' || USER_ROLE === 'admin') {
                    if (order.is_owner) {
                        if (order.status === 'pending') {
                            actionsHtml = '<button class="btn btn-primary btn-sm" onclick="updateOrderStatus(\'' + order.id + '\', \'confirmed\')">✅ Confirm</button>'
                                + '<button class="btn btn-danger btn-sm" onclick="updateOrderStatus(\'' + order.id + '\', \'cancelled\')">❌ Cancel</button>';
                        } else if (order.status === 'confirmed') {
                            actionsHtml = '<button class="btn btn-primary btn-sm" onclick="updateOrderStatus(\'' + order.id + '\', \'in_progress\')">🚗 Start Delivery</button>';
                        } else if (order.status === 'in_progress') {
                            actionsHtml = '<button class="btn btn-primary btn-sm" onclick="updateOrderStatus(\'' + order.id + '\', \'completed\')">✔️ Mark Done</button>';
                        }
                    }
                }

                // Renter: can cancel if pending
                if (order.is_renter && order.status === 'pending') {
                    actionsHtml = '<button class="btn btn-danger btn-sm" onclick="updateOrderStatus(\'' + order.id + '\', \'cancelled\')">❌ Cancel</button>';
                }

                // Renter info for owner view
                let renterInfoHtml = '';
                if (order.is_owner && order.renter_name) {
                    renterInfoHtml = '<div class="owner-renter-info">👤 Renter: <span>' + order.renter_name + '</span> — ' + (order.renter_email || '') + '</div>';
                }

                return '<div class="order-card" data-status="' + order.status + '">' +
                    '<div class="order-card-header">' +
                        '<div class="order-card-left">' +
                            '<div class="order-car-thumb">' + thumbHtml + '</div>' +
                            '<div class="order-car-info">' +
                                '<h4>' + carName + '</h4>' +
                                '<p>' + (typeLabels[order.booking_type] || order.booking_type) + ' · Order #' + order.id.substring(0, 8) + '</p>' +
                            '</div>' +
                        '</div>' +
                        '<div class="order-status-badge status-' + order.status + '">' + (statusLabels[order.status] || order.status) + '</div>' +
                    '</div>' +
                    renterInfoHtml +
                    '<div class="order-card-body">' +
                        '<div class="order-detail-item"><div class="order-detail-label">Pick-up Date</div><div class="order-detail-value">' + formatDate(order.pickup_date) + '</div></div>' +
                        (order.return_date ? '<div class="order-detail-item"><div class="order-detail-label">Return Date</div><div class="order-detail-value">' + formatDate(order.return_date) + '</div></div>' : '') +
                        '<div class="order-detail-item"><div class="order-detail-label">Pick-up Location</div><div class="order-detail-value">' + truncate(order.pickup_location || '-', 40) + '</div></div>' +
                        (order.return_location && order.booking_type !== 'self-drive' ? '<div class="order-detail-item"><div class="order-detail-label">' + (order.booking_type === 'airport' ? 'Drop-off' : 'Destination') + '</div><div class="order-detail-value">' + truncate(order.return_location || '-', 40) + '</div></div>' : '') +
                        (order.distance_km ? '<div class="order-detail-item"><div class="order-detail-label">📏 Distance</div><div class="order-detail-value">' + parseFloat(order.distance_km).toFixed(1) + ' km</div></div>' : '') +
                        '<div class="order-detail-item"><div class="order-detail-label">Payment</div><div class="order-detail-value">' + (pmLabels[order.payment_method] || order.payment_method || 'N/A') + '</div></div>' +
                        '<div class="order-detail-item"><div class="order-detail-label">Booked On</div><div class="order-detail-value">' + formatDate(order.created_at) + '</div></div>' +
                    '</div>' +
                    '<div class="order-card-footer">' +
                        '<div class="order-total">$' + parseFloat(order.total_amount).toFixed(2) + '</div>' +
                        '<div class="order-actions">' + actionsHtml + '</div>' +
                    '</div>' +
                '</div>';
            }).join('');
        }

        async function updateOrderStatus(bookingId, newStatus) {
            const labels = { confirmed: 'confirm', in_progress: 'start delivery for', completed: 'mark as done', cancelled: 'cancel' };
            if (!confirm('Are you sure you want to ' + (labels[newStatus] || newStatus) + ' this order?')) return;

            try {
                const res = await fetch(BOOKINGS_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update-status', booking_id: bookingId, status: newStatus })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('✅ Order updated!', 'success');
                    loadOrders();
                } else {
                    showToast('❌ ' + (data.message || 'Failed to update.'), 'error');
                }
            } catch (err) {
                showToast('Connection error. Please try again.', 'error');
            }
        }

        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function truncate(str, max) {
            return str.length > max ? str.substring(0, max) + '...' : str;
        }
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
