<?php
require_once dirname(__DIR__, 2) . '/helpers/notification_helper.php';
$author = $post['author_name'] ?? $post['user_name'] ?? 'Nguoi dung';
$content = $post['content'] ?? $post['caption'] ?? '[Khong co noi dung]';
$hashtagNames = $post['hashtag_names'] ?? [];
$likes = (int) ($post['like_count'] ?? 0);
$comments = (int) ($post['comment_count'] ?? 0);
$createdAt = $post['created_at'] ?? 'vua xong';
$authorColor = Avatar::colors((string) $author);
$postId = (int) ($post['id'] ?? 0);
$isLiked = !empty($post['is_liked']);
$isSaved = !empty($post['is_saved']);
$shareCount = (int) ($post['share_count'] ?? 0);
$saveCount = (int) ($post['save_count'] ?? 0);
$visible = (string) ($post['visible'] ?? 'public');
$visibleIcon = 'bi-globe2';
$visibleLabel = 'Công khai';
if ($visible === 'followers') {
	$visibleIcon = 'bi-people';
	$visibleLabel = 'Người theo dõi';
} elseif ($visible === 'private') {
	$visibleIcon = 'bi-lock';
	$visibleLabel = 'Chỉ mình tôi';
}
?>
<?php
$cardPostPath = (BASE_URL === '' ? '' : rtrim((string) BASE_URL, '/')) . '/post/' . $postId;
?>
<article class="card border-0 shadow-sm rounded-4 mb-3 js-post-card" data-post-id="<?= $postId ?>" data-post-url="<?= htmlspecialchars($cardPostPath, ENT_QUOTES, 'UTF-8') ?>">
	<div class="card-body p-3 p-md-4">
		<div>
			<div class="d-flex align-items-start justify-content-between mb-3 position-relative" style="z-index: 5;">
				<a href="<?= htmlspecialchars(profile_url((string) $author), ENT_QUOTES, 'UTF-8') ?>"
				   class="js-post-card-author d-flex align-items-center gap-2 text-decoration-none text-body min-w-0 position-relative"
				   style="z-index: 3;">
				<?php
				$authorAvatarUrl = $post['author_avatar_url'] ?? '';
				$displayAvatarUrl = $authorAvatarUrl ? media_public_src($authorAvatarUrl) : '';
				if ($displayAvatarUrl):
				?>
					<!-- Image Avatar -->
					<img src="<?= htmlspecialchars($displayAvatarUrl) ?>"
						 class="rounded-circle"
						 width="40" height="40"
						 style="object-fit: cover; flex-shrink: 0;"
						 alt="Avatar"
						 loading="lazy"
						 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
					<!-- Text Avatar Fallback -->
					<div class="avatar-sm" style="background: <?= htmlspecialchars($authorColor['bg']) ?>; color: <?= htmlspecialchars($authorColor['fg']) ?>; display: none;">
						<?= Avatar::initials((string) $author) ?>
					</div>
				<?php else: ?>
					<!-- Text Avatar (no S3 URL) -->
					<div class="avatar-sm" style="background: <?= htmlspecialchars($authorColor['bg']) ?>; color: <?= htmlspecialchars($authorColor['fg']) ?>;">
						<?= Avatar::initials((string) $author) ?>
					</div>
				<?php endif; ?>
					<div class="min-w-0">
						<div class="fw-semibold text-truncate"><?= htmlspecialchars($author) ?></div>
						<div class="small text-secondary d-flex align-items-center gap-1">
							<span><?= htmlspecialchars((string) $createdAt) ?></span>
							<i class="bi <?= $visibleIcon ?>" title="<?= htmlspecialchars($visibleLabel, ENT_QUOTES, 'UTF-8') ?>"></i>
						</div>
					</div>
				</a>
				<?php if (isset($currentUser['id']) && (int) $currentUser['id'] === (int) ($post['user_id'] ?? 0)): ?>
					<div class="dropdown position-relative js-post-card-menu" style="z-index: 3;">
						<button class="btn btn-sm btn-light rounded-pill" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" data-bs-auto-close="outside">
							<i class="bi bi-three-dots"></i>
						</button>
						<ul class="dropdown-menu dropdown-menu-end post-action-menu">
							<li>
								<a class="dropdown-item" href="<?= BASE_URL ?>/post/edit/<?= (int) $post['id'] ?>">
									Chỉnh sửa
								</a>
							</li>
							<li>
								<button
									type="button"
									class="dropdown-item text-danger post-card-delete-btn js-app-confirm-trigger w-100 text-start border-0"
									data-delete-action="<?= htmlspecialchars(BASE_URL . '/post/' . (int) $post['id'] . '/delete', ENT_QUOTES, 'UTF-8') ?>"
									data-delete-csrf="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>"
									data-confirm-title="Xóa bài viết"
									data-confirm-message="Bạn có chắc muốn xóa bài viết này?"
									data-confirm-danger="1"
									data-confirm-ok="Xóa"
								>
									Xóa bài viết
								</button>
							</li>
						</ul>
					</div>
				<?php endif; ?>
			</div>

			<div class="mb-3 position-relative" style="z-index: 1;">
				<p class="mb-0"><?= format_post_display_html((string) $content, is_array($hashtagNames) ? $hashtagNames : []) ?></p>
				<?php if (!empty($post['media'])): ?>
					<?php
					$mediaItems = array_values(array_filter((array) $post['media'], static function ($m) {
						$u = (string) ($m['media_url'] ?? '');
						return trim($u) !== '';
					}));
					?>
					<?php $totalMedia = count($mediaItems); ?>
					<div class="mt-3 row g-2">
						<?php foreach (array_slice($mediaItems, 0, 4) as $idx => $media): ?>
							<?php
								$src = media_public_src((string) ($media['media_url'] ?? ''));
								if ($src === '') {
									continue;
								}
								$isVideo = (($media['media_type'] ?? '') === 'video');
								$extraCount = max(0, $totalMedia - 4);
								?>
							<div class="<?= $totalMedia === 1 ? 'col-12' : 'col-6' ?>">
								<div class="preview-wrapper position-relative">
									<?php if ($isVideo): ?>
										<video src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" controls class="w-100 rounded-4 <?= $totalMedia === 1 ? '' : 'post-media-tile' ?>" playsinline></video>
									<?php else: ?>
										<img src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>"
										class="img-fluid rounded-4 js-open-post-modal-media <?= $totalMedia === 1 ? 'w-100' : 'post-media-tile' ?>" alt=""
										loading="lazy"
										style="background: #f0f0f0;">
									<?php endif; ?>
									<?php if ($idx === 3 && $extraCount > 0): ?>
										<div class="post-media-more-overlay">+<?= $extraCount ?></div>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="feed-post-actions position-relative d-flex align-items-center justify-content-between gap-3 text-secondary small" style="z-index: 10;">
			<div class="d-flex align-items-center gap-3">
			<form
				method="POST"
				action="/post/<?= $postId ?>/like"
				class="m-0 d-inline-flex align-items-center gap-1 ajax-post-like"
				data-post-id="<?= $postId ?>"
			>
					<input type="hidden" name="post_id" value="<?= $postId ?>">
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
				<button class="js-comment-btn text-decoration-none d-inline-flex align-items-center gap-1 text-secondary border-0 bg-transparent p-0" style="cursor: pointer; font: inherit;" aria-label="Bình luận">
					<i class="bi bi-chat"></i>
					<span id="comment-count-<?= $postId ?>"><?= $comments ?></span>
				</button>
				<form
					method="POST"
			action="/post/<?= $postId ?>/share"
			class="m-0 d-inline-flex align-items-center gap-1 ajax-post-share js-share-form"
			data-post-id="<?= $postId ?>"
			data-post-url="/post/<?= $postId ?>"
				>
					<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
					<button type="submit" class="btn btn-link text-decoration-none p-0 border-0 text-secondary" aria-label="Chia sẻ">
						<i class="bi bi-share"></i>
					</button>
					<span id="share-count-<?= $postId ?>" class="text-secondary"><?= $shareCount ?></span>
				</form>
			</div>
			<form
					method="POST"
				action="/post/<?= $postId ?>/save"
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
