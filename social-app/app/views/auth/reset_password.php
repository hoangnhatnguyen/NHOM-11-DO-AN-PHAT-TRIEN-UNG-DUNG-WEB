<section class="auth-shell">
    <div class="row g-4 align-items-center auth-stage mx-auto">
        <div class="col-12 col-lg-6">
            <?php include VIEW_PATH . 'partials/auth/brand_panel.php'; ?>
        </div>

        <div class="col-12 col-lg-6 d-flex justify-content-center justify-content-lg-end">
            <div class="auth-card auth-card-sm">
                <h1 class="auth-title mt-1">Đặt lại mật khẩu</h1>

                <?php include VIEW_PATH . 'partials/auth/form_alert.php'; ?>

                <form method="post" action="<?= BASE_URL ?>/reset-password/<?= urlencode($token ?? '') ?>" novalidate>
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

                    <label class="form-label auth-label">Nhập mật khẩu mới</label>
                    <input
                        type="password"
                        class="form-control auth-input mb-3"
                        name="password"
                        placeholder="Mật khẩu"
                        required
                    >

                    <label class="form-label auth-label">Xác nhận lại mật khẩu mới</label>
                    <input
                        type="password"
                        class="form-control auth-input mb-3"
                        name="confirm_password"
                        placeholder="Mật khẩu"
                        required
                    >

                    <button type="submit" class="btn auth-btn-primary w-100">Xác nhận</button>
                </form>
            </div>
        </div>
    </div>
</section>
