<?php include __DIR__ . '/layout/header.html.php'; ?>

    <div class="admin-page" style="padding-top:100px;min-height:100vh;background:var(--gray-50);">
        <div class="admin-container" style="max-width:1400px;margin:0 auto;padding:0 24px 60px;">

            <!-- Admin Header -->
            <div style="margin-bottom:32px;">
                <h1 style="font-size:2rem;font-weight:800;color:var(--gray-900);">⚙️ Admin Dashboard</h1>
                <p style="color:var(--gray-500);margin-top:4px;">Manage users, hero slides, promotions, vehicles and bookings</p>
            </div>

            <!-- Tab Navigation -->
            <div class="admin-tabs" style="display:flex;gap:4px;margin-bottom:24px;background:white;padding:6px;border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);flex-wrap:wrap;">
                <button class="admin-tab active" onclick="switchTab('hero')" id="tab-hero">🖼️ Hero Slides</button>
                <button class="admin-tab" onclick="switchTab('promotions')" id="tab-promotions">🎉 Promotions</button>
                <button class="admin-tab" onclick="switchTab('users')" id="tab-users">👥 Users</button>
                <button class="admin-tab" onclick="switchTab('vehicles')" id="tab-vehicles">🚗 Vehicles</button>
                <button class="admin-tab" onclick="switchTab('bookings')" id="tab-bookings">📋 Bookings</button>
            </div>

            <!-- ===== HERO SLIDES TAB ===== -->
            <div class="admin-panel" id="panel-hero">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                    <h2 style="font-size:1.25rem;font-weight:700;">Hero Slideshow Images</h2>
                    <button class="btn btn-primary" onclick="showHeroUpload()">+ Add Slide</button>
                </div>

                <!-- Upload Form (hidden) -->
                <div id="heroUploadForm" style="display:none;background:var(--gray-50);padding:20px;border-radius:var(--radius-md);margin-bottom:20px;border:2px dashed var(--gray-300);">
                    <h3 style="font-weight:600;margin-bottom:12px;">Upload New Hero Slide</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label style="font-size:0.8rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">Image *</label>
                            <input type="file" id="heroImageFile" accept="image/*" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:0.875rem;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">Title</label>
                            <input type="text" id="heroTitle" placeholder="Slide title" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:0.875rem;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">Subtitle</label>
                            <input type="text" id="heroSubtitle" placeholder="Slide subtitle" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:0.875rem;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">Sort Order</label>
                            <input type="number" id="heroSortOrder" value="0" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:0.875rem;">
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;margin-top:12px;">
                        <button class="btn btn-primary" onclick="uploadHeroSlide()">Upload</button>
                        <button class="btn btn-outline" onclick="document.getElementById('heroUploadForm').style.display='none'">Cancel</button>
                    </div>
                </div>

                <div id="heroSlidesList" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                    <div style="text-align:center;padding:40px;color:var(--gray-400);grid-column:1/-1;">Loading slides...</div>
                </div>
            </div>

            <!-- ===== PROMOTIONS TAB ===== -->
            <div class="admin-panel" id="panel-promotions" style="display:none;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                    <h2 style="font-size:1.25rem;font-weight:700;">Promotions</h2>
                    <button class="btn btn-primary" onclick="showPromoForm()">+ Add Promotion</button>
                </div>

                <!-- Promo Form (hidden) -->
                <div id="promoAddForm" style="display:none;background:var(--gray-50);padding:20px;border-radius:var(--radius-md);margin-bottom:20px;border:2px dashed var(--gray-300);">
                    <h3 style="font-weight:600;margin-bottom:12px;" id="promoFormTitle">Add Promotion</h3>
                    <input type="hidden" id="promoEditId" value="">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <div>
                            <label style="font-size:0.8rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">Code *</label>
                            <input type="text" id="promoCode" placeholder="e.g. SUMMER20" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:0.875rem;text-transform:uppercase;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">Discount Type</label>
                            <select id="promoDiscountType" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:0.875rem;">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed ($)</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.8rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">Discount Value *</label>
                            <input type="number" id="promoDiscountValue" placeholder="20" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:0.875rem;">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label style="font-size:0.8rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">Description *</label>
                            <input type="text" id="promoDescription" placeholder="Summer sale - 20% off" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:0.875rem;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">Start Date</label>
                            <input type="date" id="promoStartDate" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:0.875rem;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">End Date</label>
                            <input type="date" id="promoEndDate" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:0.875rem;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">Usage Limit</label>
                            <input type="number" id="promoUsageLimit" placeholder="100" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:0.875rem;">
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;margin-top:12px;">
                        <button class="btn btn-primary" onclick="savePromotion()" id="promoSaveBtn">Add</button>
                        <button class="btn btn-outline" onclick="hidePromoForm()">Cancel</button>
                    </div>
                </div>

                <div id="promoList">
                    <div style="text-align:center;padding:40px;color:var(--gray-400);">Loading promotions...</div>
                </div>
            </div>

            <!-- ===== USERS TAB ===== -->
            <div class="admin-panel" id="panel-users" style="display:none;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                    <h2 style="font-size:1.25rem;font-weight:700;">👥 All Users</h2>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <input type="text" id="userSearchInput" placeholder="Search name, email..." oninput="filterUsers()" style="padding:8px 14px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:0.85rem;width:220px;">
                        <select id="userRoleFilter" onchange="filterUsers()" style="padding:8px 12px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:0.85rem;">
                            <option value="">All Roles</option>
                            <option value="user">User</option>
                            <option value="driver">Driver</option>
                            <option value="callcenterstaff">Call Center Staff</option>
                            <option value="controlstaff">Control Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div id="adminUsersList">
                    <div style="text-align:center;padding:40px;color:var(--gray-400);">Loading users...</div>
                </div>
            </div>

            <!-- ===== VEHICLES TAB ===== -->
            <div class="admin-panel" id="panel-vehicles" style="display:none;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                    <h2 style="font-size:1.25rem;font-weight:700;">🚗 All Vehicles</h2>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <select id="vehicleOwnerFilter" onchange="filterAdminVehicles()" style="padding:8px 12px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:0.85rem;">
                            <option value="">All Added By Staff</option>
                        </select>
                        <select id="vehicleCategoryFilter" onchange="filterAdminVehicles()" style="padding:8px 12px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:0.85rem;">
                            <option value="">All Types</option>
                        </select>
                        <select id="vehicleStatusFilter" onchange="filterAdminVehicles()" style="padding:8px 12px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:0.85rem;">
                            <option value="">All Status</option>
                            <option value="available">Available</option>
                            <option value="rented">Rented</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div id="adminVehiclesList">
                    <div style="text-align:center;padding:40px;color:var(--gray-400);">Loading vehicles...</div>
                </div>
            </div>

            <!-- ===== BOOKINGS TAB ===== -->
            <div class="admin-panel" id="panel-bookings" style="display:none;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                    <h2 style="font-size:1.25rem;font-weight:700;">📋 All Bookings</h2>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <input type="text" id="bookingSearchInput" placeholder="Search customer or driver..." oninput="filterAdminBookings()" style="padding:8px 14px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:0.85rem;width:220px;">
                        <select id="bookingStatusFilter" onchange="filterAdminBookings()" style="padding:8px 12px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:0.85rem;">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div id="adminBookingsList">
                    <div style="text-align:center;padding:40px;color:var(--gray-400);">Loading bookings...</div>
                </div>
            </div>

        </div>
    </div>

    <style>
        .admin-tab {
            padding: 10px 20px; border-radius: var(--radius-md); font-weight: 600;
            font-size: 0.875rem; cursor: pointer; border: none; background: transparent;
            color: var(--gray-500); transition: var(--transition);
        }
        .admin-tab.active { background: var(--primary); color: white; }
        .admin-tab:hover:not(.active) { background: var(--gray-100); color: var(--gray-700); }
        .admin-panel {
            background: white; border-radius: var(--radius-lg); padding: 24px;
            box-shadow: var(--shadow-sm);
        }
        .admin-card {
            background: white; border-radius: var(--radius-md); border: 1px solid var(--gray-200);
            overflow: hidden; transition: var(--transition);
        }
        .admin-card:hover { box-shadow: var(--shadow-md); }
        .admin-card-img {
            height: 160px; background: var(--gray-100); display: flex;
            align-items: center; justify-content: center; overflow: hidden;
        }
        .admin-card-img img { width: 100%; height: 100%; object-fit: cover; }
        .admin-card-body { padding: 14px; }
        .admin-card-actions { display: flex; gap: 6px; margin-top: 10px; }
        .admin-badge {
            display: inline-block; padding: 3px 10px; border-radius: var(--radius-full);
            font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
        }
        .admin-badge.active { background: #dcfce7; color: #166534; }
        .admin-badge.inactive { background: #fef2f2; color: #991b1b; }
        .admin-table {
            width: 100%; border-collapse: collapse; font-size: 0.875rem;
        }
        .admin-table th {
            text-align: left; padding: 10px 12px; background: var(--gray-50);
            font-weight: 600; color: var(--gray-600); font-size: 0.75rem;
            text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid var(--gray-200);
        }
        .admin-table td {
            padding: 10px 12px; border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
        }
        .admin-table tr:hover td { background: var(--gray-50); }
        .btn-xs {
            padding: 4px 10px; font-size: 0.75rem; border-radius: var(--radius);
            border: none; cursor: pointer; font-weight: 600; transition: var(--transition);
        }
        .btn-xs:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-xs.danger { background: #fef2f2; color: #dc2626; }
        .btn-xs.danger:hover:not(:disabled) { background: #dc2626; color: white; }
        .btn-xs.edit { background: #eff6ff; color: #2563eb; }
        .btn-xs.edit:hover:not(:disabled) { background: #2563eb; color: white; }
        .btn-xs.toggle { background: #f0fdf4; color: #16a34a; }
        .btn-xs.toggle:hover:not(:disabled) { background: #16a34a; color: white; }

        /* Admin inline notification banner */
        .admin-alert {
            display: flex; align-items: center; gap: 10px; padding: 12px 18px;
            border-radius: var(--radius-md); margin-bottom: 16px; font-size: 0.875rem;
            font-weight: 500; animation: adminAlertIn 0.35s ease;
        }
        .admin-alert.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .admin-alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .admin-alert.warning { background: #fefce8; color: #854d0e; border: 1px solid #fef08a; }
        .admin-alert-close { background: none; border: none; cursor: pointer; font-size: 1.1rem; margin-left: auto; opacity: 0.6; }
        .admin-alert-close:hover { opacity: 1; }
        @keyframes adminAlertIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <!-- Admin alert container (always visible, inside admin page) -->
    <div id="adminAlertContainer" style="position:fixed;top:80px;right:24px;z-index:10002;display:flex;flex-direction:column;gap:10px;max-width:420px;"></div>

    <script src="/resources/js/admin.js"></script>
    <script>
        // Admin module initialized above
        document.addEventListener('DOMContentLoaded', () => loadHeroSlides());
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
