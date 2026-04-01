<?php include __DIR__ . '/layout/header.html.php'; ?>

    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <!-- ===== PROFILE PAGE ===== -->
    <section class="section" style="padding-top:96px;min-height:100vh;background:#f8fafa;" id="profile">
        <div class="section-container" style="max-width:1180px;">
            <div class="profile-layout">
                <aside class="profile-sidebar-card">
                    <div class="profile-sidebar-head">
                        <div class="profile-avatar-wrapper" style="position:relative;width:56px;height:56px;margin:0;">
                            <div class="profile-avatar" id="profileAvatar" style="width:56px;height:56px;border-radius:999px;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:800;overflow:hidden;box-shadow:0 8px 18px rgba(0,79,69,0.18);">
                                <span id="avatarInitial">?</span>
                            </div>
                            <label for="avatarUpload" style="position:absolute;bottom:-4px;right:-6px;width:22px;height:22px;border-radius:999px;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:var(--shadow-md);font-size:0.68rem;border:2px solid white;transition:var(--transition);" title="Change avatar">
                                <span class="material-symbols-outlined" style="font-size:12px;line-height:1;">photo_camera</span>
                            </label>
                            <input type="file" id="avatarUpload" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;" onchange="uploadAvatar(this)">
                        </div>
                        <div class="profile-user-copy">
                            <h2 id="profileName" class="profile-user-name">My Profile</h2>
                            <p id="profileRole" class="profile-user-role">Loading...</p>
                        </div>
                    </div>

                    <button type="button" class="profile-upgrade-btn">Upgrade Plan</button>

                    <nav class="profile-side-nav">
                        <button class="profile-tab active" onclick="switchProfileTab('info')" id="tab-info"><span class="material-symbols-outlined" aria-hidden="true">person</span><span>Profile Details</span></button>
                        <button class="profile-tab" onclick="switchProfileTab('security')" id="tab-security"><span class="material-symbols-outlined" aria-hidden="true">lock</span><span>Security</span></button>
                        <button class="profile-tab" onclick="switchProfileTab('preferences')" id="tab-preferences"><span class="material-symbols-outlined" aria-hidden="true">settings</span><span>App Settings</span></button>
                    </nav>
                </aside>

                <div class="profile-main-content">
                    <header class="profile-page-head">
                        <h1 class="profile-page-title">Personal Information</h1>
                        <p class="profile-page-subtitle">Manage your account details and preferences.</p>
                    </header>

                    <div class="profile-content-card">
                        <!-- ===== PERSONAL INFO TAB ===== -->
                        <div class="profile-panel" id="panel-info" style="padding:28px;">
                            <form id="profileForm" onsubmit="return saveProfile(event)">
                                <div class="profile-field-grid">

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

                            <!-- Membership -->
                            <div class="form-group">
                                <label class="form-label">Membership</label>
                                <input type="text" id="pMembership" class="form-input" readonly style="background:var(--gray-50);cursor:not-allowed;text-transform:capitalize;">
                            </div>

                            <!-- Bio -->
                            <div class="form-group" style="grid-column:1/-1;">
                                <label class="form-label">Professional Bio</label>
                                <textarea id="pBio" class="form-input" rows="3" style="resize:vertical;"></textarea>
                            </div>
                        </div>

                                <div class="profile-accent-card">
                                    <div class="profile-accent-thumb">
                                        <span class="material-symbols-outlined" aria-hidden="true">apartment</span>
                                    </div>
                                    <div>
                                        <h3 class="profile-accent-title">Membership</h3>
                                        <p class="profile-accent-text">Your account is currently on the <strong id="pMembershipText">standard</strong> plan. Upgrade to unlock advanced mobility features.</p>
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
                    <div class="profile-panel" id="panel-security" style="padding:28px;display:none;">
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

                        <!-- Password Reset -->
                        <div class="security-item" style="border-bottom:none;padding-top:12px;">
                            <div>
                                <div style="font-weight:600;">🔁 Reset Password</div>
                                <small style="color:var(--gray-500);">Send secure reset link directly to your account email (valid 5 minutes)</small>
                            </div>
                            <button type="button" id="sendResetEmailBtn" class="btn btn-outline" onclick="sendMyPasswordResetLink(this)" style="padding:8px 14px;font-size:0.82rem;">Send Reset Email</button>
                        </div>
                    </div>
                    </div>

                    <!-- ===== PREFERENCES TAB ===== -->
                    <div class="profile-panel" id="panel-preferences" style="padding:28px;display:none;">
                    <h3 style="font-weight:700;margin-bottom:16px;color:var(--gray-800);">Preferences</h3>
                    <div style="max-width:400px;">
                        <div class="security-item">
                            <div>
                                <div style="font-weight:600;">🌐 Language</div>
                                <small style="color:var(--gray-500);">Display language</small>
                            </div>
                            <select style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:0.875rem;">
                                <option selected>English</option>
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
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24;
        }

        .profile-layout {
            display: grid;
            grid-template-columns: 290px minmax(0, 1fr);
            gap: 30px;
            align-items: start;
        }

        .profile-sidebar-card {
            position: sticky;
            top: 102px;
            background: #f8fafb;
            border: 1px solid #dce4e2;
            border-radius: 24px;
            padding: 22px 16px 16px;
            box-shadow: 0 12px 30px rgba(0, 79, 69, 0.06);
        }

        .profile-brand {
            color: #05584e;
            font-family: 'Manrope', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: 0.01em;
            margin: 2px 8px 16px;
        }

        .profile-sidebar-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 8px 14px;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 12px;
        }

        .profile-user-name {
            margin: 0;
            font-size: 0.92rem;
            font-weight: 800;
            color: #134e4a;
            letter-spacing: 0.01em;
            font-family: 'Manrope', sans-serif;
        }

        .profile-user-role {
            margin: 4px 0 0;
            font-size: 0.74rem;
            color: var(--gray-500);
            font-weight: 600;
        }

        .profile-upgrade-btn {
            width: 100%;
            border: none;
            border-radius: 12px;
            padding: 10px 14px;
            margin: 6px 0 12px;
            font-size: 0.84rem;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
            background: linear-gradient(135deg, #004f45 0%, #00695c 100%);
            box-shadow: 0 12px 18px rgba(0, 79, 69, 0.18);
        }

        .profile-side-nav {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .profile-tab {
            width: 100%;
            text-align: left;
            padding: 11px 12px;
            border: none;
            border-right: 4px solid transparent;
            border-radius: 10px;
            background: transparent;
            color: #66737b;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.84rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Manrope', sans-serif;
        }

        .profile-tab .material-symbols-outlined {
            font-size: 19px;
            line-height: 1;
        }

        .profile-tab.active {
            color: #0f766e;
            background: #eaf6f2;
            border-right-color: #0f766e;
        }

        .profile-tab:hover:not(.active) {
            color: #0f766e;
            background: #edf7f4;
        }

        .profile-main-content {
            min-width: 0;
        }

        .profile-page-head {
            margin-bottom: 18px;
            padding: 8px 4px;
        }

        .profile-page-title {
            margin: 0 0 6px;
            font-size: 2.35rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #1a2020;
            font-family: 'Manrope', 'Inter', sans-serif;
        }

        .profile-page-subtitle {
            margin: 0;
            color: #556260;
            font-size: 1rem;
            font-weight: 500;
        }

        .profile-content-card {
            background: white;
            border-radius: 26px;
            border: 1px solid #dde5e3;
            box-shadow: 0 14px 40px rgba(0, 79, 69, 0.06);
            overflow: hidden;
        }

        .profile-field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #4a5956;
            margin-left: 2px;
            font-family: 'Inter', sans-serif;
        }
        .form-input {
            padding: 12px 14px;
            border: 1px solid #dbe3e1;
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: var(--transition);
            color: #1f2e2b;
            background: #f3f6f6;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
        }

        .form-input:focus {
            outline: none;
            border-color: #8abbb1;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(4, 107, 94, 0.12);
        }

        .profile-accent-card {
            margin-top: 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            background: #f2f4f4;
            border: 1px solid #e0e5e4;
            border-radius: 16px;
            padding: 16px;
        }

        .profile-accent-thumb {
            width: 64px;
            height: 64px;
            border-radius: 14px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e4e9e8;
            color: #0e5c52;
        }

        .profile-accent-thumb .material-symbols-outlined {
            font-size: 30px;
        }

        .profile-accent-title {
            margin: 0 0 4px;
            color: #0e5b52;
            font-size: 0.92rem;
            font-weight: 800;
            font-family: 'Manrope', sans-serif;
        }

        .profile-accent-text {
            margin: 0;
            color: #51615d;
            font-size: 0.82rem;
            line-height: 1.5;
        }

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

        @media (max-width: 980px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }

            .profile-sidebar-card {
                position: static;
            }
        }

        @media (max-width: 640px) {
            .profile-page-title {
                font-size: 1.9rem;
            }

            .profile-field-grid {
                grid-template-columns: 1fr;
            }

            #panel-info .form-group { grid-column: 1 / -1 !important; }
        }
    </style>

    <script src="/resources/js/profile.js"></script>

    <!-- Window scope setup for global variables -->
    <script>
        const SESSION_API = '/api/session.php';
        const PROFILE_API = '/api/profile.php';
        const FACEID_API = '/api/faceid.php';
        const EMAIL_SECURITY_API = '/api/profile.php';
        const FACE_API_CDN = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/dist/face-api.min.js';
        const FACE_MODELS_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model/';
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
