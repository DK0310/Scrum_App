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
                            <option value="renter">Renter</option>
                            <option value="owner">Owner</option>
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
                            <option value="">All Owners</option>
                        </select>
                        <select id="vehicleCategoryFilter" onchange="filterAdminVehicles()" style="padding:8px 12px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:0.85rem;">
                            <option value="">All Types</option>
                        </select>
                        <select id="vehicleStatusFilter" onchange="filterAdminVehicles()" style="padding:8px 12px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:0.85rem;">
                            <option value="">All Status</option>
                            <option value="available">Available</option>
                            <option value="rented">Rented</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="inactive">Inactive</option>
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
                        <input type="text" id="bookingSearchInput" placeholder="Search renter or owner..." oninput="filterAdminBookings()" style="padding:8px 14px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:0.85rem;width:220px;">
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
        .btn-xs.danger { background: #fef2f2; color: #dc2626; }
        .btn-xs.danger:hover { background: #dc2626; color: white; }
        .btn-xs.edit { background: #eff6ff; color: #2563eb; }
        .btn-xs.edit:hover { background: #2563eb; color: white; }
        .btn-xs.toggle { background: #f0fdf4; color: #16a34a; }
        .btn-xs.toggle:hover { background: #16a34a; color: white; }
    </style>

    <script>
        const ADMIN_API = '/api/admin.php';

        // ===== TAB SWITCHING =====
        function switchTab(tab) {
            document.querySelectorAll('.admin-panel').forEach(p => p.style.display = 'none');
            document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
            document.getElementById('panel-' + tab).style.display = 'block';
            document.getElementById('tab-' + tab).classList.add('active');

            // Load data for tab
            if (tab === 'hero') loadHeroSlides();
            if (tab === 'promotions') loadPromotions();
            if (tab === 'users') loadAdminUsers();
            if (tab === 'vehicles') loadAdminVehicles();
            if (tab === 'bookings') loadAdminBookings();
        }

        // ===== HERO SLIDES =====
        function showHeroUpload() {
            document.getElementById('heroUploadForm').style.display = 'block';
        }

        async function uploadHeroSlide() {
            const fileInput = document.getElementById('heroImageFile');
            if (!fileInput.files[0]) {
                showToast('Please select an image.', 'error');
                return;
            }

            const fd = new FormData();
            fd.append('image', fileInput.files[0]);
            fd.append('title', document.getElementById('heroTitle').value);
            fd.append('subtitle', document.getElementById('heroSubtitle').value);
            fd.append('sort_order', document.getElementById('heroSortOrder').value);

            try {
                const res = await fetch(ADMIN_API + '?action=hero-slide-upload', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showToast('Slide uploaded!', 'success');
                    document.getElementById('heroUploadForm').style.display = 'none';
                    fileInput.value = '';
                    document.getElementById('heroTitle').value = '';
                    document.getElementById('heroSubtitle').value = '';
                    document.getElementById('heroSortOrder').value = '0';
                    loadHeroSlides();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (e) {
                showToast('Upload failed.', 'error');
            }
        }

        async function loadHeroSlides() {
            try {
                const res = await fetch(ADMIN_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'hero-slides-list' })
                });
                const data = await res.json();
                if (data.success) {
                    renderHeroSlides(data.slides);
                } else {
                    document.getElementById('heroSlidesList').innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);grid-column:1/-1;">Failed to load slides.</div>';
                }
            } catch (e) {
                document.getElementById('heroSlidesList').innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);grid-column:1/-1;">Error loading slides.</div>';
            }
        }

        function renderHeroSlides(slides) {
            const el = document.getElementById('heroSlidesList');
            if (slides.length === 0) {
                el.innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);grid-column:1/-1;">No hero slides yet. Add one above!</div>';
                return;
            }
            el.innerHTML = slides.map(s => `
                <div class="admin-card">
                    <div class="admin-card-img">
                        <img src="${s.image_url}" alt="${s.title || 'Slide'}">
                    </div>
                    <div class="admin-card-body">
                        <div style="font-weight:600;color:var(--gray-800);margin-bottom:4px;">${s.title || '(No title)'}</div>
                        <div style="font-size:0.8rem;color:var(--gray-500);margin-bottom:6px;">${s.subtitle || ''}</div>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span class="admin-badge ${s.is_active ? 'active' : 'inactive'}">${s.is_active ? 'Active' : 'Inactive'}</span>
                            <span style="font-size:0.75rem;color:var(--gray-400);">Order: ${s.sort_order}</span>
                        </div>
                        <div class="admin-card-actions">
                            <button class="btn-xs toggle" onclick="toggleSlide('${s.id}', ${!s.is_active})">${s.is_active ? 'Disable' : 'Enable'}</button>
                            <button class="btn-xs danger" onclick="deleteSlide('${s.id}')">Delete</button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        async function toggleSlide(id, active) {
            try {
                const res = await fetch(ADMIN_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'hero-slide-update', slide_id: id, is_active: active })
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) loadHeroSlides();
            } catch (e) { showToast('Failed.', 'error'); }
        }

        async function deleteSlide(id) {
            if (!confirm('Delete this hero slide?')) return;
            try {
                const res = await fetch(ADMIN_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'hero-slide-delete', slide_id: id })
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) loadHeroSlides();
            } catch (e) { showToast('Failed.', 'error'); }
        }

        // ===== PROMOTIONS =====
        function showPromoForm(promo = null) {
            const form = document.getElementById('promoAddForm');
            form.style.display = 'block';

            if (promo) {
                document.getElementById('promoFormTitle').textContent = 'Edit Promotion';
                document.getElementById('promoSaveBtn').textContent = 'Update';
                document.getElementById('promoEditId').value = promo.id;
                document.getElementById('promoCode').value = promo.code || '';
                document.getElementById('promoDescription').value = promo.description || '';
                document.getElementById('promoDiscountType').value = promo.discount_type || 'percentage';
                document.getElementById('promoDiscountValue').value = promo.discount_value || '';
                document.getElementById('promoStartDate').value = promo.start_date ? promo.start_date.substring(0, 10) : '';
                document.getElementById('promoEndDate').value = promo.end_date ? promo.end_date.substring(0, 10) : '';
                document.getElementById('promoUsageLimit').value = promo.usage_limit || '';
            } else {
                document.getElementById('promoFormTitle').textContent = 'Add Promotion';
                document.getElementById('promoSaveBtn').textContent = 'Add';
                document.getElementById('promoEditId').value = '';
                document.getElementById('promoCode').value = '';
                document.getElementById('promoDescription').value = '';
                document.getElementById('promoDiscountType').value = 'percentage';
                document.getElementById('promoDiscountValue').value = '';
                document.getElementById('promoStartDate').value = '';
                document.getElementById('promoEndDate').value = '';
                document.getElementById('promoUsageLimit').value = '';
            }
        }

        function hidePromoForm() {
            document.getElementById('promoAddForm').style.display = 'none';
        }

        async function savePromotion() {
            const editId = document.getElementById('promoEditId').value;
            const code = document.getElementById('promoCode').value.trim();
            const description = document.getElementById('promoDescription').value.trim();
            const discountValue = document.getElementById('promoDiscountValue').value;

            if (!code || !description || !discountValue) {
                showToast('Code, description and discount value are required.', 'error');
                return;
            }

            const payload = {
                action: editId ? 'promotion-update' : 'promotion-add',
                code, description,
                discount_type: document.getElementById('promoDiscountType').value,
                discount_value: parseFloat(discountValue),
                start_date: document.getElementById('promoStartDate').value || null,
                end_date: document.getElementById('promoEndDate').value || null,
                usage_limit: document.getElementById('promoUsageLimit').value ? parseInt(document.getElementById('promoUsageLimit').value) : null
            };
            if (editId) payload.promotion_id = editId;

            try {
                const res = await fetch(ADMIN_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) { hidePromoForm(); loadPromotions(); }
            } catch (e) { showToast('Failed.', 'error'); }
        }

        async function loadPromotions() {
            try {
                const res = await fetch(ADMIN_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'promotions-list' })
                });
                const data = await res.json();
                if (data.success) renderPromotions(data.promotions);
                else document.getElementById('promoList').innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">Failed to load.</div>';
            } catch (e) {
                document.getElementById('promoList').innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">Error loading.</div>';
            }
        }

        function renderPromotions(promos) {
            const el = document.getElementById('promoList');
            if (promos.length === 0) {
                el.innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">No promotions yet.</div>';
                return;
            }
            el.innerHTML = `<div style="overflow-x:auto;"><table class="admin-table">
                <thead><tr>
                    <th>Code</th><th>Description</th><th>Discount</th><th>Period</th><th>Usage</th><th>Status</th><th>Actions</th>
                </tr></thead>
                <tbody>${promos.map(p => {
                    const discount = p.discount_type === 'percentage' ? p.discount_value + '%' : '$' + p.discount_value;
                    const start = p.start_date ? p.start_date.substring(0, 10) : '—';
                    const end = p.end_date ? p.end_date.substring(0, 10) : '—';
                    const usage = (p.usage_count || 0) + (p.usage_limit ? '/' + p.usage_limit : '');
                    return `<tr>
                        <td><strong>${p.code}</strong></td>
                        <td>${p.description}</td>
                        <td>${discount}</td>
                        <td>${start} → ${end}</td>
                        <td>${usage}</td>
                        <td><span class="admin-badge ${p.is_active ? 'active' : 'inactive'}">${p.is_active ? 'Active' : 'Inactive'}</span></td>
                        <td>
                            <button class="btn-xs edit" onclick='showPromoForm(${JSON.stringify(p).replace(/'/g, "&#39;")})'>Edit</button>
                            <button class="btn-xs danger" onclick="deletePromotion('${p.id}')">Delete</button>
                        </td>
                    </tr>`;
                }).join('')}</tbody></table></div>`;
        }

        async function deletePromotion(id) {
            if (!confirm('Delete this promotion?')) return;
            try {
                const res = await fetch(ADMIN_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'promotion-delete', promotion_id: id })
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) loadPromotions();
            } catch (e) { showToast('Failed.', 'error'); }
        }

        // ===== VEHICLES =====
        let allAdminVehicles = [];

        async function loadAdminVehicles() {
            try {
                const res = await fetch(ADMIN_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'admin-list-vehicles' })
                });
                const data = await res.json();
                if (data.success) {
                    allAdminVehicles = data.vehicles;
                    populateVehicleFilters(data.vehicles);
                    filterAdminVehicles();
                }
                else document.getElementById('adminVehiclesList').innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">Failed to load.</div>';
            } catch (e) {
                document.getElementById('adminVehiclesList').innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">Error loading.</div>';
            }
        }

        function populateVehicleFilters(vehicles) {
            // Owner filter
            const owners = [...new Set(vehicles.map(v => v.owner_name).filter(Boolean))].sort();
            const ownerSelect = document.getElementById('vehicleOwnerFilter');
            const currentOwner = ownerSelect.value;
            ownerSelect.innerHTML = '<option value="">All Owners</option>' + owners.map(o => `<option value="${o}">${o}</option>`).join('');
            ownerSelect.value = currentOwner;

            // Category filter
            const cats = [...new Set(vehicles.map(v => v.category).filter(Boolean))].sort();
            const catSelect = document.getElementById('vehicleCategoryFilter');
            const currentCat = catSelect.value;
            catSelect.innerHTML = '<option value="">All Types</option>' + cats.map(c => `<option value="${c}">${c}</option>`).join('');
            catSelect.value = currentCat;
        }

        function filterAdminVehicles() {
            const ownerFilter = document.getElementById('vehicleOwnerFilter').value;
            const catFilter = document.getElementById('vehicleCategoryFilter').value;
            const statusFilter = document.getElementById('vehicleStatusFilter').value;

            let filtered = allAdminVehicles;
            if (ownerFilter) filtered = filtered.filter(v => v.owner_name === ownerFilter);
            if (catFilter) filtered = filtered.filter(v => v.category === catFilter);
            if (statusFilter) filtered = filtered.filter(v => v.status === statusFilter);

            renderAdminVehicles(filtered);
        }

        function renderAdminVehicles(vehicles) {
            const el = document.getElementById('adminVehiclesList');
            if (vehicles.length === 0) {
                el.innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">No vehicles found.</div>';
                return;
            }
            el.innerHTML = `<div style="font-size:0.8rem;color:var(--gray-400);margin-bottom:8px;">Showing ${vehicles.length} vehicle(s)</div>
            <div style="overflow-x:auto;"><table class="admin-table">
                <thead><tr>
                    <th>Vehicle</th><th>Owner</th><th>License</th><th>Price/Day</th><th>Bookings</th><th>Status</th><th>Actions</th>
                </tr></thead>
                <tbody>${vehicles.map(v => `<tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:48px;height:36px;border-radius:6px;overflow:hidden;background:var(--gray-100);flex-shrink:0;">
                                ${v.thumbnail ? `<img src="${v.thumbnail}" style="width:100%;height:100%;object-fit:cover;">` : ''}
                            </div>
                            <div>
                                <div style="font-weight:600;">${v.brand} ${v.model} ${v.year}</div>
                                <div style="font-size:0.75rem;color:var(--gray-400);">${v.category || ''}</div>
                            </div>
                        </div>
                    </td>
                    <td>${v.owner_name || 'Unknown'}<br><span style="font-size:0.75rem;color:var(--gray-400);">${v.owner_email || ''}</span></td>
                    <td>${v.license_plate || '—'}</td>
                    <td>$${v.price_per_day}</td>
                    <td>${v.total_bookings || 0} <span style="font-size:0.75rem;color:var(--gray-400);">(${v.active_bookings || 0} active)</span></td>
                    <td><span class="admin-badge ${v.status === 'available' ? 'active' : 'inactive'}">${v.status || 'N/A'}</span></td>
                    <td><button class="btn-xs danger" onclick="adminDeleteVehicle('${v.id}')">Delete</button></td>
                </tr>`).join('')}</tbody></table></div>`;
        }

        async function adminDeleteVehicle(id) {
            if (!confirm('Are you sure you want to delete this vehicle? This action cannot be undone.')) return;
            try {
                const res = await fetch(ADMIN_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'admin-delete-vehicle', vehicle_id: id })
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) loadAdminVehicles();
            } catch (e) { showToast('Failed.', 'error'); }
        }

        // ===== BOOKINGS =====
        let allAdminBookings = [];

        async function loadAdminBookings() {
            try {
                const res = await fetch(ADMIN_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'admin-list-bookings' })
                });
                const data = await res.json();
                if (data.success) {
                    allAdminBookings = data.bookings;
                    filterAdminBookings();
                }
                else document.getElementById('adminBookingsList').innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">Failed to load.</div>';
            } catch (e) {
                document.getElementById('adminBookingsList').innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">Error loading.</div>';
            }
        }

        function filterAdminBookings() {
            const search = (document.getElementById('bookingSearchInput').value || '').toLowerCase().trim();
            const statusFilter = document.getElementById('bookingStatusFilter').value;

            let filtered = allAdminBookings;
            if (search) {
                filtered = filtered.filter(b =>
                    (b.renter_name || '').toLowerCase().includes(search) ||
                    (b.renter_email || '').toLowerCase().includes(search) ||
                    (b.owner_name || '').toLowerCase().includes(search)
                );
            }
            if (statusFilter) filtered = filtered.filter(b => b.status === statusFilter);

            renderAdminBookings(filtered);
        }

        function renderAdminBookings(bookings) {
            const el = document.getElementById('adminBookingsList');
            if (bookings.length === 0) {
                el.innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">No bookings found.</div>';
                return;
            }

            const statusColors = {
                'pending': '#f59e0b', 'confirmed': '#22c55e', 'in_progress': '#3b82f6',
                'completed': '#6b7280', 'cancelled': '#ef4444'
            };

            el.innerHTML = `<div style="font-size:0.8rem;color:var(--gray-400);margin-bottom:8px;">Showing ${bookings.length} booking(s)</div>
            <div style="overflow-x:auto;"><table class="admin-table">
                <thead><tr>
                    <th>Vehicle</th><th>Renter</th><th>Owner</th><th>Pickup Date</th><th>Return Date</th><th>Days</th><th>Total</th><th>Status</th><th>Actions</th>
                </tr></thead>
                <tbody>${bookings.map(b => {
                    const vehicle = b.brand ? `${b.brand} ${b.model} ${b.year}` : 'Unknown';
                    const pickupDate = (b.pickup_date || b.start_date) ? new Date(b.pickup_date || b.start_date).toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'}) : '—';
                    const returnDate = (b.return_date || b.end_date) ? new Date(b.return_date || b.end_date).toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'}) : '—';
                    const rawStart = b.pickup_date || b.start_date;
                    const rawEnd = b.return_date || b.end_date;
                    const days = (rawStart && rawEnd) ? Math.max(1, Math.ceil((new Date(rawEnd) - new Date(rawStart)) / (1000*60*60*24))) : (b.total_days || '—');
                    const color = statusColors[b.status] || '#6b7280';
                    const totalVal = b.total_amount || b.total_price;
                    const total = totalVal ? '$' + parseFloat(totalVal).toFixed(2) : '—';
                    return `<tr>
                        <td><strong>${vehicle}</strong><br><span style="font-size:0.75rem;color:var(--gray-400);">${b.license_plate || ''}</span></td>
                        <td>${b.renter_name || 'Unknown'}<br><span style="font-size:0.75rem;color:var(--gray-400);">${b.renter_email || ''}</span></td>
                        <td>${b.owner_name || 'Unknown'}</td>
                        <td style="white-space:nowrap;">${pickupDate}</td>
                        <td style="white-space:nowrap;">${returnDate}</td>
                        <td style="text-align:center;">${days}</td>
                        <td style="font-weight:700;color:var(--primary);">${total}</td>
                        <td><span style="color:${color};font-weight:600;font-size:0.8rem;text-transform:capitalize;">${(b.status || 'N/A').replace('_',' ')}</span></td>
                        <td><button class="btn-xs danger" onclick="adminDeleteBooking('${b.id}')">Delete</button></td>
                    </tr>`;
                }).join('')}</tbody></table></div>`;
        }

        async function adminDeleteBooking(id) {
            if (!confirm('Are you sure you want to delete this booking? This action cannot be undone.')) return;
            try {
                const res = await fetch(ADMIN_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'admin-delete-booking', booking_id: id })
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) loadAdminBookings();
            } catch (e) { showToast('Failed.', 'error'); }
        }

        // ===== USERS =====
        let allAdminUsers = [];

        async function loadAdminUsers() {
            try {
                const res = await fetch(ADMIN_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'admin-list-users' })
                });
                const data = await res.json();
                if (data.success) {
                    allAdminUsers = data.users;
                    filterUsers();
                } else {
                    document.getElementById('adminUsersList').innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">Failed to load users.</div>';
                }
            } catch (e) {
                document.getElementById('adminUsersList').innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">Error loading users.</div>';
            }
        }

        function filterUsers() {
            const search = (document.getElementById('userSearchInput').value || '').toLowerCase().trim();
            const roleFilter = document.getElementById('userRoleFilter').value;

            let filtered = allAdminUsers;
            if (search) {
                filtered = filtered.filter(u =>
                    (u.full_name || '').toLowerCase().includes(search) ||
                    (u.email || '').toLowerCase().includes(search) ||
                    (u.phone || '').toLowerCase().includes(search)
                );
            }
            if (roleFilter) filtered = filtered.filter(u => u.role === roleFilter);

            renderAdminUsers(filtered);
        }

        function renderAdminUsers(users) {
            const el = document.getElementById('adminUsersList');
            if (users.length === 0) {
                el.innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">No users found.</div>';
                return;
            }

            const roleColors = { 'admin': '#ef4444', 'owner': '#3b82f6', 'renter': '#22c55e' };

            el.innerHTML = `<div style="font-size:0.8rem;color:var(--gray-400);margin-bottom:8px;">Showing ${users.length} user(s)</div>
            <div style="overflow-x:auto;"><table class="admin-table">
                <thead><tr>
                    <th>User</th><th>Email</th><th>Phone</th><th>Role</th><th>Auth</th><th>Status</th><th>Joined</th><th>Actions</th>
                </tr></thead>
                <tbody>${users.map(u => {
                    const roleColor = roleColors[u.role] || '#6b7280';
                    const joined = u.created_at ? new Date(u.created_at).toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'}) : '—';
                    const lastLogin = u.last_login_at ? new Date(u.last_login_at).toLocaleDateString('en-US', {month:'short',day:'numeric'}) : 'Never';
                    return `<tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;flex-shrink:0;">
                                    ${(u.full_name || u.email || '?').charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <div style="font-weight:600;">${u.full_name || '(No name)'}</div>
                                    <div style="font-size:0.75rem;color:var(--gray-400);">Last login: ${lastLogin}</div>
                                </div>
                            </div>
                        </td>
                        <td>${u.email || '—'}</td>
                        <td>${u.phone || '—'}</td>
                        <td><span style="color:${roleColor};font-weight:600;font-size:0.8rem;text-transform:capitalize;">${u.role}</span></td>
                        <td style="text-transform:capitalize;">${u.auth_provider || '—'}</td>
                        <td><span class="admin-badge ${u.is_active ? 'active' : 'inactive'}">${u.is_active ? 'Active' : 'Inactive'}</span></td>
                        <td style="white-space:nowrap;">${joined}</td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <button class="btn-xs toggle" onclick="adminToggleUser('${u.id}', ${!u.is_active})">${u.is_active ? 'Disable' : 'Enable'}</button>
                                <button class="btn-xs edit" onclick="adminChangeRole('${u.id}', '${u.role}', '${(u.full_name || '').replace(/'/g, "\\'")}')"">Role</button>
                                <button class="btn-xs danger" onclick="adminDeleteUser('${u.id}', '${(u.full_name || '').replace(/'/g, "\\'")}')"">Del</button>
                            </div>
                        </td>
                    </tr>`;
                }).join('')}</tbody></table></div>`;
        }

        async function adminToggleUser(id, active) {
            try {
                const res = await fetch(ADMIN_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'admin-update-user', user_id: id, is_active: active })
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) loadAdminUsers();
            } catch (e) { showToast('Failed.', 'error'); }
        }

        function adminChangeRole(id, currentRole, name) {
            const newRole = prompt(`Change role for "${name}":\nCurrent: ${currentRole}\n\nEnter new role (renter, owner, admin):`, currentRole);
            if (!newRole || newRole === currentRole) return;
            if (!['renter', 'owner', 'admin'].includes(newRole)) {
                showToast('Invalid role. Use: renter, owner, or admin.', 'error');
                return;
            }

            fetch(ADMIN_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'admin-update-user', user_id: id, role: newRole })
            })
            .then(r => r.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) loadAdminUsers();
            })
            .catch(() => showToast('Failed.', 'error'));
        }

        async function adminDeleteUser(id, name) {
            if (!confirm(`Are you sure you want to delete user "${name}"? This action cannot be undone.`)) return;
            try {
                const res = await fetch(ADMIN_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'admin-delete-user', user_id: id })
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) loadAdminUsers();
            } catch (e) { showToast('Failed.', 'error'); }
        }

        // ===== INIT =====
        document.addEventListener('DOMContentLoaded', () => loadHeroSlides());
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
