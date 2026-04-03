<?php
require_once dirname(__DIR__, 2) . '/helpers/notification_helper.php';
if (!function_exists('format_comment_time_vi')) {
	function format_comment_time_vi(?string $rawDateTime): string {
		if ($rawDateTime === null || trim($rawDateTime) === '') {
			return '';
		}

		try {
			$createdAt = new DateTimeImmutable($rawDateTime);
			$now = new DateTimeImmutable('now');
		} catch (Throwable $e) {
			return '';
		}

		$diffSeconds = $now->getTimestamp() - $createdAt->getTimestamp();
		if ($diffSeconds < 0) {
			$diffSeconds = 0;
		}

		if ($diffSeconds >= 86400) {
			return $createdAt->format('d/m/Y');
		}

		if ($diffSeconds >= 3600) {
			return (string) floor($diffSeconds / 3600) . ' giờ trước';
		}

		if ($diffSeconds >= 60) {
			return (string) floor($diffSeconds / 60) . ' phút trước';
		}

		return 'vừa xong';
	}
}
?>

<div class="mb-3">
	<a href="<?= BASE_URL ?>/" class="btn btn-sm btn-light rounded-pill text-black mt-3 fs-5 fw-bold px-3" id="back-to-post">
		<i class="bi bi-arrow-left me-1"></i> Bài viết
	</a>
</div>

<article class="card border-0 shadow-sm rounded-4 mb-4">
	<div class="card-body p-3 p-md-4">
        <?php
        $detailAuthor = (string) ($post['author_name'] ?? '');
        $detailAuthorColor = Avatar::colors($detailAuthor);
        $detailAvRaw = (string) ($post['author_avatar_url'] ?? '');
        $detailAvSrc = $detailAvRaw !== '' ? media_public_src($detailAvRaw) : '';
		$detailVisible = (string) ($post['visible'] ?? 'public');
		$detailVisibleIcon = 'bi-globe2';
		$detailVisibleLabel = 'Công khai';
		if ($detailVisible === 'followers') {
			$detailVisibleIcon = 'bi-people';
			$detailVisibleLabel = 'Người theo dõi';
		} elseif ($detailVisible === 'private') {
			$detailVisibleIcon = 'bi-lock';
			$detailVisibleLabel = 'Chỉ mình tôi';
		}
        ?>
        <div class="d-flex align-items-start justify-content-between gap-2">
            <a href="<?= htmlspecialchars(profile_url($detailAuthor), ENT_QUOTES, 'UTF-8') ?>" class="d-flex align-items-center gap-2 text-decoration-none text-body min-w-0">
                <?php if ($detailAvSrc !== ''): ?>
                    <img src="<?= htmlspecialchars($detailAvSrc, ENT_QUOTES, 'UTF-8') ?>" alt="" width="44" height="44" class="rounded-circle flex-shrink-0" style="object-fit:cover"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="avatar-sm flex-shrink-0" style="background: <?= htmlspecialchars($detailAuthorColor['bg'], ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars($detailAuthorColor['fg'], ENT_QUOTES, 'UTF-8') ?>; display:none;"><?= htmlspecialchars(strtoupper(substr($detailAuthor, 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
                <?php else: ?>
                    <div class="avatar-sm flex-shrink-0" style="background: <?= htmlspecialchars($detailAuthorColor['bg'], ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars($detailAuthorColor['fg'], ENT_QUOTES, 'UTF-8') ?>;"><?= htmlspecialchars(strtoupper(substr($detailAuthor, 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <div class="min-w-0">
                    <h5 class="fw-semibold mb-0 text-truncate"><?= htmlspecialchars($detailAuthor, ENT_QUOTES, 'UTF-8') ?></h5>
                    <div class="small text-secondary d-flex align-items-center gap-1">
						<span><?= htmlspecialchars((string) $post['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
						<i class="bi <?= $detailVisibleIcon ?>" title="<?= htmlspecialchars($detailVisibleLabel, ENT_QUOTES, 'UTF-8') ?>"></i>
					</div>
                </div>
            </a>

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
		
		<div class="mt-3 mb-3 ms-1 position-relative" style="z-index: 2;"><?= format_post_display_html((string) ($post['content'] ?? ''), $post['hashtag_names'] ?? []) ?></div>

		<?php foreach ($media as $m): ?>
			<?php
			$src = media_public_src((string) ($m['media_url'] ?? ''));
			if ($src === '') {
				continue;
			}
			$isVideo = (($m['media_type'] ?? '') === 'video');
			?>
			<?php if ($isVideo): ?>
			<video src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" controls class="w-100 rounded-4 mb-2" playsinline></video>
			<?php else: ?>
			<img src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" class="img-fluid rounded-4 mb-2 w-100" style="object-fit: cover;" alt="">
			<?php endif; ?>
		<?php endforeach; ?>

		<hr class="my-3">

		<div class="d-flex align-items-center gap-4 flex-wrap mb-3 small">
			<form method="POST" action="<?= BASE_URL ?>/post/<?= (int) $post['id'] ?>/like" class="m-0 d-inline-flex align-items-center gap-1 ajax-post-like" data-post-id="<?= (int) $post['id'] ?>">
				<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
				<button id="like-btn-<?= (int) $post['id'] ?>" type="submit" class="btn btn-link text-decoration-none p-0 border-0 <?= !empty($post['is_liked']) ? 'text-danger' : 'text-secondary' ?>" aria-label="Yêu thích">
					<i id="like-icon-<?= (int) $post['id'] ?>" class="bi <?= !empty($post['is_liked']) ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
				</button>
				<span id="like-count-<?= (int) $post['id'] ?>" class="<?= !empty($post['is_liked']) ? 'text-danger' : 'text-secondary' ?>"><?= (int) ($post['like_count'] ?? 0) ?></span>
			</form>

			<a href="#comment-box" class="text-decoration-none d-inline-flex align-items-center gap-1 text-secondary" aria-label="Bình luận">
				<i class="bi bi-chat"></i>
				<span><?= (int) ($post['comment_count'] ?? 0) ?></span>
			</a>

			<form method="POST" action="<?= BASE_URL ?>/post/<?= (int) $post['id'] ?>/share" class="m-0 d-inline-flex align-items-center gap-1 ajax-post-share js-share-form" data-post-id="<?= (int) $post['id'] ?>" data-post-url="<?= BASE_URL ?>/post/<?= (int) $post['id'] ?>">
				<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
				<button type="submit" class="btn btn-link text-decoration-none p-0 border-0 text-secondary" aria-label="Chia sẻ">
					<i class="bi bi-share"></i>
				</button>
				<span id="share-count-<?= (int) $post['id'] ?>" class="text-secondary"><?= (int) ($post['share_count'] ?? 0) ?></span>
			</form>

			<!-- Save post -->
            <form method="POST" action="<?= BASE_URL ?>/post/<?= (int) $post['id'] ?>/save" class="m-0 d-inline-flex align-items-center gap-1 ajax-post-save" data-post-id="<?= (int) $post['id'] ?>">
				<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
				<button id="save-btn-<?= (int) $post['id'] ?>" type="submit" class="btn btn-link text-decoration-none p-0 border-0 <?= !empty($post['is_saved']) ? 'text-warning' : 'text-secondary' ?>" aria-label="Lưu bài viết">
					<i id="save-icon-<?= (int) $post['id'] ?>" class="bi <?= !empty($post['is_saved']) ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
				</button>
				<span id="save-count-<?= (int) $post['id'] ?>" class="<?= !empty($post['is_saved']) ? 'text-warning' : 'text-secondary' ?>"><?= (int) ($post['save_count'] ?? 0) ?></span>
			</form>
		</div>

        <hr class="my-3">
		<!-- Comment form -->
		<form method="POST" action="<?= BASE_URL ?>/post/<?= (int) $post['id'] ?>/comment" class="mb-3 comment-form" id="comment-box">
			<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
			<div class="input-group rounded-4 p-2 bg-light">
				<input type="text" name="content" class="form-control border-0 bg-light" placeholder="Viết bình luận..." required>
				<button type="submit" class="btn btn-primary opacity-80 rounded-pill">
					<i class="me-2"></i> Bình luận
				</button>
			</div>
		</form>

		<!-- Comment section -->
        <div class="mt-2">
			<h6 class="fw-semibold mb-2">Bình luận</h6>
			<?php $postId = (int) ($post['id'] ?? 0); ?>
			<?php if (empty($commentsTree ?? [])): ?>
				<p class="text-secondary small mb-0">Chưa có bình luận nào.</p>
			<?php else: ?>
				<?php foreach ($commentsTree as $node): ?>
					<?php
					$depth = 0;
					include VIEW_PATH . 'partials/comment_reply_branch.php';
					?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
</article>
<!-- Hidden data for JS -->
<?php
$currentUserAvatar = (string) ($currentUser['avatar_url'] ?? '');
$currentUserAvatarSrc = $currentUserAvatar ? media_public_src($currentUserAvatar) : '';
?>
<div class="d-none">
	<span class="currentUserName"><?= htmlspecialchars($currentUser['username'] ?? 'Người dùng') ?></span>
	<img class="currentUserAvatar" data-avatar="<?= htmlspecialchars($currentUserAvatarSrc) ?>" alt="">
</div>
<script>
	console.log('Avatar debug:', {
		username: '<?= htmlspecialchars($currentUser['username'] ?? 'N/A') ?>',
		avatar_url_raw: '<?= htmlspecialchars($currentUserAvatar) ?>',
		avatar_src: '<?= htmlspecialchars($currentUserAvatarSrc) ?>',
		element: document.querySelector('.currentUserAvatar')?.getAttribute('data-avatar')
	});
</script>
<script src="/public/js/comment.js"></script>
<script src="/public/js/back-actions.js"></script>