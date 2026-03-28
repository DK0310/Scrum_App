<?php include __DIR__ . '/layout/header.html.php'; ?>

<?php
$resetToken = trim((string)($_GET['token'] ?? ''));
$source = trim((string)($_GET['source'] ?? ''));
$isTokenMode = $resetToken !== '';
?>

<section class="section" style="padding-top:110px;min-height:100vh;background:linear-gradient(180deg,#f0fdfa 0%,#f8fafc 45%,#ffffff 100%);">
	<div class="section-container" style="max-width:760px;">
		<div style="background:white;border-radius:18px;box-shadow:0 18px 45px rgba(15,118,110,0.12);overflow:hidden;border:1px solid #ccfbf1;">
			<div style="padding:32px 28px;background:linear-gradient(135deg,#0f766e 0%,#0d9488 55%,#14b8a6 100%);color:white;">
				<p style="margin:0;font-size:0.85rem;letter-spacing:.08em;text-transform:uppercase;opacity:0.85;">Account Security</p>
				<h1 style="margin:10px 0 6px 0;font-size:1.9rem;font-weight:800;line-height:1.2;">Reset Your Password</h1>
				<p style="margin:0;font-size:0.95rem;opacity:0.95;">Use a secure new password to keep your account protected.</p>
			</div>

			<div style="padding:26px 28px 30px 28px;">
				<?php if ($source === 'profile'): ?>
				<div style="background:#ecfeff;border:1px solid #a5f3fc;color:#155e75;padding:12px 14px;border-radius:10px;margin-bottom:18px;font-size:0.9rem;">
					You started this reset from your profile security settings.
				</div>
				<?php endif; ?>

				<div id="passStatus" style="display:none;padding:12px 14px;border-radius:10px;margin-bottom:18px;font-size:0.9rem;"></div>

				<?php if ($isTokenMode): ?>
				<form id="resetPasswordForm" style="display:flex;flex-direction:column;gap:14px;">
					<input type="hidden" id="resetToken" value="<?= htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8') ?>">

					<div>
						<label for="newPassword" style="display:block;font-weight:600;color:#134e4a;margin-bottom:8px;">New password</label>
						<input id="newPassword" type="password" autocomplete="new-password" placeholder="Enter new password" style="width:100%;padding:12px 14px;border:1.5px solid #99f6e4;border-radius:10px;font-size:0.95rem;">
					</div>

					<div>
						<label for="confirmPassword" style="display:block;font-weight:600;color:#134e4a;margin-bottom:8px;">Confirm new password</label>
						<input id="confirmPassword" type="password" autocomplete="new-password" placeholder="Confirm new password" style="width:100%;padding:12px 14px;border:1.5px solid #99f6e4;border-radius:10px;font-size:0.95rem;">
					</div>

					<p style="font-size:0.82rem;color:#0f766e;margin:2px 0 0 0;">Minimum length: 6 characters.</p>

					<button id="resetPasswordBtn" type="submit" style="margin-top:4px;padding:12px 16px;border:none;border-radius:10px;background:linear-gradient(135deg,#0f766e,#14b8a6);color:white;font-size:1rem;font-weight:700;cursor:pointer;">
						Update Password
					</button>
				</form>
				<?php else: ?>
				<form id="requestResetForm" style="display:flex;flex-direction:column;gap:14px;">
					<div>
						<label for="resetEmail" style="display:block;font-weight:600;color:#134e4a;margin-bottom:8px;">Account email</label>
						<input id="resetEmail" type="email" autocomplete="email" placeholder="Enter your account email" style="width:100%;padding:12px 14px;border:1.5px solid #99f6e4;border-radius:10px;font-size:0.95rem;">
					</div>

					<p style="font-size:0.85rem;color:#0f766e;margin:2px 0 0 0;">If your email exists, we will send a link valid for 5 minutes.</p>

					<button id="requestResetBtn" type="submit" style="margin-top:4px;padding:12px 16px;border:none;border-radius:10px;background:linear-gradient(135deg,#0f766e,#14b8a6);color:white;font-size:1rem;font-weight:700;cursor:pointer;">
						Send Reset Link
					</button>
				</form>
				<?php endif; ?>

				<div style="margin-top:20px;font-size:0.9rem;color:#475569;">
					Remembered your password?
					<a href="/" style="color:#0f766e;font-weight:700;text-decoration:none;">Back to Sign In</a>
				</div>
			</div>
		</div>
	</div>
</section>

<script src="/resources/js/pass.js"></script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>

