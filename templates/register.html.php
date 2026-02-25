<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container { width: 100%; max-width: 520px; }

        .brand { text-align: center; margin-bottom: 24px; }
        .brand a { color: white; text-decoration: none; font-size: 1.75rem; font-weight: 800; }

        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 28px;
            text-align: center;
        }
        .card-header h1 { font-size: 1.5rem; margin-bottom: 6px; font-weight: 800; }
        .card-header p { opacity: 0.85; font-size: 0.875rem; }
        .card-body { padding: 28px; }

        /* Step Indicator */
        .steps {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 28px;
        }
        .step-dot {
            width: 12px; height: 12px;
            border-radius: 50%;
            background: #e2e8f0;
            transition: all 0.3s;
        }
        .step-dot.active { background: #2563eb; transform: scale(1.2); }
        .step-dot.done { background: #22c55e; }
        .step-label {
            display: flex;
            justify-content: center;
            gap: 32px;
            margin-bottom: 24px;
            font-size: 0.75rem;
            color: #94a3b8;
        }
        .step-label span { transition: color 0.3s; }
        .step-label span.active { color: #2563eb; font-weight: 600; }
        .step-label span.done { color: #22c55e; }

        /* Form Elements */
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #1e293b;
            font-size: 0.875rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.875rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.3s;
            background: #fff;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* Password wrapper */
        .password-wrapper {
            position: relative;
        }
        .password-wrapper input {
            padding-right: 48px;
        }
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            color: #94a3b8;
            padding: 4px;
        }
        .toggle-password:hover { color: #2563eb; }

        .password-hint {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* OTP Input */
        .otp-container {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 24px 0;
        }
        .otp-input {
            width: 48px; height: 56px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
        }
        .otp-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Role Selection */
        .role-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 20px 0; }
        .role-card {
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .role-card:hover { border-color: #2563eb; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        .role-card.selected { border-color: #2563eb; background: #eff6ff; box-shadow: 0 4px 12px rgba(37,99,235,0.2); }
        .role-icon { font-size: 2.5rem; margin-bottom: 12px; }
        .role-title { font-weight: 700; color: #1e293b; margin-bottom: 4px; font-size: 1rem; }
        .role-desc { color: #64748b; font-size: 0.75rem; line-height: 1.5; }
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.688rem;
            font-weight: 600;
            margin-top: 8px;
        }
        .role-card:first-child .role-badge { background: #dbeafe; color: #1d4ed8; }
        .role-card:last-child .role-badge { background: #dcfce7; color: #166534; }

        /* Buttons */
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 10px;
            font-family: 'Inter', sans-serif;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(37, 99, 235, 0.4); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-outline {
            background: transparent;
            color: #2563eb;
            border: 2px solid #2563eb;
        }
        .btn-outline:hover { background: #eff6ff; }

        /* Status Bar */
        .status-bar {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.875rem;
            text-align: center;
            font-weight: 500;
            display: none;
        }
        .status-bar.show { display: block; }
        .status-loading { background: #fef3c7; color: #92400e; }
        .status-success { background: #dcfce7; color: #166534; }
        .status-error { background: #fee2e2; color: #991b1b; }
        .status-info { background: #dbeafe; color: #1e40af; }

        /* Links */
        .links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
            font-size: 0.875rem;
            color: #64748b;
        }
        .links a { color: #2563eb; text-decoration: none; font-weight: 600; }
        .links a:hover { text-decoration: underline; }

        /* Timer */
        .otp-timer {
            text-align: center;
            color: #64748b;
            font-size: 0.813rem;
            margin-bottom: 16px;
        }
        .otp-timer strong { color: #2563eb; }
        .resend-link {
            color: #2563eb;
            cursor: pointer;
            font-weight: 600;
            background: none;
            border: none;
            font-family: 'Inter', sans-serif;
            font-size: 0.813rem;
        }
        .resend-link:disabled { color: #94a3b8; cursor: not-allowed; }

        /* Dev OTP display */
        .dev-otp {
            background: #fef3c7;
            border: 2px dashed #f59e0b;
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            margin-bottom: 16px;
            font-size: 0.813rem;
            color: #92400e;
        }
        .dev-otp strong {
            font-size: 1.25rem;
            color: #d97706;
            letter-spacing: 4px;
        }

        .spinner {
            display: inline-block;
            width: 18px; height: 18px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .hidden { display: none !important; }

        /* Success animation */
        .success-icon {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 20px;
            animation: popIn 0.5s ease;
            color: white;
        }
        @keyframes popIn {
            0% { transform: scale(0); }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="brand"><a href="index.php">🚗 DriveNow</a></div>
        <div class="card">
            <div class="card-header">
                <h1>📝 Create Your Account</h1>
                <p>Join DriveNow — Rent or List your car today</p>
            </div>

            <div class="card-body">
                <!-- Step Indicator -->
                <div class="steps">
                    <div class="step-dot active" id="dot1"></div>
                    <div class="step-dot" id="dot2"></div>
                    <div class="step-dot" id="dot3"></div>
                </div>
                <div class="step-label">
                    <span class="active" id="label1">Info</span>
                    <span id="label2">Verify</span>
                    <span id="label3">Role</span>
                </div>

                <!-- Status Bar -->
                <div id="statusBar" class="status-bar"></div>

                <!-- ===== STEP 1: Personal Info + Password ===== -->
                <div id="step1">
                    <div class="form-group">
                        <label>👤 Full Name *</label>
                        <input type="text" id="fullName" placeholder="Enter your full name" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>📧 Email *</label>
                            <input type="email" id="email" placeholder="your@email.com" required>
                        </div>
                        <div class="form-group">
                            <label>📱 Phone *</label>
                            <input type="tel" id="phone" placeholder="+84 xxx xxx xxx" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>🎂 Date of Birth *</label>
                            <input type="date" id="dob" required>
                        </div>
                        <div class="form-group">
                            <label>📍 Address</label>
                            <input type="text" id="address" placeholder="City, Country">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>🔒 Password *</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" placeholder="Create a password" required autocomplete="new-password">
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">👁️</button>
                        </div>
                        <div class="password-hint">At least 6 characters</div>
                    </div>

                    <div class="form-group">
                        <label>🔒 Confirm Password *</label>
                        <div class="password-wrapper">
                            <input type="password" id="confirmPassword" placeholder="Confirm your password" required autocomplete="new-password">
                            <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword')">👁️</button>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary" onclick="goToStep2()">
                        Continue → Verify Email
                    </button>
                </div>

                <!-- ===== STEP 2: Email OTP Verification ===== -->
                <div id="step2" class="hidden">
                    <div style="text-align:center;margin-bottom:20px;">
                        <div style="font-size:3rem;margin-bottom:12px;">📧</div>
                        <h3 style="color:#1e293b;margin-bottom:8px;">Check Your Email</h3>
                        <p style="color:#64748b;font-size:0.875rem;">
                            We've sent a 6-digit code to<br>
                            <strong id="verifyEmail" style="color:#2563eb;"></strong>
                        </p>
                    </div>

                    <!-- Dev OTP Display (only in development) -->
                    <div id="devOtpBox" class="dev-otp hidden">
                        🛠️ Dev Mode — Your code: <strong id="devOtpCode"></strong>
                    </div>

                    <div class="otp-container">
                        <input type="text" class="otp-input" maxlength="1" id="otp1" oninput="otpInput(this, 'otp2')" onkeydown="otpKeydown(event, this, null)">
                        <input type="text" class="otp-input" maxlength="1" id="otp2" oninput="otpInput(this, 'otp3')" onkeydown="otpKeydown(event, this, 'otp1')">
                        <input type="text" class="otp-input" maxlength="1" id="otp3" oninput="otpInput(this, 'otp4')" onkeydown="otpKeydown(event, this, 'otp2')">
                        <input type="text" class="otp-input" maxlength="1" id="otp4" oninput="otpInput(this, 'otp5')" onkeydown="otpKeydown(event, this, 'otp3')">
                        <input type="text" class="otp-input" maxlength="1" id="otp5" oninput="otpInput(this, 'otp6')" onkeydown="otpKeydown(event, this, 'otp4')">
                        <input type="text" class="otp-input" maxlength="1" id="otp6" oninput="otpInput(this, null)" onkeydown="otpKeydown(event, this, 'otp5')">
                    </div>

                    <div class="otp-timer" id="otpTimer">
                        Code expires in <strong id="countdown">5:00</strong>
                    </div>

                    <button type="button" class="btn btn-primary" id="verifyBtn" onclick="verifyOtp()">
                        ✅ Verify Email
                    </button>

                    <div style="text-align:center;margin-top:12px;">
                        <button class="resend-link" id="resendBtn" onclick="resendOtp()" disabled>
                            Resend Code
                        </button>
                        <span style="color:#94a3b8;font-size:0.75rem;" id="resendTimer"> (wait 30s)</span>
                    </div>

                    <button type="button" class="btn btn-outline" style="margin-top:12px;" onclick="backToStep1()">
                        ← Back to Edit Info
                    </button>
                </div>

                <!-- ===== STEP 3: Choose Role ===== -->
                <div id="step3" class="hidden">
                    <div style="text-align:center;margin-bottom:20px;">
                        <div style="font-size:3rem;margin-bottom:12px;">🎯</div>
                        <h3 style="color:#1e293b;margin-bottom:8px;">How Will You Use DriveNow?</h3>
                        <p style="color:#64748b;font-size:0.875rem;">Choose your primary role. You can change this later.</p>
                    </div>

                    <div class="role-grid">
                        <div class="role-card" id="roleRenter" onclick="selectRole('renter')">
                            <div class="role-icon">🚗</div>
                            <div class="role-title">Renter</div>
                            <div class="role-desc">I want to rent cars for my trips and daily commute</div>
                            <span class="role-badge">Rent a Car</span>
                        </div>
                        <div class="role-card" id="roleOwner" onclick="selectRole('owner')">
                            <div class="role-icon">🏠</div>
                            <div class="role-title">Car Owner</div>
                            <div class="role-desc">I want to list my car and earn money from rentals</div>
                            <span class="role-badge">Earn Money</span>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary" id="createBtn" onclick="createAccount()" disabled>
                        🚀 Create My Account
                    </button>
                </div>

                <!-- ===== STEP 4: Success ===== -->
                <div id="step4" class="hidden">
                    <div style="text-align:center;padding:20px 0;">
                        <div class="success-icon">✓</div>
                        <h2 style="color:#1e293b;margin-bottom:8px;">Welcome to DriveNow! 🎉</h2>
                        <p style="color:#64748b;margin-bottom:24px;" id="successMsg">Your account has been created successfully.</p>
                        <a href="index.php" class="btn btn-primary" style="display:inline-block;width:auto;padding:14px 40px;">
                            🏠 Go to Homepage
                        </a>
                    </div>
                </div>

                <!-- Links -->
                <div class="links" id="regLinks">
                    Already have an account? <a href="login.php">Sign In</a>
                    <br><br>
                    <a href="index.php">← Back to Home</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ========== STATE ==========
        let currentStep = 1;
        let selectedRole = '';
        let emailVerified = false;
        let countdownInterval = null;
        let resendInterval = null;

        // ========== TOGGLE PASSWORD ==========
        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            const btn = input.nextElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
            } else {
                input.type = 'password';
                btn.textContent = '👁️';
            }
        }

        // ========== STEP NAVIGATION ==========
        function setStep(step) {
            currentStep = step;
            for (let i = 1; i <= 4; i++) {
                const el = document.getElementById('step' + i);
                if (el) el.classList.toggle('hidden', i !== step);
            }
            // Update dots
            for (let i = 1; i <= 3; i++) {
                const dot = document.getElementById('dot' + i);
                const label = document.getElementById('label' + i);
                dot.className = 'step-dot';
                label.className = '';
                if (i < step) { dot.classList.add('done'); label.classList.add('done'); }
                else if (i === step && step <= 3) { dot.classList.add('active'); label.classList.add('active'); }
            }
            // Hide links on success
            if (step === 4) document.getElementById('regLinks').classList.add('hidden');
        }

        // ========== STEP 1 → 2: Validate & Send OTP ==========
        async function goToStep2() {
            const fullName = document.getElementById('fullName').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const dob = document.getElementById('dob').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            // Validation
            if (!fullName) return showStatus('error', 'Please enter your full name.');
            if (!email || !email.includes('@')) return showStatus('error', 'Please enter a valid email address.');
            if (!phone) return showStatus('error', 'Please enter your phone number.');
            if (!dob) return showStatus('error', 'Please select your date of birth.');
            if (!password || password.length < 6) return showStatus('error', 'Password must be at least 6 characters.');
            if (password !== confirmPassword) return showStatus('error', 'Passwords do not match.');

            showStatus('loading', '<span class="spinner"></span> Sending verification code...');

            try {
                const res = await fetch('/api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'email-send-otp', email: email })
                });
                const data = await res.json();

                if (data.success) {
                    hideStatus();
                    document.getElementById('verifyEmail').textContent = email;
                    setStep(2);

                    // Show dev OTP if available
                    if (data.dev_otp) {
                        document.getElementById('devOtpBox').classList.remove('hidden');
                        document.getElementById('devOtpCode').textContent = data.dev_otp;
                    }

                    startCountdown();
                    startResendTimer();
                    document.getElementById('otp1').focus();
                } else {
                    showStatus('error', data.message);
                }
            } catch (err) {
                showStatus('error', 'Connection error: ' + err.message);
            }
        }

        // ========== OTP INPUT HELPERS ==========
        function otpInput(el, nextId) {
            el.value = el.value.replace(/[^0-9]/g, '');
            if (el.value && nextId) {
                document.getElementById(nextId).focus();
            }
            // Auto-verify when all 6 digits entered
            if (!nextId && el.value) {
                const otp = getOtpValue();
                if (otp.length === 6) verifyOtp();
            }
        }

        function otpKeydown(e, el, prevId) {
            if (e.key === 'Backspace' && !el.value && prevId) {
                document.getElementById(prevId).focus();
            }
        }

        function getOtpValue() {
            let otp = '';
            for (let i = 1; i <= 6; i++) {
                otp += document.getElementById('otp' + i).value;
            }
            return otp;
        }

        // ========== STEP 2: Verify OTP ==========
        async function verifyOtp() {
            const otp = getOtpValue();
            if (otp.length !== 6) return showStatus('error', 'Please enter the complete 6-digit code.');

            const email = document.getElementById('email').value.trim();
            showStatus('loading', '<span class="spinner"></span> Verifying...');
            document.getElementById('verifyBtn').disabled = true;

            try {
                const res = await fetch('/api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'email-verify-otp', email: email, otp: otp })
                });
                const data = await res.json();

                if (data.success) {
                    emailVerified = true;
                    hideStatus();
                    clearInterval(countdownInterval);
                    setStep(3);
                } else {
                    showStatus('error', data.message);
                    document.getElementById('verifyBtn').disabled = false;
                    // Clear OTP inputs
                    for (let i = 1; i <= 6; i++) document.getElementById('otp' + i).value = '';
                    document.getElementById('otp1').focus();
                }
            } catch (err) {
                showStatus('error', 'Connection error: ' + err.message);
                document.getElementById('verifyBtn').disabled = false;
            }
        }

        // ========== RESEND OTP ==========
        async function resendOtp() {
            const email = document.getElementById('email').value.trim();
            showStatus('loading', '<span class="spinner"></span> Resending code...');

            try {
                const res = await fetch('/api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'email-send-otp', email: email })
                });
                const data = await res.json();

                if (data.success) {
                    showStatus('success', '✅ New code sent!');
                    if (data.dev_otp) {
                        document.getElementById('devOtpBox').classList.remove('hidden');
                        document.getElementById('devOtpCode').textContent = data.dev_otp;
                    }
                    // Reset inputs & timers
                    for (let i = 1; i <= 6; i++) document.getElementById('otp' + i).value = '';
                    document.getElementById('otp1').focus();
                    startCountdown();
                    startResendTimer();
                    setTimeout(hideStatus, 2000);
                } else {
                    showStatus('error', data.message);
                }
            } catch (err) {
                showStatus('error', 'Connection error: ' + err.message);
            }
        }

        // ========== STEP 3: Select Role ==========
        function selectRole(role) {
            selectedRole = role;
            document.getElementById('roleRenter').classList.toggle('selected', role === 'renter');
            document.getElementById('roleOwner').classList.toggle('selected', role === 'owner');
            document.getElementById('createBtn').disabled = false;
        }

        // ========== STEP 3 → 4: Create Account ==========
        async function createAccount() {
            if (!selectedRole) return showStatus('error', 'Please select a role.');
            if (!emailVerified) return showStatus('error', 'Email not verified. Please go back and verify.');

            showStatus('loading', '<span class="spinner"></span> Creating your account...');
            document.getElementById('createBtn').disabled = true;

            const payload = {
                action: 'register',
                full_name: document.getElementById('fullName').value.trim(),
                email: document.getElementById('email').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                date_of_birth: document.getElementById('dob').value,
                address: document.getElementById('address').value.trim(),
                password: document.getElementById('password').value,
                role: selectedRole
            };

            try {
                const res = await fetch('/api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    hideStatus();
                    const roleName = selectedRole === 'owner' ? 'Car Owner' : 'Renter';
                    document.getElementById('successMsg').innerHTML =
                        `Welcome, <strong>${data.user.full_name}</strong>! You're registered as a <strong>${roleName}</strong>. Start exploring DriveNow now.`;
                    setStep(4);
                } else {
                    showStatus('error', data.message);
                    document.getElementById('createBtn').disabled = false;
                }
            } catch (err) {
                showStatus('error', 'Connection error: ' + err.message);
                document.getElementById('createBtn').disabled = false;
            }
        }

        // ========== BACK TO STEP 1 ==========
        function backToStep1() {
            clearInterval(countdownInterval);
            clearInterval(resendInterval);
            hideStatus();
            setStep(1);
        }

        // ========== TIMERS ==========
        function startCountdown() {
            let seconds = 300; // 5 minutes
            clearInterval(countdownInterval);
            countdownInterval = setInterval(() => {
                seconds--;
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                document.getElementById('countdown').textContent = m + ':' + String(s).padStart(2, '0');
                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                    document.getElementById('otpTimer').innerHTML = '<span style="color:#dc2626;">⏰ Code expired. Please resend.</span>';
                }
            }, 1000);
        }

        function startResendTimer() {
            let resendSec = 30;
            const btn = document.getElementById('resendBtn');
            const timer = document.getElementById('resendTimer');
            btn.disabled = true;
            clearInterval(resendInterval);
            resendInterval = setInterval(() => {
                resendSec--;
                timer.textContent = ` (wait ${resendSec}s)`;
                if (resendSec <= 0) {
                    clearInterval(resendInterval);
                    btn.disabled = false;
                    timer.textContent = '';
                }
            }, 1000);
        }

        // ========== STATUS HELPERS ==========
        function showStatus(type, message) {
            const bar = document.getElementById('statusBar');
            bar.className = 'status-bar status-' + type + ' show';
            bar.innerHTML = message;
        }

        function hideStatus() {
            document.getElementById('statusBar').classList.remove('show');
        }
    </script>
</body>
</html>
