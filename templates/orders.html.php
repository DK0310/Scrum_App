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

    <script src="/public/js/orders.js"></script>
    
    <!-- Set USER_ROLE from PHP before orders.js initializes -->
    <script>
    </script>

    <style>
        .review-modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 10000;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            animation: reviewOverlayIn 0.25s ease;
        }
        @keyframes reviewOverlayIn { from { opacity: 0; } to { opacity: 1; } }
        .review-modal {
            background: white; border-radius: 20px; width: 95%; max-width: 460px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25); overflow: hidden;
            animation: reviewModalIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes reviewModalIn { from { opacity: 0; transform: scale(0.85) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .review-modal-header {
            text-align: center; padding: 28px 28px 16px;
            background: linear-gradient(135deg, #fef9c3 0%, #fde68a 100%);
            border-bottom: 1px solid #fcd34d;
        }
        .review-modal-body { padding: 24px 28px; }
        .review-modal-footer { display: flex; gap: 10px; padding: 16px 28px 28px; }

        .review-stars-input {
            display: flex; gap: 6px; justify-content: center;
        }
        .review-star-btn {
            font-size: 2.2rem; cursor: pointer; color: var(--gray-300);
            transition: all 0.15s; user-select: none; line-height: 1;
        }
        .review-star-btn:hover, .review-star-btn.active {
            color: #f59e0b; transform: scale(1.15);
        }
        .review-star-btn:hover { text-shadow: 0 0 12px rgba(245,158,11,0.4); }
    </style>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
