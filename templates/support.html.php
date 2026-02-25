<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== CUSTOMER SUPPORT ===== -->
    <section class="section" style="padding-top:100px;background:var(--gray-100);" id="support">
        <div class="section-container">
            <div class="section-header" style="justify-content:center;text-align:center;">
                <div>
                    <h2 class="section-title">🛡️ Customer Support</h2>
                    <p class="section-subtitle">We're here to help 24/7 — reach us anytime, anywhere</p>
                </div>
            </div>
            <div class="support-grid">
                <div class="support-card" onclick="openChatbot()">
                    <div class="support-icon">🤖</div>
                    <h3 class="support-title">AI Chatbot</h3>
                    <p class="support-description">Get instant answers to your questions with our intelligent AI assistant.</p>
                </div>
                <div class="support-card" onclick="window.open('tel:+18003748366')">
                    <div class="support-icon">📞</div>
                    <h3 class="support-title">Phone Support</h3>
                    <p class="support-description">Call us 24/7 at +1 (800) DRIVE-NOW for immediate assistance.</p>
                </div>
                <div class="support-card" onclick="window.open('mailto:support@drivenow.com')">
                    <div class="support-icon">📧</div>
                    <h3 class="support-title">Email Support</h3>
                    <p class="support-description">Send us an email and we'll respond within 2 hours.</p>
                </div>
                <div class="support-card" onclick="openEnquiryModal()">
                    <div class="support-icon">📋</div>
                    <h3 class="support-title">Trip Enquiry</h3>
                    <p class="support-description">Need a custom trip? Submit an enquiry and we'll plan it for you.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== FAQ SECTION ===== -->
    <section class="section">
        <div class="section-container">
            <div class="section-header" style="justify-content:center;text-align:center;">
                <div>
                    <h2 class="section-title">❓ Frequently Asked Questions</h2>
                    <p class="section-subtitle">Quick answers to common questions</p>
                </div>
            </div>
            <div style="max-width:720px;margin:0 auto;display:flex;flex-direction:column;gap:12px;">
                <div style="background:white;border-radius:var(--radius-md);padding:20px;border:1px solid var(--gray-200);cursor:pointer;" onclick="this.querySelector('.faq-answer').classList.toggle('open')">
                    <h4 style="color:var(--gray-800);margin-bottom:0;">How do I cancel a booking?</h4>
                    <div class="faq-answer" style="max-height:0;overflow:hidden;transition:max-height 0.3s ease;color:var(--gray-500);font-size:0.875rem;">
                        <p style="padding-top:12px;">You can cancel any booking up to 24 hours before pick-up time for a full refund. Go to My Bookings and click Cancel.</p>
                    </div>
                </div>
                <div style="background:white;border-radius:var(--radius-md);padding:20px;border:1px solid var(--gray-200);cursor:pointer;" onclick="this.querySelector('.faq-answer').classList.toggle('open')">
                    <h4 style="color:var(--gray-800);margin-bottom:0;">What payment methods do you accept?</h4>
                    <div class="faq-answer" style="max-height:0;overflow:hidden;transition:max-height 0.3s ease;color:var(--gray-500);font-size:0.875rem;">
                        <p style="padding-top:12px;">We accept cash, bank transfer, credit/debit cards, PayPal, Apple Pay, Google Pay, cryptocurrency, and digital wallets.</p>
                    </div>
                </div>
                <div style="background:white;border-radius:var(--radius-md);padding:20px;border:1px solid var(--gray-200);cursor:pointer;" onclick="this.querySelector('.faq-answer').classList.toggle('open')">
                    <h4 style="color:var(--gray-800);margin-bottom:0;">Is insurance included?</h4>
                    <div class="faq-answer" style="max-height:0;overflow:hidden;transition:max-height 0.3s ease;color:var(--gray-500);font-size:0.875rem;">
                        <p style="padding-top:12px;">Basic insurance is included with all rentals. Premium and comprehensive insurance options are available at checkout.</p>
                    </div>
                </div>
                <div style="background:white;border-radius:var(--radius-md);padding:20px;border:1px solid var(--gray-200);cursor:pointer;" onclick="this.querySelector('.faq-answer').classList.toggle('open')">
                    <h4 style="color:var(--gray-800);margin-bottom:0;">Can I rent a car without a credit card?</h4>
                    <div class="faq-answer" style="max-height:0;overflow:hidden;transition:max-height 0.3s ease;color:var(--gray-500);font-size:0.875rem;">
                        <p style="padding-top:12px;">Yes! We accept cash and bank transfer as alternatives to credit cards. Just select your preferred payment method during booking.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== ENQUIRY MODAL ===== -->
    <div class="modal-overlay" id="enquiryModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">📋 Trip Enquiry</h3>
                <button class="modal-close" onclick="closeModal('enquiryModal')">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Your Name</label>
                    <input type="text" class="form-input" placeholder="Full name" id="enquiryName">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" placeholder="your@email.com" id="enquiryEmail">
                </div>
                <div class="form-group">
                    <label class="form-label">Trip Details</label>
                    <textarea class="form-textarea" placeholder="Describe your trip: destination, dates, number of passengers, special requirements..." id="enquiryDetails"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Preferred Contact Method</label>
                    <select class="form-select" id="enquiryContact">
                        <option>Email</option>
                        <option>Phone Call</option>
                        <option>WhatsApp</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('enquiryModal')">Cancel</button>
                <button class="btn btn-primary" onclick="submitEnquiry()">Submit Enquiry</button>
            </div>
        </div>
    </div>

    <!-- ===== SUPPORT PAGE JAVASCRIPT ===== -->
    <script>
        function openEnquiryModal() {
            document.getElementById('enquiryModal').classList.add('open');
        }

        function submitEnquiry() {
            const name = document.getElementById('enquiryName').value;
            const email = document.getElementById('enquiryEmail').value;
            if (!name || !email) {
                showToast('Please fill in your name and email.', 'warning');
                return;
            }
            closeModal('enquiryModal');
            showToast('✅ Trip enquiry submitted! Our team will contact you within 2 hours.', 'success');
        }

        // FAQ toggle styling
        document.querySelectorAll('.faq-answer').forEach(el => {
            el.style.maxHeight = '0';
        });
        document.querySelectorAll('.faq-answer.open').forEach(el => {
            el.style.maxHeight = el.scrollHeight + 'px';
        });

        // Override click to toggle maxHeight
        document.querySelectorAll('.faq-answer').forEach(el => {
            const parent = el.parentElement;
            parent.addEventListener('click', () => {
                if (el.style.maxHeight && el.style.maxHeight !== '0px') {
                    el.style.maxHeight = '0';
                } else {
                    el.style.maxHeight = el.scrollHeight + 'px';
                }
            });
        });
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
