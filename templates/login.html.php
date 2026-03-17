<!-- LOGIN MODAL OVERLAY -->
<div id="loginModalOverlay" class="modal-overlay" style="display: none;">
    <div class="modal" style="max-width: 500px;">
        <!-- Close Button -->
        <button onclick="closeLoginModal()" class="modal-close" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">✕</button>

        <div style="padding: 40px;">
            <!-- Header -->
            <div style="margin-bottom: 30px;">
                <h2 style="margin: 0 0 10px 0; color: #0f766e; font-size: 28px; font-weight: 600;">Sign In</h2>
                <p style="margin: 0; color: #999; font-size: 14px;">Welcome back to PrivateHire</p>
            </div>

            <!-- Login Form -->
            <form id="loginForm" style="margin-bottom: 25px;">
                <!-- Email/Username Input -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: #333; font-weight: 500; font-size: 14px;">Email or Username</label>
                    <input type="text" id="loginIdentifier" name="identifier" 
                           style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box; transition: all 0.3s ease;"
                           placeholder="Enter your email or username"
                           onblur="this.style.borderColor = '#ddd'"
                           onfocus="this.style.borderColor = '#0f766e'">
                </div>

                <!-- Password Input -->
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label style="color: #333; font-weight: 500; font-size: 14px;">Password</label>
                        <button type="button" onclick="toggleLoginPassword()" 
                                style="background: none; border: none; color: #0f766e; cursor: pointer; font-size: 12px; text-decoration: none;">
                            <span id="loginPasswordToggle">Show</span>
                        </button>
                    </div>
                    <input type="password" id="loginPassword" name="password" 
                           style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box; transition: all 0.3s ease;"
                           placeholder="Enter your password"
                           onblur="this.style.borderColor = '#ddd'"
                           onfocus="this.style.borderColor = '#0f766e'">
                </div>

                <!-- Remember Me -->
                <div style="display: flex; align-items: center; margin-bottom: 25px;">
                    <input type="checkbox" id="rememberMe" style="margin-right: 8px;">
                    <label for="rememberMe" style="color: #666; font-size: 14px; cursor: pointer;">Remember me</label>
                </div>

                <!-- Status Message -->
                <div id="loginStatus" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; text-align: center;"></div>

                <!-- Sign In Button -->
                <button type="button" onclick="doLogin()" 
                        style="width: 100%; padding: 12px; background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;"
                        onmouseover="this.style.filter = 'brightness(1.1)'"
                        onmouseout="this.style.filter = 'brightness(1)'">
                    Sign In
                </button>
            </form>

            <!-- Divider -->
            <div style="display: flex; align-items: center; margin: 25px 0; color: #999; font-size: 13px;">
                <div style="flex: 1; height: 1px; background: #ddd;"></div>
                <span style="margin: 0 15px;">or</span>
                <div style="flex: 1; height: 1px; background: #ddd;"></div>
            </div>

            <!-- Face ID Button -->
            <button type="button" onclick="showFaceIdLogin()" 
                    style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; background: white; border-radius: 8px; font-size: 16px; font-weight: 600; color: #333; cursor: pointer; margin-bottom: 25px; transition: all 0.3s ease;"
                    onmouseover="this.style.borderColor = '#0f766e'; this.style.color = '#0f766e';"
                    onmouseout="this.style.borderColor = '#e0e0e0'; this.style.color = '#333';">
                👤 Sign In with Face ID
            </button>

            <!-- Sign Up Link -->
            <div style="text-align: center; font-size: 14px; color: #666;">
                Don't have an account? 
                <button type="button" onclick="switchAuthModal('register')" 
                        style="background: none; border: none; color: #0f766e; cursor: pointer; font-weight: 600; text-decoration: none; padding: 0;">
                    Sign Up
                </button>
            </div>

            <!-- Forgot Password -->
            <div style="text-align: center; margin-top: 15px; font-size: 13px;">
                <button type="button" onclick="alert('Password reset coming soon')" 
                        style="background: none; border: none; color: #999; cursor: pointer; text-decoration: none; padding: 0;">
                    Forgot password?
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #loginModalOverlay {
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
        animation: fadeIn 0.3s ease;
    }

    #loginModalOverlay .modal {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        animation: slideUp 0.3s ease;
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
        #loginModalOverlay .modal {
            width: 90%;
            max-width: 100%;
        }
    }
</style>
