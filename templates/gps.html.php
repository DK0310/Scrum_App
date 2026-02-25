<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== GPS TRACKING ===== -->
    <section class="section" style="padding-top:100px;">
        <div class="section-container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">📍 GPS Car Tracking</h2>
                    <p class="section-subtitle">Real-time tracking for car owners — know where your car is, always</p>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:center;">
                <div class="map-container">
                    <div class="map-placeholder">
                        <div class="map-placeholder-icon">🗺️</div>
                        <p>Interactive GPS Map</p>
                        <p style="font-size:0.813rem;">Real-time car location tracking</p>
                        <button class="btn btn-primary btn-sm" onclick="showToast('GPS tracking requires an active rental booking.','info')">Enable Tracking</button>
                    </div>
                </div>
                <div>
                    <h3 style="font-size:1.5rem;font-weight:800;color:var(--gray-900);margin-bottom:16px;">Track Your Fleet in Real-Time</h3>
                    <p style="color:var(--gray-500);margin-bottom:24px;line-height:1.7;">
                        As a car owner, you get access to our advanced GPS tracking system. Monitor your vehicle's location, speed, and route history all from your dashboard.
                    </p>
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span style="width:40px;height:40px;border-radius:50%;background:var(--primary-50);display:flex;align-items:center;justify-content:center;">📍</span>
                            <div>
                                <strong>Live Location</strong>
                                <p style="font-size:0.813rem;color:var(--gray-500);">Track your car's position in real time</p>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span style="width:40px;height:40px;border-radius:50%;background:var(--success-light);display:flex;align-items:center;justify-content:center;">🛣️</span>
                            <div>
                                <strong>Route History</strong>
                                <p style="font-size:0.813rem;color:var(--gray-500);">View complete trip history and routes</p>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span style="width:40px;height:40px;border-radius:50%;background:var(--warning-light);display:flex;align-items:center;justify-content:center;">🔔</span>
                            <div>
                                <strong>Geo-fence Alerts</strong>
                                <p style="font-size:0.813rem;color:var(--gray-500);">Get notified when car leaves designated area</p>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span style="width:40px;height:40px;border-radius:50%;background:var(--gray-100);display:flex;align-items:center;justify-content:center;">📊</span>
                            <div>
                                <strong>Speed Monitoring</strong>
                                <p style="font-size:0.813rem;color:var(--gray-500);">Monitor driving speed and get alerts</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
