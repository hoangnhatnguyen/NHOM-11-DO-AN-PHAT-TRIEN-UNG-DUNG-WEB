<?php
$composerName = (string) (($currentUser['username'] ?? 'Người dùng'));
$composerColor = Avatar::colors($composerName);
?>

<ul class="nav nav-underline justify-content-center mb-3 feed-tabs">
	<li class="nav-item"><a class="nav-link active" href="#">Dành cho bạn</a></li>
	<li class="nav-item"><a class="nav-link" href="#">Đang theo dõi</a></li>
</ul>

<div class="d-flex border-bottom pb-3 mb-3">
	<div class="avatar-sm me-2" style="background: <?= htmlspecialchars($composerColor['bg']) ?>; color: <?= htmlspecialchars($composerColor['fg']) ?>;">
		<?= Avatar::initials($composerName) ?>
	</div>
	<input type="text" class="form-control border-0 bg-light rounded-pill" placeholder="Hãy viết gì đó..." disabled>
</div>

<div class="d-flex justify-content-between align-items-center">
	<div class="text-secondary small"><i class="bi bi-globe2 me-1"></i>Công khai</div>
	<button class="btn btn-primary rounded-pill px-4" disabled>Đăng</button>
</div>
