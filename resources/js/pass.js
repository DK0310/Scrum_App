const PASSWORD_CHANGE_API = '/api/password-change.php';

function passSetStatus(message, type = 'info') {
	const status = document.getElementById('passStatus');
	if (!status) return;

	status.style.display = 'block';
	status.textContent = message;
	status.style.border = '1px solid transparent';

	if (type === 'success') {
		status.style.background = '#dcfce7';
		status.style.color = '#166534';
		status.style.borderColor = '#bbf7d0';
		return;
	}

	if (type === 'error') {
		status.style.background = '#fee2e2';
		status.style.color = '#991b1b';
		status.style.borderColor = '#fecaca';
		return;
	}

	status.style.background = '#dbeafe';
	status.style.color = '#1e40af';
	status.style.borderColor = '#bfdbfe';
}

async function passVerifyTokenOnLoad() {
	const tokenInput = document.getElementById('resetToken');
	if (!tokenInput) return;

	const token = tokenInput.value.trim();
	if (!token) {
		passSetStatus('Reset token missing.', 'error');
		return;
	}

	try {
		const res = await fetch(PASSWORD_CHANGE_API, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ action: 'verify-token', token })
		});
		const data = await res.json();

		if (!data.success || !data.valid) {
			passSetStatus(data.message || 'This reset link is invalid or expired.', 'error');
			const form = document.getElementById('resetPasswordForm');
			if (form) form.style.display = 'none';
			return;
		}

		passSetStatus('Reset link verified. Please enter your new password.', 'success');
	} catch (error) {
		passSetStatus('Unable to verify reset link right now. Please try again.', 'error');
		console.error(error);
	}
}

async function passHandleRequestReset(e) {
	e.preventDefault();

	const emailInput = document.getElementById('resetEmail');
	const submitBtn = document.getElementById('requestResetBtn');
	if (!emailInput || !submitBtn) return;

	const email = emailInput.value.trim();
	if (!email) {
		passSetStatus('Please enter your account email.', 'error');
		return;
	}

	submitBtn.disabled = true;
	submitBtn.textContent = 'Sending...';

	try {
		const res = await fetch(PASSWORD_CHANGE_API, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ action: 'send-reset-link', email })
		});

		const data = await res.json();
		passSetStatus(data.message || 'If an account exists for this email, a reset link has been sent.', 'success');
	} catch (error) {
		passSetStatus('Unable to request reset link right now. Please try again.', 'error');
		console.error(error);
	}

	submitBtn.disabled = false;
	submitBtn.textContent = 'Send Reset Link';
}

async function passHandleResetPassword(e) {
	e.preventDefault();

	const token = document.getElementById('resetToken')?.value.trim() || '';
	const newPassword = document.getElementById('newPassword')?.value || '';
	const confirmPassword = document.getElementById('confirmPassword')?.value || '';
	const submitBtn = document.getElementById('resetPasswordBtn');

	if (!token) {
		passSetStatus('Reset token missing.', 'error');
		return;
	}

	if (!newPassword || !confirmPassword) {
		passSetStatus('Please fill in both password fields.', 'error');
		return;
	}

	if (newPassword.length < 6) {
		passSetStatus('Password must be at least 6 characters.', 'error');
		return;
	}

	if (newPassword !== confirmPassword) {
		passSetStatus('Passwords do not match.', 'error');
		return;
	}

	if (!submitBtn) return;

	submitBtn.disabled = true;
	submitBtn.textContent = 'Updating...';

	try {
		const res = await fetch(PASSWORD_CHANGE_API, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				action: 'reset-password',
				token,
				new_password: newPassword,
				confirm_password: confirmPassword
			})
		});

		const data = await res.json();
		if (!data.success) {
			passSetStatus(data.message || 'Could not reset password.', 'error');
			submitBtn.disabled = false;
			submitBtn.textContent = 'Update Password';
			return;
		}

		passSetStatus(data.message || 'Password reset successful.', 'success');
		submitBtn.textContent = 'Success';

		setTimeout(() => {
			window.location.href = data.redirect || '/';
		}, 1200);
	} catch (error) {
		passSetStatus('Unable to reset password right now. Please try again.', 'error');
		submitBtn.disabled = false;
		submitBtn.textContent = 'Update Password';
		console.error(error);
	}
}

document.addEventListener('DOMContentLoaded', () => {
	const requestForm = document.getElementById('requestResetForm');
	if (requestForm) {
		requestForm.addEventListener('submit', passHandleRequestReset);
	}

	const resetForm = document.getElementById('resetPasswordForm');
	if (resetForm) {
		resetForm.addEventListener('submit', passHandleResetPassword);
		passVerifyTokenOnLoad();
	}
});

