    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div>
                    <div class="footer-brand">🚗 DriveNow</div>
                    <p class="footer-description">
                        The world's leading car rental platform. Book premium cars from trusted owners in 120+ countries. Self-drive or with driver.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-link" title="Facebook">📘</a>
                        <a href="#" class="social-link" title="Twitter/X">🐦</a>
                        <a href="#" class="social-link" title="Instagram">📸</a>
                        <a href="#" class="social-link" title="YouTube">🎬</a>
                        <a href="#" class="social-link" title="TikTok">🎵</a>
                        <a href="#" class="social-link" title="LinkedIn">💼</a>
                    </div>
                </div>
                <div>
                    <h4 class="footer-title">Company</h4>
                    <ul class="footer-links">
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Press</a></li>
                        <li><a href="#">Partners</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="footer-title">Services</h4>
                    <ul class="footer-links">
                        <li><a href="cars.php">Self-Drive Rental</a></li>
                        <li><a href="booking.php">Book a Driver</a></li>
                        <li><a href="booking.php">Airport Transfer</a></li>
                        <li><a href="membership.php">Corporate Fleet</a></li>
                        <li><a href="booking.php">Long-term Rental</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="footer-title">Support</h4>
                    <ul class="footer-links">
                        <li><a href="support.php">Help Center</a></li>
                        <li><a href="support.php">Contact Us</a></li>
                        <li><a href="support.php">Trip Enquiry</a></li>
                        <li><a href="#">Cancellation Policy</a></li>
                        <li><a href="#">Insurance</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="footer-title">Download App</h4>
                    <div class="footer-devices" style="flex-direction:column;">
                        <a href="#" class="footer-device-badge">🍎 App Store</a>
                        <a href="#" class="footer-device-badge">🤖 Google Play</a>
                        <a href="#" class="footer-device-badge">🖥️ Web App</a>
                    </div>
                    <h4 class="footer-title" style="margin-top:24px;">Supported Devices</h4>
                    <p style="font-size:0.813rem;color:var(--gray-400);">iOS, Android, Web Browser, Desktop, Tablet — all devices supported.</p>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© 2026 DriveNow. All rights reserved. Available in 120+ countries worldwide.</span>
                <div style="display:flex;gap:16px;">
                    <a href="#" style="color:var(--gray-400);transition:var(--transition);">Privacy</a>
                    <a href="#" style="color:var(--gray-400);">Terms</a>
                    <a href="#" style="color:var(--gray-400);">Cookies</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- ===== AI CHATBOT WIDGET ===== -->
    <div class="chatbot-widget" id="chatbotWidget">
        <div class="chatbot-window" id="chatbotWindow">
            <div class="chatbot-header">
                <div class="chatbot-header-info">
                    <div class="chatbot-header-avatar">🤖</div>
                    <div>
                        <div class="chatbot-header-name">DriveNow AI Assistant</div>
                        <div class="chatbot-header-status">● Online — powered by AI + Memory</div>
                    </div>
                </div>
                <button class="chatbot-close" onclick="toggleChatbot()">✕</button>
            </div>
            <div class="chatbot-messages" id="chatMessages">
                <div class="chat-message bot">
                    <div class="chat-bubble">
                        👋 Hi there! I'm your DriveNow AI assistant. I can help you with:<br><br>
                        🔍 Finding the perfect car<br>
                        📋 Booking assistance<br>
                        💳 Payment questions<br>
                        🗺️ Trip recommendations<br><br>
                        How can I help you today?
                    </div>
                </div>
                <div class="chatbot-typing" id="typingIndicator">
                    <span></span><span></span><span></span>
                </div>
            </div>
            <div class="chatbot-input">
                <input type="text" id="chatInput" placeholder="Type your message..." onkeypress="if(event.key==='Enter')sendChatMessage()">
                <button onclick="sendChatMessage()">➤</button>
            </div>
        </div>
        <button class="chatbot-toggle" onclick="toggleChatbot()" id="chatbotToggle">💬</button>
    </div>

    <!-- ===== TOAST CONTAINER ===== -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- ===== SHARED JAVASCRIPT ===== -->
    <script>
        // ===== CONFIGURATION =====
        const N8N_WEBHOOK_URL = '<?= \EnvLoader::get("N8N_WEBHOOK_URL", "http://localhost:5678/webhook/83eb33b2-fde3-4aa6-aa37-c3da8c1ca60f") ?>';
        const CHATBOT_API_URL = '/api/chatbot-with-memory.php';

        // ===== NAVBAR =====
        window.addEventListener('scroll', () => {
            document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 10);
        });

        function toggleMobileMenu() {
            document.getElementById('navMenu').classList.toggle('mobile-open');
        }

        // ===== NOTIFICATIONS =====
        function toggleNotifications() {
            document.getElementById('notificationPanel').classList.toggle('open');
        }

        function markAllRead() {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            document.getElementById('notifCount').textContent = '0';
            showToast('All notifications marked as read.', 'success');
        }

        document.addEventListener('click', (e) => {
            const panel = document.getElementById('notificationPanel');
            const btn = document.getElementById('notifBtn');
            if (panel && btn && !panel.contains(e.target) && !btn.contains(e.target)) {
                panel.classList.remove('open');
            }
        });

        // ===== LANGUAGE =====
        const languages = ['EN', 'VI', 'FR', 'DE', 'ES', 'JP', 'KR', 'ZH'];
        let currentLangIndex = 0;
        function toggleLanguageMenu() {
            currentLangIndex = (currentLangIndex + 1) % languages.length;
            document.getElementById('langBtn').textContent = '🌐 ' + languages[currentLangIndex];
            showToast('Language switched to ' + languages[currentLangIndex], 'info');
        }

        // ===== CHATBOT =====
        function toggleChatbot() {
            const win = document.getElementById('chatbotWindow');
            win.classList.toggle('open');
            document.getElementById('chatbotToggle').textContent = win.classList.contains('open') ? '✕' : '💬';
        }

        function openChatbot() {
            const win = document.getElementById('chatbotWindow');
            if (!win.classList.contains('open')) toggleChatbot();
        }

        function addChatMessage(text, isUser = false) {
            const messagesDiv = document.getElementById('chatMessages');
            const typing = document.getElementById('typingIndicator');
            
            const msgDiv = document.createElement('div');
            msgDiv.className = 'chat-message ' + (isUser ? 'user' : 'bot');
            msgDiv.innerHTML = '<div class="chat-bubble">' + text + '</div>';
            
            messagesDiv.insertBefore(msgDiv, typing);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        function showChatTyping(show) {
            document.getElementById('typingIndicator').classList.toggle('active', show);
            if (show) {
                document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;
            }
        }

        async function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (!message) return;

            addChatMessage(message, true);
            input.value = '';
            showChatTyping(true);

            try {
                const response = await fetch(CHATBOT_API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        message: message,
                        chatInput: message,
                        timestamp: new Date().toISOString()
                    })
                });

                const responseText = await response.text();
                showChatTyping(false);

                if (!responseText || responseText.trim() === '') {
                    addChatMessage('✅ Message received! Processing your request...');
                    return;
                }

                try {
                    const data = JSON.parse(responseText);
                    if (data.success) {
                        addChatMessage(data.response);
                    } else {
                        addChatMessage('❌ ' + (data.message || 'Something went wrong. Please try again.'));
                    }
                } catch (e) {
                    // n8n might return plain text
                    addChatMessage(responseText);
                }
            } catch (error) {
                showChatTyping(false);
                addChatMessage('⚠️ Connection error. Please check if the server is running.');
                console.error('Chat error:', error);
            }
        }

        // ===== TOAST NOTIFICATIONS =====
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
            
            const toast = document.createElement('div');
            toast.className = 'toast toast-' + type;
            toast.innerHTML = `
                <span class="toast-icon">${icons[type] || 'ℹ️'}</span>
                <span class="toast-message">${message}</span>
                <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
            `;
            
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }

        // ===== ADD NOTIFICATION =====
        function addNotification(title, text, type = 'booking') {
            const list = document.getElementById('notificationList');
            const icons = { booking: '📋', payment: '💳', alert: '🔔', promo: '🎉' };
            
            const item = document.createElement('li');
            item.className = 'notification-item unread';
            item.innerHTML = `
                <div class="notification-icon ${type}">${icons[type] || '🔔'}</div>
                <div class="notification-content">
                    <div class="notification-title">${title}</div>
                    <div class="notification-text">${text}</div>
                    <div class="notification-time">Just now</div>
                </div>
            `;
            
            list.insertBefore(item, list.firstChild);
            
            const badge = document.getElementById('notifCount');
            if (badge) badge.textContent = parseInt(badge.textContent || 0) + 1;
        }

        // ===== AUTH =====
        async function logout() {
            try {
                await fetch('/api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'logout' })
                });
                window.location.reload();
            } catch (error) {
                console.error('Logout error:', error);
            }
        }

        // ===== MODALS (shared) =====
        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) overlay.classList.remove('open');
            });
        });

        // ===== SMOOTH SCROLL FOR NAV =====
        document.querySelectorAll('.navbar-nav a').forEach(link => {
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                if (href.startsWith('#')) {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        document.getElementById('navMenu').classList.remove('mobile-open');
                    }
                }
            });
        });

        console.log('🚗 DriveNow Platform loaded successfully!');
    </script>
</body>
</html>
