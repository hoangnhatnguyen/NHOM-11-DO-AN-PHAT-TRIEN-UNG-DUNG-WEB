<!doctype html>
<html lang="vi">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($title ?? 'Admin — ' . APP_NAME) ?></title>
	<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/public/images/favicon.ico">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
	<link href="<?= BASE_URL ?>/public/css/post-media.css" rel="stylesheet">
	<style>
		.admin-shell { min-height: 100vh; }
		.admin-sidebar { min-height: 100vh; background: #0f172a; }
		.admin-sidebar .nav-link { color: rgba(255,255,255,.75); border-radius: .5rem; margin-bottom: .25rem; }
		.admin-sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,.08); }
		.admin-sidebar .nav-link.active { color: #fff; background: rgba(26, 98, 145, .45); }
		.admin-main { background: #f1f5f9; min-height: 100vh; }
	</style>
</head>
<body>
<?php $adminTab = $adminTab ?? 'dashboard'; ?>
<div class="container-fluid p-0">
	<div class="row g-0 admin-shell">
		<aside class="col-12 col-lg-2 admin-sidebar p-3 p-lg-4">
			<div class="d-flex align-items-center justify-content-between mb-4">
				<span class="text-white fw-bold small text-uppercase tracking-wide">Quản trị</span>
				<a href="<?= BASE_URL ?>/" class="btn btn-sm btn-outline-light rounded-pill">Về app</a>
			</div>
			<nav class="nav flex-column">
				<a class="nav-link <?= $adminTab === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin"><i class="bi bi-speedometer2 me-2"></i>Tổng quan</a>
				<a class="nav-link <?= $adminTab === 'users' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/users"><i class="bi bi-people me-2"></i>Người dùng</a>
				<a class="nav-link <?= $adminTab === 'posts' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/posts"><i class="bi bi-file-post me-2"></i>Bài viết</a>
			</nav>
		</aside>
		<main class="col-12 col-lg-10 admin-main p-3 p-md-4">
			<?php include $contentView; ?>
		</main>
	</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<?php include VIEW_PATH . 'partials/confirm_modal.php'; ?>
<script src="<?= BASE_URL ?>/public/js/confirm-modal.js"></script>
<script>window.__APP_BASE__ = <?= json_encode((string) BASE_URL, JSON_UNESCAPED_UNICODE) ?>;</script>
</body>
</html>
