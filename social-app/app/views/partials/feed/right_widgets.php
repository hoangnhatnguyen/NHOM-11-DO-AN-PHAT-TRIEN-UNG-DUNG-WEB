<?php
if (!isset($suggestedFollows)) {
	$suggestedFollows = [];
	$uid = (int) ($_SESSION['user']['id'] ?? 0);
	if ($uid > 0) {
		require_once dirname(__DIR__, 3) . '/models/Follow.php';
		$suggestedFollows = (new Follow())->suggestForViewer($uid, 5);
	}
}
if (!isset($trendingHashtags) || !is_array($trendingHashtags)) {
	require_once dirname(__DIR__, 3) . '/models/Hashtag.php';
	$trendingHashtags = (new Hashtag())->getTrending(10);
}
require_once dirname(__DIR__, 3) . '/helpers/media.php';
?>
<aside class="d-flex flex-column gap-3 right-sticky">

	<!-- 🔍 SEARCH -->
	<div class="position-relative search-box">
		<i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-secondary"></i>

		<input
			id="search-input"
			class="form-control rounded-pill ps-5 border-0 shadow-sm feed-search-input"
			placeholder="Tìm kiếm..."
		>

		<!-- 🔥 POPUP GẦN ĐÂY -->
		<div id="recent-popup" class="recent-popup hidden">
			<div class="d-flex justify-content-between px-3 py-2 border-bottom">
				<span class="fw-bold">Gần đây</span>
				<button type="button" id="clear-recent" class="btn btn-sm text-primary">Xóa tất cả</button>
			</div>
			<div id="recent-list"></div>
		</div>
	</div>

	<!-- 🔥 TRENDING -->
	<section class="card border-primary-subtle rounded-4 shadow-sm">
		<div class="card-body p-3">
			<h6 class="fw-bold text-primary mb-3">Đang phổ biến</h6>

			<div id="right-trending" class="d-flex flex-column gap-2">
				<?php if (empty($trendingHashtags)): ?>
					<p class="text-muted small mb-0">Chưa có hashtag nào trong bài viết active.</p>
				<?php else: ?>
					<?php foreach (array_values($trendingHashtags) as $ti => $row): ?>
						<?php
						$hname = (string) ($row['name'] ?? '');
						if ($hname === '') {
							continue;
						}
						$qVal = '#' . $hname;
						?>
						<div class="trend" data-q="<?= htmlspecialchars($qVal, ENT_QUOTES, 'UTF-8') ?>" role="button" tabindex="0">
							<small class="text-secondary">#<?= (int) $ti + 1 ?> Trending</small><br>
							<b>#<?= htmlspecialchars($hname, ENT_QUOTES, 'UTF-8') ?></b>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- 🔥 SUGGEST USER (server: Follow model + avatar / follow API) -->
	<section class="card border-primary-subtle rounded-4 shadow-sm">
		<div class="card-body p-3">
			<h6 class="fw-bold text-primary mb-3">Gợi ý theo dõi</h6>
			<?php if (empty($suggestedFollows)): ?>
				<p class="text-muted small mb-0">Không còn gợi ý.</p>
			<?php else: ?>
				<ul id="suggestBox" class="list-unstyled mb-0 small">
					<?php foreach ($suggestedFollows as $u): ?>
						<?php
							$sid = (int) ($u['id'] ?? 0);
							$uname = (string) ($u['username'] ?? '');
							$rawAv = (string) ($u['avatar_url'] ?? '');
							$suggestAvatar = $rawAv !== '' ? media_public_src($rawAv) : '';
							$suggestColor = Avatar::colors($uname);
						?>
						<li class="d-flex justify-content-between align-items-center gap-2 mb-2">
							<a href="<?= htmlspecialchars(profile_url($uname), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none text-body d-flex align-items-center gap-2 min-w-0 flex-grow-1">
								<?php if ($suggestAvatar !== ''): ?>
									<img src="<?= htmlspecialchars($suggestAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" width="36" height="36" class="rounded-circle flex-shrink-0" style="object-fit:cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
									<span class="avatar-sm flex-shrink-0" style="background: <?= htmlspecialchars($suggestColor['bg'], ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars($suggestColor['fg'], ENT_QUOTES, 'UTF-8') ?>; display:none;"><?= htmlspecialchars(Avatar::initials($uname), ENT_QUOTES, 'UTF-8') ?></span>
								<?php else: ?>
									<span class="avatar-sm flex-shrink-0" style="background: <?= htmlspecialchars($suggestColor['bg'], ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars($suggestColor['fg'], ENT_QUOTES, 'UTF-8') ?>;"><?= htmlspecialchars(Avatar::initials($uname), ENT_QUOTES, 'UTF-8') ?></span>
								<?php endif; ?>
								<span class="text-truncate">@<?= htmlspecialchars($uname, ENT_QUOTES, 'UTF-8') ?></span>
							</a>
							<button type="button"
								class="btn btn-sm rounded-pill btn-brand-follow flex-shrink-0 js-suggest-follow"
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

<style>
.recent-popup {
	position: absolute;
	top: 45px;
	width: 100%;
	background: white;
	border-radius: 12px;
	box-shadow: 0 4px 12px rgba(0,0,0,0.1);
	z-index: 999;
}

.hidden {
	display: none !important;
}

.recent-item {
	padding: 10px 15px;
	cursor: pointer;
}

.recent-item:hover {
	background: #f5f5f5;
}

.trend {
	padding: 10px 0;
	cursor: pointer;
	border-bottom: 1px solid #eee;
}

.trend:hover {
	background: #f5f5f5;
}
</style>

<script>
(function () {
	var base = <?= json_encode((string) BASE_URL, JSON_UNESCAPED_UNICODE) ?>;
	function followErrorMessage(err) {
		if (err === 'blocked_relationship') {
			return 'Không thể theo dõi người dùng này vì hai bên đang có trạng thái chặn.';
		}
		if (err === 'follow_requires_mutual') {
			return 'Người dùng này chỉ cho phép bạn chung theo dõi.';
		}
		if (err === 'user_not_found') {
			return 'Không tìm thấy người dùng.';
		}
		return 'Không thể theo dõi lúc này.';
	}
	function showFollowErrorPopup(message) {
		if (typeof bootstrap === 'undefined') {
			window.alert(message);
			return;
		}

		var modalEl = document.getElementById('followErrorModal');
		if (!modalEl) {
			var wrapper = document.createElement('div');
			wrapper.innerHTML = '' +
				'<div class="modal fade" id="followErrorModal" tabindex="-1" aria-hidden="true">' +
				'  <div class="modal-dialog modal-dialog-centered">' +
				'    <div class="modal-content border-0 shadow">' +
				'      <div class="modal-header border-0 pb-0">' +
				'        <h5 class="modal-title fw-semibold">Thông báo</h5>' +
				'        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>' +
				'      </div>' +
				'      <div class="modal-body pt-2">' +
				'        <p class="mb-0" id="followErrorModalText"></p>' +
				'      </div>' +
				'      <div class="modal-footer border-0 pt-0">' +
				'        <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">OK</button>' +
				'      </div>' +
				'    </div>' +
				'  </div>' +
				'</div>';
			document.body.appendChild(wrapper.firstElementChild);
			modalEl = document.getElementById('followErrorModal');
		}

		var textEl = document.getElementById('followErrorModalText');
		if (textEl) {
			textEl.textContent = message;
		}
		bootstrap.Modal.getOrCreateInstance(modalEl).show();
	}
	document.querySelectorAll('.js-suggest-follow').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var id = parseInt(this.getAttribute('data-user-id'), 10);
			if (!id) return;
			var self = this;
			self.disabled = true;
			var fd = new FormData();
			fd.append('target_id', String(id));
			fetch(base + '/user-api/follow?action=follow', { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(async function (r) {
					var data = await r.json().catch(function () { return {}; });
					if (!r.ok || !data || !data.success) {
						throw new Error((data && data.error) || 'follow_failed');
					}
					return data;
				})
				.then(function () {
					self.closest('li').remove();
				})
				.catch(function (err) { showFollowErrorPopup(followErrorMessage(err && err.message)); })
				.finally(function () { self.disabled = false; });
		});
	});
})();
</script>
