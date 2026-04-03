<?php
$adminTitle = $adminTitle ?? 'Quản trị';
$adminTab = $adminTab ?? 'dashboard';
?>
<style>
	.admin-page {
		--bs-primary: #1A6291;
		--bs-primary-rgb: 26, 98, 145;
	}
</style>
<div class="d-flex align-items-center justify-content-between mb-3">
	<div>
		<span class="badge rounded-pill bg-light text-dark px-3 py-2 fs-6 shadow-sm">
			<?= htmlspecialchars($adminTitle) ?>
		</span>
	</div>
	<div class="d-flex align-items-center gap-2">
		<a href="<?= BASE_URL ?>/admin" class="btn btn-sm rounded-pill <?= $adminTab === 'dashboard' ? 'btn-primary' : 'btn-light' ?>">Tổng quan</a>
		<a href="<?= BASE_URL ?>/admin/users" class="btn btn-sm rounded-pill <?= $adminTab === 'users' ? 'btn-primary' : 'btn-light' ?>">Người dùng</a>
		<a href="<?= BASE_URL ?>/admin/posts" class="btn btn-sm rounded-pill <?= $adminTab === 'posts' ? 'btn-primary' : 'btn-light' ?>">Bài viết</a>
	</div>
</div>
