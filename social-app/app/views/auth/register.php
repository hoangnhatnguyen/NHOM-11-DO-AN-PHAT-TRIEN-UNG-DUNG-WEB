<section class="auth-shell">
	<div class="row g-4 align-items-center auth-stage mx-auto">
		<div class="col-12 col-lg-6">
			<?php include VIEW_PATH . 'partials/auth/brand_panel.php'; ?>
		</div>

		<div class="col-12 col-lg-6 d-flex justify-content-center justify-content-lg-end">
			<div class="auth-card auth-card-lg">
				<p class="auth-welcome">Chào mừng bạn đến với <strong>FunPoP</strong></p>
				<h1 class="auth-title">Đăng ký</h1>

				<?php include VIEW_PATH . 'partials/auth/form_alert.php'; ?>

				<form method="post" action="<?= BASE_URL ?>/register" novalidate>
					<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

					<div class="row g-3">
						<div class="col-12 col-md-6">
							<label class="form-label auth-label">Nhập tên đăng nhập</label>
							<input
								type="text"
								class="form-control auth-input"
								name="username"
								placeholder="Tên đăng nhập"
								value="<?= htmlspecialchars($oldUsername ?? '') ?>"
								required
							>
						</div>

						<div class="col-12 col-md-6">
							<label class="form-label auth-label">Nhập Email</label>
							<input
								type="email"
								class="form-control auth-input"
								name="email"
								placeholder="Email"
								value="<?= htmlspecialchars($oldEmail ?? '') ?>"
								required
							>
						</div>

						<div class="col-12 col-md-6">
							<label class="form-label auth-label">Nhập mật khẩu</label>
							<input
								type="password"
								class="form-control auth-input"
								name="password"
								placeholder="Mật khẩu"
								required
							>
						</div>

						<div class="col-12 col-md-6">
							<label class="form-label auth-label">Nhập lại mật khẩu</label>
							<input
								type="password"
								class="form-control auth-input"
								name="confirm_password"
								placeholder="Mật khẩu"
								required
							>
						</div>
					</div>

					<div class="d-flex justify-content-between mt-3 mb-3 auth-helper-row">
						<a href="<?= BASE_URL ?>/login">Bạn đã có tài khoản?</a>
					</div>

					<button type="submit" class="btn auth-btn-primary w-100">Tạo tài khoản</button>
				</form>
			</div>
		</div>
	</div>
</section>

