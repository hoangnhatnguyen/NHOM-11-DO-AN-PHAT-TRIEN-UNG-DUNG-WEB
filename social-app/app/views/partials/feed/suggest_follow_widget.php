<?php
if (!isset($suggestedFollows)) {
	$suggestedFollows = [];
	$uid = (int) ($_SESSION['user']['id'] ?? 0);
	if ($uid > 0) {
		require_once dirname(__DIR__, 3) . '/models/Follow.php';
		$suggestedFollows = (new Follow())->suggestForViewer($uid, 5);
	}
}
require_once dirname(__DIR__, 3) . '/helpers/media.php';
?>
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
								data-user-id="<?= $sid ?>"
								data-following="0">
								Theo dõi
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</section>

<script>
(function () {
	var base = <?= json_encode((string) BASE_URL, JSON_UNESCAPED_UNICODE) ?>;
	function followErrorMessage(err) {
		if (err === 'blocked_relationship') {
			return 'Không thể theo dõi người dùng này vì hai bên đang có trạng thái chặn.';
		}
		if (err === 'follow_requires_mutual' || err === 'follow_privacy_restricted') {
			return 'Người dùng này chỉ cho phép bạn chung theo dõi.';
		}
		if (err === 'user_not_found') {
			return 'Không tìm thấy người dùng.';
		}
		return 'Không thể theo dõi lúc này.';
	}
	function suggestFollowApiErrorMessage(err) {
		if (err === 'blocked_relationship') {
			return 'Không thực hiện được vì hai bên đang có trạng thái chặn.';
		}
		if (err === 'follow_requires_mutual' || err === 'follow_privacy_restricted') {
			return 'Người dùng này chỉ cho phép bạn chung theo dõi.';
		}
		if (err === 'user_not_found') {
			return 'Không tìm thấy người dùng.';
		}
		return 'Không thực hiện được lúc này. Hãy thử lại.';
	}
	function setSuggestFollowButtonState(btn, following) {
		if (following) {
			btn.textContent = 'Đã theo dõi';
			btn.classList.remove('btn-brand-follow');
			btn.classList.add('btn-secondary');
			btn.setAttribute('data-following', '1');
		} else {
			btn.textContent = 'Theo dõi';
			btn.classList.add('btn-brand-follow');
			btn.classList.remove('btn-secondary');
			btn.setAttribute('data-following', '0');
		}
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
			var isFollowing = self.getAttribute('data-following') === '1';
			var action = isFollowing ? 'unfollow' : 'follow';
			self.disabled = true;
			var fd = new FormData();
			fd.append('target_id', String(id));
			fetch(base + '/user-api/follow?action=' + action, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(async function (r) {
					var data = await r.json().catch(function () { return {}; });
					if (!r.ok || !data || !data.success) {
						throw new Error((data && data.error) || 'request_failed');
					}
					return data;
				})
				.then(function () {
					setSuggestFollowButtonState(self, !isFollowing);
				})
				.catch(function (err) {
					var code = err && err.message;
					showFollowErrorPopup(action === 'unfollow' ? suggestFollowApiErrorMessage(code) : followErrorMessage(code));
				})
				.finally(function () {
					self.disabled = false;
				});
		});
	});
})();
</script>
