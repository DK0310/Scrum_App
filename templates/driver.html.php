<?php
/**
 * Driver Dashboard Template
 * Requires: $userRole, $currentUser, $isLoggedIn (set by public/driver.php)
 */
?>
<?php include __DIR__ . '/layout/header.html.php'; ?>
    <style>
        body { background: #f8fafc; }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            color: #1e293b;
            font-size: 2rem;
            margin: 0 0 10px 0;
        }

        .dashboard-header p {
            color: #64748b;
            font-size: 0.95rem;
        }

        /* Tabs */
        .dashboard-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
        }

        .dashboard-tabs button {
            padding: 12px 20px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 1rem;
            color: #64748b;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            font-weight: 500;
        }

        .dashboard-tabs button.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
        }

        .dashboard-tabs button:hover {
            color: #1e293b;
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Cards Grid */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }

        .card-icon {
            font-size: 2rem;
            margin-bottom: 12px;
        }

        .card-title {
            font-weight: 600;
            color: #1e293b;
            margin: 12px 0;
        }

        .card-desc {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 16px;
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #2563eb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .stat-card.green { border-left-color: #16a34a; }
        .stat-card.orange { border-left-color: #ea580c; }
        .stat-card.red { border-left-color: #dc2626; }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 0.875rem;
        }

        td {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }

        tr:hover {
            background: #f8fafc;
        }

        /* Buttons */
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.875rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .btn-primary-sm {
            background: #2563eb;
            color: white;
        }

        .btn-primary-sm:hover {
            background: #1d4ed8;
        }

        .btn-secondary-sm {
            background: #e2e8f0;
            color: #334155;
        }

        .btn-secondary-sm:hover {
            background: #cbd5e1;
        }

        .btn-success-sm {
            background: #16a34a;
            color: white;
        }

        .btn-success-sm:hover {
            background: #15803d;
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }

        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid #e2e8f0;
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Assigned vehicle card */
        .assigned-vehicle {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }
        .assigned-vehicle-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 0.9rem;
            color: #475569;
        }
        .assigned-vehicle-row strong { color: #0f172a; }
        .assigned-vehicle-empty {
            padding: 14px 12px;
            border: 1px dashed #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
<?php
/**
 * Driver Dashboard Template
 * Requires: $userRole, $currentUser, $isLoggedIn (set by public/driver.php)
 */
?>
<?php include __DIR__ . '/layout/header.html.php'; ?>

    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1>🚗 Driver Dashboard</h1>
            <p>Track your trips, manage availability, and view earnings</p>
        </div>

        <!-- Tabs -->
        <div class="dashboard-tabs">
            <button class="tab-btn active" onclick="switchTab('overview')">📊 Overview</button>
            <button class="tab-btn" onclick="switchTab('available')">📍 Available Trips</button>
            <button class="tab-btn" onclick="switchTab('mytrips')">🚗 My Trips</button>
            <button class="tab-btn" onclick="switchTab('earnings')">💰 Earnings</button>
        </div>

        <!-- TAB 1: Overview -->
        <div id="overview" class="tab-content active">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value" id="totalTrips">0</div>
                    <div class="stat-label">Total Trips</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-value" id="completedTrips">0</div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-value" id="activeTrips">0</div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card red">
                    <div class="stat-value">$0</div>
                    <div class="stat-label">Today's Earnings</div>
                </div>
            </div>

            <div class="card-grid">
                <div class="card">
                    <div class="card-icon">📍</div>
                    <div class="card-title">Available Trips</div>
                    <div class="card-desc">Check nearby trips waiting for drivers</div>
                    <button class="btn-sm btn-primary-sm" onclick="switchTab('available')">View Trips</button>
                </div>
                <div class="card">
                    <div class="card-icon">🚗</div>
                    <div class="card-title">My Active Trips</div>
                    <div class="card-desc">Track current and upcoming trips</div>
                    <button class="btn-sm btn-primary-sm" onclick="switchTab('mytrips')">View My Trips</button>
                </div>
                <div class="card">
                    <div class="card-icon">💰</div>
                    <div class="card-title">Earnings Summary</div>
                    <div class="card-desc">View your daily, weekly, and monthly income</div>
                    <button class="btn-sm btn-primary-sm" onclick="switchTab('earnings')">View Earnings</button>
                </div>

                <!-- Assigned vehicle (new) -->
                <div class="card">
                    <div class="card-icon">🚙</div>
                    <div class="card-title">Assigned Vehicle</div>
                    <div class="card-desc">Vehicle assigned to you for today</div>
                    <div id="assignedVehicleCard" class="assigned-vehicle">
                        <div class="assigned-vehicle-empty">Loading...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: Available Trips -->
        <div id="available" class="tab-content">
            <h2 style="color: #1e293b; margin-bottom: 16px;">Available Trips Nearby</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Pickup Location</th>
                            <th>Destination</th>
                            <th>Passenger</th>
                            <th>Pickup Time</th>
                            <th>Estimated Fare</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tripsTable">
                        <tr>
                            <td colspan="6" class="loading">
                                <div class="spinner"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: My Trips -->
        <div id="mytrips" class="tab-content">
            <h2 style="color: #1e293b; margin-bottom: 16px;">My Trips</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Pickup Location</th>
                            <th>Destination</th>
                            <th>Passenger</th>
                            <th>Time</th>
                            <th>Fare</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="myTripsTable">
                        <tr>
                            <td colspan="7" class="loading">
                                <div class="spinner"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 4: Earnings -->
        <div id="earnings" class="tab-content">
            <h2 style="color: #1e293b; margin-bottom: 16px;">Earnings Summary</h2>
            <div class="card-grid">
                <div class="card">
                    <div class="card-icon">📅</div>
                    <div class="card-title">Today's Earnings</div>
                    <div style="font-size: 2rem; font-weight: 800; color: #16a34a; margin: 12px 0;">$0.00</div>
                    <div class="card-desc">0 trips completed</div>
                </div>
                <div class="card">
                    <div class="card-icon">📊</div>
                    <div class="card-title">This Week</div>
                    <div style="font-size: 2rem; font-weight: 800; color: #2563eb; margin: 12px 0;">$0.00</div>
                    <div class="card-desc">0 trips completed</div>
                </div>
                <div class="card">
                    <div class="card-icon">📈</div>
                    <div class="card-title">This Month</div>
                    <div style="font-size: 2rem; font-weight: 800; color: #ea580c; margin: 12px 0;">$0.00</div>
                    <div class="card-desc">0 trips completed</div>
                </div>
            </div>
            <div class="table-container" style="margin-top: 20px;">
                <table>
                    <thead>
                        <tr>
                            <th>Trip Date</th>
                            <th>Pickup → Destination</th>
                            <th>Duration</th>
                            <th>Fare</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="earningsTable">
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px;">No earnings yet</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '../api/driver.php';

        // Tab switching
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');

            // Load data based on tab
            if (tabName === 'available') loadAvailableTrips();
            else if (tabName === 'mytrips') loadMyTrips();
            else if (tabName === 'earnings') loadEarnings();
            else if (tabName === 'overview') loadOverview();
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"]/g, (c) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;'
            }[c]));
        }

        function renderAssignedVehicle(vehicle) {
            const el = document.getElementById('assignedVehicleCard');
            if (!el) return;

            if (!vehicle) {
                el.innerHTML = '<div class="assigned-vehicle-empty">No assigned yet</div>';
                return;
            }

            const title = `${escapeHtml(vehicle.brand)} ${escapeHtml(vehicle.model)} ${escapeHtml(vehicle.year)}`.trim();
            const plate = escapeHtml(vehicle.license_plate || '—');
            const color = escapeHtml(vehicle.color || '—');
            const transmission = escapeHtml(vehicle.transmission || '—');
            const fuel = escapeHtml(vehicle.fuel_type || '—');
            const seats = escapeHtml(vehicle.seats || '—');
            const assignedDate = escapeHtml(vehicle.assigned_date || '—');

            el.innerHTML = `
                <div style="font-weight:700;color:#0f172a;">${title}</div>
                <div class="assigned-vehicle-row"><span>License</span><strong>${plate}</strong></div>
                <div class="assigned-vehicle-row"><span>Color</span><strong>${color}</strong></div>
                <div class="assigned-vehicle-row"><span>Transmission</span><strong>${transmission}</strong></div>
                <div class="assigned-vehicle-row"><span>Fuel</span><strong>${fuel}</strong></div>
                <div class="assigned-vehicle-row"><span>Seats</span><strong>${seats}</strong></div>
                <div class="assigned-vehicle-row"><span>Assigned Date</span><strong>${assignedDate}</strong></div>
            `;
        }

        async function loadAssignedVehicle() {
            const el = document.getElementById('assignedVehicleCard');
            if (el) el.innerHTML = '<div class="assigned-vehicle-empty">Loading...</div>';

            try {
                const res = await fetch(`${API_BASE}?action=get_assigned_vehicle`);
                const data = await res.json();

                if (data && data.success && data.vehicle) {
                    renderAssignedVehicle(data.vehicle);
                } else {
                    renderAssignedVehicle(null);
                }
            } catch (e) {
                renderAssignedVehicle(null);
            }
        }

        // Load Overview
        async function loadOverview() {
            // Mock data for now - implement when API ready
            document.getElementById('totalTrips').textContent = '12';
            document.getElementById('completedTrips').textContent = '12';
            document.getElementById('activeTrips').textContent = '0';

            // Assigned vehicle block
            await loadAssignedVehicle();
        }

        // Load Available Trips
        async function loadAvailableTrips() {
            const tbody = document.getElementById('tripsTable');
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px;">No trips available at the moment</td></tr>';
        }

        // Load My Trips
        async function loadMyTrips() {
            const tbody = document.getElementById('myTripsTable');
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px;">No trips currently</td></tr>';
        }

        // Load Earnings
        async function loadEarnings() {
            const tbody = document.getElementById('earningsTable');
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px;">No earnings recorded yet</td></tr>';
        }

        // Navbar functions
        function toggleSideMenu() {
            const sideMenu = document.getElementById('sideMenu');
            const overlay = document.getElementById('sideMenuOverlay');
            sideMenu.classList.toggle('open');
            overlay.classList.toggle('open');
        }

        function closeSideMenu() {
            const sideMenu = document.getElementById('sideMenu');
            const overlay = document.getElementById('sideMenuOverlay');
            sideMenu.classList.remove('open');
            overlay.classList.remove('open');
        }

        function toggleNotifications() {
            const panel = document.getElementById('notificationPanel');
            if (panel) {
                panel.classList.toggle('open');
            }
        }

        function toggleLanguageMenu() {
            const languages = ['EN', 'VI', 'FR', 'DE', 'ES'];
            const btn = document.getElementById('langBtn');
            const current = btn.textContent.split(' ')[1] || 'EN';
            const currentIdx = languages.indexOf(current);
            const nextIdx = (currentIdx + 1) % languages.length;
            btn.textContent = '🌐 ' + languages[nextIdx];
        }

        async function logout() {
            try {
                await fetch('../api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'logout' })
                });
                window.location.href = 'index.php';
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = 'index.php';
            }
        }

        // Load initial overview
        window.addEventListener('load', loadOverview);
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
