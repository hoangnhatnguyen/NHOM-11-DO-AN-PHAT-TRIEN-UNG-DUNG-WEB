<?php
/**
 * Node renderer for comment replies stored in `comments` table.
 *
 * @var array $node Node: {id, author_name, content, created_at, children[]}
 * @var int $postId
 * @var string $csrfToken
 * @var int $depth
 */

if (!isset($node) || !is_array($node)) {
	return;
}

$nodeId = (int) ($node['id'] ?? 0);
$children = $node['children'] ?? [];
$childrenCount = is_array($children) ? count($children) : 0;
$author = (string) ($node['author_name'] ?? 'Người dùng');
$content = (string) ($node['content'] ?? '');
$createdAt = $node['created_at'] ?? null;

$autoExpand = ((int) $depth) >= 1;
?>

<div class="d-block" id="comment-<?= $nodeId ?>" style="margin-left: <?= min(56, (int) $depth * 14) ?>px">
	<div class="d-flex mb-2">
		<div class="avatar-sm me-2 flex-shrink-0"><?= strtoupper(substr($author, 0, 1)) ?></div>
		<div class="border rounded-4 p-2 bg-light w-100">
			<div class="small fw-semibold"><?= htmlspecialchars($author) ?></div>
			<div><?= nl2br(htmlspecialchars($content)) ?></div>
		</div>
	</div>

	<div class="d-flex align-items-center gap-3 ms-5 mb-2 small">
		<?php if ($childrenCount > 0 && !$autoExpand): ?>
			<button
				type="button"
				class="btn btn-link btn-sm p-0 text-decoration-none text-secondary toggle-replies-btn"
				data-target="#replies-<?= $nodeId ?>"
				data-show-text="Xem <?= $childrenCount ?> câu trả lời"
				data-hide-text="Ẩn câu trả lời"
			>
				Xem <?= $childrenCount ?> câu trả lời
			</button>
		<?php endif; ?>

		<button
			type="button"
			class="btn btn-link btn-sm p-0 text-decoration-none text-secondary toggle-reply-form-btn"
			data-target="#reply-form-<?= $nodeId ?>"
		>
			Trả lời
		</button>
		<div class="text-secondary"><?= htmlspecialchars(format_comment_time_vi((string) $createdAt)) ?></div>
	</div>

	<?php if ($childrenCount > 0): ?>
		<div class="ms-3 <?= $autoExpand ? '' : 'd-none' ?>" id="replies-<?= $nodeId ?>">
			<?php foreach ($children as $child): ?>
				<?php
				// Recurse
				$node = $child;
				$depth = (int) $depth + 1;
				include VIEW_PATH . 'partials/comment_reply_branch.php';
				?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<form
		method="POST"
		action="<?= BASE_URL ?>/post/<?= (int) $postId ?>/comment/<?= (int) $nodeId ?>/reply"
		class="mb-2 d-none ajax-post-reply"
		id="reply-form-<?= $nodeId ?>"
		data-post-id="<?= (int) $postId ?>"
	>
		<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
		<div class="input-group input-group-sm rounded-4 p-1 bg-light ms-3">
			<input type="text" name="content" class="form-control border-0 bg-light" placeholder="Viết phản hồi..." required>
			<button type="submit" class="btn btn-outline-secondary rounded-pill">
				Gửi
			</button>
		</div>
	</form>
</div>