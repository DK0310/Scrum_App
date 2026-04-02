// ============================================
// LOGIN MODAL FUNCTIONS
// ============================================

function showAuthModal(mode = 'login') {
    if (mode === 'login') {
        showLoginModal();
    } else if (mode === 'register') {
        showRegisterModal();
    }
}

function showLoginModal() {
    document.getElementById('loginModalOverlay').style.display = 'flex';
    document.getElementById('loginIdentifier').focus();
}

function closeLoginModal() {
    document.getElementById('loginModalOverlay').style.display = 'none';
    if (typeof closeFaceIdLoginModal === 'function') {
        closeFaceIdLoginModal();
    }
    document.getElementById('loginForm').reset();
    document.getElementById('loginStatus').style.display = 'none';
}

function toggleLoginPassword() {
    const passwordField = document.getElementById('loginPassword');
    const toggle = document.getElementById('loginPasswordToggle');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggle.textContent = 'Hide';
    } else {
        passwordField.type = 'password';
        toggle.textContent = 'Show';
    }
}

function showLoginStatus(message, type = 'info') {
    const statusDiv = document.getElementById('loginStatus');
    if (!statusDiv) {
        console.error('loginStatus element not found');
        return;
    }
    
    statusDiv.style.display = 'block';
    statusDiv.textContent = message;
    
    // Clear previous styles
    statusDiv.style.background = '';
    statusDiv.style.color = '';
    statusDiv.style.borderRadius = '6px';
    statusDiv.style.padding = '12px';
    statusDiv.style.marginBottom = '20px';
    statusDiv.style.fontSize = '14px';
    statusDiv.style.textAlign = 'center';
    
    if (type === 'error') {
        statusDiv.style.background = '#fee2e2';
        statusDiv.style.color = '#dc2626';
        statusDiv.style.border = '1px solid #fecaca';
        console.error('Login error:', message);
    } else if (type === 'success') {
        statusDiv.style.background = '#dcfce7';
        statusDiv.style.color = '#16a34a';
        statusDiv.style.border = '1px solid #bbf7d0';
        console.log('Login success:', message);
    } else if (type === 'loading') {
        statusDiv.style.background = '#dbeafe';
        statusDiv.style.color = '#0284c7';
        statusDiv.style.border = '1px solid #bfdbfe';
        console.log('Loading:', message);
    }
}

const AUTH_FACEID_API = '/api/faceid.php';
const AUTH_FACE_API_CDN = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/dist/face-api.min.js';
const AUTH_FACE_MODELS_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model/';
const AUTH_FACE_REQUIRED_SCANS = 3;

let authFaceApiLoaded = false;
let authFaceStream = null;
let authFaceDetectionTimer = null;
let authFaceDescriptors = [];

function ensureFaceIdLoginModal() {
    let overlay = document.getElementById('faceIdLoginOverlay');
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.id = 'faceIdLoginOverlay';
    overlay.className = 'modal-overlay';
    overlay.style.display = 'none';
    overlay.innerHTML = '' +
        '<div class="modal" style="max-width:520px;width:94%;padding:20px 20px 16px;">' +
            '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px;">' +
                '<h3 style="margin:0;font-size:1.15rem;color:#0f766e;">Sign In with Face ID</h3>' +
                '<button type="button" onclick="closeFaceIdLoginModal()" style="border:none;background:#f3f4f6;border-radius:999px;width:34px;height:34px;cursor:pointer;">✕</button>' +
            '</div>' +
            '<p style="margin:0 0 12px;color:#6b7280;font-size:0.88rem;">Center your face in the frame and hold still.</p>' +
            '<div style="position:relative;border-radius:12px;overflow:hidden;background:#111827;">' +
                '<video id="faceIdLoginVideo" autoplay playsinline muted style="width:100%;height:280px;object-fit:cover;display:block;"></video>' +
                '<canvas id="faceIdLoginCanvas" style="position:absolute;inset:0;width:100%;height:100%;"></canvas>' +
            '</div>' +
            '<div style="margin-top:12px;">' +
                '<div style="height:8px;background:#e5e7eb;border-radius:999px;overflow:hidden;">' +
                    '<div id="faceIdLoginProgressBar" style="height:100%;width:0%;background:linear-gradient(90deg,#0f766e,#14b8a6);"></div>' +
                '</div>' +
                '<div id="faceIdLoginProgressText" style="margin-top:8px;font-size:0.82rem;color:#374151;">Initializing...</div>' +
            '</div>' +
            '<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">' +
                '<button type="button" class="btn btn-outline btn-sm" onclick="closeFaceIdLoginModal()">Cancel</button>' +
            '</div>' +
        '</div>';

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            closeFaceIdLoginModal();
        }
    });

    document.body.appendChild(overlay);
    return overlay;
}

async function authLoadFaceApi() {
    if (authFaceApiLoaded) return true;
    if (window.faceapi) {
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(AUTH_FACE_MODELS_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(AUTH_FACE_MODELS_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(AUTH_FACE_MODELS_URL)
        ]);
        authFaceApiLoaded = true;
        return true;
    }

    await new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = AUTH_FACE_API_CDN;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });

    await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(AUTH_FACE_MODELS_URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(AUTH_FACE_MODELS_URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(AUTH_FACE_MODELS_URL)
    ]);

    authFaceApiLoaded = true;
    return true;
}

function updateFaceIdLoginProgress(pct, text) {
    const bar = document.getElementById('faceIdLoginProgressBar');
    const label = document.getElementById('faceIdLoginProgressText');
    if (bar) bar.style.width = Math.max(0, Math.min(100, pct)) + '%';
    if (label) label.textContent = text;
}

function stopFaceIdLoginCamera() {
    if (authFaceDetectionTimer) {
        clearInterval(authFaceDetectionTimer);
        authFaceDetectionTimer = null;
    }
    if (authFaceStream) {
        authFaceStream.getTracks().forEach(track => track.stop());
        authFaceStream = null;
    }
}

function closeFaceIdLoginModal() {
    const overlay = document.getElementById('faceIdLoginOverlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
    stopFaceIdLoginCamera();
}

async function showFaceIdLogin() {
    const overlay = ensureFaceIdLoginModal();
    overlay.style.display = 'flex';
    authFaceDescriptors = [];
    updateFaceIdLoginProgress(0, 'Loading face detection models...');

    try {
        await authLoadFaceApi();
    } catch (error) {
        console.error('Face API load failed:', error);
        updateFaceIdLoginProgress(0, 'Unable to load Face ID engine. Please try again later.');
        showLoginStatus('Face ID is temporarily unavailable. Please use password login.', 'error');
        return;
    }

    try {
        authFaceStream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
        });
    } catch (error) {
        console.error('Camera access failed:', error);
        updateFaceIdLoginProgress(0, 'Camera permission denied.');
        showLoginStatus('Please allow camera access to use Face ID.', 'error');
        return;
    }

    const video = document.getElementById('faceIdLoginVideo');
    const canvas = document.getElementById('faceIdLoginCanvas');
    const ctx = canvas ? canvas.getContext('2d') : null;

    if (!video || !canvas || !ctx) {
        showLoginStatus('Face ID UI could not be initialized.', 'error');
        closeFaceIdLoginModal();
        return;
    }

    video.srcObject = authFaceStream;
    await video.play();
    updateFaceIdLoginProgress(0, 'Looking for your face...');

    authFaceDetectionTimer = setInterval(async () => {
        if (!video.videoWidth) return;

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        try {
            const detection = await faceapi
                .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ scoreThreshold: 0.5 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection) {
                updateFaceIdLoginProgress(0, 'Looking for your face...');
                return;
            }

            const box = detection.detection.box;
            ctx.strokeStyle = '#22c55e';
            ctx.lineWidth = 3;
            ctx.strokeRect(box.x, box.y, box.width, box.height);

            authFaceDescriptors.push(Array.from(detection.descriptor));
            const progress = Math.min((authFaceDescriptors.length / AUTH_FACE_REQUIRED_SCANS) * 100, 100);
            updateFaceIdLoginProgress(progress, 'Scanning... ' + Math.min(authFaceDescriptors.length, AUTH_FACE_REQUIRED_SCANS) + '/' + AUTH_FACE_REQUIRED_SCANS);

            if (authFaceDescriptors.length < AUTH_FACE_REQUIRED_SCANS) {
                return;
            }

            clearInterval(authFaceDetectionTimer);
            authFaceDetectionTimer = null;
            updateFaceIdLoginProgress(100, 'Authenticating...');

            const avgDescriptor = new Array(128).fill(0);
            authFaceDescriptors.forEach(descriptor => {
                for (let i = 0; i < 128; i++) avgDescriptor[i] += descriptor[i];
            });
            for (let i = 0; i < 128; i++) avgDescriptor[i] /= authFaceDescriptors.length;

            const res = await fetch(AUTH_FACEID_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'faceid-login', face_descriptor: avgDescriptor })
            });
            const data = await res.json();

            if (!data.success) {
                updateFaceIdLoginProgress(0, data.message || 'Face not recognized. Please try again.');
                showLoginStatus(data.message || 'Face ID login failed.', 'error');
                stopFaceIdLoginCamera();
                return;
            }

            closeFaceIdLoginModal();
            showLoginStatus('✅ ' + (data.message || 'Face ID login successful!'), 'success');
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } catch (error) {
            console.error('Face ID login error:', error);
            showLoginStatus('Face ID login failed. Please try password login.', 'error');
            closeFaceIdLoginModal();
        }
    }, 450);
}

function openForgotPassword() {
    window.location.href = '/password-change.php';
}

// ============================================
// REGISTER MODAL FUNCTIONS
// ============================================

let regOtpResendTimer = 0;
let regUsernameCheckTimer = null;
let regUsernameRequestSeq = 0;

function showRegisterModal() {
    document.getElementById('registerModalOverlay').style.display = 'flex';
    setDOBDateRange();
    regGoToStep(1);
}

function closeRegisterModal() {
    document.getElementById('registerModalOverlay').style.display = 'none';
    resetRegisterForm();
}

function resetRegisterForm() {
    document.getElementById('regUsername').value = '';
    document.getElementById('regEmail').value = '';
    document.getElementById('regPhone').value = '';
    document.getElementById('regDOB').value = '';
    document.getElementById('regPassword').value = '';
    document.getElementById('regConfirmPassword').value = '';
    document.getElementById('regUsernameError').style.display = 'none';
    document.getElementById('regEmailError').style.display = 'none';
    document.getElementById('regPhoneError').style.display = 'none';
    document.getElementById('regDOBError').style.display = 'none';
    const passError = document.getElementById('regPasswordError');
    const confirmError = document.getElementById('regConfirmPasswordError');
    if (passError) passError.style.display = 'none';
    if (confirmError) confirmError.style.display = 'none';
    setDOBDateRange();
}

function switchAuthModal(mode) {
    if (mode === 'login') {
        closeRegisterModal();
        showLoginModal();
    } else if (mode === 'register') {
        closeLoginModal();
        showRegisterModal();
    }
}

function regGoToStep(stepNum) {
    // Hide all steps
    for (let i = 1; i <= 3; i++) {
        document.getElementById('regStep' + i).style.display = 'none';
    }

    // Show selected step
    document.getElementById('regStep' + stepNum).style.display = 'block';

    // Update progress dots
    document.querySelectorAll('.step-dot').forEach(dot => {
        const step = parseInt(dot.getAttribute('data-step'));
        if (step <= stepNum) {
            dot.style.background = '#0f766e';
            dot.style.color = 'white';
        } else {
            dot.style.background = '#e0e0e0';
            dot.style.color = '#999';
        }
    });

    // Step 2 specific: create OTP inputs
    if (stepNum === 2) {
        document.getElementById('regOtpEmail').textContent = document.getElementById('regEmail').value;
        createOtpInputs();
    }
}

// Real-time validation functions
function setDOBDateRange() {
    const dobInput = document.getElementById('regDOB');
    const today = new Date();
    
    // Maximum date: today (must be at least 18 today)
    // Minimum date: 100 years ago
    const maxDate = today.toISOString().split('T')[0]; // Today
    
    const minDate = new Date(today);
    minDate.setFullYear(today.getFullYear() - 100);
    const minDateStr = minDate.toISOString().split('T')[0]; // 100 years ago
    
    dobInput.max = maxDate;
    dobInput.min = minDateStr;
}

async function checkRegUsernameAvailability(username, requestId) {
    const errorDiv = document.getElementById('regUsernameError');

    try {
        const formData = new FormData();
        formData.append('action', 'check-username');
        formData.append('username', username);

        const response = await fetch('/api/register.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        // Ignore stale responses while user keeps typing.
        if (requestId !== regUsernameRequestSeq) {
            return true;
        }

        if (!result.success) {
            errorDiv.textContent = result.message || 'Username already taken';
            errorDiv.style.display = 'block';
            return false;
        }

        errorDiv.style.display = 'none';
        return true;
    } catch (error) {
        console.error('Username validation error:', error);
        return false;
    }
}

async function validateRegUsername(immediate = false) {
    const username = document.getElementById('regUsername').value.trim();
    const errorDiv = document.getElementById('regUsernameError');
    
    if (!username) {
        errorDiv.style.display = 'none';
        return false;
    }

    if (username.length < 3) {
        errorDiv.textContent = 'Username must be at least 3 characters';
        errorDiv.style.display = 'block';
        return false;
    }

    // For click Next, run DB check immediately.
    if (immediate) {
        regUsernameRequestSeq += 1;
        return checkRegUsernameAvailability(username, regUsernameRequestSeq);
    }

    // Debounce DB calls while typing.
    if (regUsernameCheckTimer) {
        clearTimeout(regUsernameCheckTimer);
    }
    regUsernameCheckTimer = setTimeout(() => {
        regUsernameRequestSeq += 1;
        checkRegUsernameAvailability(username, regUsernameRequestSeq);
    }, 300);

    return true;
}

function validateRegPasswords() {
    const password = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('regConfirmPassword').value;
    const passError = document.getElementById('regPasswordError');
    const confirmError = document.getElementById('regConfirmPasswordError');

    if (passError) {
        if (!password) {
            passError.style.display = 'none';
        } else if (password.length < 6) {
            passError.textContent = 'Password must be at least 6 characters';
            passError.style.display = 'block';
        } else {
            passError.style.display = 'none';
        }
    }

    if (confirmError) {
        if (!confirmPassword) {
            confirmError.style.display = 'none';
        } else if (password !== confirmPassword) {
            confirmError.textContent = 'Passwords do not match';
            confirmError.style.display = 'block';
        } else {
            confirmError.style.display = 'none';
        }
    }

    return password.length >= 6 && confirmPassword.length > 0 && password === confirmPassword;
}

async function validateRegEmail() {
    const email = document.getElementById('regEmail').value.trim();
    const errorDiv = document.getElementById('regEmailError');
    
    if (!email) {
        errorDiv.style.display = 'none';
        return;
    }

    if (!email.includes('@')) {
        errorDiv.textContent = 'Invalid email format';
        errorDiv.style.display = 'block';
        return;
    }

    // Check if email exists
    try {
        const formData = new FormData();
        formData.append('action', 'check-email');
        formData.append('email', email);

        const response = await fetch('/api/register.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            errorDiv.textContent = result.message || 'Email already registered';
            errorDiv.style.display = 'block';
        } else {
            errorDiv.style.display = 'none';
        }
    } catch (error) {
        console.error('Email validation error:', error);
    }
}

async function validateRegPhone() {
    const phone = document.getElementById('regPhone').value.trim();
    const errorDiv = document.getElementById('regPhoneError');
    
    if (!phone) {
        errorDiv.style.display = 'none';
        return;
    }

    // Basic phone validation: at least 10 digits
    const digitsOnly = phone.replace(/\D/g, '');
    if (digitsOnly.length < 10) {
        errorDiv.textContent = 'Phone number must have at least 10 digits';
        errorDiv.style.display = 'block';
        return;
    }

    // Check if phone exists
    try {
        const formData = new FormData();
        formData.append('action', 'check-phone');
        formData.append('phone', phone);

        const response = await fetch('/api/register.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            errorDiv.textContent = result.message || 'Phone already registered';
            errorDiv.style.display = 'block';
        } else {
            errorDiv.style.display = 'none';
        }
    } catch (error) {
        console.error('Phone validation error:', error);
    }
}

function validateRegAge() {
    const dob = document.getElementById('regDOB').value.trim();
    let errorDiv = document.getElementById('regDOBError');
    
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = 'regDOBError';
        errorDiv.style.color = '#dc2626';
        errorDiv.style.fontSize = '12px';
        errorDiv.style.marginTop = '4px';
        document.getElementById('regDOB').parentNode.appendChild(errorDiv);
    }
    
    if (!dob) {
        errorDiv.style.display = 'none';
        return;
    }

    const dobDate = new Date(dob + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    let age = today.getFullYear() - dobDate.getFullYear();
    const monthDiff = today.getMonth() - dobDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
        age--;
    }

    // Check age constraints
    if (age < 18) {
        errorDiv.textContent = 'You must be at least 18 years old';
        errorDiv.style.display = 'block';
    } else if (age > 100) {
        errorDiv.textContent = 'Age cannot exceed 100 years';
        errorDiv.style.display = 'block';
    } else {
        errorDiv.style.display = 'none';
    }
}

async function regGoToStep2() {
    const username = document.getElementById('regUsername').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const phone = document.getElementById('regPhone').value.trim();
    const dob = document.getElementById('regDOB').value.trim();
    const password = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('regConfirmPassword').value;
    const statusDiv = document.getElementById('regStep1Status');

    if (!username || !email || !phone || !dob || !password || !confirmPassword) {
        showRegStatus('Please fill in all fields', 'error', 1);
        return;
    }

    if (username.length < 3) {
        showRegStatus('Username must be at least 3 characters', 'error', 1);
        return;
    }

    const usernameAvailable = await validateRegUsername(true);
    if (!usernameAvailable) {
        showRegStatus('Username already taken. Please choose another one.', 'error', 1);
        return;
    }

    if (!email.includes('@')) {
        showRegStatus('Invalid email format', 'error', 1);
        return;
    }

    const phoneDigits = phone.replace(/\D/g, '');
    if (phoneDigits.length < 10) {
        showRegStatus('Phone number must have at least 10 digits', 'error', 1);
        return;
    }

    if (password.length < 6) {
        showRegStatus('Password must be at least 6 characters', 'error', 1);
        return;
    }

    if (!validateRegPasswords()) {
        showRegStatus('Passwords do not match', 'error', 1);
        return;
    }

    // Check age (18+) - convert date string to proper date object
    const dobDate = new Date(dob + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    let age = today.getFullYear() - dobDate.getFullYear();
    const monthDiff = today.getMonth() - dobDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
        age--;
    }

    if (age < 18) {
        showRegStatus('You must be at least 18 years old', 'error', 1);
        return;
    }

    if (age > 100) {
        showRegStatus('Age cannot exceed 100 years', 'error', 1);
        return;
    }

    // Send OTP
    showRegStatus('Sending verification code...', 'loading', 1);

    try {
        const formData = new FormData();
        formData.append('action', 'send-otp');
        formData.append('username', username);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('dob', dob);
        formData.append('password', password);

        const response = await fetch('/api/register.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            // Hide status and move to step 2
            document.getElementById('regStep1Status').style.display = 'none';
            regGoToStep(2);
            startOtpCountdown();
        } else {
            showRegStatus('✗ ' + (result.message || 'Failed to send OTP'), 'error', 1);
        }
    } catch (error) {
        console.error('OTP send error:', error);
        showRegStatus('✗ Network error. Please try again.', 'error', 1);
    }
}

function createOtpInputs() {
    const container = document.getElementById('otpInputContainer');
    container.innerHTML = '';

    for (let i = 0; i < 6; i++) {
        const input = document.createElement('input');
        input.type = 'text';
        input.maxLength = '1';
        input.className = 'otp-input';
        input.id = 'otp' + i;
        input.inputMode = 'numeric';
        input.style.width = '50px';
        input.style.height = '50px';
        input.style.fontSize = '24px';
        input.style.textAlign = 'center';
        input.style.border = '2px solid #ddd';
        input.style.borderRadius = '8px';
        input.style.fontWeight = '600';
        input.style.transition = 'all 0.3s ease';

        input.addEventListener('input', (e) => {
            if (e.target.value && i < 5) {
                document.getElementById('otp' + (i + 1)).focus();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && i > 0) {
                document.getElementById('otp' + (i - 1)).focus();
            }
        });

        container.appendChild(input);
    }

    document.getElementById('otp0').focus();
}

async function regVerifyOtp() {
    const email = document.getElementById('regEmail').value;
    let otpCode = '';

    for (let i = 0; i < 6; i++) {
        otpCode += document.getElementById('otp' + i).value;
    }

    if (otpCode.length !== 6) {
        showRegStatus('Please enter all 6 digits', 'error', 2);
        return;
    }

    showRegStatus('Verifying code...', 'loading', 2);

    try {
        const formData = new FormData();
        formData.append('action', 'verify-otp');
        formData.append('email', email);
        formData.append('otp', otpCode);

        const response = await fetch('/api/register.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            document.getElementById('regStep2Status').style.display = 'none';
            regGoToStep(3);

            // Auto-redirect after successful account creation.
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showRegStatus('✗ ' + (result.message || 'Invalid code'), 'error', 2);
        }
    } catch (error) {
        console.error('OTP verify error:', error);
        showRegStatus('✗ Network error. Please try again.', 'error', 2);
    }
}

function regResendOtp() {
    if (regOtpResendTimer > 0) return;

    const email = document.getElementById('regEmail').value;
    const btn = document.getElementById('resendOtpBtn');
    btn.disabled = true;
    btn.style.opacity = '0.5';

    const formData = new FormData();
    formData.append('action', 'resend-otp');
    formData.append('email', email);

    fetch('/api/register.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            startOtpCountdown();
        }
    })
    .catch(err => console.error('Resend error:', err));
}

function startOtpCountdown() {
    regOtpResendTimer = 60;
    const countdownEl = document.getElementById('otpCountdown');
    const btn = document.getElementById('resendOtpBtn');

    const timer = setInterval(() => {
        regOtpResendTimer--;
        if (regOtpResendTimer > 0) {
            countdownEl.textContent = 'Resend in ' + regOtpResendTimer + 's';
        } else {
            clearInterval(timer);
            countdownEl.textContent = '';
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    }, 1000);
}

function toggleRegPassword(fieldId) {
    const field = document.getElementById(fieldId);
    const toggle = document.getElementById(fieldId === 'regPassword' ? 'regPasswordToggle1' : 'regPasswordToggle2');

    if (field.type === 'password') {
        field.type = 'text';
        toggle.textContent = 'Hide';
    } else {
        field.type = 'password';
        toggle.textContent = 'Show';
    }
}

function showRegStatus(message, type = 'info', step = 1) {
    const statusDiv = document.getElementById('regStep' + step + 'Status');
    statusDiv.style.display = 'block';
    statusDiv.textContent = message;

    if (type === 'error') {
        statusDiv.style.background = '#fee2e2';
        statusDiv.style.color = '#dc2626';
    } else if (type === 'success') {
        statusDiv.style.background = '#dcfce7';
        statusDiv.style.color = '#16a34a';
    } else if (type === 'loading') {
        statusDiv.style.background = '#dbeafe';
        statusDiv.style.color = '#0284c7';
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    // Close modals on overlay click
    document.getElementById('loginModalOverlay')?.addEventListener('click', (e) => {
        if (e.target.id === 'loginModalOverlay') {
            closeLoginModal();
        }
    });

    document.getElementById('registerModalOverlay')?.addEventListener('click', (e) => {
        if (e.target.id === 'registerModalOverlay') {
            closeRegisterModal();
        }
    });

    // Prevent modal close when clicking inside
    document.querySelector('#loginModalOverlay .modal')?.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    document.querySelector('#registerModalOverlay .modal')?.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    // Login form: PHP handles auth logic, JS only updates visual state
    document.getElementById('loginForm')?.addEventListener('submit', (e) => {
        const identifier = document.getElementById('loginIdentifier')?.value.trim() || '';
        const password = document.getElementById('loginPassword')?.value || '';

        if (!identifier || !password) {
            e.preventDefault();
            showLoginStatus('Please fill in all fields', 'error');
            return;
        }

        showLoginStatus('Signing in...', 'loading');
    });
});
