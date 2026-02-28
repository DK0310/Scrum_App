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

        .container { width: 100%; max-width: 480px; }

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

        /* Form Elements */
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #1e293b;
            font-size: 0.875rem;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.938rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Password toggle */
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

        /* Welcome back info */
        .user-welcome {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            margin-bottom: 16px;
        }
        .user-welcome .name { font-size: 1.125rem; font-weight: 700; color: #1e293b; }
        .user-welcome .role-tag {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 6px;
        }
        .role-tag.renter { background: #dbeafe; color: #1d4ed8; }
        .role-tag.owner { background: #dcfce7; color: #166534; }

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

        @keyframes popIn {
            0% { transform: scale(0); }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        @keyframes pulse-ring { 0%{box-shadow:0 0 0 0 rgba(37,99,235,0.4)} 70%{box-shadow:0 0 0 14px rgba(37,99,235,0)} 100%{box-shadow:0 0 0 0 rgba(37,99,235,0)} }

        /* Face ID Styles */
        .divider {
            display: flex; align-items: center; gap: 12px; margin: 20px 0;
            font-size: 0.8rem; color: #94a3b8;
        }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }

        .btn-faceid {
            width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 12px;
            background: white; color: #1e293b; font-size: 0.95rem; font-weight: 600;
            cursor: pointer; transition: all 0.3s; display: flex; align-items: center;
            justify-content: center; gap: 8px; font-family: 'Inter', sans-serif;
        }
        .btn-faceid:hover { border-color: #2563eb; color: #2563eb; background: #eff6ff; transform: translateY(-1px); }
        .btn-faceid:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .faceid-camera-box {
            position: relative; width: 100%; max-width: 360px; margin: 12px auto;
            border-radius: 16px; overflow: hidden; background: #0f172a;
        }
        .faceid-camera-box video { width: 100%; border-radius: 16px; transform: scaleX(-1); }
        .faceid-camera-box canvas {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; transform: scaleX(-1);
        }
        .faceid-oval {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 180px; height: 230px; border: 3px dashed rgba(255,255,255,0.4);
            border-radius: 50%; pointer-events: none; transition: border-color 0.3s;
        }
        .faceid-oval.detected { border-color: #22c55e; border-style: solid; }
        .faceid-status-bar {
            position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%);
            background: rgba(0,0,0,0.7); color: white; padding: 6px 16px;
            border-radius: 50px; font-size: 0.75rem; font-weight: 600; white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="brand"><a href="index.php">🚗 DriveNow</a></div>
        <div class="card">
            <div class="card-header">
                <h1>🔐 Sign In</h1>
                <p>Enter your email or username and password</p>
            </div>

            <div class="card-body">
                <!-- Status Bar -->
                <div id="statusBar" class="status-bar"></div>

                <!-- ===== Login Form ===== -->
                <div id="loginForm">
                    <div style="text-align:center;margin-bottom:24px;">
                        <div style="font-size:3rem;margin-bottom:8px;">👋</div>
                        <p style="color:#64748b;font-size:0.875rem;">Welcome back! Sign in to your account.</p>
                    </div>

                    <div class="form-group">
                        <label>📧 Email or Username</label>
                        <input type="text" id="loginIdentifier" placeholder="your@email.com or your name" required autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label>🔒 Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="loginPassword" placeholder="Enter your password" required autocomplete="current-password">
                            <button type="button" class="toggle-password" onclick="togglePassword()">👁️</button>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary" id="loginBtn" onclick="doLogin()">
                        🔓 Sign In
                    </button>

                    <!-- Face ID Quick Login -->
                    <div class="divider">or</div>
                    <button type="button" class="btn-faceid" id="faceIdLoginBtn" onclick="startFaceIdLogin()">
                        🔐 Sign in with Face ID
                    </button>
                </div>

                <!-- ===== Face ID Camera ===== -->
                <div id="faceIdPanel" class="hidden">
                    <div style="text-align:center;margin-bottom:12px;">
                        <div style="font-size:2rem;margin-bottom:4px;">🔐</div>
                        <h3 style="color:#1e293b;font-weight:700;margin-bottom:4px;">Face ID Login</h3>
                        <p style="color:#94a3b8;font-size:0.8rem;">Look at the camera to sign in</p>
                    </div>
                    <div class="faceid-camera-box">
                        <video id="faceLoginVideo" autoplay muted playsinline></video>
                        <canvas id="faceLoginCanvas"></canvas>
                        <div class="faceid-oval" id="faceLoginOval"></div>
                        <div class="faceid-status-bar" id="faceLoginStatus">⏳ Loading...</div>
                    </div>
                    <div style="margin-top:12px;">
                        <div style="background:#f1f5f9;border-radius:50px;height:6px;overflow:hidden;">
                            <div id="faceLoginProgress" style="height:100%;width:0%;background:linear-gradient(90deg,#2563eb,#22c55e);border-radius:50px;transition:width 0.3s;"></div>
                        </div>
                        <small style="color:#94a3b8;display:block;margin-top:4px;text-align:center;" id="faceLoginProgressText">Initializing...</small>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="cancelFaceIdLogin()" style="margin-top:16px;background:#64748b;">
                        ← Back to Password Login
                    </button>
                </div>

                <!-- ===== Success (auto-redirect) ===== -->
                <div id="loginSuccess" class="hidden">
                    <div style="text-align:center;padding:20px 0;">
                        <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#22c55e,#16a34a);display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 20px;color:white;animation:popIn 0.5s ease;">✓</div>

                        <div class="user-welcome" id="welcomeBox" style="display:none;">
                            <div class="name" id="welcomeName"></div>
                            <div class="role-tag" id="welcomeRole"></div>
                        </div>

                        <h2 style="color:#1e293b;margin-bottom:8px;" id="welcomeMsg">Welcome back! 🎉</h2>
                        <p style="color:#64748b;margin-bottom:24px;">Redirecting to homepage...</p>
                    </div>
                </div>

                <!-- Links -->
                <div class="links" id="loginLinks">
                    Don't have an account? <a href="register.php">Sign Up</a>
                    <br><br>
                    <a href="index.php">← Back to Home</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Allow Enter key to submit
        document.getElementById('loginIdentifier').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') document.getElementById('loginPassword').focus();
        });
        document.getElementById('loginPassword').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') doLogin();
        });

        // ========== Toggle Password Visibility ==========
        function togglePassword() {
            const input = document.getElementById('loginPassword');
            const btn = input.nextElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
            } else {
                input.type = 'password';
                btn.textContent = '👁️';
            }
        }

        // ========== Login ==========
        async function doLogin() {
            const identifier = document.getElementById('loginIdentifier').value.trim();
            const password = document.getElementById('loginPassword').value;

            if (!identifier) return showStatus('error', 'Please enter your email or username.');
            if (!password) return showStatus('error', 'Please enter your password.');

            showStatus('loading', '<span class="spinner"></span> Signing in...');
            document.getElementById('loginBtn').disabled = true;

            try {
                const res = await fetch('/api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'login', identifier: identifier, password: password })
                });
                const data = await res.json();

                if (data.success) {
                    hideStatus();
                    document.getElementById('loginForm').classList.add('hidden');
                    document.getElementById('loginSuccess').classList.remove('hidden');
                    document.getElementById('loginLinks').classList.add('hidden');

                    // Show user info
                    if (data.user) {
                        document.getElementById('welcomeBox').style.display = 'block';
                        document.getElementById('welcomeName').textContent = '👤 ' + (data.user.full_name || data.user.email);
                        if (data.user.role) {
                            const roleEl = document.getElementById('welcomeRole');
                            roleEl.textContent = data.user.role === 'owner' ? '🏠 Car Owner' : '🚗 Renter';
                            roleEl.className = 'role-tag ' + data.user.role;
                        }
                        document.getElementById('welcomeMsg').textContent = data.message;
                    }

                    // Redirect
                    // Redirect after login
                    const params = new URLSearchParams(window.location.search);
                    let redirect = params.get('redirect') || 'index.php';
                    const carId = params.get('car_id');
                    if (carId && redirect.includes('booking')) {
                        redirect += (redirect.includes('?') ? '&' : '?') + 'car_id=' + encodeURIComponent(carId);
                    }
                    setTimeout(() => {
                        window.location.href = redirect;
                    }, 2000);
                } else {
                    showStatus('error', data.message);
                    document.getElementById('loginBtn').disabled = false;
                }
            } catch (err) {
                showStatus('error', 'Connection error: ' + err.message);
                document.getElementById('loginBtn').disabled = false;
            }
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

        // ========== FACE ID LOGIN ==========
        const FACE_API_CDN = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/dist/face-api.min.js';
        const FACE_MODELS_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model/';
        let faceApiLoaded = false;
        let faceLoginStream = null;
        let faceLoginInterval = null;
        let faceLoginAttempts = 0;
        const MAX_FACE_ATTEMPTS = 30; // 15 seconds at 500ms intervals

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

        async function startFaceIdLogin() {
            // First check if any user has Face ID enabled (quick check)
            showStatus('loading', '<span class="spinner"></span> Checking Face ID availability...');

            try {
                await loadFaceApi();
            } catch (e) {
                showStatus('error', 'Failed to load face detection. Check internet connection.');
                return;
            }

            hideStatus();

            // Switch to Face ID panel
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('faceIdPanel').classList.remove('hidden');
            document.getElementById('loginLinks').classList.add('hidden');
            faceLoginAttempts = 0;

            try {
                faceLoginStream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
                });
                const video = document.getElementById('faceLoginVideo');
                video.srcObject = faceLoginStream;
                await video.play();

                document.getElementById('faceLoginStatus').textContent = '👀 Looking for your face...';
                document.getElementById('faceLoginProgressText').textContent = 'Position your face inside the oval';

                setTimeout(() => startFaceLoginDetection(), 500);
            } catch (e) {
                showStatus('error', 'Camera access denied. Please allow camera permission.');
                cancelFaceIdLogin();
            }
        }

        function startFaceLoginDetection() {
            const video = document.getElementById('faceLoginVideo');
            const canvas = document.getElementById('faceLoginCanvas');
            const ctx = canvas.getContext('2d');

            faceLoginInterval = setInterval(async () => {
                if (!video.videoWidth) return;
                faceLoginAttempts++;

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

                    // Draw landmarks
                    ctx.fillStyle = 'rgba(34,197,94,0.5)';
                    detection.landmarks.positions.forEach(pt => {
                        ctx.beginPath();
                        ctx.arc(pt.x, pt.y, 2, 0, 2 * Math.PI);
                        ctx.fill();
                    });

                    document.getElementById('faceLoginOval').classList.add('detected');
                    document.getElementById('faceLoginStatus').textContent = '✅ Face detected — verifying...';
                    document.getElementById('faceLoginProgress').style.width = '80%';
                    document.getElementById('faceLoginProgressText').textContent = 'Authenticating...';

                    // Stop detection & try login
                    clearInterval(faceLoginInterval);
                    await attemptFaceLogin(Array.from(detection.descriptor));
                } else {
                    document.getElementById('faceLoginOval').classList.remove('detected');
                    document.getElementById('faceLoginStatus').textContent = '👀 Looking for your face...';

                    const pct = Math.min((faceLoginAttempts / MAX_FACE_ATTEMPTS) * 60, 60);
                    document.getElementById('faceLoginProgress').style.width = pct + '%';

                    if (faceLoginAttempts >= MAX_FACE_ATTEMPTS) {
                        clearInterval(faceLoginInterval);
                        document.getElementById('faceLoginStatus').textContent = '😕 Could not detect face';
                        document.getElementById('faceLoginProgressText').textContent = 'Face not detected. Try again or use password.';
                        document.getElementById('faceLoginProgress').style.width = '100%';
                        document.getElementById('faceLoginProgress').style.background = '#ef4444';

                        setTimeout(() => {
                            showStatus('error', 'Could not detect your face. Please try again or use password login.');
                            cancelFaceIdLogin();
                        }, 2000);
                    }
                }
            }, 500);
        }

        async function attemptFaceLogin(descriptor) {
            document.getElementById('faceLoginProgress').style.width = '90%';

            try {
                const res = await fetch('/api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'faceid-login', face_descriptor: descriptor })
                });
                const data = await res.json();

                if (data.success) {
                    document.getElementById('faceLoginProgress').style.width = '100%';
                    document.getElementById('faceLoginStatus').textContent = '🎉 Authenticated!';
                    document.getElementById('faceLoginProgressText').textContent = `Matched: ${data.match_score}`;

                    stopFaceLoginCamera();

                    // Switch to success view
                    document.getElementById('faceIdPanel').classList.add('hidden');
                    document.getElementById('loginSuccess').classList.remove('hidden');

                    if (data.user) {
                        document.getElementById('welcomeBox').style.display = 'block';
                        document.getElementById('welcomeName').textContent = '👤 ' + (data.user.full_name || data.user.email);
                        if (data.user.role) {
                            const roleEl = document.getElementById('welcomeRole');
                            roleEl.textContent = data.user.role === 'owner' ? '🏠 Car Owner' : '🚗 Renter';
                            roleEl.className = 'role-tag ' + data.user.role;
                        }
                        document.getElementById('welcomeMsg').textContent = data.message;
                    }

                    const params = new URLSearchParams(window.location.search);
                    let redirect = params.get('redirect') || 'index.php';
                    const carId = params.get('car_id');
                    if (carId && redirect.includes('booking')) {
                        redirect += (redirect.includes('?') ? '&' : '?') + 'car_id=' + encodeURIComponent(carId);
                    }
                    setTimeout(() => { window.location.href = redirect; }, 2000);
                } else {
                    // Face not recognized or not enabled
                    stopFaceLoginCamera();
                    const msg = data.message || 'Face not recognized.';

                    if (msg.includes('No users have Face ID')) {
                        showStatus('error', '🔐 No Face ID accounts found. Please enable Face ID in your Profile → Security settings first.');
                    } else {
                        showStatus('error', '😕 ' + msg + ' Please try again or use password login. Make sure Face ID is enabled in Profile → Security.');
                    }
                    cancelFaceIdLogin();
                }
            } catch (e) {
                stopFaceLoginCamera();
                showStatus('error', 'Network error during face authentication.');
                cancelFaceIdLogin();
            }
        }

        function cancelFaceIdLogin() {
            stopFaceLoginCamera();
            document.getElementById('faceIdPanel').classList.add('hidden');
            document.getElementById('loginForm').classList.remove('hidden');
            document.getElementById('loginLinks').classList.remove('hidden');
            // Reset progress bar
            document.getElementById('faceLoginProgress').style.width = '0%';
            document.getElementById('faceLoginProgress').style.background = 'linear-gradient(90deg,#2563eb,#22c55e)';
        }

        function stopFaceLoginCamera() {
            if (faceLoginInterval) { clearInterval(faceLoginInterval); faceLoginInterval = null; }
            if (faceLoginStream) { faceLoginStream.getTracks().forEach(t => t.stop()); faceLoginStream = null; }
        }
    </script>
</body>
</html>
