<?php
if (!isset($suggestedFollows)) {
	$suggestedFollows = [];
	$uid = (int) ($_SESSION['user']['id'] ?? 0);
	if ($uid > 0) {
		require_once dirname(__DIR__, 3) . '/models/Follow.php';
		$suggestedFollows = (new Follow())->suggestForViewer($uid, 5);
	}
}
?>
<aside class="d-flex flex-column gap-3 right-sticky">
	<div class="position-relative">
		<i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-secondary"></i>
		<input class="form-control rounded-pill ps-5 border-0 shadow-sm feed-search-input" placeholder="Tìm kiếm..." disabled>
	</div>

	<section class="card border-primary-subtle rounded-4 shadow-sm">
		<div class="card-body p-3">
			<h6 class="fw-bold text-primary mb-3">Đang phổ biến</h6>
			<ol class="list-group list-group-numbered list-group-flush small">
				<li class="list-group-item px-0">#khaitrienmvc</li>
				<li class="list-group-item px-0">#php</li>
				<li class="list-group-item px-0">#bootstrap</li>
				<li class="list-group-item px-0">#newfeed</li>
				<li class="list-group-item px-0">#socialapp</li>
			</ol>
		</div>
	</section>

	<section class="card border-primary-subtle rounded-4 shadow-sm">
		<div class="card-body p-3">
			<h6 class="fw-bold text-primary mb-3">Gợi ý theo dõi</h6>
			<?php if (empty($suggestedFollows)): ?>
				<p class="text-muted small mb-0">Không còn gợi ý.</p>
			<?php else: ?>
				<ul class="list-unstyled mb-0 small">
					<?php foreach ($suggestedFollows as $u): ?>
						<?php
							$sid = (int) ($u['id'] ?? 0);
							$uname = (string) ($u['username'] ?? '');
							$rawAv = (string) ($u['avatar_url'] ?? '');
							$suggestAvatar = '';
							if ($rawAv !== '' && preg_match('#^https?://#i', $rawAv)) {
								$suggestAvatar = $rawAv;
							} elseif ($rawAv !== '') {
								$suggestAvatar = media_public_src($rawAv);
							}
							$suggestColor = Avatar::colors($uname);
						?>
						<li class="d-flex justify-content-between align-items-center gap-2 mb-2">
							<a href="<?= BASE_URL ?>/users/finder?id=<?= $sid ?>" class="text-decoration-none text-body d-flex align-items-center gap-2 min-w-0 flex-grow-1">
								<?php if ($suggestAvatar !== ''): ?>
									<img src="<?= htmlspecialchars($suggestAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" width="36" height="36" class="rounded-circle flex-shrink-0" style="object-fit:cover">
								<?php else: ?>
									<span class="avatar-sm flex-shrink-0" style="background: <?= htmlspecialchars($suggestColor['bg'], ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars($suggestColor['fg'], ENT_QUOTES, 'UTF-8') ?>;"><?= htmlspecialchars(Avatar::initials($uname), ENT_QUOTES, 'UTF-8') ?></span>
								<?php endif; ?>
								<span class="text-truncate">@<?= htmlspecialchars($uname, ENT_QUOTES, 'UTF-8') ?></span>
							</a>
							<button type="button"
								class="btn btn-sm rounded-pill btn-outline-primary flex-shrink-0 js-suggest-follow"
								data-user-id="<?= $sid ?>">
								Theo dõi
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</section>
</aside>
<script>
(function () {
	var base = <?= json_encode((string) BASE_URL, JSON_UNESCAPED_UNICODE) ?>;
	document.querySelectorAll('.js-suggest-follow').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var id = parseInt(this.getAttribute('data-user-id'), 10);
			if (!id) return;
			var self = this;
			self.disabled = true;
			var fd = new FormData();
			fd.append('target_id', String(id));
			fetch(base + '/user-api/follow?action=follow', { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (data && data.success) {
						self.closest('li').remove();
					}
				})
				.catch(function () {})
				.finally(function () { self.disabled = false; });
		});
	});
})();
</script>
