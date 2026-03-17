<!-- REGISTER MODAL OVERLAY -->
<div id="registerModalOverlay" class="modal-overlay" style="display: none;">
    <div class="modal" style="max-width: 550px;">
        <!-- Close Button -->
        <button onclick="closeRegisterModal()" class="modal-close" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">✕</button>

        <div style="padding: 40px;">
            <!-- Progress Indicator -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; gap: 10px;">
                <div class="step-dot active" data-step="1" style="width: 40px; height: 40px; border-radius: 50%; background: #0f766e; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; cursor: pointer;" onclick="regGoToStep(1)">1</div>
                <div style="flex: 1; height: 2px; background: #ddd;"></div>
                <div class="step-dot" data-step="2" style="width: 40px; height: 40px; border-radius: 50%; background: #e0e0e0; color: #999; display: flex; align-items: center; justify-content: center; font-weight: 600; cursor: pointer;" onclick="regGoToStep(2)">2</div>
                <div style="flex: 1; height: 2px; background: #ddd;"></div>
                <div class="step-dot" data-step="3" style="width: 40px; height: 40px; border-radius: 50%; background: #e0e0e0; color: #999; display: flex; align-items: center; justify-content: center; font-weight: 600; cursor: pointer;" onclick="regGoToStep(3)">3</div>
                <div style="flex: 1; height: 2px; background: #ddd;"></div>
                <div class="step-dot" data-step="4" style="width: 40px; height: 40px; border-radius: 50%; background: #e0e0e0; color: #999; display: flex; align-items: center; justify-content: center; font-weight: 600;">4</div>
            </div>

            <!-- ===== STEP 1: PERSONAL INFO ===== -->
            <div id="regStep1" style="display: block;">
                <h2 style="margin: 0 0 10px 0; color: #0f766e; font-size: 24px; font-weight: 600;">Create Account</h2>
                <p style="margin: 0 0 25px 0; color: #999; font-size: 14px;">Step 1 of 4: Your Information</p>

                <!-- Full Name -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; margin-bottom: 8px; color: #333; font-weight: 500; font-size: 14px;">Full Name</label>
                    <input type="text" id="regFullName" 
                           style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box;"
                           placeholder="Enter your full name"
                           onblur="this.style.borderColor = '#ddd'"
                           onfocus="this.style.borderColor = '#0f766e'">
                </div>

                <!-- Email -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; margin-bottom: 8px; color: #333; font-weight: 500; font-size: 14px;">Email Address</label>
                    <input type="email" id="regEmail" 
                           style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box;"
                           placeholder="you@example.com"
                           onblur="this.style.borderColor = '#ddd'"
                           onfocus="this.style.borderColor = '#0f766e'">
                </div>

                <!-- Phone -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; margin-bottom: 8px; color: #333; font-weight: 500; font-size: 14px;">Phone Number</label>
                    <input type="tel" id="regPhone" 
                           style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box;"
                           placeholder="Your phone number"
                           onblur="this.style.borderColor = '#ddd'"
                           onfocus="this.style.borderColor = '#0f766e'">
                </div>

                <!-- Date of Birth -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; margin-bottom: 8px; color: #333; font-weight: 500; font-size: 14px;">Date of Birth</label>
                    <input type="date" id="regDOB" 
                           style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box;"
                           onblur="this.style.borderColor = '#ddd'"
                           onfocus="this.style.borderColor = '#0f766e'">
                </div>

                <!-- Password -->
                <div style="margin-bottom: 18px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <label style="color: #333; font-weight: 500; font-size: 14px;">Password</label>
                        <button type="button" onclick="toggleRegPassword('regPassword')" 
                                style="background: none; border: none; color: #0f766e; cursor: pointer; font-size: 12px;">
                            <span id="regPasswordToggle1">Show</span>
                        </button>
                    </div>
                    <input type="password" id="regPassword" 
                           style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box;"
                           placeholder="At least 6 characters"
                           onblur="this.style.borderColor = '#ddd'"
                           onfocus="this.style.borderColor = '#0f766e'">
                </div>

                <!-- Confirm Password -->
                <div style="margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <label style="color: #333; font-weight: 500; font-size: 14px;">Confirm Password</label>
                        <button type="button" onclick="toggleRegPassword('regConfirmPassword')" 
                                style="background: none; border: none; color: #0f766e; cursor: pointer; font-size: 12px;">
                            <span id="regPasswordToggle2">Show</span>
                        </button>
                    </div>
                    <input type="password" id="regConfirmPassword" 
                           style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box;"
                           placeholder="Confirm your password"
                           onblur="this.style.borderColor = '#ddd'"
                           onfocus="this.style.borderColor = '#0f766e'">
                </div>

                <!-- Status Message -->
                <div id="regStep1Status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; text-align: center;"></div>

                <!-- Next Button -->
                <button type="button" onclick="regGoToStep2()" 
                        style="width: 100%; padding: 12px; background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;"
                        onmouseover="this.style.filter = 'brightness(1.1)'"
                        onmouseout="this.style.filter = 'brightness(1)'">
                    Next: Verify Email →
                </button>

                <!-- Sign In Link -->
                <div style="text-align: center; margin-top: 15px; font-size: 14px; color: #666;">
                    Already have an account? 
                    <button type="button" onclick="switchAuthModal('login')" 
                            style="background: none; border: none; color: #0f766e; cursor: pointer; font-weight: 600;">
                        Sign In
                    </button>
                </div>
            </div>

            <!-- ===== STEP 2: EMAIL OTP ===== -->
            <div id="regStep2" style="display: none;">
                <h2 style="margin: 0 0 10px 0; color: #0f766e; font-size: 24px; font-weight: 600;">Verify Email</h2>
                <p style="margin: 0 0 25px 0; color: #999; font-size: 14px;">Step 2 of 4: Enter the code sent to your email</p>

                <!-- Email Display -->
                <div style="padding: 12px; background: #f0fdfa; border-radius: 8px; margin-bottom: 25px; color: #333; font-size: 14px; text-align: center;">
                    <span id="regOtpEmail" style="font-weight: 600;"></span>
                </div>

                <!-- OTP Input Fields -->
                <div id="otpInputContainer" style="display: flex; gap: 10px; justify-content: center; margin-bottom: 25px;"></div>

                <!-- Resend OTP -->
                <div style="text-align: center; margin-bottom: 25px; font-size: 13px; color: #666;">
                    Didn't receive the code? 
                    <button type="button" id="resendOtpBtn" onclick="regResendOtp()" 
                            style="background: none; border: none; color: #0f766e; cursor: pointer; font-weight: 600; padding: 0;">
                        Resend OTP
                    </button>
                    <span id="otpCountdown" style="margin-left: 10px; color: #999;"></span>
                </div>

                <!-- Status Message -->
                <div id="regStep2Status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; text-align: center;"></div>

                <!-- Buttons -->
                <div style="display: flex; gap: 15px;">
                    <button type="button" onclick="regGoToStep(1)" 
                            style="flex: 1; padding: 12px; border: 2px solid #ddd; background: white; border-radius: 8px; font-size: 16px; font-weight: 600; color: #333; cursor: pointer;">
                        ← Back
                    </button>
                    <button type="button" onclick="regVerifyOtp()" 
                            style="flex: 1; padding: 12px; background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer;">
                        Verify Code
                    </button>
                </div>
            </div>

            <!-- ===== STEP 3: ROLE SELECTION ===== -->
            <div id="regStep3" style="display: none;">
                <h2 style="margin: 0 0 10px 0; color: #0f766e; font-size: 24px; font-weight: 600;">Choose Role</h2>
                <p style="margin: 0 0 25px 0; color: #999; font-size: 14px;">Step 3 of 4: What's your primary role?</p>

                <!-- Role Cards -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
                    <!-- Renter Card -->
                    <div class="role-card" data-role="renter" onclick="selectRole('renter')" 
                         style="padding: 25px; border: 2px solid #ddd; border-radius: 10px; text-align: center; cursor: pointer; transition: all 0.3s ease; background: white;">
                        <div style="font-size: 40px; margin-bottom: 10px;">👤</div>
                        <div style="font-weight: 600; color: #333; font-size: 16px;">Renter</div>
                        <div style="color: #999; font-size: 12px; margin-top: 8px;">Looking to rent a car</div>
                    </div>

                    <!-- Owner Card -->
                    <div class="role-card" data-role="owner" onclick="selectRole('owner')" 
                         style="padding: 25px; border: 2px solid #ddd; border-radius: 10px; text-align: center; cursor: pointer; transition: all 0.3s ease; background: white;">
                        <div style="font-size: 40px; margin-bottom: 10px;">🚗</div>
                        <div style="font-weight: 600; color: #333; font-size: 16px;">Car Owner</div>
                        <div style="color: #999; font-size: 12px; margin-top: 8px;">Looking to rent out your car</div>
                    </div>
                </div>

                <!-- Status Message -->
                <div id="regStep3Status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; text-align: center;"></div>

                <!-- Buttons -->
                <div style="display: flex; gap: 15px;">
                    <button type="button" onclick="regGoToStep(2)" 
                            style="flex: 1; padding: 12px; border: 2px solid #ddd; background: white; border-radius: 8px; font-size: 16px; font-weight: 600; color: #333; cursor: pointer;">
                        ← Back
                    </button>
                    <button type="button" id="regNextBtn" onclick="regGoToStep4()" 
                            style="flex: 1; padding: 12px; background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; opacity: 0.5;"
                            disabled>
                        Next: Confirm →
                    </button>
                </div>
            </div>

            <!-- ===== STEP 4: SUCCESS ===== -->
            <div id="regStep4" style="display: none; text-align: center;">
                <div style="margin-bottom: 30px;">
                    <div style="width: 80px; height: 80px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; margin-bottom: 20px;">
                        <span style="font-size: 40px;">✓</span>
                    </div>
                </div>

                <h2 style="margin: 0 0 10px 0; color: #0f766e; font-size: 28px; font-weight: 600;">Account Created! 🎉</h2>
                <p style="margin: 0 0 25px 0; color: #666; font-size: 16px;">Welcome to PrivateHire</p>

                <div style="padding: 15px; background: #f0fdfa; border-radius: 8px; margin-bottom: 25px; font-size: 14px; color: #333;">
                    <p style="margin: 0;">Your account has been successfully created. You'll be redirected shortly...</p>
                </div>

                <button type="button" onclick="window.location.reload()" 
                        style="width: 100%; padding: 12px; background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer;">
                    Go to Dashboard →
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #registerModalOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        overflow-y: auto;
        animation: fadeIn 0.3s ease;
    }

    #registerModalOverlay .modal {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        animation: slideUp 0.3s ease;
        margin: 20px auto;
    }

    .role-card:hover {
        border-color: #0f766e;
        box-shadow: 0 4px 12px rgba(15, 118, 110, 0.1);
    }

    .role-card.selected {
        border-color: #0f766e;
        background: #f0fdfa;
    }

    .otp-input {
        width: 50px;
        height: 50px;
        font-size: 24px;
        text-align: center;
        border: 2px solid #ddd;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .otp-input:focus {
        border-color: #0f766e;
        outline: none;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 600px) {
        #registerModalOverlay .modal {
            width: 90%;
            max-width: 100%;
        }

        .role-card {
            padding: 20px !important;
        }

        .otp-input {
            width: 40px;
            height: 40px;
            font-size: 18px;
        }
    }
</style>
