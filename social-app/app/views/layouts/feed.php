<!doctype html>
<html lang="vi">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($title ?? APP_NAME) ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
	<link href="<?= BASE_URL ?>/public/css/saved.css" rel="stylesheet">
	<link href="<?= BASE_URL ?>/public/css/post-media.css" rel="stylesheet">
	<link href="<?= BASE_URL ?>/public/css/mobile-ui.css" rel="stylesheet">
	<?php foreach (($pageStyles ?? []) as $style): ?>
		<?php $href = (string) ($style['href'] ?? ''); ?>
		<?php if ($href === '') { continue; } ?>
		<link href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
	<?php endforeach; ?>
</head>
<body class="app-bg">
	<?php include VIEW_PATH . 'partials/navbar.php'; ?>
	<?php include VIEW_PATH . 'partials/mobile_shell_nav.php'; ?>
	<main class="container-fluid py-4">
		<div class="container-fluid feed-layout px-lg-4">
			<div class="row g-3 g-lg-4 feed-layout-row">
				<div class="col-12 col-md-2 col-lg-3 feed-sidebar-column">
					<?php include VIEW_PATH . 'partials/feed/left_sidebar.php'; ?>
				</div>

				<div class="col-12 col-md-7 col-lg-6 bg-white feed-main-column">
					<?php include $contentView; ?>
				</div>

				<div class="col-12 col-md-3 col-lg-3 feed-widgets-column">
					<?php include VIEW_PATH . 'partials/feed/right_widgets.php'; ?>
				</div>
			</div>
		</div>
	</main>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<?php include VIEW_PATH . 'partials/confirm_modal.php'; ?>
	<script src="<?= BASE_URL ?>/public/js/confirm-modal.js"></script>
	
	<!-- Post Detail Modal -->
	<div class="modal fade" id="postDetailModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header border-0 pb-0">
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body" id="postDetailContent" style="max-height: 80vh; overflow-y: auto;">
					<!-- Post detail will be loaded here -->
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="postEditModal" tabindex="-1" aria-labelledby="postEditModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header border-0 pb-0">
					<h5 class="modal-title" id="postEditModalLabel">Chỉnh sửa bài viết</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
				</div>
				<div class="modal-body" id="postEditModalContent" style="max-height: 80vh; overflow-y: auto;"></div>
			</div>
		</div>
	</div>

	<script src="<?= BASE_URL ?>/public/js/ajax-post-actions.js"></script>
	<?php
	$__jsAppBase = trim((string) BASE_URL);
	if ($__jsAppBase !== '' && preg_match('#^https?://#i', $__jsAppBase)) {
		$__u = parse_url($__jsAppBase, PHP_URL_PATH);
		$__jsAppBase = ($__u !== null && $__u !== false) ? rtrim((string) $__u, '/') : '';
	}
	if ($__jsAppBase === '') {
		$__jsAppBase = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
	}
	$__mentionUsersUrl = ($__jsAppBase === '') ? '/api/mention_users.php' : rtrim($__jsAppBase, '/') . '/api/mention_users.php';
	$__mentionUsernamesJson = '[]';
	try {
		$__pdo = Database::getInstance()->getConnection();
		$__stmt = $__pdo->query('SELECT username FROM users ORDER BY CHAR_LENGTH(username) DESC');
		$__mn = $__stmt->fetchAll(PDO::FETCH_COLUMN);
		$__mentionUsernamesJson = json_encode(
			array_values(array_filter(array_map('strval', $__mn ?: []))),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	} catch (Throwable $e) {
	}
	?>
	<script>
	window.__APP_BASE__ = <?= json_encode($__jsAppBase, JSON_UNESCAPED_UNICODE) ?>;
	window.__MENTION_USERS_URL__ = <?= json_encode($__mentionUsersUrl, JSON_UNESCAPED_UNICODE) ?>;
	window.__MENTION_USERNAMES__ = <?= $__mentionUsernamesJson ?>;
	</script>
	<script src="<?= BASE_URL ?>/public/js/app-url.js"></script>
	<script src="<?= BASE_URL ?>/public/js/post-store-submit.js"></script>
	<script src="<?= BASE_URL ?>/public/js/message-badge.js" type="module"></script>
	<script src="<?= BASE_URL ?>/public/js/right_widgets.js"></script>
	<script src="<?= BASE_URL ?>/public/js/notification.js"></script>
	<script src="<?= BASE_URL ?>/public/js/comment.js"></script>
	<script src="<?= BASE_URL ?>/public/js/mention-autocomplete.js"></script>
	<script src="<?= BASE_URL ?>/public/js/post-edit-form.js"></script>
	<script src="<?= BASE_URL ?>/public/js/post-edit-modal.js"></script>
	<script src="<?= BASE_URL ?>/public/js/post-modal.js"></script>
	<script src="<?= BASE_URL ?>/public/js/infinite-scroll.js"></script>
	<?php foreach (($pageScripts ?? []) as $script): ?>
		<?php $src = (string) ($script['src'] ?? ''); ?>
		<?php if ($src === '') { continue; } ?>
		<script src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>"<?= !empty($script['module']) ? ' type="module"' : '' ?>></script>
	<?php endforeach; ?>
</body>
</html>
