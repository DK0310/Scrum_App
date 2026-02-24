<?php
session_start();
$title = "Đăng ký - Face ID";
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
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
            height: 300px;
            object-fit: cover;
            display: block;
        }

        #canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 300px;
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
            width: 180px;
            height: 220px;
            border: 3px dashed rgba(255,255,255,0.5);
            border-radius: 50%;
            pointer-events: none;
        }

        .status-bar {
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
            text-align: center;
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

        .btn-secondary {
            background: #6c757d;
            color: white;
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

        .face-detected {
            border-color: #28a745 !important;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.5);
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #fff;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>📝 Đăng ký tài khoản</h1>
                <p>Sử dụng Face ID để bảo mật</p>
            </div>
            
            <div class="card-body">
                <div id="statusBar" class="status-bar status-loading">
                    <span class="spinner"></span> Đang tải mô hình nhận diện khuôn mặt...
                </div>

                <form id="registerForm">
                    <div class="form-group">
                        <label>👤 Tên người dùng</label>
                        <input type="text" id="username" name="username" required placeholder="Nhập tên người dùng">
                    </div>

                    <div class="form-group">
                        <label>📧 Email</label>
                        <input type="email" id="email" name="email" required placeholder="Nhập email">
                    </div>

                    <div class="form-group">
                        <label>📷 Quét khuôn mặt</label>
                        <div class="camera-container" id="cameraContainer">
                            <video id="video" autoplay muted playsinline></video>
                            <canvas id="canvas"></canvas>
                            <div class="face-guide" id="faceGuide"></div>
                            <div class="camera-overlay" id="cameraOverlay">
                                <span>📷 Đang khởi động camera...</span>
                            </div>
                        </div>
                    </div>

                    <button type="button" id="captureBtn" class="btn btn-secondary" disabled onclick="captureFace()">
                        📸 Chụp khuôn mặt
                    </button>

                    <button type="submit" id="registerBtn" class="btn btn-primary" disabled>
                        ✅ Đăng ký
                    </button>
                </form>

                <div class="links">
                    Đã có tài khoản? <a href="login.php">Đăng nhập</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        let video, canvas, ctx;
        let faceDescriptor = null;
        let isModelLoaded = false;
        let detectInterval = null;

        // Khởi tạo khi trang load
        window.onload = async function() {
            video = document.getElementById('video');
            canvas = document.getElementById('canvas');
            ctx = canvas.getContext('2d');

            await loadModels();
            await startCamera();
        };

        // Load face-api models
        async function loadModels() {
            const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model';
            
            try {
                updateStatus('loading', '⏳ Đang tải mô hình AI...');
                
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                ]);
                
                isModelLoaded = true;
                updateStatus('ready', '✅ Mô hình đã sẵn sàng! Hãy đưa khuôn mặt vào khung hình.');
                document.getElementById('captureBtn').disabled = false;
                
            } catch (error) {
                console.error('Error loading models:', error);
                updateStatus('error', '❌ Lỗi tải mô hình: ' + error.message);
            }
        }

        // Khởi động camera
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
                console.error('Camera error:', error);
                updateStatus('error', '❌ Không thể truy cập camera. Vui lòng cấp quyền.');
            }
        }

        // Phát hiện khuôn mặt liên tục
        function startFaceDetection() {
            detectInterval = setInterval(async () => {
                if (!isModelLoaded) return;
                
                const detections = await faceapi.detectAllFaces(
                    video, 
                    new faceapi.TinyFaceDetectorOptions()
                ).withFaceLandmarks();

                // Vẽ lên canvas
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                if (detections.length > 0) {
                    // Vẽ khung và landmarks
                    faceapi.draw.drawDetections(canvas, detections);
                    faceapi.draw.drawFaceLandmarks(canvas, detections);
                    
                    document.getElementById('faceGuide').classList.add('face-detected');
                    updateStatus('detecting', '😊 Phát hiện khuôn mặt! Nhấn "Chụp khuôn mặt"');
                } else {
                    document.getElementById('faceGuide').classList.remove('face-detected');
                    if (isModelLoaded) {
                        updateStatus('ready', '👀 Đưa khuôn mặt vào khung hình...');
                    }
                }
            }, 200);
        }

        // Chụp và lưu face descriptor
        async function captureFace() {
            if (!isModelLoaded) return;
            
            updateStatus('loading', '⏳ Đang xử lý khuôn mặt...');
            document.getElementById('captureBtn').disabled = true;

            try {
                const detection = await faceapi.detectSingleFace(
                    video,
                    new faceapi.TinyFaceDetectorOptions()
                ).withFaceLandmarks().withFaceDescriptor();

                if (detection) {
                    faceDescriptor = Array.from(detection.descriptor);
                    updateStatus('ready', '✅ Đã lưu khuôn mặt thành công!');
                    document.getElementById('registerBtn').disabled = false;
                    document.getElementById('captureBtn').textContent = '🔄 Chụp lại';
                } else {
                    updateStatus('error', '❌ Không tìm thấy khuôn mặt. Thử lại!');
                }
            } catch (error) {
                updateStatus('error', '❌ Lỗi: ' + error.message);
            }
            
            document.getElementById('captureBtn').disabled = false;
        }

        // Xử lý form đăng ký
        document.getElementById('registerForm').onsubmit = async function(e) {
            e.preventDefault();
            
            if (!faceDescriptor) {
                alert('Vui lòng chụp khuôn mặt trước!');
                return;
            }

            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;

            updateStatus('loading', '⏳ Đang đăng ký...');
            document.getElementById('registerBtn').disabled = true;

            try {
                const response = await fetch('face-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'register',
                        username: username,
                        email: email,
                        faceDescriptor: faceDescriptor
                    })
                });

                const responseText = await response.text();
                console.log('Server response:', responseText);
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseErr) {
                    updateStatus('error', '❌ Server trả về lỗi: ' + responseText.substring(0, 200));
                    document.getElementById('registerBtn').disabled = false;
                    return;
                }
                
                if (result.success) {
                    updateStatus('ready', '🎉 Đăng ký thành công!');
                    alert('Đăng ký thành công! Chuyển đến trang đăng nhập.');
                    window.location.href = 'login.php';
                } else {
                    updateStatus('error', '❌ ' + result.message);
                    document.getElementById('registerBtn').disabled = false;
                }
            } catch (error) {
                updateStatus('error', '❌ Lỗi kết nối: ' + error.message);
                document.getElementById('registerBtn').disabled = false;
            }
        };

        // Cập nhật status bar
        function updateStatus(type, message) {
            const statusBar = document.getElementById('statusBar');
            statusBar.className = 'status-bar status-' + type;
            statusBar.innerHTML = message;
        }
    </script>
</body>
</html>
