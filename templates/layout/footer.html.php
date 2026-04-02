<!-- ===== FOOTER ===== -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-legal-top">
                <a href="#" class="footer-legal-link">Privacy Policy</a>
                <a href="#" class="footer-legal-link">Terms Of Service</a>
            </div>
            <div class="footer-grid">
                <div>
                    <div class="footer-brand">PrivateHire</div>
                    <p class="footer-description">
                        The world's leading car rental platform. Book minicabs or hire cars with drivers from trusted owners in 120+ countries.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-link" title="Instagram" aria-label="Instagram">
                            <img src="/resources/images/logo/instagram.jpg" alt="Instagram" class="social-link-img">
                        </a>
                        <a href="#" class="social-link" title="Facebook" aria-label="Facebook">
                            <img src="/resources/images/logo/Logo_de_Facebook.png" alt="Facebook" class="social-link-img">
                        </a>
                        <a href="#" class="social-link" title="YouTube" aria-label="YouTube">
                            <img src="/resources/images/logo/youtube.png" alt="YouTube" class="social-link-img">
                        </a>
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
                        <li><a href="/cars.php">Hire With Driver</a></li>
                        <li><a href="/booking.php?mode=minicab">Book a Minicab</a></li>
                        <li><a href="/membership.php">Corporate Fleet</a></li>
                        <li><a href="/booking.php">Long-term Rental</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="footer-title">Support</h4>
                    <ul class="footer-links">
                        <li><a href="/support.php">Help Center</a></li>
                        <li><a href="/support.php">Contact Us</a></li>
                        <li><a href="/support.php">Trip Enquiry</a></li>
                        <li><a href="#">Cancellation Policy</a></li>
                        <li><a href="#">Insurance</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="footer-title">Download App</h4>
                    <div class="footer-devices footer-devices-images" style="flex-direction:row;">
                        <a href="#" class="footer-device-badge footer-device-image-badge" aria-label="App Store">
                            <img src="/resources/images/logo/app-store.png" alt="App Store" class="footer-device-img">
                        </a>
                        <a href="#" class="footer-device-badge footer-device-image-badge" aria-label="Google Play">
                            <img src="/resources/images/logo/chplay.jpg" alt="Google Play" class="footer-device-img">
                        </a>
                    </div>
                    <h4 class="footer-title" style="margin-top:24px;">Supported Devices</h4>
                    <p style="font-size:0.813rem;color:var(--gray-400);">iOS, Android, Desktop, Tablet - all devices supported.</p>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© 2026 PrivateHire. All rights reserved. Available in 120+ countries worldwide.</span>
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
                        <div class="chatbot-header-name">PrivateHire AI Assistant</div>
                        <div class="chatbot-header-status">● Online — powered by AI + Memory</div>
                    </div>
                </div>
                <button class="chatbot-close" onclick="toggleChatbot()">✕</button>
            </div>
            <div class="chatbot-messages" id="chatMessages">
                <div class="chat-message bot">
                    <div class="chat-bubble">
                        👋 Hi there! I'm your PrivateHire AI assistant. I can help you with:<br><br>
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

    <!-- ===== AUTH MODALS (Login & Register) ===== -->
    <?php include __DIR__ . '/../login.html.php'; ?>
    <?php include __DIR__ . '/../register.html.php'; ?>

    <!-- ===== EXTERNAL JAVASCRIPT MODULES ===== -->
    <script src="/resources/js/utils.js"></script>
    <script src="/resources/js/navbar.js"></script>
    <script src="/resources/js/notifications.js"></script>
    <script src="/resources/js/auth-state.js"></script>
    <script src="/resources/js/chatbot.js"></script>
    <script src="/resources/js/auth.js"></script>
    <script src="/resources/js/app-init.js"></script>

    <!-- ===== INITIALIZE MODULES ===== -->
    <script>
        // Initialize chatbot with N8N webhook URL
        document.addEventListener('DOMContentLoaded', () => {
            const webhookUrl = '<?= \EnvLoader::get("N8N_WEBHOOK_URL", "http://localhost:5678/webhook/83eb33b2-fde3-4aa6-aa37-c3da8c1ca60f") ?>';
            initChatbot(webhookUrl);

            // Sync auth UI from real session state.
            if (typeof initAuthStateSync === 'function') {
                initAuthStateSync(<?= isset($isLoggedIn) && $isLoggedIn ? 'true' : 'false' ?>, '<?= htmlspecialchars($currentPage ?? '', ENT_QUOTES, 'UTF-8') ?>');
            }

            // Initialize notifications
            if (typeof initNotifications === 'function') {
                initNotifications(<?= isset($isLoggedIn) && $isLoggedIn ? 'true' : 'false' ?>);
            }
        });
    </script>
</body>
</html>
