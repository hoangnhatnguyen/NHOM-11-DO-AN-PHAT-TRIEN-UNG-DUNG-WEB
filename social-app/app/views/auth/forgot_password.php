<section class="auth-shell">
	<div class="row g-4 align-items-center auth-stage mx-auto">
		<div class="col-12 col-lg-6">
			<?php include VIEW_PATH . 'partials/auth/brand_panel.php'; ?>
		</div>

		<div class="col-12 col-lg-6 d-flex justify-content-center justify-content-lg-end">
			<div class="auth-card auth-card-sm">
				<p class="auth-welcome">Chào mừng bạn đến với <strong>FunPoP</strong></p>
				<h1 class="auth-title">Quên mật khẩu</h1>

				<?php include VIEW_PATH . 'partials/auth/form_alert.php'; ?>

				<form method="post" action="<?= BASE_URL ?>/forgot-password" novalidate>
					<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

					<label class="form-label auth-label">Nhập Email</label>
					<input
						type="email"
						class="form-control auth-input mb-3"
						name="email"
						placeholder="Email"
						value="<?= htmlspecialchars($oldEmail ?? '') ?>"
						required
					>

					<button type="submit" class="btn auth-btn-primary w-100">Xác thực tài khoản</button>
				</form>

				<div class="text-center mt-3 auth-helper-row">
					<a href="<?= BASE_URL ?>/login">Quay lại đăng nhập</a>
				</div>
			</div>
		</div>
	</div>
</section>

