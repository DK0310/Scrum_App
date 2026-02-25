<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== MY VEHICLES DASHBOARD ===== -->
    <section class="section" style="padding-top:100px;">
        <div class="section-container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">🚗 My Vehicles</h2>
                    <p class="section-subtitle">Manage your fleet — add, edit, or remove vehicles</p>
                </div>
                <button class="btn btn-primary" onclick="openAddVehicleModal()">➕ Add New Vehicle</button>
            </div>

            <!-- Stats Overview -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:32px;" id="ownerStats">
                <div style="background:white;border-radius:var(--radius-md);padding:20px;border:1px solid var(--gray-200);text-align:center;">
                    <div style="font-size:2rem;font-weight:800;color:var(--primary);" id="statTotal">0</div>
                    <div style="font-size:0.875rem;color:var(--gray-500);">Total Vehicles</div>
                </div>
                <div style="background:white;border-radius:var(--radius-md);padding:20px;border:1px solid var(--gray-200);text-align:center;">
                    <div style="font-size:2rem;font-weight:800;color:var(--success);" id="statAvailable">0</div>
                    <div style="font-size:0.875rem;color:var(--gray-500);">Available</div>
                </div>
                <div style="background:white;border-radius:var(--radius-md);padding:20px;border:1px solid var(--gray-200);text-align:center;">
                    <div style="font-size:2rem;font-weight:800;color:var(--warning);" id="statRented">0</div>
                    <div style="font-size:0.875rem;color:var(--gray-500);">Currently Rented</div>
                </div>
                <div style="background:white;border-radius:var(--radius-md);padding:20px;border:1px solid var(--gray-200);text-align:center;">
                    <div style="font-size:2rem;font-weight:800;color:var(--gray-600);" id="statBookings">0</div>
                    <div style="font-size:0.875rem;color:var(--gray-500);">Total Bookings</div>
                </div>
            </div>

            <!-- Vehicle List -->
            <div id="vehicleList">
                <div style="text-align:center;padding:60px 20px;">
                    <div class="loading-spinner" style="margin:0 auto 16px;width:40px;height:40px;border:3px solid var(--gray-200);border-top-color:var(--primary);border-radius:50%;animation:spin 0.8s linear infinite;"></div>
                    <p style="color:var(--gray-500);">Loading your vehicles...</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== ADD/EDIT VEHICLE MODAL ===== -->
    <div class="modal-overlay" id="vehicleModal">
        <div class="modal" style="max-width:720px;max-height:90vh;overflow-y:auto;">
            <div class="modal-header">
                <h3 class="modal-title" id="vehicleModalTitle">➕ Add New Vehicle</h3>
                <button class="modal-close" onclick="closeModal('vehicleModal')">✕</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editVehicleId" value="">

                <!-- Row 1: Brand, Model, Year -->
                <div style="display:grid;grid-template-columns:1fr 1fr 120px;gap:12px;margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Brand *</label>
                        <input type="text" class="form-input" id="vBrand" placeholder="e.g. Toyota, BMW...">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Model *</label>
                        <input type="text" class="form-input" id="vModel" placeholder="e.g. Camry, 3 Series...">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Year *</label>
                        <input type="number" class="form-input" id="vYear" placeholder="2025" min="1990" max="2030">
                    </div>
                </div>

                <!-- Row 2: License, Category, Color -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">License Plate *</label>
                        <input type="text" class="form-input" id="vLicensePlate" placeholder="e.g. 51A-12345">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Category</label>
                        <select class="form-select" id="vCategory">
                            <option value="sedan">Sedan</option>
                            <option value="suv">SUV</option>
                            <option value="luxury">Luxury</option>
                            <option value="sports">Sports</option>
                            <option value="electric">Electric</option>
                            <option value="van">Van / Minibus</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Color</label>
                        <input type="text" class="form-input" id="vColor" placeholder="e.g. White, Black...">
                    </div>
                </div>

                <!-- Row 3: Transmission, Fuel, Seats -->
                <div style="display:grid;grid-template-columns:1fr 1fr 100px;gap:12px;margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Transmission</label>
                        <select class="form-select" id="vTransmission">
                            <option value="automatic">Automatic</option>
                            <option value="manual">Manual</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Fuel Type</label>
                        <select class="form-select" id="vFuelType">
                            <option value="petrol">Petrol</option>
                            <option value="diesel">Diesel</option>
                            <option value="electric">Electric</option>
                            <option value="hybrid">Hybrid</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Seats</label>
                        <input type="number" class="form-input" id="vSeats" value="5" min="2" max="50">
                    </div>
                </div>

                <!-- Row 4: Engine, Consumption -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Engine Size</label>
                        <div style="position:relative;">
                            <input type="text" class="form-input" id="vEngine" placeholder="e.g. 2, 3.5" oninput="autoFormatEngine(this)" style="padding-right:40px;">
                            <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--gray-400);font-size:0.875rem;pointer-events:none;" id="engineSuffix">L</span>
                        </div>
                        <small style="color:var(--gray-400);font-size:0.75rem;">Enter number only, e.g. "2" → 2L</small>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Consumption</label>
                        <div style="position:relative;">
                            <input type="text" class="form-input" id="vConsumption" placeholder="e.g. 9.72" oninput="autoFormatConsumption(this)" style="padding-right:80px;">
                            <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--gray-400);font-size:0.875rem;pointer-events:none;" id="consumptionSuffix">L/100km</span>
                        </div>
                        <small style="color:var(--gray-400);font-size:0.75rem;">Enter number only, e.g. "9.72" → 9.72L/100km</small>
                    </div>
                </div>

                <!-- Row 5: Pricing -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Price/Day ($) *</label>
                        <input type="number" class="form-input" id="vPriceDay" placeholder="65" min="1" step="0.01">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Price/Week ($)</label>
                        <input type="number" class="form-input" id="vPriceWeek" placeholder="400" min="0" step="0.01">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Price/Month ($)</label>
                        <input type="number" class="form-input" id="vPriceMonth" placeholder="1500" min="0" step="0.01">
                    </div>
                </div>

                <!-- Row 6: Location -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">City</label>
                        <input type="text" class="form-input" id="vCity" placeholder="e.g. Ho Chi Minh City">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Address</label>
                        <input type="text" class="form-input" id="vAddress" placeholder="Full pickup address">
                    </div>
                </div>

                <!-- Features -->
                <div class="form-group">
                    <label class="form-label">Features (select all that apply)</label>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;" id="featureCheckboxes">
                        <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);cursor:pointer;font-size:0.875rem;transition:var(--transition);">
                            <input type="checkbox" value="GPS" class="vFeature"> 📍 GPS
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);cursor:pointer;font-size:0.875rem;">
                            <input type="checkbox" value="A/C" class="vFeature"> ❄️ A/C
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);cursor:pointer;font-size:0.875rem;">
                            <input type="checkbox" value="Bluetooth" class="vFeature"> 🎵 Bluetooth
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);cursor:pointer;font-size:0.875rem;">
                            <input type="checkbox" value="Backup Camera" class="vFeature"> 📷 Backup Camera
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);cursor:pointer;font-size:0.875rem;">
                            <input type="checkbox" value="4WD" class="vFeature"> 🏔️ 4WD
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);cursor:pointer;font-size:0.875rem;">
                            <input type="checkbox" value="Sunroof" class="vFeature"> ☀️ Sunroof
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);cursor:pointer;font-size:0.875rem;">
                            <input type="checkbox" value="Autopilot" class="vFeature"> 🤖 Autopilot
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);cursor:pointer;font-size:0.875rem;">
                            <input type="checkbox" value="Child Seat" class="vFeature"> 👶 Child Seat
                        </label>
                    </div>
                </div>

                <!-- Image Upload -->
                <div class="form-group">
                    <label class="form-label">Vehicle Images</label>
                    <div style="border:2px dashed var(--gray-300);border-radius:var(--radius-md);padding:24px;text-align:center;cursor:pointer;transition:var(--transition);" id="imageDropZone" onclick="document.getElementById('imageInput').click()">
                        <div style="font-size:2rem;margin-bottom:8px;">📸</div>
                        <p style="color:var(--gray-500);font-size:0.875rem;">Click to upload or drag & drop images here</p>
                        <p style="color:var(--gray-400);font-size:0.75rem;">JPEG, PNG, WebP — Max 5MB each</p>
                    </div>
                    <input type="file" id="imageInput" accept="image/*" multiple style="display:none" onchange="handleImageUpload(this)">
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;" id="imagePreviewList"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('vehicleModal')">Cancel</button>
                <button class="btn btn-primary" id="vehicleSubmitBtn" onclick="submitVehicle()">➕ Add Vehicle</button>
            </div>
        </div>
    </div>

    <!-- ===== DELETE CONFIRMATION MODAL ===== -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal" style="max-width:420px;">
            <div class="modal-header">
                <h3 class="modal-title">⚠️ Delete Vehicle</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">✕</button>
            </div>
            <div class="modal-body" style="text-align:center;">
                <div style="font-size:3rem;margin-bottom:12px;">🗑️</div>
                <p style="color:var(--gray-600);margin-bottom:8px;">Are you sure you want to delete this vehicle?</p>
                <p style="font-weight:700;color:var(--gray-800);margin-bottom:8px;" id="deleteVehicleName"></p>
                <p style="color:var(--danger);font-size:0.875rem;">This action cannot be undone.</p>
            </div>
            <div class="modal-footer" style="justify-content:center;">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn" onclick="confirmDelete()">🗑️ Delete</button>
            </div>
        </div>
    </div>

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
        .vehicle-item {
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-200);
            padding: 20px;
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 20px;
            align-items: center;
            margin-bottom: 12px;
            transition: var(--transition);
        }
        .vehicle-item:hover { box-shadow: var(--shadow-md); }
        .vehicle-thumb {
            width: 120px; height: 80px;
            border-radius: var(--radius);
            background: var(--gray-100);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem;
            overflow: hidden;
        }
        .vehicle-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .vehicle-info h4 { font-size: 1rem; font-weight: 700; color: var(--gray-900); margin-bottom: 4px; }
        .vehicle-info p { font-size: 0.813rem; color: var(--gray-500); margin-bottom: 4px; }
        .vehicle-meta { display: flex; gap: 12px; flex-wrap: wrap; }
        .vehicle-meta span { font-size: 0.75rem; padding: 2px 8px; background: var(--gray-100); border-radius: var(--radius); color: var(--gray-600); }
        .vehicle-actions { display: flex; gap: 8px; flex-shrink: 0; }
        .status-badge { display: inline-block; padding: 2px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; }
        .status-available { background: #dcfce7; color: #166534; }
        .status-rented { background: #fef3c7; color: #92400e; }
        .status-maintenance { background: #fef2f2; color: #991b1b; }
        .status-inactive { background: var(--gray-100); color: var(--gray-500); }
        .image-preview {
            position: relative; width: 80px; height: 60px;
            border-radius: var(--radius); overflow: hidden;
            border: 1px solid var(--gray-200);
        }
        .image-preview img { width: 100%; height: 100%; object-fit: cover; }
        .image-preview .remove-img {
            position: absolute; top: 2px; right: 2px;
            width: 18px; height: 18px; border-radius: 50%;
            background: rgba(0,0,0,0.6); color: white;
            border: none; cursor: pointer; font-size: 10px;
            display: flex; align-items: center; justify-content: center;
        }
    </style>

    <!-- ===== MY VEHICLES JAVASCRIPT ===== -->
    <script>
        const VEHICLES_API = '/api/vehicles.php';
        let myVehicles = [];
        let uploadedImages = [];   // [{id: 'uuid', url: '/api/vehicles.php?action=get-image&id=uuid'}, ...]
        let deleteTargetId = null;

        // ===== AUTO-FORMAT ENGINE SIZE =====
        // User types "2" → stores as "2L", types "3.5" → "3.5L"
        function autoFormatEngine(input) {
            // Strip non-numeric characters except dot
            let val = input.value.replace(/[^0-9.]/g, '');
            // Prevent multiple dots
            const parts = val.split('.');
            if (parts.length > 2) val = parts[0] + '.' + parts.slice(1).join('');
            input.value = val;
        }

        function getFormattedEngine() {
            const raw = document.getElementById('vEngine').value.trim().replace(/[^0-9.]/g, '');
            if (!raw) return '';
            return raw + 'L';
        }

        // ===== AUTO-FORMAT CONSUMPTION =====
        // User types "9.72" → stores as "9.72L/100km"
        function autoFormatConsumption(input) {
            // Strip non-numeric characters except dot
            let val = input.value.replace(/[^0-9.]/g, '');
            const parts = val.split('.');
            if (parts.length > 2) val = parts[0] + '.' + parts.slice(1).join('');
            input.value = val;
        }

        function getFormattedConsumption() {
            const raw = document.getElementById('vConsumption').value.trim().replace(/[^0-9.]/g, '');
            if (!raw) return '';
            return raw + 'L/100km';
        }

        // ===== PARSE VALUES FOR EDIT (strip suffix for display in input) =====
        function parseEngineForInput(engineStr) {
            if (!engineStr) return '';
            return engineStr.replace(/L$/i, '').trim();
        }

        function parseConsumptionForInput(consumptionStr) {
            if (!consumptionStr) return '';
            return consumptionStr.replace(/L\/100km$/i, '').trim();
        }

        // Load vehicles on page load
        document.addEventListener('DOMContentLoaded', loadMyVehicles);

        async function loadMyVehicles() {
            try {
                const res = await fetch(VEHICLES_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'my-vehicles' })
                });
                const data = await res.json();

                if (!data.success) {
                    document.getElementById('vehicleList').innerHTML = `
                        <div style="text-align:center;padding:60px 20px;">
                            <div style="font-size:3rem;margin-bottom:12px;">⚠️</div>
                            <p style="color:var(--gray-500);">${data.message}</p>
                        </div>`;
                    return;
                }

                myVehicles = data.vehicles;
                renderVehicles();
                updateStats();
            } catch (err) {
                document.getElementById('vehicleList').innerHTML = `
                    <div style="text-align:center;padding:60px 20px;">
                        <div style="font-size:3rem;margin-bottom:12px;">❌</div>
                        <p style="color:var(--danger);">Failed to load vehicles. Please try again.</p>
                    </div>`;
            }
        }

        function updateStats() {
            document.getElementById('statTotal').textContent = myVehicles.length;
            document.getElementById('statAvailable').textContent = myVehicles.filter(v => v.status === 'available').length;
            document.getElementById('statRented').textContent = myVehicles.filter(v => v.status === 'rented').length;
            const totalBookings = myVehicles.reduce((sum, v) => sum + (parseInt(v.total_bookings) || 0), 0);
            document.getElementById('statBookings').textContent = totalBookings;
        }

        function renderVehicles() {
            const list = document.getElementById('vehicleList');

            if (myVehicles.length === 0) {
                list.innerHTML = `
                    <div style="text-align:center;padding:60px 20px;">
                        <div style="font-size:2rem;margin-bottom:16px;color:var(--gray-300);">�</div>
                        <h3 style="color:var(--gray-700);margin-bottom:8px;">No vehicles yet</h3>
                        <p style="color:var(--gray-500);margin-bottom:16px;">Start listing your cars to earn money from rentals!</p>
                        <button class="btn btn-primary" onclick="openAddVehicleModal()">➕ Add Your First Vehicle</button>
                    </div>`;
                return;
            }

            list.innerHTML = myVehicles.map(v => {
                const thumb = (v.images && v.images.length > 0) 
                    ? `<img src="${v.images[0]}" alt="${v.brand} ${v.model}">`
                    : `<span style="font-size:0.75rem;color:var(--gray-400);">No Photo</span>`;
                const features = Array.isArray(v.features) ? v.features.slice(0, 3).map(f => `<span>${f}</span>`).join('') : '';

                return `
                <div class="vehicle-item">
                    <div class="vehicle-thumb">${thumb}</div>
                    <div class="vehicle-info">
                        <h4>${v.brand} ${v.model} ${v.year}</h4>
                        <p>${v.category} • ${v.transmission} • ${v.fuel_type} • ${v.seats} seats</p>
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span class="status-badge status-${v.status}">${v.status}</span>
                            <strong style="color:var(--primary);font-size:0.875rem;">$${v.price_per_day}/day</strong>
                            <span style="font-size:0.813rem;color:var(--gray-400);">⭐ ${v.avg_rating} (${v.total_reviews || 0} reviews) • ${v.total_bookings || 0} bookings</span>
                        </div>
                        <div class="vehicle-meta" style="margin-top:6px;">${features}</div>
                    </div>
                    <div class="vehicle-actions">
                        <button class="btn btn-secondary btn-sm" onclick="editVehicle('${v.id}')">✏️ Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteVehicle('${v.id}', '${v.brand} ${v.model} ${v.year}')">🗑️</button>
                    </div>
                </div>`;
            }).join('');
        }

        // ===== ADD VEHICLE MODAL =====
        function openAddVehicleModal() {
            document.getElementById('editVehicleId').value = '';
            document.getElementById('vehicleModalTitle').textContent = '➕ Add New Vehicle';
            document.getElementById('vehicleSubmitBtn').textContent = '➕ Add Vehicle';

            // Reset all fields
            ['vBrand','vModel','vYear','vLicensePlate','vColor','vEngine','vConsumption',
             'vPriceDay','vPriceWeek','vPriceMonth','vCity','vAddress'].forEach(id => {
                document.getElementById(id).value = '';
            });
            document.getElementById('vCategory').value = 'sedan';
            document.getElementById('vTransmission').value = 'automatic';
            document.getElementById('vFuelType').value = 'petrol';
            document.getElementById('vSeats').value = '5';
            document.querySelectorAll('.vFeature').forEach(cb => cb.checked = false);
            uploadedImages = [];
            document.getElementById('imagePreviewList').innerHTML = '';

            document.getElementById('vehicleModal').classList.add('open');
        }

        function editVehicle(vehicleId) {
            const v = myVehicles.find(v => v.id === vehicleId);
            if (!v) return;

            document.getElementById('editVehicleId').value = v.id;
            document.getElementById('vehicleModalTitle').textContent = '✏️ Edit Vehicle';
            document.getElementById('vehicleSubmitBtn').textContent = '💾 Save Changes';

            document.getElementById('vBrand').value = v.brand || '';
            document.getElementById('vModel').value = v.model || '';
            document.getElementById('vYear').value = v.year || '';
            document.getElementById('vLicensePlate').value = v.license_plate || '';
            document.getElementById('vCategory').value = v.category || 'sedan';
            document.getElementById('vColor').value = v.color || '';
            document.getElementById('vTransmission').value = v.transmission || 'automatic';
            document.getElementById('vFuelType').value = v.fuel_type || 'petrol';
            document.getElementById('vSeats').value = v.seats || 5;
            document.getElementById('vEngine').value = parseEngineForInput(v.engine_size || '');
            document.getElementById('vConsumption').value = parseConsumptionForInput(v.consumption || '');
            document.getElementById('vPriceDay').value = v.price_per_day || '';
            document.getElementById('vPriceWeek').value = v.price_per_week || '';
            document.getElementById('vPriceMonth').value = v.price_per_month || '';
            document.getElementById('vCity').value = v.location_city || '';
            document.getElementById('vAddress').value = v.location_address || '';

            // Check features
            document.querySelectorAll('.vFeature').forEach(cb => {
                cb.checked = Array.isArray(v.features) && v.features.includes(cb.value);
            });

            // Load existing images (convert to {id, url} objects)
            const imgIds = Array.isArray(v.image_ids) ? v.image_ids : [];
            const imgUrls = Array.isArray(v.images) ? v.images : [];
            uploadedImages = imgIds.map((id, i) => ({ id, url: imgUrls[i] || '/api/vehicles.php?action=get-image&id=' + id }));
            renderImagePreviews();

            document.getElementById('vehicleModal').classList.add('open');
        }

        // ===== IMAGE UPLOAD =====
        async function handleImageUpload(input) {
            const files = input.files;
            if (!files.length) return;

            for (const file of files) {
                if (file.size > 5 * 1024 * 1024) {
                    showToast(`${file.name} is too large (max 5MB).`, 'error');
                    continue;
                }

                const formData = new FormData();
                formData.append('image', file);
                formData.append('action', 'upload-image');

                try {
                    const res = await fetch(VEHICLES_API + '?action=upload-image', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();

                    if (data.success) {
                        uploadedImages.push({ id: data.image_id, url: data.url });
                        renderImagePreviews();
                        showToast('Image uploaded!', 'success');
                    } else {
                        showToast(data.message || 'Upload failed.', 'error');
                    }
                } catch (err) {
                    showToast('Failed to upload image.', 'error');
                }
            }
            input.value = '';
        }

        function renderImagePreviews() {
            const container = document.getElementById('imagePreviewList');
            container.innerHTML = uploadedImages.map((img, i) => `
                <div class="image-preview">
                    <img src="${img.url}" alt="Car image">
                    <button class="remove-img" onclick="removeImage(${i})">✕</button>
                </div>
            `).join('');
        }

        function removeImage(index) {
            uploadedImages.splice(index, 1);
            renderImagePreviews();
        }

        // ===== SUBMIT VEHICLE =====
        async function submitVehicle() {
            const editId = document.getElementById('editVehicleId').value;
            const isEdit = !!editId;

            const brand = document.getElementById('vBrand').value.trim();
            const model = document.getElementById('vModel').value.trim();
            const year  = parseInt(document.getElementById('vYear').value);
            const licensePlate = document.getElementById('vLicensePlate').value.trim();
            const priceDay = parseFloat(document.getElementById('vPriceDay').value);

            if (!brand || !model || !year || !licensePlate || !priceDay) {
                showToast('Please fill in all required fields (Brand, Model, Year, License Plate, Price/Day).', 'warning');
                return;
            }

            const features = [];
            document.querySelectorAll('.vFeature:checked').forEach(cb => features.push(cb.value));

            const payload = {
                action: isEdit ? 'update' : 'add',
                brand, model, year,
                license_plate: licensePlate,
                category: document.getElementById('vCategory').value,
                transmission: document.getElementById('vTransmission').value,
                fuel_type: document.getElementById('vFuelType').value,
                seats: parseInt(document.getElementById('vSeats').value) || 5,
                color: document.getElementById('vColor').value.trim(),
                engine_size: getFormattedEngine(),
                consumption: getFormattedConsumption(),
                price_per_day: priceDay,
                price_per_week: parseFloat(document.getElementById('vPriceWeek').value) || null,
                price_per_month: parseFloat(document.getElementById('vPriceMonth').value) || null,
                location_city: document.getElementById('vCity').value.trim(),
                location_address: document.getElementById('vAddress').value.trim(),
                features: features,
                image_ids: uploadedImages.map(img => img.id)
            };

            if (isEdit) {
                payload.vehicle_id = editId;
            }

            const btn = document.getElementById('vehicleSubmitBtn');
            btn.disabled = true;
            btn.textContent = isEdit ? '💾 Saving...' : '➕ Adding...';

            try {
                const res = await fetch(VEHICLES_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.force_logout) {
                    showToast(data.message, 'error');
                    setTimeout(() => { window.location.href = 'login.php'; }, 2000);
                    return;
                }
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('vehicleModal');
                    loadMyVehicles();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Connection error. Please try again.', 'error');
            }

            btn.disabled = false;
            btn.textContent = isEdit ? '💾 Save Changes' : '➕ Add Vehicle';
        }

        // ===== DELETE VEHICLE =====
        function deleteVehicle(id, name) {
            deleteTargetId = id;
            document.getElementById('deleteVehicleName').textContent = name;
            document.getElementById('deleteModal').classList.add('open');
        }

        async function confirmDelete() {
            if (!deleteTargetId) return;

            try {
                const res = await fetch(VEHICLES_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', vehicle_id: deleteTargetId })
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('deleteModal');
                    loadMyVehicles();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Connection error.', 'error');
            }
            deleteTargetId = null;
        }

        // ===== MODAL HELPER =====
        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
