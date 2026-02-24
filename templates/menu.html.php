<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Scrum Project' ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
                        try {
                // Gửi POST request với JSON body - dùng API mới với Mem0
                const response = await fetch('chatbot-with-memory.php', {
                    method: 'POST',
                    mode: 'cors',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: message,
                        timestamp: new Date().toISOString()
                    })
                });egoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }

        /* Header */
        .header {
            width: 100%;
            max-width: 900px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 25px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .logo {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-name {
            font-weight: 600;
            color: #333;
        }

        .btn-auth {
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-logout {
            background: #dc3545;
            color: white;
        }

        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .container {
            width: 100%;
            max-width: 500px;
        }

        /* Status Card */
        .status-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .status-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .status-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-error {
            background: #f8d7da;
            color: #721c24;
        }

        /* Chatbot Container */
        .chatbot {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .chatbot-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .chatbot-header h1 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .chatbot-header p {
            font-size: 12px;
            opacity: 0.8;
        }

        .chatbot-messages {
            height: 400px;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }

        .message {
            margin-bottom: 15px;
            display: flex;
        }

        .message.bot {
            justify-content: flex-start;
        }

        .message.user {
            justify-content: flex-end;
        }

        .message-content {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
        }

        .message.bot .message-content {
            background: #fff;
            color: #333;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .message.user .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .chatbot-input {
            display: flex;
            padding: 15px;
            background: #fff;
            border-top: 1px solid #eee;
        }

        .chatbot-input input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }

        .chatbot-input input:focus {
            border-color: #667eea;
        }

        .chatbot-input button {
            margin-left: 10px;
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .chatbot-input button:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .typing-indicator {
            display: none;
            padding: 12px 16px;
            background: #fff;
            border-radius: 18px;
            border-bottom-left-radius: 4px;
            width: fit-content;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .typing-indicator span {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #667eea;
            border-radius: 50%;
            margin-right: 4px;
            animation: typing 1s infinite;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
            margin-right: 0;
        }

        @keyframes typing {
            0%, 100% { opacity: 0.3; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1); }
        }

        /* Scrollbar */
        .chatbot-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chatbot-messages::-webkit-scrollbar-track {
            background: transparent;
        }

        .chatbot-messages::-webkit-scrollbar-thumb {
            background: #ddd;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <!-- Header với thông tin user -->
    <header class="header">
        <div class="logo">🚀 Scrum Project</div>
        <div class="user-section">
            <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
                <span class="user-name">👤 <?= htmlspecialchars($currentUser) ?></span>
                <a href="#" class="btn-auth btn-logout" onclick="logout()">Đăng xuất</a>
            <?php else: ?>
                <a href="login.php" class="btn-auth btn-login">🔐 Đăng nhập</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <!-- Status Card -->
    

        <!-- Chatbot -->
        <div class="chatbot">
            <div class="chatbot-header">
                <h1>AI Assistant</h1>
            </div>
            
            <div class="chatbot-messages" id="chatMessages">
                <div class="message bot">
                    <div class="message-content">
                        Hi, this is your AI assistant. How can I help you today?
                    </div>
                </div>
                <div class="typing-indicator" id="typingIndicator">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            
            <div class="chatbot-input">
                <input type="text" id="userInput" placeholder="Message..." onkeypress="handleKeyPress(event)">
                <button onclick="sendMessage()">Send</button>
            </div>
        </div>
    </div>

    <script>
        const N8N_WEBHOOK_URL = 'http://localhost:5678/webhook/83eb33b2-fde3-4aa6-aa37-c3da8c1ca60f';
        const CHATBOT_API_URL = 'chatbot-with-memory.php'; // API mới với Mem0
        
        function addMessage(text, isUser = false) {
            const messagesDiv = document.getElementById('chatMessages');
            const typingIndicator = document.getElementById('typingIndicator');
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isUser ? 'user' : 'bot'}`;
            messageDiv.innerHTML = `<div class="message-content">${text}</div>`;
            
            messagesDiv.insertBefore(messageDiv, typingIndicator);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
        
        function showTyping(show) {
            document.getElementById('typingIndicator').style.display = show ? 'block' : 'none';
        }
        
        async function sendMessage() {
            const input = document.getElementById('userInput');
            const message = input.value.trim();
            
            if (!message) return;
            
            addMessage(message, true);
            input.value = '';
            showTyping(true);
            
            try {
                // Gửi POST request với JSON body
                const response = await fetch(N8N_WEBHOOK_URL, {
                    method: 'POST',
                    mode: 'cors',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        chatInput: message,
                        message: message,
                        prompt: message,
                        timestamp: new Date().toISOString()
                    })
                });
                
                // Lấy response text trước
                const responseText = await response.text();
                console.log('API response:', responseText);
                
                showTyping(false);
                
                // Kiểm tra nếu response rỗng
                if (!responseText || responseText.trim() === '') {
                    addMessage('✅ Đã xử lý! (API không trả về nội dung)');
                    return;
                }
                
                // Thử parse JSON
                try {
                    const data = JSON.parse(responseText);
                    console.log('Parsed data:', data);
                    
                    if (data.success) {
                        addMessage(data.response);
                        // Nếu dùng Mem0, hiển thị thông báo
                        if (data.context_used) {
                            console.log('💾 Memory được sử dụng để cải thiện response');
                        }
                    } else {
                        addMessage('❌ ' + (data.message || 'Lỗi không xác định'));
                    }
                } catch (jsonError) {
                    // Nếu không phải JSON, hiển thị text trực tiếp
                    addMessage(responseText);
                }
                
            } catch (error) {
                showTyping(false);
                console.error('Fetch error:', error);
                addMessage('⚠️ Lỗi: ' + error.message);
            }
        }
        
        function handleKeyPress(event) {
            if (event.key === 'Enter') {
                sendMessage();
            }
        }

        // Đăng xuất
        async function logout() {
            try {
                await fetch('face-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'logout' })
                });
                window.location.reload();
            } catch (error) {
                console.error('Logout error:', error);
            }
        }
    </script>
</body>
</html>