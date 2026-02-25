<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== BOOKING SECTION ===== -->
    <section class="section" style="padding-top:100px;background:var(--gray-100);" id="booking">
        <div class="section-container">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:40px;">
                <div class="booking-form">
                    <h2 class="booking-form-title">📋 Book Your Ride</h2>
                    <p style="color:var(--gray-500);margin-bottom:24px;">Book online instantly or call us at <strong>+1 (800) DRIVE-NOW</strong></p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-input" placeholder="John Doe" id="bookingName" value="<?= $isLoggedIn ? htmlspecialchars($currentUser) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-input" placeholder="john@email.com" id="bookingEmail">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-input" placeholder="+1 (555) 000-0000" id="bookingPhone">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Booking Type</label>
                            <select class="form-select" id="bookingType">
                                <option value="self-drive">Self-Drive</option>
                                <option value="with-driver">With Driver</option>
                                <option value="airport">Airport Transfer</option>
                                <option value="corporate">Corporate / Long-term</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Pick-up Date & Time</label>
                            <input type="datetime-local" class="form-input" id="bookingPickup">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Return Date & Time</label>
                            <input type="datetime-local" class="form-input" id="bookingReturn">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Special Requests / Trip Enquiry</label>
                        <textarea class="form-textarea" placeholder="Any special requirements? Need a child seat? Ask about a specific trip..." id="bookingNotes"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Promo Code</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" class="form-input" placeholder="Enter promo code" id="bookingPromo" value="<?= htmlspecialchars($promoCode) ?>">
                            <button class="btn btn-secondary" onclick="validatePromo()">Apply</button>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-lg btn-block" onclick="submitBooking()">
                        🚗 Confirm Booking
                    </button>
                    <p style="text-align:center;margin-top:12px;font-size:0.813rem;color:var(--gray-400);">
                        You can cancel or edit your booking up to 24 hours before pick-up.
                    </p>
                </div>

                <!-- Payment Methods -->
                <div>
                    <h2 style="font-size:1.5rem;font-weight:800;color:var(--gray-900);margin-bottom:16px;">💳 Payment Options</h2>
                    <p style="color:var(--gray-500);margin-bottom:24px;line-height:1.7;">We accept all major payment methods. Choose what works best for you.</p>
                    
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:32px;">
                        <div class="payment-method active" onclick="selectPayment(this, 'cash')">
                            <span class="payment-method-icon">💵</span>
                            <span>Cash</span>
                        </div>
                        <div class="payment-method" onclick="selectPayment(this, 'transfer')">
                            <span class="payment-method-icon">🏦</span>
                            <span>Bank Transfer</span>
                        </div>
                        <div class="payment-method" onclick="selectPayment(this, 'card')">
                            <span class="payment-method-icon">💳</span>
                            <span>Credit / Debit Card</span>
                        </div>
                        <div class="payment-method" onclick="selectPayment(this, 'paypal')">
                            <span class="payment-method-icon">🅿️</span>
                            <span>PayPal</span>
                        </div>
                        <div class="payment-method" onclick="selectPayment(this, 'apple')">
                            <span class="payment-method-icon">🍎</span>
                            <span>Apple Pay</span>
                        </div>
                        <div class="payment-method" onclick="selectPayment(this, 'google')">
                            <span class="payment-method-icon">🔵</span>
                            <span>Google Pay</span>
                        </div>
                        <div class="payment-method" onclick="selectPayment(this, 'crypto')">
                            <span class="payment-method-icon">₿</span>
                            <span>Cryptocurrency</span>
                        </div>
                        <div class="payment-method" onclick="selectPayment(this, 'wallet')">
                            <span class="payment-method-icon">👛</span>
                            <span>Digital Wallet</span>
                        </div>
                    </div>

                    <div style="background:var(--success-light);border-radius:var(--radius-md);padding:20px;margin-bottom:24px;">
                        <h4 style="color:var(--success);margin-bottom:8px;">🔒 Secure Payment Guarantee</h4>
                        <p style="font-size:0.875rem;color:var(--gray-600);line-height:1.6;">All transactions are encrypted with SSL. Your payment information is never stored on our servers.</p>
                    </div>

                    <div style="background:white;border-radius:var(--radius-md);padding:20px;border:1px solid var(--gray-200);">
                        <h4 style="margin-bottom:12px;color:var(--gray-800);">📞 Prefer to Book by Phone?</h4>
                        <p style="font-size:0.875rem;color:var(--gray-500);margin-bottom:12px;">Our team is available 24/7 to assist with bookings, enquiries, and special requests.</p>
                        <a href="tel:+18003748366" class="btn btn-primary">📞 Call +1 (800) DRIVE-NOW</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== BOOKING PAGE JAVASCRIPT ===== -->
    <script>
        function submitBooking() {
            const name = document.getElementById('bookingName').value;
            const email = document.getElementById('bookingEmail').value;

            if (!name || !email) {
                showToast('Please fill in your name and email.', 'warning');
                return;
            }

            showToast('🎉 Booking submitted! Confirmation email will be sent to ' + email, 'success');
            addNotification('Booking Submitted', 'Your booking has been submitted. Check your email for confirmation.', 'booking');
        }

        function validatePromo() {
            const code = document.getElementById('bookingPromo').value.toUpperCase();
            const validCodes = { 'WEEKEND20': '20% off weekends!', 'FIRST50': '$50 off first booking!', 'LONGTERM30': '30% off 30+ day rentals!' };

            if (validCodes[code]) {
                showToast('✅ Promo code applied: ' + validCodes[code], 'success');
            } else {
                showToast('❌ Invalid promo code.', 'error');
            }
        }

        function selectPayment(el, method) {
            document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
            el.classList.add('active');
        }
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
