<?php
$author = $post['author_name'] ?? $post['user_name'] ?? 'Nguoi dung';
$content = $post['content'] ?? $post['caption'] ?? '[Khong co noi dung]';
$likes = (int) ($post['like_count'] ?? 0);
$comments = (int) ($post['comment_count'] ?? 0);
$createdAt = $post['created_at'] ?? 'vua xong';
$postId = (int) ($post['id'] ?? 0);
$isLiked = !empty($post['is_liked']);
$isSaved = !empty($post['is_saved']);
$shareCount = (int) ($post['share_count'] ?? 0);
$saveCount = (int) ($post['save_count'] ?? 0);
?>
<?php
$visibleValue = (string) ($post['visible'] ?? 'public');
$visibleIcon = 'bi-globe2';
$visibleLabel = 'Công khai';
if ($visibleValue === 'followers') {
	$visibleIcon = 'bi-people';
	$visibleLabel = 'Người theo dõi';
} elseif ($visibleValue === 'private') {
	$visibleIcon = 'bi-lock';
	$visibleLabel = 'Chỉ mình tôi';
}
?>
<article class="card border-0 shadow-sm rounded-4 mb-3 position-relative post-card-clickable" data-detail-url="<?= BASE_URL ?>/post/<?= $postId ?>">
	<div class="card-body p-3 p-md-4 position-relative" style="z-index:2;">
		<div class="d-flex align-items-start justify-content-between mb-3">
			<div class="d-flex align-items-center gap-2 post-card-no-detail">
				<div class="avatar-sm"><?= strtoupper(substr($author, 0, 1)) ?></div>
				<div>
                    <h5 class="fw-semibold mb-0"><?= htmlspecialchars($post['author_name'] ?? '') ?></h5>
                    <div class="small text-secondary d-inline-flex align-items-center gap-1">
						<span><?= htmlspecialchars((string) $post['created_at']) ?></span>
						<i class="bi <?= htmlspecialchars($visibleIcon, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($visibleLabel, ENT_QUOTES, 'UTF-8') ?>"></i>
					</div>
                </div>
			</div>
			<?php if (isset($currentUser['id']) && (int) $currentUser['id'] === (int) ($post['user_id'] ?? 0)): ?>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="<?= BASE_URL ?>/post/edit/<?= (int) $post['id'] ?>">
                                Chỉnh sửa
                            </a>
                        </li>
                        <li>
                            <a
                                class="dropdown-item text-danger"
                                href="<?= BASE_URL ?>/post/delete/<?= (int) $post['id'] ?>"
                                onclick="return confirm('Bạn có chắc muốn xóa bài viết này?')"
                            >
                                Xóa bài viết
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
		</div>

		<div class="mb-3">
  			<p class="mb-0"><?= nl2br(htmlspecialchars($content)) ?></p>
			<?php if (!empty($post['media'])): ?>
				<?php
					$validMedia = [];
					foreach (($post['media'] ?? []) as $m) {
						$srcCheck = media_public_src((string) ($m['media_url'] ?? ''));
						if ($srcCheck !== '') {
							$validMedia[] = $m;
						}
					}
					$maxPreviewMedia = 4;
					$previewMedia = array_slice($validMedia, 0, $maxPreviewMedia);
					$remainingMediaCount = max(0, count($validMedia) - $maxPreviewMedia);
				?>
				<?php if (!empty($previewMedia)): ?>
				<div class="mt-3">
					<div class="post-card-media-grid">
					<?php foreach ($previewMedia as $index => $media): ?>
						<?php
							$src = media_public_src((string) ($media['media_url'] ?? ''));
							if ($src === '') {
								continue;
							}
							$isVideo = (($media['media_type'] ?? '') === 'video');
							?>
							<?php if ($isVideo): ?>
							<div class="post-card-media-item<?= ($remainingMediaCount > 0 && $index === $maxPreviewMedia - 1) ? ' has-more-overlay' : '' ?>">
								<video src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" controls class="w-100 h-100" playsinline></video>
								<?php if ($remainingMediaCount > 0 && $index === $maxPreviewMedia - 1): ?>
									<div class="post-card-more-overlay">+<?= $remainingMediaCount ?></div>
								<?php endif; ?>
							</div>
							<?php else: ?>
							<div class="post-card-media-item<?= ($remainingMediaCount > 0 && $index === $maxPreviewMedia - 1) ? ' has-more-overlay' : '' ?>">
								<img src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>"
									class="w-100 h-100" alt="">
								<?php if ($remainingMediaCount > 0 && $index === $maxPreviewMedia - 1): ?>
									<div class="post-card-more-overlay">+<?= $remainingMediaCount ?></div>
								<?php endif; ?>
							</div>
							<?php endif; ?>
					<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<div class="d-flex align-items-center justify-content-between gap-3 text-secondary small">
			<div class="d-flex align-items-center gap-3">
			<form
				method="POST"
				action="<?= BASE_URL ?>/post/<?= $postId ?>/like"
				class="m-0 d-inline-flex align-items-center gap-1 ajax-post-like"
				data-post-id="<?= $postId ?>"
			>
					<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
					<button
						id="like-btn-<?= $postId ?>"
						type="submit"
						class="btn btn-link text-decoration-none p-0 border-0 <?= $isLiked ? 'text-danger' : 'text-secondary' ?>"
						aria-label="Yêu thích"
					>
					<i id="like-icon-<?= $postId ?>" class="bi <?= $isLiked ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
					</button>
					<span id="like-count-<?= $postId ?>" class="<?= $isLiked ? 'text-danger' : 'text-secondary' ?>"><?= $likes ?></span>
				</form>
				<a href="<?= BASE_URL ?>/post/<?= $postId ?>#comment-box" class="text-decoration-none d-inline-flex align-items-center gap-1 text-secondary" aria-label="Bình luận">
					<i class="bi bi-chat"></i>
					<span><?= $comments ?></span>
				</a>
				<div class="m-0 d-inline-flex align-items-center gap-1">
					<button
						type="button"
						class="btn btn-link text-decoration-none p-0 border-0 text-secondary open-share-modal-btn"
						aria-label="Chia sẻ"
						data-post-id="<?= $postId ?>"
						data-share-url="<?= BASE_URL ?>/post/<?= $postId ?>/share"
						data-share-link="<?= BASE_URL ?>/post/<?= $postId ?>"
						data-csrf="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>"
					>
						<i class="bi bi-share"></i>
					</button>
					<span id="share-count-<?= $postId ?>" class="text-secondary"><?= $shareCount ?></span>
				</div>
			</div>
			<form
					method="POST"
					action="<?= BASE_URL ?>/post/<?= $postId ?>/save"
					class="m-0 d-inline-flex align-items-center gap-1 ajax-post-save"
					data-post-id="<?= $postId ?>"
				>
				<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
				<button
					type="submit"
					id="save-btn-<?= $postId ?>"
					class="btn btn-link text-decoration-none p-0 border-0 <?= $isSaved ? 'text-warning' : 'text-secondary' ?>"
					aria-label="Lưu bài viết"
				>
				<i id="save-icon-<?= $postId ?>" class="bi <?= $isSaved ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
				</button>
				<span id="save-count-<?= $postId ?>" class="<?= $isSaved ? 'text-warning' : 'text-secondary' ?>"><?= $saveCount ?></span>
			</form>
		</div>
	</div>
</article>
<script>
(function () {
	const cards = document.querySelectorAll('.post-card-clickable');
	cards.forEach(function (card) {
		if (card.dataset.boundClick === '1') return;
		card.dataset.boundClick = '1';
		card.addEventListener('click', function (e) {
			const target = e.target;
			if (!target) return;
			if (
				target.closest('.post-card-no-detail') ||
				target.closest('a') ||
				target.closest('button') ||
				target.closest('form') ||
				target.closest('input') ||
				target.closest('textarea') ||
				target.closest('select') ||
				target.closest('label')
			) {
				return;
			}
			const url = card.getAttribute('data-detail-url');
			if (url) {
				window.location.href = url;
			}
		});
	});
})();
</script>
