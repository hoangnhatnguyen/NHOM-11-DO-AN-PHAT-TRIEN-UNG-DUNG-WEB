<!doctype html>
<html lang="vi">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($title ?? APP_NAME) ?></title>
	<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/public/images/favicon.ico">
	<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/public/images/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/public/images/favicon-16x16.png">
	<link rel="apple-touch-icon" href="<?= BASE_URL ?>/public/images/favicon-32x32.png">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<!-- Main CSS -->
	<link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
	<link href="<?= BASE_URL ?>/public/css/saved.css" rel="stylesheet">
</head>
<body class="app-bg">
	<?php include VIEW_PATH . 'partials/navbar.php'; ?>
	<main class="container-fluid py-4">
		<?php include $contentView; ?>
	</main>
	<!-- Global Incoming Call Notification -->
	<div id="globalIncomingCallCard" class="global-call-notification d-none" role="dialog" aria-live="polite" aria-label="Cuộc gọi video đến">
		<div class="call-notification-content">
			<div class="call-notification-header">
				<i class="bi bi-telephone-inbound-fill"></i>
				<span>Cuộc gọi video đến</span>
			</div>
			<div id="globalIncomingCallText" class="call-notification-text">Ai đó đang gọi cho bạn</div>
			<div class="call-notification-actions">
				<button type="button" id="globalAcceptCallBtn" class="call-accept-btn">Nhận</button>
				<button type="button" id="globalDeclineCallBtn" class="call-decline-btn">Từ chối</button>
			</div>
		</div>
	</div>

	<!-- Global Call Overlay (full screen when outside chat) -->
	<div id="globalCallOverlay" class="global-call-overlay d-none" role="dialog" aria-label="Cuộc gọi video">
		<div class="global-call-stage">
			<div class="global-call-topbar">
				<div>
					<div id="globalCallPeerName" class="global-call-peer">Đang gọi...</div>
					<div id="globalCallStatus" class="global-call-status">Đang chuẩn bị kết nối...</div>
				</div>
				<a href="<?= BASE_URL ?>/messages" class="global-call-chip" id="globalOpenChatBtn" title="Mở chat">
					<i class="bi bi-chat-dots-fill"></i>
				</a>
			</div>

			<video id="globalRemoteVideo" class="global-remote-video" autoplay playsinline></video>
			<video id="globalLocalVideo" class="global-local-video" autoplay muted playsinline></video>

			<div class="global-call-actions">
				<button type="button" id="globalAcceptCallOverlayBtn" class="global-call-chip accept">
					<i class="bi bi-telephone-fill"></i>
					<span>Nhận</span>
				</button>
				<button type="button" id="globalDeclineCallOverlayBtn" class="global-call-chip decline">
					<i class="bi bi-telephone-x-fill"></i>
					<span>Từ chối</span>
				</button>
			</div>
		</div>
	</div>

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
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<script>window.__APP_BASE__ = <?= json_encode((string) BASE_URL, JSON_UNESCAPED_UNICODE) ?>;</script>
	<script src="<?= BASE_URL ?>/public/js/ajax-post-actions.js"></script>
	<script src="<?= BASE_URL ?>/public/js/comment.js"></script>
	<script src="<?= BASE_URL ?>/public/js/post-modal.js"></script>
	<script src="<?= BASE_URL ?>/public/js/avatar-fallback.js"></script>
	<!-- Global message badge loader -->
	<script src="<?= BASE_URL ?>/public/js/message-badge.js" type="module"></script>
	<!-- Global incoming call notification -->
	<script src="<?= BASE_URL ?>/public/js/global-call-notification.js" type="module"></script>
	<?php foreach (($pageScripts ?? []) as $script): ?>
		<?php $src = (string) ($script['src'] ?? ''); ?>
		<?php if ($src === '') { continue; } ?>
		<script src="<?= htmlspecialchars($src) ?>"<?= !empty($script['module']) ? ' type="module"' : '' ?>></script>
	<?php endforeach; ?>
	<script src="<?= BASE_URL ?>/public/js/notification.js"></script>
</body>
</html>

