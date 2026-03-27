<?php
$brandTitle = $brandTitle ?? APP_NAME;
$brandTagline = $brandTagline ?? 'Pop your passion.';
$brandDesc = $brandDesc ?? 'Chào mừng bạn đến với FunPoP – thế giới nơi fan tự hội và cảm xúc thăng hoa. Đăng nhập hoặc tạo tài khoản để khám phá cộng đồng fandom của bạn!';
?>
<div class="auth-brand-panel">
    <img src="<?= BASE_URL ?>/public/images/app-logo-1.png" alt="<?= htmlspecialchars(APP_NAME) ?>" class="auth-brand-logo"/>
    <h2 class="auth-brand-title mb-1"><?= htmlspecialchars($brandTitle) ?></h2>
    <p class="auth-brand-tagline mb-3"><?= htmlspecialchars($brandTagline) ?></p>
    <p class="auth-brand-desc mb-0"><?= htmlspecialchars($brandDesc) ?></p>
</div>
