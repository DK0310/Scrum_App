// ============================================
// LOGIN MODAL FUNCTIONS
// ============================================

function showAuthModal(mode = 'login') {
    if (mode === 'login') {
        showLoginModal();
    } else if (mode === 'register') {
        showRegisterModal();
    }
}

function showLoginModal() {
    document.getElementById('loginModalOverlay').style.display = 'flex';
    document.getElementById('loginIdentifier').focus();
}

function closeLoginModal() {
    document.getElementById('loginModalOverlay').style.display = 'none';
    document.getElementById('loginForm').reset();
    document.getElementById('loginStatus').style.display = 'none';
}

function toggleLoginPassword() {
    const passwordField = document.getElementById('loginPassword');
    const toggle = document.getElementById('loginPasswordToggle');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggle.textContent = 'Hide';
    } else {
        passwordField.type = 'password';
        toggle.textContent = 'Show';
    }
}

function showLoginStatus(message, type = 'info') {
    const statusDiv = document.getElementById('loginStatus');
    if (!statusDiv) {
        console.error('loginStatus element not found');
        return;
    }
    
    statusDiv.style.display = 'block';
    statusDiv.textContent = message;
    
    // Clear previous styles
    statusDiv.style.background = '';
    statusDiv.style.color = '';
    statusDiv.style.borderRadius = '6px';
    statusDiv.style.padding = '12px';
    statusDiv.style.marginBottom = '20px';
    statusDiv.style.fontSize = '14px';
    statusDiv.style.textAlign = 'center';
    
    if (type === 'error') {
        statusDiv.style.background = '#fee2e2';
        statusDiv.style.color = '#dc2626';
        statusDiv.style.border = '1px solid #fecaca';
        console.error('Login error:', message);
    } else if (type === 'success') {
        statusDiv.style.background = '#dcfce7';
        statusDiv.style.color = '#16a34a';
        statusDiv.style.border = '1px solid #bbf7d0';
        console.log('Login success:', message);
    } else if (type === 'loading') {
        statusDiv.style.background = '#dbeafe';
        statusDiv.style.color = '#0284c7';
        statusDiv.style.border = '1px solid #bfdbfe';
        console.log('Loading:', message);
    }
}

function showFaceIdLogin() {
    alert('👤 Face ID login coming soon!');
}

// ============================================
// REGISTER MODAL FUNCTIONS
// ============================================

let regSelectedRole = null;
let regOtpResendTimer = 0;

function showRegisterModal() {
    document.getElementById('registerModalOverlay').style.display = 'flex';
    regGoToStep(1);
}

function closeRegisterModal() {
    document.getElementById('registerModalOverlay').style.display = 'none';
    resetRegisterForm();
}

function resetRegisterForm() {
    document.getElementById('regFullName').value = '';
    document.getElementById('regEmail').value = '';
    document.getElementById('regPhone').value = '';
    document.getElementById('regDOB').value = '';
    document.getElementById('regPassword').value = '';
    document.getElementById('regConfirmPassword').value = '';
    regSelectedRole = null;
}

function switchAuthModal(mode) {
    if (mode === 'login') {
        closeRegisterModal();
        showLoginModal();
    } else if (mode === 'register') {
        closeLoginModal();
        showRegisterModal();
    }
}

function regGoToStep(stepNum) {
    // Hide all steps
    for (let i = 1; i <= 4; i++) {
        document.getElementById('regStep' + i).style.display = 'none';
    }

    // Show selected step
    document.getElementById('regStep' + stepNum).style.display = 'block';

    // Update progress dots
    document.querySelectorAll('.step-dot').forEach(dot => {
        const step = parseInt(dot.getAttribute('data-step'));
        if (step <= stepNum) {
            dot.style.background = '#0f766e';
            dot.style.color = 'white';
        } else {
            dot.style.background = '#e0e0e0';
            dot.style.color = '#999';
        }
    });

    // Step 2 specific: create OTP inputs
    if (stepNum === 2) {
        document.getElementById('regOtpEmail').textContent = document.getElementById('regEmail').value;
        createOtpInputs();
    }

    // Step 3 specific: reset role selection
    if (stepNum === 3) {
        regSelectedRole = null;
        document.querySelectorAll('.role-card').forEach(card => {
            card.classList.remove('selected');
        });
        document.getElementById('regNextBtn').style.opacity = '0.5';
        document.getElementById('regNextBtn').disabled = true;
    }
}

async function regGoToStep2() {
    const fullName = document.getElementById('regFullName').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const phone = document.getElementById('regPhone').value.trim();
    const dob = document.getElementById('regDOB').value;
    const password = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('regConfirmPassword').value;
    const statusDiv = document.getElementById('regStep1Status');

    // Validation
    if (!fullName || !email || !phone || !dob || !password || !confirmPassword) {
        showRegStatus('Please fill in all fields', 'error', 1);
        return;
    }

    if (!email.includes('@')) {
        showRegStatus('Invalid email format', 'error', 1);
        return;
    }

    if (password.length < 6) {
        showRegStatus('Password must be at least 6 characters', 'error', 1);
        return;
    }

    if (password !== confirmPassword) {
        showRegStatus('Passwords do not match', 'error', 1);
        return;
    }

    // Check age (18+)
    const dobDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - dobDate.getFullYear();
    const monthDiff = today.getMonth() - dobDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
        age--;
    }

    if (age < 18) {
        showRegStatus('You must be at least 18 years old', 'error', 1);
        return;
    }

    // Send OTP
    showRegStatus('Sending verification code...', 'loading', 1);

    try {
        const formData = new FormData();
        formData.append('action', 'send-otp');
        formData.append('email', email);

        const response = await fetch('/api/register.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            // Hide status and move to step 2
            document.getElementById('regStep1Status').style.display = 'none';
            regGoToStep(2);
            startOtpCountdown();
        } else {
            showRegStatus('✗ ' + (result.message || 'Failed to send OTP'), 'error', 1);
        }
    } catch (error) {
        console.error('OTP send error:', error);
        showRegStatus('✗ Network error. Please try again.', 'error', 1);
    }
}

function createOtpInputs() {
    const container = document.getElementById('otpInputContainer');
    container.innerHTML = '';

    for (let i = 0; i < 6; i++) {
        const input = document.createElement('input');
        input.type = 'text';
        input.maxLength = '1';
        input.className = 'otp-input';
        input.id = 'otp' + i;
        input.inputMode = 'numeric';
        input.style.width = '50px';
        input.style.height = '50px';
        input.style.fontSize = '24px';
        input.style.textAlign = 'center';
        input.style.border = '2px solid #ddd';
        input.style.borderRadius = '8px';
        input.style.fontWeight = '600';
        input.style.transition = 'all 0.3s ease';

        input.addEventListener('input', (e) => {
            if (e.target.value && i < 5) {
                document.getElementById('otp' + (i + 1)).focus();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && i > 0) {
                document.getElementById('otp' + (i - 1)).focus();
            }
        });

        container.appendChild(input);
    }

    document.getElementById('otp0').focus();
}

async function regVerifyOtp() {
    const email = document.getElementById('regEmail').value;
    let otpCode = '';

    for (let i = 0; i < 6; i++) {
        otpCode += document.getElementById('otp' + i).value;
    }

    if (otpCode.length !== 6) {
        showRegStatus('Please enter all 6 digits', 'error', 2);
        return;
    }

    showRegStatus('Verifying code...', 'loading', 2);

    try {
        const formData = new FormData();
        formData.append('action', 'verify-otp');
        formData.append('email', email);
        formData.append('otp', otpCode);

        const response = await fetch('/api/register.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            document.getElementById('regStep2Status').style.display = 'none';
            regGoToStep(3);
        } else {
            showRegStatus('✗ ' + (result.message || 'Invalid code'), 'error', 2);
        }
    } catch (error) {
        console.error('OTP verify error:', error);
        showRegStatus('✗ Network error. Please try again.', 'error', 2);
    }
}

function regResendOtp() {
    if (regOtpResendTimer > 0) return;

    const email = document.getElementById('regEmail').value;
    const btn = document.getElementById('resendOtpBtn');
    btn.disabled = true;
    btn.style.opacity = '0.5';

    const formData = new FormData();
    formData.append('action', 'send-otp');
    formData.append('email', email);

    fetch('/api/register.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            startOtpCountdown();
        }
    })
    .catch(err => console.error('Resend error:', err));
}

function startOtpCountdown() {
    regOtpResendTimer = 60;
    const countdownEl = document.getElementById('otpCountdown');
    const btn = document.getElementById('resendOtpBtn');

    const timer = setInterval(() => {
        regOtpResendTimer--;
        if (regOtpResendTimer > 0) {
            countdownEl.textContent = 'Resend in ' + regOtpResendTimer + 's';
        } else {
            clearInterval(timer);
            countdownEl.textContent = '';
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    }, 1000);
}

function selectRole(role) {
    regSelectedRole = role;

    // Update card styling
    document.querySelectorAll('.role-card').forEach(card => {
        if (card.getAttribute('data-role') === role) {
            card.classList.add('selected');
            card.style.borderColor = '#0f766e';
            card.style.background = '#f0fdfa';
        } else {
            card.classList.remove('selected');
            card.style.borderColor = '#ddd';
            card.style.background = 'white';
        }
    });

    // Enable next button
    document.getElementById('regNextBtn').style.opacity = '1';
    document.getElementById('regNextBtn').disabled = false;
}

async function regGoToStep4() {
    if (!regSelectedRole) {
        showRegStatus('Please select a role', 'error', 3);
        return;
    }

    const fullName = document.getElementById('regFullName').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const phone = document.getElementById('regPhone').value.trim();
    const dob = document.getElementById('regDOB').value;
    const password = document.getElementById('regPassword').value;
    const statusDiv = document.getElementById('regStep3Status');

    showRegStatus('Creating your account...', 'loading', 3);

    try {
        const formData = new FormData();
        formData.append('action', 'register');
        formData.append('full_name', fullName);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('dob', dob);
        formData.append('password', password);
        formData.append('role', regSelectedRole);

        const response = await fetch('/api/register.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            showRegStatus('✗ ' + (result.message || 'Unable to create account'), 'error', 3);
            return;
        }

        statusDiv.style.display = 'none';
        regGoToStep(4);

        // Auto-redirect after success so navbar/session can refresh
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    } catch (error) {
        console.error('Register error:', error);
        showRegStatus('✗ Network error. Please try again.', 'error', 3);
    }
}

function toggleRegPassword(fieldId) {
    const field = document.getElementById(fieldId);
    const toggle = document.getElementById(fieldId === 'regPassword' ? 'regPasswordToggle1' : 'regPasswordToggle2');

    if (field.type === 'password') {
        field.type = 'text';
        toggle.textContent = 'Hide';
    } else {
        field.type = 'password';
        toggle.textContent = 'Show';
    }
}

function showRegStatus(message, type = 'info', step = 1) {
    const statusDiv = document.getElementById('regStep' + step + 'Status');
    statusDiv.style.display = 'block';
    statusDiv.textContent = message;

    if (type === 'error') {
        statusDiv.style.background = '#fee2e2';
        statusDiv.style.color = '#dc2626';
    } else if (type === 'success') {
        statusDiv.style.background = '#dcfce7';
        statusDiv.style.color = '#16a34a';
    } else if (type === 'loading') {
        statusDiv.style.background = '#dbeafe';
        statusDiv.style.color = '#0284c7';
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    // Close modals on overlay click
    document.getElementById('loginModalOverlay')?.addEventListener('click', (e) => {
        if (e.target.id === 'loginModalOverlay') {
            closeLoginModal();
        }
    });

    document.getElementById('registerModalOverlay')?.addEventListener('click', (e) => {
        if (e.target.id === 'registerModalOverlay') {
            closeRegisterModal();
        }
    });

    // Prevent modal close when clicking inside
    document.querySelector('#loginModalOverlay .modal')?.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    document.querySelector('#registerModalOverlay .modal')?.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    // Login form: PHP handles auth logic, JS only updates visual state
    document.getElementById('loginForm')?.addEventListener('submit', (e) => {
        const identifier = document.getElementById('loginIdentifier')?.value.trim() || '';
        const password = document.getElementById('loginPassword')?.value || '';

        if (!identifier || !password) {
            e.preventDefault();
            showLoginStatus('Please fill in all fields', 'error');
            return;
        }

        showLoginStatus('Signing in...', 'loading');
    });
});
