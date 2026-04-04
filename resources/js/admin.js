// ===== ADMIN DASHBOARD MODULE =====
// Manages all admin panel functionality: hero slides, promotions, users, vehicles, bookings

const ADMIN_API = '/api/admin.php';

// ===== STATE =====
let allAdminVehicles = [];
let allAdminBookings = [];
let allAdminUsers = [];
let bookingHistoryState = {
    page: 1,
    limit: 50,
    total: 0,
    totalPages: 1,
    regions: [],
};
let bookingHistoryDebounceTimer = null;
let bookingHistoryListenersAttached = false;

// ===== ADMIN NOTIFICATION (self-contained) =====
function adminNotify(message, type = 'success') {
    const container = document.getElementById('adminAlertContainer');
    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    const colors = {
        success: 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0;',
        error: 'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;',
        warning: 'background:#fefce8;color:#854d0e;border:1px solid #fef08a;',
        info: 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;'
    };

    const alert = document.createElement('div');
    alert.style.cssText = (colors[type] || colors.info) + 'display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:12px;font-size:0.875rem;font-weight:600;box-shadow:0 10px 25px rgba(0,0,0,0.15);animation:adminAlertIn 0.35s ease;min-width:300px;';
    alert.innerHTML = `<span style="font-size:1.2rem;">${icons[type] || 'ℹ️'}</span><span style="flex:1;">${message}</span><button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-size:1.1rem;opacity:0.6;">✕</button>`;

    container.appendChild(alert);

    // Auto-remove after 4 seconds
    setTimeout(() => {
        if (alert.parentElement) {
            alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(20px)';
            setTimeout(() => alert.remove(), 300);
        }
    }, 4000);

    // Also call showToast if available (as backup)
    if (typeof showToast === 'function') {
        try { showToast(message, type); } catch(e) {}
    }
}

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
    if (tab === 'history') {
        initBookingHistoryFilters();
        loadAdminBookingHistory(1);
    }
}

// ===== HERO SLIDES =====
function showHeroUpload() {
    document.getElementById('heroUploadForm').style.display = 'block';
}

async function uploadHeroSlide() {
    const fileInput = document.getElementById('heroImageFile');
    if (!fileInput.files[0]) {
        adminNotify('Please select an image.', 'error');
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
            adminNotify('Slide uploaded!', 'success');
            document.getElementById('heroUploadForm').style.display = 'none';
            fileInput.value = '';
            document.getElementById('heroTitle').value = '';
            document.getElementById('heroSubtitle').value = '';
            document.getElementById('heroSortOrder').value = '0';
            loadHeroSlides();
        } else {
            adminNotify(data.message, 'error');
        }
    } catch (e) {
        adminNotify('Upload failed.', 'error');
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
        adminNotify(data.message, data.success ? 'success' : 'error');
        if (data.success) loadHeroSlides();
    } catch (e) { adminNotify('Failed.', 'error'); }
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
        adminNotify(data.message, data.success ? 'success' : 'error');
        if (data.success) loadHeroSlides();
    } catch (e) { adminNotify('Failed.', 'error'); }
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
        document.getElementById('promoStartDate').value = promo.starts_at ? promo.starts_at.substring(0, 10) : '';
        document.getElementById('promoEndDate').value = promo.expires_at ? promo.expires_at.substring(0, 10) : '';
        document.getElementById('promoUsageLimit').value = promo.max_uses || '';
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
        adminNotify('Code, description and discount value are required.', 'error');
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
        adminNotify(data.message, data.success ? 'success' : 'error');
        if (data.success) { hidePromoForm(); loadPromotions(); }
    } catch (e) { adminNotify('Failed.', 'error'); }
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
            const start = p.starts_at ? p.starts_at.substring(0, 10) : '—';
            const end = p.expires_at ? p.expires_at.substring(0, 10) : '—';
            const usage = (p.total_used || 0) + (p.max_uses ? '/' + p.max_uses : '');
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
        adminNotify(data.message, data.success ? 'success' : 'error');
        if (data.success) loadPromotions();
    } catch (e) { adminNotify('Failed.', 'error'); }
}

// ===== VEHICLES =====
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
    // Added-by-staff filter
    const owners = [...new Set(vehicles.map(v => v.added_by_staff_name).filter(Boolean))].sort();
    const ownerSelect = document.getElementById('vehicleOwnerFilter');
    const currentOwner = ownerSelect.value;
    ownerSelect.innerHTML = '<option value="">All Added By Staff</option>' + owners.map(o => `<option value="${o}">${o}</option>`).join('');
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
    if (ownerFilter) filtered = filtered.filter(v => v.added_by_staff_name === ownerFilter);
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
            <th>Vehicle</th><th>Added By Staff</th><th>Driver</th><th>License</th><th>Bookings</th><th>Status</th><th>Actions</th>
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
            <td>${v.added_by_staff_name || 'N/A'}<br><span style="font-size:0.75rem;color:var(--gray-400);">${v.added_by_staff_email || ''}</span></td>
            <td>${v.assigned_driver_name || 'Unassigned'}<br><span style="font-size:0.75rem;color:var(--gray-400);">${v.assigned_driver_email || ''}</span></td>
            <td>${v.license_plate || '—'}</td>
            <td>${v.bookings_total || 0}</td>
            <td><span class="admin-badge ${v.status === 'available' ? 'active' : 'inactive'}">${v.status || 'N/A'}</span></td>
            <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <button class="btn-xs toggle" ${v.can_set_maintenance ? '' : 'disabled'} onclick="adminSetVehicleMaintenance('${v.id}')">Maintenance</button>
                    <button class="btn-xs danger" onclick="adminDeleteVehicle('${v.id}')">Delete</button>
                </div>
            </td>
        </tr>`).join('')}</tbody></table></div>`;
}

async function adminSetVehicleMaintenance(id) {
    if (!confirm('Set this vehicle to maintenance? This is only allowed when vehicle is available and unassigned.')) return;
    try {
        const res = await fetch(ADMIN_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'admin-set-vehicle-maintenance', vehicle_id: id })
        });
        const data = await res.json();
        adminNotify(data.message, data.success ? 'success' : 'error');
        if (data.success) loadAdminVehicles();
    } catch (e) { adminNotify('Failed.', 'error'); }
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
        adminNotify(data.message, data.success ? 'success' : 'error');
        if (data.success) loadAdminVehicles();
    } catch (e) { adminNotify('Failed.', 'error'); }
}

// ===== BOOKINGS =====
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
            (b.customer_name || b.user_name || '').toLowerCase().includes(search) ||
            (b.customer_email || b.email || '').toLowerCase().includes(search) ||
            (b.driver_name || '').toLowerCase().includes(search)
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
            <th>Vehicle</th><th>Customer</th><th>Driver Name</th><th>Pickup Date</th><th>Time Pickup</th><th>Total</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>${bookings.map(b => {
            const vehicle = b.brand ? `${b.brand} ${b.model} ${b.year}` : 'Unknown';
            const pickupDate = (b.pickup_date || b.start_date) ? new Date(b.pickup_date || b.start_date).toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'}) : '—';
            const pickupTime = b.pickup_time || '—';
            const color = statusColors[b.status] || '#6b7280';
            const totalVal = b.total_amount || b.total_price;
            const total = totalVal ? '$' + parseFloat(totalVal).toFixed(2) : '—';
            return `<tr>
                <td><strong>${vehicle}</strong><br><span style="font-size:0.75rem;color:var(--gray-400);">${b.license_plate || ''}</span></td>
                <td>${b.customer_name || b.user_name || 'Unknown'}<br><span style="font-size:0.75rem;color:var(--gray-400);">${b.customer_email || b.email || ''}</span></td>
                <td>${b.driver_name || 'Unassigned'}</td>
                <td style="white-space:nowrap;">${pickupDate}</td>
                <td style="white-space:nowrap;">${pickupTime}</td>
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
        adminNotify(data.message, data.success ? 'success' : 'error');
        if (data.success) loadAdminBookings();
    } catch (e) { adminNotify('Failed.', 'error'); }
}

// ===== BOOKING HISTORY =====
function initBookingHistoryFilters() {
    if (bookingHistoryListenersAttached) return;

    const ids = ['historySearchInput', 'historyStatusFilter', 'historyRegionFilter', 'historyDateFrom', 'historyDateTo'];
    ids.forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        const eventName = id === 'historySearchInput' ? 'input' : 'change';
        el.addEventListener(eventName, () => scheduleHistoryReload(1));
    });

    bookingHistoryListenersAttached = true;
}

function scheduleHistoryReload(page = 1) {
    if (bookingHistoryDebounceTimer) {
        clearTimeout(bookingHistoryDebounceTimer);
    }
    bookingHistoryDebounceTimer = setTimeout(() => {
        loadAdminBookingHistory(page);
    }, 220);
}

function refreshBookingHistory() {
    loadAdminBookingHistory(1);
}

function getBookingHistoryFilters() {
    return {
        search: (document.getElementById('historySearchInput')?.value || '').trim(),
        status: document.getElementById('historyStatusFilter')?.value || '',
        region_id: document.getElementById('historyRegionFilter')?.value || '',
        date_from: document.getElementById('historyDateFrom')?.value || '',
        date_to: document.getElementById('historyDateTo')?.value || '',
    };
}

async function loadAdminBookingHistory(page = 1) {
    bookingHistoryState.page = page;
    const filters = getBookingHistoryFilters();

    const listPayload = {
        action: 'admin-booking-history-list',
        page,
        limit: bookingHistoryState.limit,
        ...filters,
    };

    const summaryPayload = {
        action: 'admin-booking-history-summary',
        ...filters,
    };

    try {
        const [listRes, summaryRes] = await Promise.all([
            fetch(ADMIN_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(listPayload),
            }),
            fetch(ADMIN_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(summaryPayload),
            }),
        ]);

        const listData = await listRes.json();
        const summaryData = await summaryRes.json();

        if (!listData.success) {
            document.getElementById('adminHistoryList').innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">Failed to load booking history.</div>';
            return;
        }

        const rows = listData.history || [];
        const pagination = listData.pagination || {};
        bookingHistoryState.total = pagination.total || 0;
        bookingHistoryState.totalPages = pagination.total_pages || 1;
        bookingHistoryState.page = pagination.page || page;
        bookingHistoryState.regions = listData.regions || [];

        renderHistoryRegionFilter(bookingHistoryState.regions);
        renderBookingHistoryTable(rows);
        renderBookingHistoryPagination();

        if (summaryData.success) {
            renderBookingHistorySummary(summaryData.summary || {});
            renderHistoryAggregationTable('historyDailyAggregation', summaryData.daily || []);
            renderHistoryAggregationTable('historyQuarterlyAggregation', summaryData.quarterly || []);
        }
    } catch (e) {
        document.getElementById('adminHistoryList').innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">Error loading booking history.</div>';
    }
}

function renderHistoryRegionFilter(regions) {
    const select = document.getElementById('historyRegionFilter');
    if (!select) return;

    const current = select.value;
    const options = ['<option value="">All Regions</option>'];
    regions.forEach((region) => {
        options.push(`<option value="${region.id}">${region.name}</option>`);
    });
    select.innerHTML = options.join('');

    if (current) {
        select.value = current;
    }
}

function formatHistoryUtc(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    const text = new Intl.DateTimeFormat('en-GB', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'UTC',
    }).format(date);
    return text + ' UTC';
}

function renderBookingHistoryTable(rows) {
    const el = document.getElementById('adminHistoryList');
    if (!el) return;

    if (!rows || rows.length === 0) {
        el.innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);">No archived booking records found.</div>';
        return;
    }

    const statusColors = { completed: '#16a34a', cancelled: '#dc2626' };
    el.innerHTML = `<div style="font-size:0.8rem;color:var(--gray-400);margin-bottom:8px;">Showing ${rows.length} record(s) on this page</div>
    <div style="overflow-x:auto;"><table class="admin-table">
        <thead><tr>
            <th>Booking ID</th><th>Customer</th><th>Region</th><th>Pickup Date</th><th>Archived At (UTC)</th><th>Total</th><th>Status</th><th>Action</th>
        </tr></thead>
        <tbody>${rows.map((row) => {
            const amount = row.total_amount ? '$' + Number(row.total_amount).toFixed(2) : '$0.00';
            const archivedAt = formatHistoryUtc(row.archived_at);
            const pickupDate = row.pickup_date ? new Date(row.pickup_date).toLocaleDateString('en-GB') : '—';
            const color = statusColors[row.status] || '#6b7280';
            return `<tr>
                <td><span style="font-family:monospace;font-size:0.75rem;">${row.booking_id || '—'}</span></td>
                <td>${row.customer_name || 'Unknown'}<br><span style="font-size:0.75rem;color:var(--gray-400);">${row.customer_email || ''}</span></td>
                <td>${row.region_name || 'Unassigned'}</td>
                <td>${pickupDate}</td>
                <td>${archivedAt}</td>
                <td style="font-weight:700;color:var(--primary);">${amount}</td>
                <td><span style="color:${color};font-weight:700;text-transform:capitalize;">${(row.status || '').replace('_', ' ')}</span></td>
                <td><button class="btn-xs danger" onclick="adminDeleteBookingFromHistory('${row.booking_id}')">Delete</button></td>
            </tr>`;
        }).join('')}</tbody>
    </table></div>`;
}

function renderBookingHistoryPagination() {
    const el = document.getElementById('historyPagination');
    if (!el) return;

    const page = bookingHistoryState.page;
    const totalPages = bookingHistoryState.totalPages;
    const total = bookingHistoryState.total;

    el.innerHTML = `
        <span style="font-size:0.82rem;color:var(--gray-500);margin-right:8px;">Total ${total} records</span>
        <button class="btn-xs edit" ${page <= 1 ? 'disabled' : ''} onclick="loadAdminBookingHistory(${page - 1})">Prev</button>
        <span style="font-size:0.82rem;color:var(--gray-600);">Page ${page} / ${totalPages}</span>
        <button class="btn-xs edit" ${page >= totalPages ? 'disabled' : ''} onclick="loadAdminBookingHistory(${page + 1})">Next</button>
    `;
}

function renderBookingHistorySummary(summary) {
    const totalEl = document.getElementById('historyKpiTotal');
    const completedEl = document.getElementById('historyKpiCompleted');
    const cancelledEl = document.getElementById('historyKpiCancelled');
    const revenueEl = document.getElementById('historyKpiRevenue');

    if (totalEl) totalEl.textContent = String(summary.total_bookings || 0);
    if (completedEl) completedEl.textContent = String(summary.completed_bookings || 0);
    if (cancelledEl) cancelledEl.textContent = String(summary.cancelled_bookings || 0);
    if (revenueEl) revenueEl.textContent = '$' + Number(summary.total_revenue || 0).toFixed(2);
}

function renderHistoryAggregationTable(targetId, rows) {
    const el = document.getElementById(targetId);
    if (!el) return;

    if (!rows || rows.length === 0) {
        el.innerHTML = '<div style="color:var(--gray-400);">No data.</div>';
        return;
    }

    const topRows = rows.slice(0, 10);
    el.innerHTML = `<div style="overflow-x:auto;"><table class="admin-table" style="font-size:0.78rem;">
        <thead><tr><th>Period</th><th>Total</th><th>Completed</th><th>Cancelled</th><th>Revenue</th></tr></thead>
        <tbody>${topRows.map((row) => `
            <tr>
                <td>${row.period_label || '—'}</td>
                <td>${row.total_bookings || 0}</td>
                <td>${row.completed_bookings || 0}</td>
                <td>${row.cancelled_bookings || 0}</td>
                <td>$${Number(row.total_revenue || 0).toFixed(2)}</td>
            </tr>
        `).join('')}</tbody>
    </table></div>`;
}

function buildBookingHistoryExportUrl(action) {
    const filters = getBookingHistoryFilters();
    const params = new URLSearchParams({ action });

    Object.keys(filters).forEach((key) => {
        const value = (filters[key] || '').trim();
        if (value) params.set(key, value);
    });

    return ADMIN_API + '?' + params.toString();
}

function exportBookingHistoryCsv() {
    const url = buildBookingHistoryExportUrl('admin-booking-history-export-csv');
    window.open(url, '_blank');
}

function exportBookingHistoryPdf() {
    const url = buildBookingHistoryExportUrl('admin-booking-history-export-pdf');
    window.open(url, '_blank');
}

async function adminDeleteBookingFromHistory(bookingId) {
    if (!confirm('Delete this booking permanently? This action cannot be undone.')) return;

    const reason = prompt('Delete reason (optional):', '') || '';

    try {
        const res = await fetch(ADMIN_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'admin-booking-history-delete',
                booking_id: bookingId,
                delete_reason: reason,
            }),
        });

        const data = await res.json();
        adminNotify(data.message || 'Request processed.', data.success ? 'success' : 'error');
        if (data.success) {
            loadAdminBookingHistory(bookingHistoryState.page);
        }
    } catch (e) {
        adminNotify('Failed.', 'error');
    }
}

// ===== USERS =====
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

    const roleColors = { 'admin': '#ef4444', 'user': '#3b82f6', 'driver': '#f59e0b', 'controlstaff': '#8b5cf6', 'callcenterstaff': '#0d9488' };

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
                        <button class="btn-xs edit" onclick="adminChangeRole('${u.id}', '${u.role}', '${(u.full_name || '').replace(/'/g, "\\'")}')">Role</button>
                        <button class="btn-xs danger" onclick="adminDeleteUser('${u.id}', '${(u.full_name || '').replace(/'/g, "\\'")}')">Del</button>
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
        adminNotify(data.message, data.success ? 'success' : 'error');
        if (data.success) loadAdminUsers();
    } catch (e) { adminNotify('Failed.', 'error'); }
}

function adminChangeRole(id, currentRole, name) {
    const newRole = prompt(`Change role for "${name}":\nCurrent: ${currentRole}\n\nEnter new role (user, driver, callcenterstaff, controlstaff, admin):`, currentRole);
    if (!newRole || newRole === currentRole) return;
    if (!['user', 'driver', 'callcenterstaff', 'controlstaff', 'admin'].includes(newRole)) {
        adminNotify('Invalid role. Use: user, driver, callcenterstaff, controlstaff, or admin.', 'error');
        return;
    }

    fetch(ADMIN_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'admin-update-user', user_id: id, role: newRole })
    })
    .then(r => r.json())
    .then(data => {
        adminNotify(data.message, data.success ? 'success' : 'error');
        if (data.success) loadAdminUsers();
    })
    .catch(() => adminNotify('Failed.', 'error'));
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
        adminNotify(data.message, data.success ? 'success' : 'error');
        if (data.success) loadAdminUsers();
    } catch (e) { adminNotify('Failed.', 'error'); }
}
