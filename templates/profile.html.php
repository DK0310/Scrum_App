<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== PROFILE PAGE ===== -->
    <section class="section" style="padding-top:100px;min-height:100vh;background:var(--gray-50);" id="profile">
        <div class="section-container" style="max-width:800px;">

            <!-- Profile Header -->
            <div style="text-align:center;margin-bottom:32px;">
                <div class="profile-avatar-wrapper" style="position:relative;width:110px;height:110px;margin:0 auto 16px;">
                    <div class="profile-avatar" id="profileAvatar" style="width:110px;height:110px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:2.5rem;font-weight:800;overflow:hidden;border:4px solid white;box-shadow:var(--shadow-md);">
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

    <script src="/public/js/profile.js"></script>

    <!-- Window scope setup for global variables -->
    <script>
        const AUTH_API = '/api/auth.php';
        const FACE_API_CDN = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/dist/face-api.min.js';
        const FACE_MODELS_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model/';
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
