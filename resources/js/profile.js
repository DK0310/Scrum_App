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
let currentBalance = 0;
let currentLoyaltyPoints = 0;

// Face ID state
let faceApiLoaded = false;
let faceIdStream = null;
let faceIdDetectionInterval = null;
let faceIdScans = [];

// ===== TAB SWITCHING =====
function switchProfileTab(tab) {
    document.querySelectorAll('.profile-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
    const panel = document.getElementById('panel-' + tab);
    const tabBtn = document.getElementById('tab-' + tab);
    if (panel) panel.style.display = 'block';
    if (tabBtn) tabBtn.classList.add('active');
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
        document.getElementById('pMembership').value = u.membership || 'free';
        const membershipTextEl = document.getElementById('pMembershipText');
        if (membershipTextEl) membershipTextEl.textContent = (u.membership || 'free').toLowerCase();
        document.getElementById('pBio').value = u.bio || '';
        currentBalance = Number(u.account_balance || 0);
        currentLoyaltyPoints = Number(u.loyalty_point || 0);
        setBalanceAmount(currentBalance);
        setLoyaltyPoints(currentLoyaltyPoints);

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

function setBalanceAmount(value) {
    const balanceEl = document.getElementById('balanceAmount');
    if (!balanceEl) return;
    balanceEl.textContent = '£ ' + Number(value || 0).toFixed(2);
}

function setLoyaltyPoints(value) {
    currentLoyaltyPoints = Number(value || 0);
    const pointsEl = document.getElementById('loyaltyPointsValue');
    if (pointsEl) pointsEl.textContent = String(Math.max(0, Math.floor(currentLoyaltyPoints)));

    const redeemBtn = document.getElementById('redeemGiftBtn');
    const hintEl = document.getElementById('redeemGiftHint');
    const canRedeem = currentLoyaltyPoints >= 500;

    if (redeemBtn) {
        redeemBtn.disabled = !canRedeem;
        redeemBtn.style.opacity = canRedeem ? '1' : '0.55';
        redeemBtn.style.cursor = canRedeem ? 'pointer' : 'not-allowed';
    }

    if (hintEl) {
        if (canRedeem) {
            hintEl.textContent = 'Ready to redeem. £25 will be added directly to Account Balance.';
            hintEl.style.color = '#0f766e';
        } else {
            const remaining = 500 - Math.max(0, Math.floor(currentLoyaltyPoints));
            hintEl.textContent = 'You need ' + remaining + ' more points to redeem this gift.';
            hintEl.style.color = 'var(--gray-500)';
        }
    }
}

function setTopupStatus(message, isError) {
    const statusEl = document.getElementById('topupStatus');
    if (!statusEl) return;
    statusEl.textContent = message;
    statusEl.style.color = isError ? '#b91c1c' : '#0f766e';
}

async function startPaypalTopup() {
    const amountInput = document.getElementById('topupAmount');
    const btn = document.getElementById('topupBtn');
    const amount = Number(amountInput ? amountInput.value : 0);

    if (!Number.isFinite(amount) || amount < 1) {
        setTopupStatus('Please enter a valid amount (minimum GBP 1.00).', true);
        return;
    }
    if (amount > 10000) {
        setTopupStatus('Maximum top-up per transaction is GBP 10,000.00.', true);
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Redirecting to PayPal...';
    }

    try {
        const res = await fetch(PROFILE_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'paypal-topup-create', amount })
        });
        const data = await res.json();

        if (!data.success || !data.paypal || !data.paypal.approval_url) {
            setTopupStatus(data.message || 'Unable to start PayPal top-up.', true);
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Top Up with PayPal';
            }
            return;
        }

        window.location.href = data.paypal.approval_url;
    } catch (err) {
        setTopupStatus('Network error while creating PayPal top-up.', true);
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Top Up with PayPal';
        }
    }
}

async function processBalancePaypalCallback() {
    const params = new URLSearchParams(window.location.search);
    const paypalState = (params.get('paypal') || '').toLowerCase();
    if (!paypalState) return false;

    const orderId = params.get('token') || '';

    if (paypalState === 'cancel') {
        if (orderId) {
            try {
                await fetch(PROFILE_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'paypal-topup-cancel', order_id: orderId })
                });
            } catch (err) {}
        }
        setTopupStatus('PayPal top-up was cancelled.', true);
        if (typeof showToast === 'function') showToast('Top-up cancelled.', 'warning');
        clearPaypalQueryParams();
        return true;
    }

    if (paypalState === 'return' && orderId) {
        try {
            const res = await fetch(PROFILE_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'paypal-topup-capture', order_id: orderId })
            });
            const data = await res.json();
            if (data.success) {
                setTopupStatus('Top-up successful! Credited £ ' + Number(data.credited_amount || 0).toFixed(2) + '.', false);
                if (typeof showToast === 'function') showToast('Deposit Successfully.', 'success');
            } else {
                setTopupStatus(data.message || 'Failed to capture top-up payment.', true);
                if (typeof showToast === 'function') showToast(data.message || 'Top-up failed.', 'error');
            }
        } catch (err) {
            setTopupStatus('Network error while confirming top-up.', true);
            if (typeof showToast === 'function') showToast('Network error while confirming top-up.', 'error');
        }
        clearPaypalQueryParams();
        return true;
    }

    return false;
}

function clearPaypalQueryParams() {
    const params = new URLSearchParams(window.location.search);
    params.delete('paypal');
    params.delete('token');
    params.delete('PayerID');

    const qs = params.toString();
    const newUrl = window.location.pathname + (qs ? ('?' + qs) : '');
    window.history.replaceState({}, '', newUrl);
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
            const nameLink = document.querySelector('.navbar-actions a[href="/profile.php"]');
            if (nameLink) nameLink.innerHTML = '👤 ' + payload.full_name;

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

async function deleteMyAccount() {
    const confirmInput = document.getElementById('deleteAccountConfirm');
    const btn = document.getElementById('deleteAccountBtn');
    const confirmText = (confirmInput?.value || '').trim().toUpperCase();

    if (confirmText !== 'DELETE') {
        showToast('Please type DELETE to confirm account deletion.', 'error');
        confirmInput?.focus();
        return;
    }

    const proceed = window.confirm('This will permanently delete your account and related data. This action cannot be undone. Continue?');
    if (!proceed) return;

    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Deleting...';
    }

    try {
        const res = await fetch(PROFILE_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete-account',
                confirm_text: 'DELETE'
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || 'Account deleted successfully.', 'success');
            setTimeout(() => {
                window.location.href = data.redirect_to || '/';
            }, 600);
            return;
        }

        showToast(data.message || 'Unable to delete account.', 'error');
    } catch (e) {
        showToast('Network error while deleting account.', 'error');
        console.error(e);
    }

    if (btn) {
        btn.disabled = false;
        btn.textContent = 'Delete Account';
    }
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

async function redeemLoyaltyGift() {
    const btn = document.getElementById('redeemGiftBtn');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Redeeming...';
    }

    try {
        const res = await fetch(PROFILE_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'redeem-loyalty-gift' })
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Unable to redeem loyalty gift.', 'error');
            if (typeof data.loyalty_point !== 'undefined') {
                setLoyaltyPoints(Number(data.loyalty_point || 0));
            }
            return;
        }

        setLoyaltyPoints(Number(data.loyalty_point || 0));
        setBalanceAmount(Number(data.balance || 0));
        showToast('🎁 Redeemed successfully! £25 has been added to your account balance.', 'success');
    } catch (err) {
        showToast('Network error while redeeming gift.', 'error');
    } finally {
        if (btn) {
            btn.textContent = 'Redeem 500 Points → £25';
        }
    }
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
document.addEventListener('DOMContentLoaded', async () => {
    const handledPaypal = await processBalancePaypalCallback();
    await loadProfile();

    const params = new URLSearchParams(window.location.search);
    const requestedTab = params.get('tab');
    if (requestedTab === 'balance' || handledPaypal) {
        switchProfileTab('balance');
        if (!handledPaypal) {
            setTopupStatus('Ready to top up your account balance.', false);
        }
    } else if (requestedTab === 'exchange-gifts' || requestedTab === 'gifts') {
        switchProfileTab('exchange-gifts');
    }
});

window.startPaypalTopup = startPaypalTopup;
window.redeemLoyaltyGift = redeemLoyaltyGift;
