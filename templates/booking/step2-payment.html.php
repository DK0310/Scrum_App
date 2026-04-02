            <!-- ===== STEP 2: PAYMENT ===== -->
            <div id="step2Content" style="display:none;">
                            <div class="booking-grid payment-grid-modern">
                                <div class="booking-form-card payment-form-modern">
                                    <h3 class="payment-heading">Payment Method</h3>

                                    <div class="payment-methods-grid">
                                        <div class="payment-method-card active" data-method="cash" onclick="selectPaymentMethod('cash')">
                                            <div class="pm-chip">💵</div>
                                            <span class="pm-name">Cash</span>
                                            <span class="pm-desc">Pay at destination</span>
                                            <span class="pm-check">✓</span>
                                        </div>
                                        <div class="payment-method-card" data-method="account_balance" onclick="selectPaymentMethod('account_balance')" id="accountBalanceMethodCard">
                                            <div class="pm-chip">💷</div>
                                            <span class="pm-name">Account Balance</span>
                                            <span class="pm-desc" id="accountBalanceMethodDesc">Loading balance...</span>
                                            <span class="pm-check">✓</span>
                                        </div>
                                        <div class="payment-method-card" data-method="paypal" onclick="selectPaymentMethod('paypal')">
                                            <div class="pm-chip pm-chip-logo">
                                                <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuCBU4sSgahwkKMnCEomLWind84lM-UT7qYIEvSrSltS75tTWEF7My1wXzNKkIvW32YmhTdOBE_nhb_1mnk0Oga0OH9ezDqPGl4zwJijm34ExlToAl-aYQXOzZaC3cvFuIHG6DYFWtNkD9HPLHJrBvveBiuoyI9Bkas5WqqCUPpp6N_Ud5eIEXvSBp2mZ-qMA3JrRwDn1IulQr_10prTVRIX2eTHQv1t8HmOvCYKQ6GAnnGNwdV5kEJ3ubZ3aLr6AX1aTW0s-1otamc" alt="PayPal Logo">
                                            </div>
                                            <span class="pm-name">PayPal</span>
                                            <span class="pm-desc">Faster checkout</span>
                                            <span class="pm-check">✓</span>
                                        </div>
                                    </div>

                                    <div class="promo-section" id="promoSection">
                                        <h4 class="promo-title">Promo Code</h4>

                                        <div class="promo-input-row" id="promoInputRow">
                                            <input type="text" class="form-input" id="promoCodeInput" placeholder="Enter code" style="flex:1;">
                                            <button type="button" class="btn btn-secondary" onclick="applyPromoCode()" id="promoApplyBtn">Apply</button>
                                        </div>

                                        <div class="promo-applied" id="promoApplied" style="display:none;">
                                            <div class="promo-applied-inner">
                                                <span class="promo-applied-icon">🎉</span>
                                                <div>
                                                    <div class="promo-applied-code" id="promoAppliedCode"></div>
                                                    <div class="promo-applied-desc" id="promoAppliedDesc"></div>
                                                </div>
                                                <button type="button" class="promo-remove-btn" onclick="removePromo()">✕</button>
                                            </div>
                                        </div>

                                        <div id="savedPromosSection" style="display:none;margin-top:12px;">
                                            <div style="font-size:0.8rem;color:var(--gray-500);margin-bottom:8px;">Your saved promos:</div>
                                            <div class="saved-promos-list" id="savedPromosList"></div>
                                        </div>
                                    </div>

                                    <div class="payment-security-note">
                                        <span>🔒</span>
                                        <div>
                                            <div class="payment-security-title">Secure Payment</div>
                                            <div class="payment-security-sub">All transactions are encrypted. Your data is safe.</div>
                                        </div>
                                    </div>

                                    <button type="button" class="btn btn-outline" onclick="goToStep1()" style="width:100%;">← Back to Trip Details</button>
                                </div>

                                <div class="payment-summary-card payment-summary-modern">
                                    <div class="payment-summary-inner">
                                        <h3 class="payment-summary-title">Booking Summary</h3>

                                        <div class="payment-car-header">
                                            <div class="payment-car-thumb" id="paymentCarThumb">
                                                <div class="no-image-placeholder" style="height:100%;display:flex;align-items:center;justify-content:center;color:var(--gray-400);font-size:0.8rem;">No Photo</div>
                                            </div>
                                            <div class="payment-car-info">
                                                <h3 id="paymentCarTitle" style="font-size:1.1rem;font-weight:700;color:var(--gray-900);margin-bottom:2px;"></h3>
                                                <p id="paymentCarSub" style="font-size:0.8rem;color:var(--gray-500);margin-bottom:6px;"></p>
                                                <span class="badge" id="paymentBookingType" style="font-size:0.75rem;"></span>
                                            </div>
                                        </div>

                                        <div class="payment-details-list payment-route-list">
                                            <div class="payment-detail-row">
                                                <span class="payment-detail-icon">●</span>
                                                <div>
                                                    <div class="payment-detail-label">Pick-up</div>
                                                    <div class="payment-detail-value" id="paymentPickupLoc"></div>
                                                    <div class="payment-detail-sub" id="paymentPickupDate"></div>
                                                </div>
                                            </div>
                                            <div class="payment-detail-row" id="paymentReturnLocRow">
                                                <span class="payment-detail-icon">◎</span>
                                                <div>
                                                    <div class="payment-detail-label" id="paymentReturnLocLabel">Destination</div>
                                                    <div class="payment-detail-value" id="paymentReturnLoc"></div>
                                                </div>
                                            </div>
                                            <div class="payment-detail-row" id="paymentReturnRow">
                                                <span class="payment-detail-icon">📅</span>
                                                <div>
                                                    <div class="payment-detail-label">Return</div>
                                                    <div class="payment-detail-value" id="paymentReturnDate"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="payment-price-breakdown">
                                            <div class="price-row">
                                                <span>Daily Rate</span>
                                                <span id="paymentDailyRate"></span>
                                            </div>
                                            <div class="price-row" id="paymentDaysRow">
                                                <span id="paymentDaysLabel"></span>
                                                <span id="paymentSubtotal"></span>
                                            </div>
                                            <div class="price-row" id="paymentDistanceRow" style="display:none;">
                                                <span>Distance</span>
                                                <span id="paymentDistance"></span>
                                            </div>
                                            <div class="price-row" id="paymentTransferRow" style="display:none;">
                                                <span>Transfer Cost</span>
                                                <span id="paymentTransferCost" style="font-weight:700;color:var(--primary);"></span>
                                            </div>
                                            <div class="price-row promo-row" id="paymentPromoRow" style="display:none;">
                                                <span style="color:var(--success);">Promo Discount</span>
                                                <span style="color:var(--success);" id="paymentDiscount"></span>
                                            </div>
                                            <div class="price-row total-row">
                                                <span>Total</span>
                                                <span id="paymentTotal"></span>
                                            </div>
                                        </div>

                                        <button type="button" class="btn btn-primary btn-lg btn-block" onclick="confirmBooking()" id="confirmBtn">
                                            Confirm & Book
                                        </button>
                                        <p class="payment-terms-text">
                                            By confirming, you agree to our service terms and privacy policy.
                                        </p>
                                    </div>
                                </div>
                </div>
            </div>
