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

let regOtpResendTimer = 0;
let regUsernameCheckTimer = null;
let regUsernameRequestSeq = 0;

function showRegisterModal() {
    document.getElementById('registerModalOverlay').style.display = 'flex';
    setDOBDateRange();
    regGoToStep(1);
}

function closeRegisterModal() {
    document.getElementById('registerModalOverlay').style.display = 'none';
    resetRegisterForm();
}

function resetRegisterForm() {
    document.getElementById('regUsername').value = '';
    document.getElementById('regEmail').value = '';
    document.getElementById('regPhone').value = '';
    document.getElementById('regDOB').value = '';
    document.getElementById('regPassword').value = '';
    document.getElementById('regConfirmPassword').value = '';
    document.getElementById('regUsernameError').style.display = 'none';
    document.getElementById('regEmailError').style.display = 'none';
    document.getElementById('regPhoneError').style.display = 'none';
    document.getElementById('regDOBError').style.display = 'none';
    const passError = document.getElementById('regPasswordError');
    const confirmError = document.getElementById('regConfirmPasswordError');
    if (passError) passError.style.display = 'none';
    if (confirmError) confirmError.style.display = 'none';
    setDOBDateRange();
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
    for (let i = 1; i <= 3; i++) {
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
}

// Real-time validation functions
function setDOBDateRange() {
    const dobInput = document.getElementById('regDOB');
    const today = new Date();
    
    // Maximum date: today (must be at least 18 today)
    // Minimum date: 100 years ago
    const maxDate = today.toISOString().split('T')[0]; // Today
    
    const minDate = new Date(today);
    minDate.setFullYear(today.getFullYear() - 100);
    const minDateStr = minDate.toISOString().split('T')[0]; // 100 years ago
    
    dobInput.max = maxDate;
    dobInput.min = minDateStr;
}

async function checkRegUsernameAvailability(username, requestId) {
    const errorDiv = document.getElementById('regUsernameError');

    try {
        const formData = new FormData();
        formData.append('action', 'check-username');
        formData.append('username', username);

        const response = await fetch('/api/register.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        // Ignore stale responses while user keeps typing.
        if (requestId !== regUsernameRequestSeq) {
            return true;
        }

        if (!result.success) {
            errorDiv.textContent = result.message || 'Username already taken';
            errorDiv.style.display = 'block';
            return false;
        }

        errorDiv.style.display = 'none';
        return true;
    } catch (error) {
        console.error('Username validation error:', error);
        return false;
    }
}

async function validateRegUsername(immediate = false) {
    const username = document.getElementById('regUsername').value.trim();
    const errorDiv = document.getElementById('regUsernameError');
    
    if (!username) {
        errorDiv.style.display = 'none';
        return false;
    }

    if (username.length < 3) {
        errorDiv.textContent = 'Username must be at least 3 characters';
        errorDiv.style.display = 'block';
        return false;
    }

    // For click Next, run DB check immediately.
    if (immediate) {
        regUsernameRequestSeq += 1;
        return checkRegUsernameAvailability(username, regUsernameRequestSeq);
    }

    // Debounce DB calls while typing.
    if (regUsernameCheckTimer) {
        clearTimeout(regUsernameCheckTimer);
    }
    regUsernameCheckTimer = setTimeout(() => {
        regUsernameRequestSeq += 1;
        checkRegUsernameAvailability(username, regUsernameRequestSeq);
    }, 300);

    return true;
}

function validateRegPasswords() {
    const password = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('regConfirmPassword').value;
    const passError = document.getElementById('regPasswordError');
    const confirmError = document.getElementById('regConfirmPasswordError');

    if (passError) {
        if (!password) {
            passError.style.display = 'none';
        } else if (password.length < 6) {
            passError.textContent = 'Password must be at least 6 characters';
            passError.style.display = 'block';
        } else {
            passError.style.display = 'none';
        }
    }

    if (confirmError) {
        if (!confirmPassword) {
            confirmError.style.display = 'none';
        } else if (password !== confirmPassword) {
            confirmError.textContent = 'Passwords do not match';
            confirmError.style.display = 'block';
        } else {
            confirmError.style.display = 'none';
        }
    }

    return password.length >= 6 && confirmPassword.length > 0 && password === confirmPassword;
}

async function validateRegEmail() {
    const email = document.getElementById('regEmail').value.trim();
    const errorDiv = document.getElementById('regEmailError');
    
    if (!email) {
        errorDiv.style.display = 'none';
        return;
    }

    if (!email.includes('@')) {
        errorDiv.textContent = 'Invalid email format';
        errorDiv.style.display = 'block';
        return;
    }

    // Check if email exists
    try {
        const formData = new FormData();
        formData.append('action', 'check-email');
        formData.append('email', email);

        const response = await fetch('/api/register.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            errorDiv.textContent = result.message || 'Email already registered';
            errorDiv.style.display = 'block';
        } else {
            errorDiv.style.display = 'none';
        }
    } catch (error) {
        console.error('Email validation error:', error);
    }
}

async function validateRegPhone() {
    const phone = document.getElementById('regPhone').value.trim();
    const errorDiv = document.getElementById('regPhoneError');
    
    if (!phone) {
        errorDiv.style.display = 'none';
        return;
    }

    // Basic phone validation: at least 10 digits
    const digitsOnly = phone.replace(/\D/g, '');
    if (digitsOnly.length < 10) {
        errorDiv.textContent = 'Phone number must have at least 10 digits';
        errorDiv.style.display = 'block';
        return;
    }

    // Check if phone exists
    try {
        const formData = new FormData();
        formData.append('action', 'check-phone');
        formData.append('phone', phone);

        const response = await fetch('/api/register.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            errorDiv.textContent = result.message || 'Phone already registered';
            errorDiv.style.display = 'block';
        } else {
            errorDiv.style.display = 'none';
        }
    } catch (error) {
        console.error('Phone validation error:', error);
    }
}

function validateRegAge() {
    const dob = document.getElementById('regDOB').value.trim();
    let errorDiv = document.getElementById('regDOBError');
    
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = 'regDOBError';
        errorDiv.style.color = '#dc2626';
        errorDiv.style.fontSize = '12px';
        errorDiv.style.marginTop = '4px';
        document.getElementById('regDOB').parentNode.appendChild(errorDiv);
    }
    
    if (!dob) {
        errorDiv.style.display = 'none';
        return;
    }

    const dobDate = new Date(dob + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    let age = today.getFullYear() - dobDate.getFullYear();
    const monthDiff = today.getMonth() - dobDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
        age--;
    }

    // Check age constraints
    if (age < 18) {
        errorDiv.textContent = 'You must be at least 18 years old';
        errorDiv.style.display = 'block';
    } else if (age > 100) {
        errorDiv.textContent = 'Age cannot exceed 100 years';
        errorDiv.style.display = 'block';
    } else {
        errorDiv.style.display = 'none';
    }
}

async function regGoToStep2() {
    const username = document.getElementById('regUsername').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const phone = document.getElementById('regPhone').value.trim();
    const dob = document.getElementById('regDOB').value.trim();
    const password = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('regConfirmPassword').value;
    const statusDiv = document.getElementById('regStep1Status');

    if (!username || !email || !phone || !dob || !password || !confirmPassword) {
        showRegStatus('Please fill in all fields', 'error', 1);
        return;
    }

    if (username.length < 3) {
        showRegStatus('Username must be at least 3 characters', 'error', 1);
        return;
    }

    const usernameAvailable = await validateRegUsername(true);
    if (!usernameAvailable) {
        showRegStatus('Username already taken. Please choose another one.', 'error', 1);
        return;
    }

    if (!email.includes('@')) {
        showRegStatus('Invalid email format', 'error', 1);
        return;
    }

    const phoneDigits = phone.replace(/\D/g, '');
    if (phoneDigits.length < 10) {
        showRegStatus('Phone number must have at least 10 digits', 'error', 1);
        return;
    }

    if (password.length < 6) {
        showRegStatus('Password must be at least 6 characters', 'error', 1);
        return;
    }

    if (!validateRegPasswords()) {
        showRegStatus('Passwords do not match', 'error', 1);
        return;
    }

    // Check age (18+) - convert date string to proper date object
    const dobDate = new Date(dob + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    let age = today.getFullYear() - dobDate.getFullYear();
    const monthDiff = today.getMonth() - dobDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
        age--;
    }

    if (age < 18) {
        showRegStatus('You must be at least 18 years old', 'error', 1);
        return;
    }

    if (age > 100) {
        showRegStatus('Age cannot exceed 100 years', 'error', 1);
        return;
    }

    // Send OTP
    showRegStatus('Sending verification code...', 'loading', 1);

    try {
        const formData = new FormData();
        formData.append('action', 'send-otp');
        formData.append('username', username);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('dob', dob);
        formData.append('password', password);

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

            // Auto-redirect after successful account creation.
            setTimeout(() => {
                window.location.reload();
            }, 1500);
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
    formData.append('action', 'resend-otp');
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
