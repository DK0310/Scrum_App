<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== PROFILE PAGE ===== -->
    <section class="section" style="padding-top:100px;min-height:100vh;background:var(--gray-50);" id="profile">
        <div class="section-container" style="max-width:800px;">

            <!-- Profile Header -->
            <div style="text-align:center;margin-bottom:32px;">
                <div class="profile-avatar-wrapper" style="position:relative;width:110px;height:110px;margin:0 auto 16px;">
                    <div class="profile-avatar" id="profileAvatar" style="width:110px;height:110px;border                    if (newRole === 'owner') {
                        // Show My Vehicles link if not present
                        if (!myVehiclesLink) {
                            const ordersLi = document.querySelector('.navbar-nav a[href="orders.php"]')?.parentElement;
                            if (ordersLi) {
                                const li = document.createElement('li');
                                li.innerHTML = '<a href="my-vehicles.php" style="color:var(--primary);font-weight:600;">\u{1F697} My Vehicles</a>';
                                ordersLi.after(li);
                            }
                        }
                        // Remove admin link if present
                        if (adminLink) adminLink.parentElement.remove();
                    } else if (newRole === 'renter') {
                        // Remove My Vehicles link if present
                        if (myVehiclesLink) myVehiclesLink.parentElement.remove();
                        // Remove admin link if present
                        if (adminLink) adminLink.parentElement.remove();
                    }ckground:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:2.5rem;font-weight:800;overflow:hidden;border:4px solid white;box-shadow:var(--shadow-md);">
                        <span id="avatarInitial">?</span>
                    </div>
                    <label for="avatarUpload" style="position:absolute;bottom:2px;right:2px;width:34px;height:34px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:var(--shadow-sm);font-size:0.9rem;border:2px solid white;transition:var(--transition);" title="Change avatar">
                        📷
                    </label>
                    <input type="file" id="avatarUpload" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;" onchange="uploadAvatar(this)">
                </div>
                <h1 style="font-size:1.75rem;font-weight:800;color:var(--gray-900);" id="profileName">My Profile</h1>
                <p style="color:var(--gray-500);margin-top:4px;" id="profileRole">Loading...</p>
            </div>

            <!-- Profile Card -->
            <div style="background:white;border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);overflow:hidden;">

                <!-- Tab Navigation -->
                <div style="display:flex;border-bottom:2px solid var(--gray-100);padding:0 24px;">
                    <button class="profile-tab active" onclick="switchProfileTab('info')" id="tab-info">👤 Personal Info</button>
                    <button class="profile-tab" onclick="switchProfileTab('security')" id="tab-security">🔒 Security</button>
                    <button class="profile-tab" onclick="switchProfileTab('preferences')" id="tab-preferences">⚙️ Preferences</button>
                </div>

                <!-- ===== PERSONAL INFO TAB ===== -->
                <div class="profile-panel" id="panel-info" style="padding:24px;">
                    <form id="profileForm" onsubmit="return saveProfile(event)">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

                            <!-- Full Name -->
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" id="pFullName" class="form-input" required>
                            </div>

                            <!-- Email (editable, triggers OTP) -->
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" id="pEmail" class="form-input">
                                <small style="color:var(--gray-400);font-size:0.7rem;">Changing email requires OTP verification</small>
                            </div>

                            <!-- Phone -->
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" id="pPhone" class="form-input">
                            </div>

                            <!-- Date of Birth -->
                            <div class="form-group">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" id="pDob" class="form-input">
                            </div>

                            <!-- Role -->
                            <div class="form-group">
                                <label class="form-label">Account Type</label>
                                <select id="pRole" class="form-input">
                                    <option value="renter">🚗 Renter — I want to rent cars</option>
                                    <option value="owner">🏢 Owner — I want to list my cars</option>
                                </select>
                            </div>

                            <!-- Membership -->
                            <div class="form-group">
                                <label class="form-label">Membership</label>
                                <input type="text" id="pMembership" class="form-input" readonly style="background:var(--gray-50);cursor:not-allowed;text-transform:capitalize;">
                            </div>

                            <!-- Address -->
                            <div class="form-group" style="grid-column:1/-1;">
                                <label class="form-label">Address</label>
                                <input type="text" id="pAddress" class="form-input">
                            </div>

                            <!-- City -->
                            <div class="form-group">
                                <label class="form-label">City</label>
                                <input type="text" id="pCity" class="form-input">
                            </div>

                            <!-- Country -->
                            <div class="form-group">
                                <label class="form-label">Country</label>
                                <input type="text" id="pCountry" class="form-input">
                            </div>

                            <!-- Driving License -->
                            <div class="form-group">
                                <label class="form-label">Driving License No.</label>
                                <input type="text" id="pLicense" class="form-input">
                            </div>

                            <!-- License Expiry -->
                            <div class="form-group">
                                <label class="form-label">License Expiry Date</label>
                                <input type="date" id="pLicenseExpiry" class="form-input">
                            </div>

                            <!-- ID Card -->
                            <div class="form-group">
                                <label class="form-label">ID Card / Passport No.</label>
                                <input type="text" id="pIdCard" class="form-input">
                            </div>

                            <!-- Bio -->
                            <div class="form-group" style="grid-column:1/-1;">
                                <label class="form-label">Bio</label>
                                <textarea id="pBio" class="form-input" rows="3" style="resize:vertical;"></textarea>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:24px;padding-top:16px;border-top:1px solid var(--gray-100);">
                            <button type="button" class="btn btn-outline" onclick="loadProfile()">↩️ Reset</button>
                            <button type="submit" class="btn btn-primary" id="saveProfileBtn">💾 Save Changes</button>
                        </div>
                    </form>
                </div>

                <!-- ===== SECURITY TAB ===== -->
                <div class="profile-panel" id="panel-security" style="padding:24px;display:none;">
                    <div style="max-width:400px;">
                        <h3 style="font-weight:700;margin-bottom:16px;color:var(--gray-800);">Account Security</h3>

                        <!-- Email Verified -->
                        <div class="security-item">
                            <div>
                                <div style="font-weight:600;">📧 Email Verification</div>
                                <small id="emailVerifiedStatus" style="color:var(--gray-500);">Checking...</small>
                            </div>
                            <span id="emailVerifiedBadge" class="admin-badge">—</span>
                        </div>

                        <!-- Phone Verified -->
                        <div class="security-item">
                            <div>
                                <div style="font-weight:600;">📱 Phone Verification</div>
                                <small id="phoneVerifiedStatus" style="color:var(--gray-500);">Checking...</small>
                            </div>
                            <span id="phoneVerifiedBadge" class="admin-badge">—</span>
                        </div>

                        <!-- Face ID -->
                        <div class="security-item" style="flex-direction:column;align-items:stretch;gap:12px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <div>
                                    <div style="font-weight:600;">🔐 Face ID Login</div>
                                    <small id="faceIdStatus" style="color:var(--gray-500);">Checking...</small>
                                </div>
                                <span id="faceIdBadge" class="admin-badge">—</span>
                            </div>
                            <!-- Face ID Action Buttons -->
                            <div id="faceIdActions" style="display:flex;gap:8px;">
                                <button class="btn btn-primary" id="faceIdEnableBtn" onclick="openFaceIdSetup()" style="padding:8px 16px;font-size:0.8rem;border-radius:var(--radius);">
                                    📸 Set Up Face ID
                                </button>
                                <button class="btn btn-outline" id="faceIdDisableBtn" onclick="disableFaceId()" style="padding:8px 16px;font-size:0.8rem;border-radius:var(--radius);display:none;color:#dc2626;border-color:#dc2626;">
                                    🗑️ Remove Face ID
                                </button>
                            </div>
                        </div>

                        <!-- Auth Provider -->
                        <div class="security-item">
                            <div>
                                <div style="font-weight:600;">🔑 Login Method</div>
                                <small id="authProviderText" style="color:var(--gray-500);">—</small>
                            </div>
                        </div>

                        <!-- Account Created -->
                        <div class="security-item" style="border-bottom:none;">
                            <div>
                                <div style="font-weight:600;">📅 Member Since</div>
                                <small id="memberSinceText" style="color:var(--gray-500);">—</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== PREFERENCES TAB ===== -->
                <div class="profile-panel" id="panel-preferences" style="padding:24px;display:none;">
                    <h3 style="font-weight:700;margin-bottom:16px;color:var(--gray-800);">Preferences</h3>
                    <div style="max-width:400px;">
                        <div class="security-item">
                            <div>
                                <div style="font-weight:600;">🌐 Language</div>
                                <small style="color:var(--gray-500);">Display language</small>
                            </div>
                            <select style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:0.875rem;">
                                <option>English</option>
                                <option>Tiếng Việt</option>
                            </select>
                        </div>
                        <div class="security-item" style="border-bottom:none;">
                            <div>
                                <div style="font-weight:600;">🔔 Email Notifications</div>
                                <small style="color:var(--gray-500);">Receive booking updates via email</small>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ===== EMAIL CHANGE OTP MODAL ===== -->
    <div class="modal-overlay" id="emailChangeModal">
        <div class="modal" style="max-width:480px;">
            <div class="modal-header">
                <h3 class="modal-title">📧 Verify Email Change</h3>
                <button class="modal-close" onclick="closeModal('emailChangeModal');cancelEmailChange();">✕</button>
            </div>
            <div class="modal-body" style="text-align:center;">
                <p style="color:var(--gray-600);font-size:0.875rem;margin-bottom:8px;">
                    We've sent verification codes to both your <strong>current</strong> and <strong>new</strong> email addresses.
                </p>
                <p style="font-size:0.8rem;color:var(--gray-400);margin-bottom:20px;">
                    <span id="ecOldEmailDisplay"></span> → <strong id="ecNewEmailDisplay" style="color:var(--primary);"></strong>
                </p>

                <!-- OTP for old email -->
                <div style="margin-bottom:20px;">
                    <label style="font-weight:600;font-size:0.85rem;color:var(--gray-700);display:block;margin-bottom:8px;">Code from current email:</label>
                    <div style="display:flex;gap:6px;justify-content:center;">
                        <input type="text" class="otp-box" maxlength="1" id="ecOld1" oninput="ecOtpInput(this,'ecOld2')" onkeydown="ecOtpKey(event,this,null)">
                        <input type="text" class="otp-box" maxlength="1" id="ecOld2" oninput="ecOtpInput(this,'ecOld3')" onkeydown="ecOtpKey(event,this,'ecOld1')">
                        <input type="text" class="otp-box" maxlength="1" id="ecOld3" oninput="ecOtpInput(this,'ecOld4')" onkeydown="ecOtpKey(event,this,'ecOld2')">
                        <input type="text" class="otp-box" maxlength="1" id="ecOld4" oninput="ecOtpInput(this,'ecOld5')" onkeydown="ecOtpKey(event,this,'ecOld3')">
                        <input type="text" class="otp-box" maxlength="1" id="ecOld5" oninput="ecOtpInput(this,'ecOld6')" onkeydown="ecOtpKey(event,this,'ecOld4')">
                        <input type="text" class="otp-box" maxlength="1" id="ecOld6" oninput="ecOtpInput(this,null)" onkeydown="ecOtpKey(event,this,'ecOld5')">
                    </div>
                </div>

                <!-- OTP for new email -->
                <div style="margin-bottom:20px;">
                    <label style="font-weight:600;font-size:0.85rem;color:var(--gray-700);display:block;margin-bottom:8px;">Code from new email:</label>
                    <div style="display:flex;gap:6px;justify-content:center;">
                        <input type="text" class="otp-box" maxlength="1" id="ecNew1" oninput="ecOtpInput(this,'ecNew2')" onkeydown="ecOtpKey(event,this,null)">
                        <input type="text" class="otp-box" maxlength="1" id="ecNew2" oninput="ecOtpInput(this,'ecNew3')" onkeydown="ecOtpKey(event,this,'ecNew1')">
                        <input type="text" class="otp-box" maxlength="1" id="ecNew3" oninput="ecOtpInput(this,'ecNew4')" onkeydown="ecOtpKey(event,this,'ecNew2')">
                        <input type="text" class="otp-box" maxlength="1" id="ecNew4" oninput="ecOtpInput(this,'ecNew5')" onkeydown="ecOtpKey(event,this,'ecNew3')">
                        <input type="text" class="otp-box" maxlength="1" id="ecNew5" oninput="ecOtpInput(this,'ecNew6')" onkeydown="ecOtpKey(event,this,'ecNew4')">
                        <input type="text" class="otp-box" maxlength="1" id="ecNew6" oninput="ecOtpInput(this,null)" onkeydown="ecOtpKey(event,this,'ecNew5')">
                    </div>
                </div>

                <p style="font-size:0.75rem;color:var(--gray-400);margin-bottom:16px;" id="ecTimerText">
                    Codes expire in <strong id="ecCountdown">5:00</strong>
                </p>

                <div id="ecStatusBar" style="padding:10px;border-radius:var(--radius);margin-bottom:12px;font-size:0.85rem;display:none;"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('emailChangeModal');cancelEmailChange();">Cancel</button>
                <button class="btn btn-primary" id="ecVerifyBtn" onclick="verifyEmailChange()">✅ Verify & Change</button>
            </div>
        </div>
    </div>

    <!-- ===== FACE ID SETUP MODAL ===== -->
    <div class="modal-overlay" id="faceIdModal">
        <div class="modal" style="max-width:520px;">
            <div class="modal-header">
                <h3 class="modal-title">🔐 Face ID Setup</h3>
                <button class="modal-close" onclick="closeFaceIdSetup()">✕</button>
            </div>
            <div class="modal-body" style="text-align:center;">
                <!-- Step 1: Instructions -->
                <div id="faceIdStep1">
                    <div style="font-size:3.5rem;margin-bottom:12px;">🧑‍💻</div>
                    <h4 style="color:var(--gray-800);margin-bottom:8px;">Enable Quick Login with Face ID</h4>
                    <p style="color:var(--gray-500);font-size:0.85rem;margin-bottom:20px;">
                        We'll scan your face using your camera to create a secure biometric profile.
                        This allows you to sign in instantly without a password.
                    </p>
                    <div style="background:var(--gray-50);border-radius:var(--radius);padding:16px;text-align:left;margin-bottom:20px;">
                        <div style="font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:8px;">📋 Requirements:</div>
                        <ul style="list-style:none;font-size:0.8rem;color:var(--gray-600);display:flex;flex-direction:column;gap:6px;">
                            <li>✅ Good lighting — face your light source</li>
                            <li>✅ Look straight at the camera</li>
                            <li>✅ Remove sunglasses or masks</li>
                            <li>✅ Keep still during scanning</li>
                        </ul>
                    </div>
                    <button class="btn btn-primary" onclick="startFaceIdScan()" style="width:100%;">📸 Start Camera Scan</button>
                </div>

                <!-- Step 2: Camera Scanning -->
                <div id="faceIdStep2" style="display:none;">
                    <div style="position:relative;width:100%;max-width:400px;margin:0 auto;border-radius:16px;overflow:hidden;background:#000;">
                        <video id="faceIdVideo" autoplay muted playsinline style="width:100%;border-radius:16px;transform:scaleX(-1);"></video>
                        <canvas id="faceIdCanvas" style="position:absolute;top:0;left:0;width:100%;height:100%;transform:scaleX(-1);"></canvas>
                        <!-- Scan overlay -->
                        <div id="faceIdScanOverlay" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:200px;height:260px;border:3px dashed rgba(255,255,255,0.5);border-radius:50%;pointer-events:none;"></div>
                        <!-- Status indicator -->
                        <div id="faceIdScanStatus" style="position:absolute;bottom:12px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,0.7);color:white;padding:8px 20px;border-radius:50px;font-size:0.8rem;font-weight:600;">
                            ⏳ Loading face detection models...
                        </div>
                    </div>
                    <div id="faceIdProgress" style="margin-top:16px;">
                        <div style="background:var(--gray-100);border-radius:50px;height:8px;overflow:hidden;">
                            <div id="faceIdProgressBar" style="height:100%;width:0%;background:linear-gradient(90deg,var(--primary),#22c55e);border-radius:50px;transition:width 0.3s;"></div>
                        </div>
                        <small style="color:var(--gray-500);margin-top:6px;display:block;" id="faceIdProgressText">Preparing camera...</small>
                    </div>
                    <button class="btn btn-outline" onclick="closeFaceIdSetup()" style="margin-top:12px;">Cancel</button>
                </div>

                <!-- Step 3: Success -->
                <div id="faceIdStep3" style="display:none;">
                    <div style="width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,#22c55e,#16a34a);display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 16px;color:white;animation:popIn 0.5s ease;">✓</div>
                    <h4 style="color:var(--gray-800);margin-bottom:8px;">Face ID Enabled! 🎉</h4>
                    <p style="color:var(--gray-500);font-size:0.85rem;margin-bottom:8px;">
                        Your face has been securely registered. You can now use <strong>Face ID</strong> to sign in instantly.
                    </p>
                    <div id="faceIdMatchInfo" style="background:#dcfce7;border-radius:var(--radius);padding:12px;font-size:0.8rem;color:#166534;margin-bottom:16px;"></div>
                    <button class="btn btn-primary" onclick="closeFaceIdSetup();loadProfile();" style="width:100%;">👍 Done</button>
                </div>

                <!-- Step Error -->
                <div id="faceIdStepError" style="display:none;">
                    <div style="font-size:3rem;margin-bottom:12px;">😕</div>
                    <h4 style="color:#dc2626;margin-bottom:8px;">Face Scan Failed</h4>
                    <p style="color:var(--gray-500);font-size:0.85rem;margin-bottom:16px;" id="faceIdErrorMsg">
                        We couldn't detect your face clearly. Please try again.
                    </p>
                    <div style="display:flex;gap:8px;">
                        <button class="btn btn-primary" onclick="retryFaceIdScan()" style="flex:1;">🔄 Try Again</button>
                        <button class="btn btn-outline" onclick="closeFaceIdSetup()" style="flex:1;">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .profile-tab {
            padding: 14px 20px; font-weight: 600; font-size: 0.875rem; cursor: pointer;
            border: none; background: transparent; color: var(--gray-500);
            border-bottom: 3px solid transparent; transition: var(--transition);
        }
        .profile-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
        .profile-tab:hover:not(.active) { color: var(--gray-700); }

        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-label { font-size: 0.8rem; font-weight: 600; color: var(--gray-600); }
        .form-input {
            padding: 10px 14px; border: 1.5px solid var(--gray-200); border-radius: var(--radius);
            font-size: 0.875rem; font-family: inherit; transition: var(--transition);
            color: var(--gray-800);
        }
        .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }

        .security-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 0; border-bottom: 1px solid var(--gray-100);
        }
        .admin-badge { display: inline-block; padding: 3px 10px; border-radius: 50px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; }
        .admin-badge.active { background: #dcfce7; color: #166534; }
        .admin-badge.inactive { background: #fef2f2; color: #991b1b; }

        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background: var(--gray-300); border-radius: 50px; transition: 0.3s;
        }
        .toggle-slider:before {
            content: ''; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px;
            background: white; border-radius: 50%; transition: 0.3s;
        }
        .toggle-switch input:checked + .toggle-slider { background: var(--primary); }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }

        .otp-box {
            width: 42px; height: 50px; text-align: center; font-size: 1.3rem; font-weight: 700;
            border: 2px solid var(--gray-200); border-radius: var(--radius); font-family: inherit;
            transition: var(--transition);
        }
        .otp-box:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }

        .profile-avatar-wrapper label:hover { transform: scale(1.1); background: var(--primary-dark) !important; }

        @keyframes popIn { 0%{transform:scale(0)} 70%{transform:scale(1.1)} 100%{transform:scale(1)} }
        @keyframes pulse-ring { 0%{box-shadow:0 0 0 0 rgba(34,197,94,0.5)} 70%{box-shadow:0 0 0 12px rgba(34,197,94,0)} 100%{box-shadow:0 0 0 0 rgba(34,197,94,0)} }
        @keyframes scan-line { 0%{top:10%} 50%{top:80%} 100%{top:10%} }

        .faceid-scanning .faceIdScanOverlay { border-color: #22c55e !important; animation: pulse-ring 1.5s infinite; }

        @media (max-width: 640px) {
            #panel-info .form-group { grid-column: 1 / -1 !important; }
            #panel-info form > div:first-child { grid-template-columns: 1fr !important; }
        }
    </style>

    <script>
        const AUTH_API = '/api/auth.php';
        let currentProfile = null;
        let originalEmail = '';
        let ecCountdownInterval = null;

        function switchProfileTab(tab) {
            document.querySelectorAll('.profile-panel').forEach(p => p.style.display = 'none');
            document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
            document.getElementById('panel-' + tab).style.display = 'block';
            document.getElementById('tab-' + tab).classList.add('active');
        }

        // ===== UPLOAD AVATAR =====
        async function uploadAvatar(input) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];

            if (file.size > 3 * 1024 * 1024) {
                showToast('Image too large. Max 3MB.', 'error');
                input.value = '';
                return;
            }

            const formData = new FormData();
            formData.append('action', 'upload-avatar');
            formData.append('avatar', file);

            try {
                showToast('⏳ Uploading avatar...', 'info');
                const res = await fetch(AUTH_API, { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    showToast('✅ Avatar updated!', 'success');
                    // Update avatar display
                    const avatarEl = document.getElementById('profileAvatar');
                    avatarEl.innerHTML = `<img src="${data.avatar_url}" style="width:100%;height:100%;object-fit:cover;">`;
                } else {
                    showToast(data.message || 'Failed to upload avatar.', 'error');
                }
            } catch (e) {
                showToast('Network error uploading avatar.', 'error');
                console.error(e);
            }
            input.value = '';
        }

        // ===== LOAD PROFILE =====
        async function loadProfile() {
            try {
                const res = await fetch(AUTH_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get-profile' })
                });
                const data = await res.json();

                if (!data.success) {
                    showToast(data.message || 'Failed to load profile', 'error');
                    return;
                }

                currentProfile = data.user;
                const u = data.user;

                // Header
                const name = u.full_name || u.email || 'User';
                const initial = name.charAt(0).toUpperCase();

                // Avatar - check for BLOB avatar first
                const avatarEl = document.getElementById('profileAvatar');
                if (u.avatar_url) {
                    avatarEl.innerHTML = `<img src="${u.avatar_url}" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML='<span id=\\'avatarInitial\\'>${initial}</span>'">`;
                } else {
                    avatarEl.innerHTML = `<span id="avatarInitial">${initial}</span>`;
                }

                document.getElementById('profileName').textContent = name;

                const roleBadge = u.role === 'admin' ? '⚙️ Administrator' : u.role === 'owner' ? '🏢 Vehicle Owner' : '🚗 Renter';
                document.getElementById('profileRole').textContent = roleBadge;

                // Form fields
                document.getElementById('pFullName').value = u.full_name || '';
                document.getElementById('pEmail').value = u.email || '';
                originalEmail = u.email || '';
                document.getElementById('pPhone').value = u.phone || '';
                document.getElementById('pDob').value = u.date_of_birth ? u.date_of_birth.substring(0, 10) : '';
                document.getElementById('pRole').value = u.role || 'renter';
                document.getElementById('pMembership').value = u.membership || 'free';
                document.getElementById('pAddress').value = u.address || '';
                document.getElementById('pCity').value = u.city || '';
                document.getElementById('pCountry').value = u.country || '';
                document.getElementById('pLicense').value = u.driving_license || '';
                document.getElementById('pLicenseExpiry').value = u.license_expiry ? u.license_expiry.substring(0, 10) : '';
                document.getElementById('pIdCard').value = u.id_card_number || '';
                document.getElementById('pBio').value = u.bio || '';

                // Disable role for admin
                if (u.role === 'admin') {
                    document.getElementById('pRole').disabled = true;
                    document.getElementById('pRole').innerHTML = '<option value="admin">⚙️ Administrator</option>';
                }

                // Security tab
                document.getElementById('emailVerifiedStatus').textContent = u.email_verified ? 'Your email is verified' : 'Not verified yet';
                document.getElementById('emailVerifiedBadge').textContent = u.email_verified ? 'Verified' : 'Not Verified';
                document.getElementById('emailVerifiedBadge').className = 'admin-badge ' + (u.email_verified ? 'active' : 'inactive');

                document.getElementById('phoneVerifiedStatus').textContent = u.phone_verified ? 'Your phone is verified' : 'Not verified yet';
                document.getElementById('phoneVerifiedBadge').textContent = u.phone_verified ? 'Verified' : 'Not Verified';
                document.getElementById('phoneVerifiedBadge').className = 'admin-badge ' + (u.phone_verified ? 'active' : 'inactive');

                document.getElementById('faceIdStatus').textContent = u.faceid_enabled ? 'Face ID is enabled — quick login ready' : 'Not set up — enable for quick login';
                document.getElementById('faceIdBadge').textContent = u.faceid_enabled ? 'Enabled' : 'Disabled';
                document.getElementById('faceIdBadge').className = 'admin-badge ' + (u.faceid_enabled ? 'active' : 'inactive');

                // Toggle Face ID action buttons
                document.getElementById('faceIdEnableBtn').style.display = u.faceid_enabled ? 'none' : 'inline-flex';
                document.getElementById('faceIdEnableBtn').textContent = u.faceid_enabled ? '🔄 Re-scan Face' : '📸 Set Up Face ID';
                document.getElementById('faceIdDisableBtn').style.display = u.faceid_enabled ? 'inline-flex' : 'none';
                // If enabled, also show re-scan button
                if (u.faceid_enabled) {
                    document.getElementById('faceIdEnableBtn').style.display = 'inline-flex';
                    document.getElementById('faceIdEnableBtn').textContent = '🔄 Re-scan Face';
                }

                document.getElementById('authProviderText').textContent = (u.auth_provider || 'email').charAt(0).toUpperCase() + (u.auth_provider || 'email').slice(1);

                const createdDate = u.created_at ? new Date(u.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '—';
                document.getElementById('memberSinceText').textContent = createdDate;

            } catch (e) {
                showToast('Error loading profile.', 'error');
                console.error(e);
            }
        }

        // ===== SAVE PROFILE =====
        async function saveProfile(e) {
            e.preventDefault();

            const newEmail = document.getElementById('pEmail').value.trim();

            // Check if email changed — trigger OTP flow
            if (newEmail && newEmail !== originalEmail) {
                await initiateEmailChange(newEmail);
                return false;
            }

            return await doSaveProfile();
        }

        async function doSaveProfile() {
            const btn = document.getElementById('saveProfileBtn');
            btn.disabled = true;
            btn.textContent = '⏳ Saving...';

            const payload = {
                action: 'update-profile',
                full_name: document.getElementById('pFullName').value.trim(),
                phone: document.getElementById('pPhone').value.trim(),
                date_of_birth: document.getElementById('pDob').value || null,
                role: document.getElementById('pRole').value,
                address: document.getElementById('pAddress').value.trim(),
                city: document.getElementById('pCity').value.trim(),
                country: document.getElementById('pCountry').value.trim(),
                driving_license: document.getElementById('pLicense').value.trim(),
                license_expiry: document.getElementById('pLicenseExpiry').value || null,
                id_card_number: document.getElementById('pIdCard').value.trim(),
                bio: document.getElementById('pBio').value.trim(),
            };

            if (!payload.full_name) {
                showToast('Full name is required.', 'error');
                btn.disabled = false;
                btn.textContent = '💾 Save Changes';
                return false;
            }

            try {
                const res = await fetch(AUTH_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    showToast('✅ Profile updated successfully!', 'success');

                    // Update header name
                    const nameLink = document.querySelector('.navbar-actions a[href="profile.php"]');
                    if (nameLink) nameLink.innerHTML = '👤 ' + payload.full_name;

                    // Instant role change — update navbar UI
                    const newRole = payload.role;
                    const myVehiclesLink = document.querySelector('.navbar-nav a[href="my-vehicles.php"]');
                    const adminLink = document.querySelector('.navbar-nav a[href="admin.php"]');

                    if (newRole === 'owner') {
                        // Show My Vehicles link if not present
                        if (!myVehiclesLink) {
                            const ordersLi = document.querySelector('.navbar-nav a[href="orders.php"]')?.parentElement;
                            if (ordersLi) {
                                const li = document.createElement('li');
                                li.innerHTML = '<a href="my-vehicles.php" style="color:var(--primary);font-weight:600;">� My Vehicles</a>';
                                ordersLi.after(li);
                            }
                        }
                    } else if (newRole === 'renter') {
                        // Remove My Vehicles link if present
                        if (myVehiclesLink) myVehiclesLink.parentElement.remove();
                    }

                    // Update profile role display immediately
                    const roleBadge = newRole === 'admin' ? '⚙️ Administrator' : newRole === 'owner' ? '🏢 Vehicle Owner' : '🚗 Renter';
                    document.getElementById('profileRole').textContent = roleBadge;

                    // Reload profile data
                    loadProfile();
                } else {
                    showToast(data.message || 'Failed to update profile.', 'error');
                }
            } catch (e) {
                showToast('Error saving profile.', 'error');
                console.error(e);
            }

            btn.disabled = false;
            btn.textContent = '💾 Save Changes';
            return false;
        }

        // ===== EMAIL CHANGE WITH DOUBLE OTP =====
        async function initiateEmailChange(newEmail) {
            const btn = document.getElementById('saveProfileBtn');
            btn.disabled = true;
            btn.textContent = '⏳ Sending OTPs...';

            try {
                const res = await fetch(AUTH_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'email-change-send-otp',
                        old_email: originalEmail,
                        new_email: newEmail
                    })
                });
                const data = await res.json();

                if (data.success) {
                    // Show OTP modal
                    document.getElementById('ecOldEmailDisplay').textContent = originalEmail;
                    document.getElementById('ecNewEmailDisplay').textContent = newEmail;
                    document.getElementById('emailChangeModal').classList.add('open');
                    // Clear OTP inputs
                    for (let i = 1; i <= 6; i++) {
                        document.getElementById('ecOld' + i).value = '';
                        document.getElementById('ecNew' + i).value = '';
                    }
                    document.getElementById('ecOld1').focus();
                    startEcCountdown();
                    document.getElementById('ecStatusBar').style.display = 'none';
                } else {
                    showToast(data.message || 'Failed to send OTP.', 'error');
                }
            } catch (e) {
                showToast('Network error.', 'error');
            }

            btn.disabled = false;
            btn.textContent = '💾 Save Changes';
        }

        function ecOtpInput(el, nextId) {
            el.value = el.value.replace(/[^0-9]/g, '');
            if (el.value && nextId) document.getElementById(nextId).focus();
        }
        function ecOtpKey(e, el, prevId) {
            if (e.key === 'Backspace' && !el.value && prevId) document.getElementById(prevId).focus();
        }

        function getEcOtp(prefix) {
            let otp = '';
            for (let i = 1; i <= 6; i++) otp += document.getElementById(prefix + i).value;
            return otp;
        }

        async function verifyEmailChange() {
            const otpOld = getEcOtp('ecOld');
            const otpNew = getEcOtp('ecNew');

            if (otpOld.length !== 6 || otpNew.length !== 6) {
                showEcStatus('error', 'Please enter both 6-digit codes.');
                return;
            }

            const btn = document.getElementById('ecVerifyBtn');
            btn.disabled = true;
            btn.textContent = '⏳ Verifying...';
            showEcStatus('loading', 'Verifying codes...');

            try {
                const res = await fetch(AUTH_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'email-change-verify',
                        otp_old: otpOld,
                        otp_new: otpNew
                    })
                });
                const data = await res.json();

                if (data.success) {
                    closeModal('emailChangeModal');
                    clearInterval(ecCountdownInterval);
                    originalEmail = data.new_email;
                    showToast('✅ Email changed successfully!', 'success');

                    // Now save the rest of the profile
                    await doSaveProfile();
                } else {
                    showEcStatus('error', data.message || 'Verification failed.');
                }
            } catch (e) {
                showEcStatus('error', 'Network error.');
            }

            btn.disabled = false;
            btn.textContent = '✅ Verify & Change';
        }

        function cancelEmailChange() {
            // Reset email field back to original
            document.getElementById('pEmail').value = originalEmail;
            clearInterval(ecCountdownInterval);
        }

        function startEcCountdown() {
            let seconds = 300;
            clearInterval(ecCountdownInterval);
            ecCountdownInterval = setInterval(() => {
                seconds--;
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                document.getElementById('ecCountdown').textContent = m + ':' + String(s).padStart(2, '0');
                if (seconds <= 0) {
                    clearInterval(ecCountdownInterval);
                    document.getElementById('ecTimerText').innerHTML = '<span style="color:var(--danger);">⏰ Codes expired. Please try again.</span>';
                }
            }, 1000);
        }

        function showEcStatus(type, msg) {
            const bar = document.getElementById('ecStatusBar');
            bar.style.display = 'block';
            if (type === 'error') { bar.style.background = '#fee2e2'; bar.style.color = '#991b1b'; }
            else if (type === 'loading') { bar.style.background = '#fef3c7'; bar.style.color = '#92400e'; }
            else { bar.style.background = '#dcfce7'; bar.style.color = '#166534'; }
            bar.textContent = msg;
        }

        // Init
        document.addEventListener('DOMContentLoaded', () => loadProfile());

        // ===== FACE ID SYSTEM =====
        const FACE_API_CDN = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/dist/face-api.min.js';
        const FACE_MODELS_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model/';
        let faceApiLoaded = false;
        let faceIdStream = null;
        let faceIdDetectionInterval = null;
        let faceIdScans = [];
        const REQUIRED_SCANS = 5;

        async function loadFaceApi() {
            if (faceApiLoaded) return true;
            return new Promise((resolve, reject) => {
                if (window.faceapi) { faceApiLoaded = true; resolve(true); return; }
                const s = document.createElement('script');
                s.src = FACE_API_CDN;
                s.onload = async () => {
                    try {
                        await Promise.all([
                            faceapi.nets.tinyFaceDetector.loadFromUri(FACE_MODELS_URL),
                            faceapi.nets.faceLandmark68Net.loadFromUri(FACE_MODELS_URL),
                            faceapi.nets.faceRecognitionNet.loadFromUri(FACE_MODELS_URL)
                        ]);
                        faceApiLoaded = true;
                        resolve(true);
                    } catch (e) { reject(e); }
                };
                s.onerror = reject;
                document.head.appendChild(s);
            });
        }

        function openFaceIdSetup() {
            document.getElementById('faceIdModal').classList.add('open');
            showFaceIdStep('faceIdStep1');
        }

        function closeFaceIdSetup() {
            stopFaceIdCamera();
            document.getElementById('faceIdModal').classList.remove('open');
        }

        function showFaceIdStep(stepId) {
            ['faceIdStep1', 'faceIdStep2', 'faceIdStep3', 'faceIdStepError'].forEach(id => {
                document.getElementById(id).style.display = id === stepId ? 'block' : 'none';
            });
        }

        async function startFaceIdScan() {
            showFaceIdStep('faceIdStep2');
            faceIdScans = [];
            updateFaceIdProgress(0, 'Loading face detection models...');

            try {
                await loadFaceApi();
                updateFaceIdProgress(0, 'Starting camera...');
            } catch (e) {
                console.error('Face API load error:', e);
                document.getElementById('faceIdErrorMsg').textContent = 'Failed to load face detection. Please check your internet connection.';
                showFaceIdStep('faceIdStepError');
                return;
            }

            try {
                faceIdStream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
                });
                const video = document.getElementById('faceIdVideo');
                video.srcObject = faceIdStream;
                await video.play();

                updateFaceIdProgress(0, 'Position your face inside the oval...');
                document.getElementById('faceIdScanStatus').textContent = '👀 Looking for your face...';

                // Start detecting
                setTimeout(() => startFaceDetectionLoop(), 500);
            } catch (e) {
                console.error('Camera error:', e);
                document.getElementById('faceIdErrorMsg').textContent = 'Camera access denied. Please allow camera permission and try again.';
                showFaceIdStep('faceIdStepError');
            }
        }

        function startFaceDetectionLoop() {
            const video = document.getElementById('faceIdVideo');
            const canvas = document.getElementById('faceIdCanvas');
            const ctx = canvas.getContext('2d');

            faceIdDetectionInterval = setInterval(async () => {
                if (!video.videoWidth) return;

                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;

                const detection = await faceapi
                    .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ scoreThreshold: 0.5 }))
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                ctx.clearRect(0, 0, canvas.width, canvas.height);

                if (detection) {
                    // Draw face box
                    const box = detection.detection.box;
                    ctx.strokeStyle = '#22c55e';
                    ctx.lineWidth = 3;
                    ctx.strokeRect(box.x, box.y, box.width, box.height);

                    // Draw landmarks dots
                    const landmarks = detection.landmarks.positions;
                    ctx.fillStyle = 'rgba(34,197,94,0.6)';
                    landmarks.forEach(pt => {
                        ctx.beginPath();
                        ctx.arc(pt.x, pt.y, 2, 0, 2 * Math.PI);
                        ctx.fill();
                    });

                    // Collect descriptor
                    faceIdScans.push(Array.from(detection.descriptor));
                    const progress = Math.min(faceIdScans.length / REQUIRED_SCANS * 100, 100);
                    updateFaceIdProgress(progress, `Scanning... ${Math.min(faceIdScans.length, REQUIRED_SCANS)}/${REQUIRED_SCANS}`);
                    document.getElementById('faceIdScanStatus').textContent = '✅ Face detected — hold still...';
                    document.getElementById('faceIdScanOverlay').style.borderColor = '#22c55e';

                    if (faceIdScans.length >= REQUIRED_SCANS) {
                        clearInterval(faceIdDetectionInterval);
                        await completeFaceIdSetup();
                    }
                } else {
                    document.getElementById('faceIdScanStatus').textContent = '👀 Looking for your face...';
                    document.getElementById('faceIdScanOverlay').style.borderColor = 'rgba(255,255,255,0.5)';
                }
            }, 500);
        }

        async function completeFaceIdSetup() {
            updateFaceIdProgress(100, 'Processing face data...');
            document.getElementById('faceIdScanStatus').textContent = '⏳ Saving...';

            // Average descriptors for more accuracy
            const avgDescriptor = new Array(128).fill(0);
            faceIdScans.forEach(d => {
                for (let i = 0; i < 128; i++) avgDescriptor[i] += d[i];
            });
            for (let i = 0; i < 128; i++) avgDescriptor[i] /= faceIdScans.length;

            stopFaceIdCamera();

            try {
                const res = await fetch(AUTH_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'enable-faceid', face_descriptor: avgDescriptor })
                });
                const data = await res.json();

                if (data.success) {
                    document.getElementById('faceIdMatchInfo').textContent =
                        `Face registered with ${faceIdScans.length} samples. Your Face ID is ready to use on the login page.`;
                    showFaceIdStep('faceIdStep3');
                } else {
                    document.getElementById('faceIdErrorMsg').textContent = data.message || 'Failed to save face data.';
                    showFaceIdStep('faceIdStepError');
                }
            } catch (e) {
                document.getElementById('faceIdErrorMsg').textContent = 'Network error. Please try again.';
                showFaceIdStep('faceIdStepError');
            }
        }

        function retryFaceIdScan() {
            faceIdScans = [];
            startFaceIdScan();
        }

        async function disableFaceId() {
            if (!confirm('Are you sure you want to disable Face ID login?')) return;
            try {
                const res = await fetch(AUTH_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'disable-faceid' })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('✅ Face ID disabled.', 'success');
                    loadProfile();
                } else {
                    showToast(data.message || 'Failed.', 'error');
                }
            } catch (e) {
                showToast('Network error.', 'error');
            }
        }

        function updateFaceIdProgress(pct, text) {
            document.getElementById('faceIdProgressBar').style.width = pct + '%';
            document.getElementById('faceIdProgressText').textContent = text;
        }

        function stopFaceIdCamera() {
            if (faceIdDetectionInterval) { clearInterval(faceIdDetectionInterval); faceIdDetectionInterval = null; }
            if (faceIdStream) { faceIdStream.getTracks().forEach(t => t.stop()); faceIdStream = null; }
        }
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
