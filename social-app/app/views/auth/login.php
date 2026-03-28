<section class="auth-shell">
	<div class="row g-4 align-items-center auth-stage mx-auto">
		<div class="col-12 col-lg-6">
			<?php include VIEW_PATH . 'partials/auth/brand_panel.php'; ?>
		</div>

		<div class="col-12 col-lg-6 d-flex justify-content-center justify-content-lg-end">
			<div class="auth-card auth-card-sm">
				<p class="auth-welcome">Chào mừng bạn đến với <strong>FunPoP</strong></p>
				<h1 class="auth-title">Đăng nhập</h1>

				<?php include VIEW_PATH . 'partials/auth/form_alert.php'; ?>

				<form method="post" action="<?= BASE_URL ?>/login" novalidate>
					<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

					<label class="form-label auth-label">Email của bạn</label>
					<input
						type="email"
						class="form-control auth-input mb-3"
						name="email"
						placeholder="Email"
						value="<?= htmlspecialchars($oldEmail ?? '') ?>"
						required
					>

					<label class="form-label auth-label">Mật khẩu</label>
					<input
						type="password"
						class="form-control auth-input mb-3"
						name="password"
						placeholder="Mật khẩu"
						required
					>

					<div class="d-flex justify-content-between mb-3 auth-helper-row">
						<a href="<?= BASE_URL ?>/register">Bạn chưa có tài khoản?</a>
						<a href="<?= BASE_URL ?>/forgot-password">Quên mật khẩu</a>
					</div>

					<button type="submit" class="btn auth-btn-primary w-100">Đăng nhập</button>
				</form>
			</div>
		</div>
	</div>
</section>

