<?php
require_once dirname(__DIR__, 2) . '/helpers/notification_helper.php';
/**
 * Facebook-style comment tree: 2 levels only (top-level + replies)
 * All comments can be replied to
 *
 * @var array $node Node: {id, level, author_name, content, created_at, children[]}
 * @var int $postId
 * @var string $csrfToken
 * @var int $depth
 */

if (!isset($node) || !is_array($node)) {
	return;
}

$nodeId = (int) ($node['id'] ?? 0);
$nodeLevel = (int) ($node['level'] ?? 1);
$children = $node['children'] ?? [];
$childrenCount = is_array($children) ? count($children) : 0;
$author = (string) ($node['author_name'] ?? 'Người dùng');
$content = (string) ($node['content'] ?? '');
$createdAt = $node['created_at'] ?? null;
$profileBase = rtrim((string) (($profileBaseUrl ?? BASE_URL) ?: ''), '/');
$avatarUrlRaw = (string) ($node['author_avatar_url'] ?? '');
$avatarDisplayUrl = $avatarUrlRaw ? media_public_src($avatarUrlRaw) : '';
$authorColor = Avatar::colors($author);

$autoExpand = ((int) $depth) >= 1;
$indentLevel = (int) $depth;
$indentPx = min(56, $indentLevel * 14);
$hasChildren = $childrenCount > 0;
?>

<!-- Comment wrapper - Facebook style flat display -->
<div class="comment-node mb-2" id="comment-<?= $nodeId ?>" style="margin-left: <?= $indentPx ?>px;" data-level="<?= $nodeLevel ?>">
	<!-- Comment card -->
	<div class="d-flex gap-2">
		<!-- Avatar column -->
		<div class="flex-shrink-0">
			<a href="<?= htmlspecialchars($profileBase . '/profile?u=' . rawurlencode($author), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none">
				<?php if ($avatarDisplayUrl): ?>
					<img src="<?= htmlspecialchars($avatarDisplayUrl, ENT_QUOTES, 'UTF-8') ?>"
						 class="rounded-circle"
						 width="36" height="36"
						 style="object-fit: cover; flex-shrink: 0;"
						 alt="Avatar"
						 loading="lazy"
						 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
					<div class="avatar-sm" style="background: <?= htmlspecialchars($authorColor['bg'], ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars($authorColor['fg'], ENT_QUOTES, 'UTF-8') ?>; display: none; width: 36px; height: 36px; font-size: 14px; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold;"><?= strtoupper(substr($author, 0, 1)) ?></div>
				<?php else: ?>
					<div class="avatar-sm" style="background: <?= htmlspecialchars($authorColor['bg'], ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars($authorColor['fg'], ENT_QUOTES, 'UTF-8') ?>; width: 36px; height: 36px; font-size: 14px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold;"><?= strtoupper(substr($author, 0, 1)) ?></div>
				<?php endif; ?>
			</a>
		</div>

		<!-- Comment content column -->
		<div class="flex-grow-1 min-width-0">
			<!-- Comment bubble -->
			<div class="border rounded-3 p-2 bg-light comment-bubble">
				<a href="<?= htmlspecialchars($profileBase . '/profile?u=' . rawurlencode($author), ENT_QUOTES, 'UTF-8') ?>" class="small fw-semibold text-decoration-none text-dark"><?= htmlspecialchars($author) ?></a>
				<div class="small text-break"><?= format_post_body_html($content) ?></div>
			</div>

			<!-- Action buttons -->
			<div class="d-flex align-items-center gap-2 mt-1 ms-1 small flex-wrap">
				<?php if ($hasChildren): ?>
					<button
						type="button"
						class="btn btn-link btn-sm p-0 text-decoration-none text-secondary toggle-replies-btn"
						data-target="#replies-<?= $nodeId ?>"
						data-show-text="Xem <?= $childrenCount ?> phản hồi"
						data-hide-text="Ẩn phản hồi"
						style="font-size: 12px; cursor: pointer;"
					>
						<?php if ($autoExpand): ?>
							Ẩn phản hồi
						<?php else: ?>
							Xem <?= $childrenCount ?> phản hồi
						<?php endif; ?>
					</button>
				<?php endif; ?>

				<!-- All comments can be replied -->
				<button
					type="button"
					class="btn btn-link btn-sm p-0 text-decoration-none text-secondary toggle-reply-form-btn"
					data-post-id="<?= (int) $postId ?>"
					data-comment-id="<?= (int) $nodeId ?>"
					data-comment-author="<?= htmlspecialchars($author, ENT_QUOTES, 'UTF-8') ?>"
					data-csrf="<?= htmlspecialchars($csrfToken) ?>"
					data-level="<?= $nodeLevel ?>"
					style="font-size: 12px; cursor: pointer;"
				>
					Trả lời
				</button>
				<span class="text-secondary" style="font-size: 12px;"><?= htmlspecialchars(format_comment_time_vi((string) $createdAt)) ?></span>
			</div>
		</div>
	</div>

	<!-- Replies container - maintains flat display -->
	<?php if ($hasChildren): ?>
		<div id="replies-<?= $nodeId ?>" class="mt-2<?= $autoExpand ? '' : ' d-none' ?>">
			<?php 
			$parentDepth = $depth;
			foreach ($children as $child): 
			?>
				<?php
				// Recurse - reset depth for each sibling at same level
				$node = $child;
				$depth = (int) $parentDepth + 1;
				include VIEW_PATH . 'partials/comment_reply_branch.php';
				?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>