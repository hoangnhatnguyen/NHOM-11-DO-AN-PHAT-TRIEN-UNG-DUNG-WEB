<section class="auth-shell">
    <div class="row g-4 align-items-center auth-stage mx-auto">
        <div class="col-12 col-lg-6">
            <?php include VIEW_PATH . 'partials/auth/brand_panel.php'; ?>
        </div>

        <div class="col-12 col-lg-6 d-flex justify-content-center justify-content-lg-end">
            <div class="auth-card auth-card-sm">
                <h1 class="auth-title">Thông báo</h1>
                <p class="mb-2 fs-5 fw-semibold">Chúng tôi đã gửi liên kết xác thực đến địa chỉ email của bạn.</p>
                <p class="text-secondary mb-0">Hãy kiểm tra hộp thư đến và làm theo hướng dẫn để đặt lại mật khẩu.</p>
                <div class="mt-4">
                    <a href="<?= BASE_URL ?>/login" class="btn auth-btn-primary w-100">Về trang đăng nhập</a>
                </div>
            </div>
        </div>
    </div>
</section>
