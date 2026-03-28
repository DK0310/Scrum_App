/**
 * Profile Module - User profile management with Face ID biometric authentication
 * Exported functions: switchProfileTab, uploadAvatar, loadProfile, saveProfile, etc.
 */

// ===== CONSTANTS & CONFIGURATION =====
const SESSION_API = '/api/session.php';
const PROFILE_API = '/api/profile.php';
const FACEID_API = '/api/faceid.php';
const EMAIL_SECURITY_API = PROFILE_API;
const FACE_API_CDN = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/dist/face-api.min.js';
const FACE_MODELS_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model/';
const REQUIRED_SCANS = 5;

// ===== STATE MANAGEMENT =====
let currentProfile = null;
let originalEmail = '';
let ecCountdownInterval = null;

// Face ID state
let faceApiLoaded = false;
let faceIdStream = null;
let faceIdDetectionInterval = null;
let faceIdScans = [];

// ===== TAB SWITCHING =====
function switchProfileTab(tab) {
    document.querySelectorAll('.profile-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('panel-' + tab).style.display = 'block';
    document.getElementById('tab-' + tab).classList.add('active');
}

// ===== AVATAR UPLOAD =====
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
        const res = await fetch(PROFILE_API, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            showToast('✅ Avatar updated!', 'success');
            // Update avatar display immediately
            const avatarEl = document.getElementById('profileAvatar');
            const avatarUrlWithCache = data.avatar_url + (data.avatar_url.includes('?') ? '&t=' : '?t=') + Date.now();
            avatarEl.innerHTML = `<img src="${avatarUrlWithCache}" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML='<span id=\\'avatarInitial\\'>' + (currentProfile?.full_name?.[0] ?? '?').toUpperCase() + '</span>'">`;
            
            // Refresh profile from database after short delay to verify save succeeded
            setTimeout(() => {
                loadProfile();
            }, 500);
        } else {
            showToast(data.message || 'Failed to upload avatar.', 'error');
        }
    } catch (e) {
        showToast('Network error uploading avatar.', 'error');
        console.error(e);
    }
    input.value = '';
}

// ===== LOAD PROFILE DATA =====
async function loadProfile() {
    try {
        const res = await fetch(PROFILE_API, {
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
            // Add cache-busting parameter to ensure fresh avatar
            const avatarUrlWithCache = u.avatar_url.includes('?') 
                ? u.avatar_url + '&t=' + Date.now()
                : u.avatar_url + '?t=' + Date.now();
            avatarEl.innerHTML = `<img src="${avatarUrlWithCache}" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML='<span id=\\'avatarInitial\\'>${initial}</span>'">`;
        } else {
            avatarEl.innerHTML = `<span id="avatarInitial">${initial}</span>`;
        }

        document.getElementById('profileName').textContent = name;

        // Role badge
        let roleBadge;
        switch(u.role) {
            case 'admin': roleBadge = '⚙️ Administrator'; break;
            case 'controlstaff': roleBadge = '🧭 Control Staff'; break;
            case 'callcenterstaff': roleBadge = '📞 Call Center Staff'; break;
            case 'driver': roleBadge = '🚗 Driver'; break;
            case 'user': roleBadge = '👤 Customer'; break;
            default: roleBadge = '👤 Customer';
        }
        document.getElementById('profileRole').textContent = roleBadge;

        // Form fields
        document.getElementById('pFullName').value = u.full_name || '';
        document.getElementById('pEmail').value = u.email || '';
        originalEmail = u.email || '';
        document.getElementById('pPhone').value = u.phone || '';
        document.getElementById('pDob').value = u.date_of_birth ? u.date_of_birth.substring(0, 10) : '';
        document.getElementById('pRole').value = u.role || 'user';
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
        const res = await fetch(PROFILE_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast('✅ Profile updated successfully!', 'success');

            // Update header name
            const nameLink = document.querySelector('.navbar-actions a[href="/api/profile.php"]');
            if (nameLink) nameLink.innerHTML = '👤 ' + payload.full_name;

            // Instant role change — update navbar UI
            const newRole = payload.role;
            const myVehiclesLink = document.querySelector('.navbar-nav a[href="my-vehicles.php"]');

            if (['controlstaff', 'admin'].includes(newRole)) {
                // Show My Vehicles link - controlstaff/admin can manage vehicles
                if (!myVehiclesLink) {
                    const ordersLi = document.querySelector('.navbar-nav a[href="/api/orders.php"]')?.parentElement;
                    if (ordersLi) {
                        const li = document.createElement('li');
                        li.innerHTML = '<a href="my-vehicles.php" style="color:var(--primary);font-weight:600;">🚗 My Vehicles</a>';
                        ordersLi.after(li);
                    }
                }
            } else if (['user', 'driver', 'callcenterstaff'].includes(newRole)) {
                // Remove My Vehicles link for non-control roles
                if (myVehiclesLink) myVehiclesLink.parentElement.remove();
            }

            const roleBadge = newRole === 'admin' ? '⚙️ Administrator' 
                : newRole === 'controlstaff' ? '🧭 Control Staff'
                : newRole === 'callcenterstaff' ? '📞 Call Center Staff'
                : newRole === 'driver' ? '🚗 Driver'
                : newRole === 'user' ? '👤 Customer' : '👤 Customer';
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

async function sendMyPasswordResetLink(buttonEl) {
    const btn = buttonEl || document.getElementById('sendResetEmailBtn');
    if (!btn) return;

    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = '⏳ Sending...';

    try {
        const res = await fetch('/api/password-change.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send-reset-link-current-user' })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || 'Reset link sent to your account email.', 'success');
        } else {
            showToast(data.message || 'Unable to send reset link.', 'error');
        }
    } catch (e) {
        showToast('Network error while sending reset link.', 'error');
        console.error(e);
    }

    btn.disabled = false;
    btn.textContent = oldText;
}

// ===== EMAIL CHANGE WITH DOUBLE OTP =====
async function initiateEmailChange(newEmail) {
    const btn = document.getElementById('saveProfileBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Sending OTPs...';

    try {
        const res = await fetch(EMAIL_SECURITY_API, {
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
        const res = await fetch(EMAIL_SECURITY_API, {
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

// ===== FACE ID BIOMETRIC AUTHENTICATION SYSTEM =====
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
                completeFaceIdSetup();
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
        const res = await fetch(FACEID_API, {
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
        const res = await fetch(FACEID_API, {
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

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', () => loadProfile());
