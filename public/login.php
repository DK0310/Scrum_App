<?php
session_start();
$title = "Đăng nhập - Face ID";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 500px;
        }

        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .card-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .card-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .card-body {
            padding: 30px;
        }

        .camera-container {
            position: relative;
            width: 100%;
            border-radius: 15px;
            overflow: hidden;
            background: #000;
            margin-bottom: 20px;
        }

        #video {
            width: 100%;
            height: 350px;
            object-fit: cover;
            display: block;
        }

        #canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 350px;
        }

        .camera-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background: rgba(0,0,0,0.7);
            color: white;
            font-size: 14px;
        }

        .face-guide {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 240px;
            border: 3px dashed rgba(255,255,255,0.5);
            border-radius: 50%;
            pointer-events: none;
            transition: all 0.3s;
        }

        .face-detected {
            border-color: #28a745 !important;
            border-style: solid !important;
            box-shadow: 0 0 30px rgba(40, 167, 69, 0.6);
        }

        .status-bar {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            font-weight: 500;
        }

        .status-loading {
            background: #fff3cd;
            color: #856404;
        }

        .status-ready {
            background: #d4edda;
            color: #155724;
        }

        .status-error {
            background: #f8d7da;
            color: #721c24;
        }

        .status-detecting {
            background: #cce5ff;
            color: #004085;
        }

        .status-success {
            background: #d4edda;
            color: #155724;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            text-align: center;
        }

        .user-info .name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .user-info .email {
            font-size: 13px;
            color: #666;
        }

        .match-score {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }

        .match-high {
            background: #d4edda;
            color: #155724;
        }

        .match-medium {
            background: #fff3cd;
            color: #856404;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .hidden {
            display: none !important;
        }

        .scan-line {
            position: absolute;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #667eea, transparent);
            top: 0;
            animation: scan 2s linear infinite;
        }

        @keyframes scan {
            0% { top: 0; }
            100% { top: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>🔐 Đăng nhập</h1>
                <p>Nhận diện khuôn mặt để đăng nhập</p>
            </div>
            
            <div class="card-body">
                <div id="statusBar" class="status-bar status-loading">
                    <span class="spinner"></span> Đang tải mô hình nhận diện...
                </div>

                <div id="userInfo" class="user-info hidden">
                    <div class="name" id="userName"></div>
                    <div class="email" id="userEmail"></div>
                    <div class="match-score match-high" id="matchScore"></div>
                </div>

                <div class="camera-container">
                    <video id="video" autoplay muted playsinline></video>
                    <canvas id="canvas"></canvas>
                    <div class="face-guide" id="faceGuide"></div>
                    <div class="scan-line" id="scanLine"></div>
                    <div class="camera-overlay" id="cameraOverlay">
                        <span>📷 Đang khởi động camera...</span>
                    </div>
                </div>

                <button type="button" id="loginBtn" class="btn btn-primary" disabled onclick="loginWithFace()">
                    🔓 Đăng nhập bằng khuôn mặt
                </button>

                <div class="links">
                    Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        let video, canvas, ctx;
        let isModelLoaded = false;
        let isProcessing = false;

        window.onload = async function() {
            video = document.getElementById('video');
            canvas = document.getElementById('canvas');
            ctx = canvas.getContext('2d');

            await loadModels();
            await startCamera();
        };

        async function loadModels() {
            const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model';
            
            try {
                updateStatus('loading', '<span class="spinner"></span> Đang tải mô hình AI...');
                
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                ]);
                
                isModelLoaded = true;
                updateStatus('ready', '✅ Sẵn sàng! Đưa khuôn mặt vào khung hình.');
                document.getElementById('loginBtn').disabled = false;
                
            } catch (error) {
                console.error('Error loading models:', error);
                updateStatus('error', '❌ Lỗi tải mô hình: ' + error.message);
            }
        }

        async function startCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { 
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        facingMode: 'user'
                    }
                });
                
                video.srcObject = stream;
                video.onloadedmetadata = () => {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    document.getElementById('cameraOverlay').classList.add('hidden');
                    startFaceDetection();
                };
                
            } catch (error) {
                updateStatus('error', '❌ Không thể truy cập camera.');
            }
        }

        function startFaceDetection() {
            setInterval(async () => {
                if (!isModelLoaded || isProcessing) return;
                
                const detections = await faceapi.detectAllFaces(
                    video, 
                    new faceapi.TinyFaceDetectorOptions()
                ).withFaceLandmarks();

                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                if (detections.length > 0) {
                    faceapi.draw.drawDetections(canvas, detections);
                    faceapi.draw.drawFaceLandmarks(canvas, detections);
                    document.getElementById('faceGuide').classList.add('face-detected');
                    
                    if (!isProcessing) {
                        updateStatus('detecting', '😊 Phát hiện khuôn mặt! Nhấn đăng nhập.');
                    }
                } else {
                    document.getElementById('faceGuide').classList.remove('face-detected');
                    if (!isProcessing) {
                        updateStatus('ready', '👀 Đưa khuôn mặt vào khung hình...');
                    }
                }
            }, 200);
        }

        async function loginWithFace() {
            if (!isModelLoaded || isProcessing) return;
            
            isProcessing = true;
            updateStatus('loading', '<span class="spinner"></span> Đang xác thực khuôn mặt...');
            document.getElementById('loginBtn').disabled = true;

            try {
                const detection = await faceapi.detectSingleFace(
                    video,
                    new faceapi.TinyFaceDetectorOptions()
                ).withFaceLandmarks().withFaceDescriptor();

                if (!detection) {
                    updateStatus('error', '❌ Không tìm thấy khuôn mặt. Thử lại!');
                    isProcessing = false;
                    document.getElementById('loginBtn').disabled = false;
                    return;
                }

                const faceDescriptor = Array.from(detection.descriptor);

                // Gửi đến server để xác thực
                const response = await fetch('face-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'login',
                        faceDescriptor: faceDescriptor
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    updateStatus('success', '🎉 Đăng nhập thành công!');
                    
                    // Hiển thị thông tin user
                    document.getElementById('userInfo').classList.remove('hidden');
                    document.getElementById('userName').textContent = '👤 ' + result.user.username;
                    document.getElementById('userEmail').textContent = result.user.email;
                    document.getElementById('matchScore').textContent = 
                        '✓ Độ khớp: ' + (result.matchScore * 100).toFixed(1) + '%';
                    
                    // Chuyển trang sau 2 giây
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 2000);
                } else if (result.retryRequired) {
                    // Độ khớp dưới 50% → yêu cầu quét lại
                    updateStatus('error', 
                        '⚠️ Độ khớp chỉ ' + (result.matchScore * 100).toFixed(1) + '% (tối thiểu 50%). ' +
                        'Hãy đảm bảo đủ ánh sáng và nhìn thẳng vào camera.'
                    );
                    document.getElementById('loginBtn').disabled = false;
                    document.getElementById('loginBtn').textContent = '🔄 Quét lại khuôn mặt';
                } else {
                    updateStatus('error', '❌ ' + result.message);
                    document.getElementById('loginBtn').disabled = false;
                }
                
            } catch (error) {
                updateStatus('error', '❌ Lỗi: ' + error.message);
                document.getElementById('loginBtn').disabled = false;
            }
            
            isProcessing = false;
        }

        function updateStatus(type, message) {
            const statusBar = document.getElementById('statusBar');
            statusBar.className = 'status-bar status-' + type;
            statusBar.innerHTML = message;
        }
    </script>
</body>
</html>
