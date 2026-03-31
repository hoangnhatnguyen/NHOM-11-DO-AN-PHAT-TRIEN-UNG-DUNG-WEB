<!doctype html>
<html lang="vi">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($title ?? APP_NAME) ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
	<link href="<?= BASE_URL ?>/public/css/post-media.css" rel="stylesheet">
</head>
<body class="app-bg">
	<?php include VIEW_PATH . 'partials/navbar.php'; ?>
	<main class="container-fluid py-4">
		<div class="container-fluid feed-layout px-lg-4">
			<div class="row g-3 g-lg-4">
				<div class="col-12 col-md-2 col-lg-3">
					<?php include VIEW_PATH . 'partials/feed/left_sidebar.php'; ?>
				</div>

				<div class="col-12 col-md-7 col-lg-6 bg-white">
					<?php include $contentView; ?>
				</div>

				<div class="col-12 col-md-3 col-lg-3">
					<?php include VIEW_PATH . 'partials/feed/right_widgets.php'; ?>
				</div>
			</div>
		</div>
	</main>
	<div class="modal fade" id="sharePostModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Chia sẻ bài viết</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<label for="sharePostLinkInput" class="form-label">Link bài viết</label>
					<div class="input-group">
						<input type="text" id="sharePostLinkInput" class="form-control" readonly>
						<button type="button" class="btn btn-primary" id="copySharePostLinkBtn">Copy</button>
					</div>
					<div class="small text-success mt-2 d-none" id="shareCopySuccess">Đã copy link.</div>
				</div>
			</div>
		</div>
	</div>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<script>
		(function () {
			function removeClasses(el, classNames) {
				if (!el) return;
				classNames.forEach(function (c) {
					el.classList.remove(c);
				});
			}

			document.addEventListener('submit', function (e) {
				var form = e.target;
				if (!form || !form.matches('form.ajax-post-like, form.ajax-post-save, form.ajax-post-share')) {
					return;
				}

				e.preventDefault();

				var postId = form.getAttribute('data-post-id') || '';
				var action = form.getAttribute('action') || '';
				if (!action) return;

				var formData = new FormData(form);

				fetch(action, {
					method: 'POST',
					body: formData,
					headers: {
						'X-Requested-With': 'XMLHttpRequest'
					}
				})
					.then(function (res) { return res.json(); })
					.then(function (data) {
						if (!data || !data.ok) return;

						if (data.kind === 'like') {
							var countEl = document.getElementById('like-count-' + data.postId);
							var btnEl = document.getElementById('like-btn-' + data.postId);
							var iconEl = document.getElementById('like-icon-' + data.postId);

							if (countEl) {
								countEl.textContent = data.like_count;
								removeClasses(countEl, ['text-danger', 'text-secondary']);
								countEl.classList.add(data.is_liked ? 'text-danger' : 'text-secondary');
							}

							if (btnEl) {
								removeClasses(btnEl, ['text-danger', 'text-secondary']);
								btnEl.classList.add(data.is_liked ? 'text-danger' : 'text-secondary');
							}

							if (iconEl) {
								removeClasses(iconEl, ['bi-heart', 'bi-heart-fill']);
								iconEl.classList.add(data.is_liked ? 'bi-heart-fill' : 'bi-heart');
							}
						}

						if (data.kind === 'save') {
							var countEl = document.getElementById('save-count-' + data.postId);
							var btnEl = document.getElementById('save-btn-' + data.postId);
							var iconEl = document.getElementById('save-icon-' + data.postId);

							if (countEl) {
								countEl.textContent = data.save_count;
								removeClasses(countEl, ['text-warning', 'text-secondary']);
								countEl.classList.add(data.is_saved ? 'text-warning' : 'text-secondary');
							}

							if (btnEl) {
								removeClasses(btnEl, ['text-warning', 'text-secondary']);
								btnEl.classList.add(data.is_saved ? 'text-warning' : 'text-secondary');
							}

							if (iconEl) {
								removeClasses(iconEl, ['bi-bookmark', 'bi-bookmark-fill']);
								iconEl.classList.add(data.is_saved ? 'bi-bookmark-fill' : 'bi-bookmark');
							}
						}

						if (data.kind === 'share') {
							var countEl = document.getElementById('share-count-' + data.postId);
							if (countEl) {
								countEl.textContent = data.share_count;
							}
						}
					})
					.catch(function () {
						// Nếu lỗi Ajax, fallback submit bình thường.
						form.submit();
					});
			});

			var shareModalEl = document.getElementById('sharePostModal');
			var shareLinkInput = document.getElementById('sharePostLinkInput');
			var shareCopySuccess = document.getElementById('shareCopySuccess');
			var copyBtn = document.getElementById('copySharePostLinkBtn');
			var shareModal = shareModalEl ? new bootstrap.Modal(shareModalEl) : null;

			function updateShareCount(postId, value) {
				var countEl = document.getElementById('share-count-' + postId);
				if (countEl) countEl.textContent = String(value);
			}

			function sendShare(postId, shareUrl, csrf) {
				if (!shareUrl || !csrf) return;
				var body = new FormData();
				body.append('_csrf', csrf);
				fetch(shareUrl, {
					method: 'POST',
					body: body,
					headers: { 'X-Requested-With': 'XMLHttpRequest' }
				})
					.then(function (res) { return res.json(); })
					.then(function (data) {
						if (data && data.ok && data.kind === 'share') {
							updateShareCount(data.postId, data.share_count);
						}
					})
					.catch(function () {});
			}

			document.addEventListener('click', function (e) {
				var btn = e.target && e.target.closest ? e.target.closest('.open-share-modal-btn') : null;
				if (!btn) return;
				e.preventDefault();

				var postId = btn.getAttribute('data-post-id') || '';
				var shareUrl = btn.getAttribute('data-share-url') || '';
				var shareLink = btn.getAttribute('data-share-link') || '';
				var csrf = btn.getAttribute('data-csrf') || '';

				if (shareLinkInput) shareLinkInput.value = shareLink;
				if (shareCopySuccess) shareCopySuccess.classList.add('d-none');
				if (shareModal) shareModal.show();
				sendShare(postId, shareUrl, csrf);
			});

			if (copyBtn && shareLinkInput) {
				copyBtn.addEventListener('click', function () {
					shareLinkInput.select();
					shareLinkInput.setSelectionRange(0, 99999);
					navigator.clipboard.writeText(shareLinkInput.value).then(function () {
						if (shareCopySuccess) shareCopySuccess.classList.remove('d-none');
					}).catch(function () {
						try {
							document.execCommand('copy');
							if (shareCopySuccess) shareCopySuccess.classList.remove('d-none');
						} catch (err) {}
					});
				});
			}
		})();
	</script>
</body>
</html>
