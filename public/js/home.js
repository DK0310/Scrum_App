// ===== HOMEPAGE MODULE =====
// Manages homepage functionality: available vehicles grid, hero slides, reviews

const VEHICLES_API = '/api/vehicles.php';
const ADMIN_API = '/api/admin.php';

// ===== UTILITIES =====
function vehicleName(v) {
    const brand = (v.brand || '').trim();
    const model = (v.model || '').trim();
    return [brand, model].filter(Boolean).join(' ').trim() || 'Vehicle';
}

function money(v) {
    const n = Number(v);
    if (!Number.isFinite(n)) return '';
    return '$' + n.toLocaleString(undefined, { maximumFractionDigits: 0 });
}

function escapeHtml(str) {
    return String(str)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeHtmlAttr(str) {
    return escapeHtml(str).replaceAll('`', '&#096;');
}

// ===== VEHICLE CARD RENDERING =====
function cardVehicle(v) {
    const name = vehicleName(v);
    const price = money(v.price_per_day);
    const plate = (v.license_plate || '-');
    const img = v.vehicle_image;

    const imgHtml = img
        ? `<img src="${escapeHtmlAttr(img)}" alt="${escapeHtmlAttr(name)}" style="width:100%;height:150px;object-fit:cover;display:block;">`
        : `<div class="no-image-placeholder" style="height:150px;">No Image</div>`;

    return `
        <div class="card" style="padding:0;overflow:hidden;border-radius:14px;">
            <div style="background:var(--gray-100);">${imgHtml}</div>
            <div style="padding:14px 14px 12px;">
                <div style="font-weight:800;color:var(--gray-900);font-size:0.98rem;line-height:1.25;">${escapeHtml(name)}</div>
                <div style="margin-top:8px;display:flex;justify-content:space-between;gap:10px;align-items:center;">
                    <div style="color:var(--primary);font-weight:900;">${escapeHtml(price)}<span style="color:var(--gray-400);font-weight:700;">/day</span></div>
                    <div style="font-size:0.82rem;color:var(--gray-600);font-weight:700;">${escapeHtml(plate)}</div>
                </div>
                <div style="margin-top:10px;">
                    <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:var(--success-light);color:var(--success);font-weight:800;font-size:0.75rem;">Available</span>
                </div>
            </div>
        </div>
    `;
}

// ===== VEHICLE LOADING =====
async function loadAvailableVehicles() {
    const grid = document.getElementById('availableVehiclesGrid');
    try {
        const url = new URL(VEHICLES_API, window.location.origin);
        url.searchParams.set('action', 'public-list');

        const res = await fetch(url.toString());
        const data = await res.json();

        if (!data.success) throw new Error(data.message || 'Failed to load vehicles');

        const vehicles = Array.isArray(data.vehicles) ? data.vehicles : [];

        if (vehicles.length === 0) {
            grid.innerHTML = `
                <div style="grid-column:1/-1;text-align:center;padding:26px 18px;color:var(--gray-500);">
                    No available vehicles found.
                </div>
            `;
            return;
        }

        grid.innerHTML = vehicles.map(cardVehicle).join('');
    } catch (e) {
        grid.innerHTML = `
            <div style="grid-column:1/-1;text-align:center;padding:26px 18px;color:var(--danger);">
                Failed to load vehicles.
            </div>
        `;
    }
}

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', () => {
    loadAvailableVehicles();

    // Keep existing homepage loaders (hero, reviews) if present
    if (typeof loadHeroSlides === 'function') loadHeroSlides();
    if (typeof loadHomeReviews === 'function') loadHomeReviews();
});
