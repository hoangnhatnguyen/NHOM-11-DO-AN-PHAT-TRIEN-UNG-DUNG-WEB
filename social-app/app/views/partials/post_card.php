<?php
$author = $post['author_name'] ?? $post['user_name'] ?? 'Nguoi dung';
$content = $post['content'] ?? $post['caption'] ?? '[Khong co noi dung]';
$likes = (int) ($post['like_count'] ?? 0);
$comments = (int) ($post['comment_count'] ?? 0);
$createdAt = $post['created_at'] ?? 'vua xong';
?>

<article class="card border-0 shadow-sm rounded-4 mb-3">
	<div class="card-body p-3 p-md-4">
		<div class="d-flex align-items-start justify-content-between mb-3">
			<div class="d-flex align-items-center gap-2">
				<div class="avatar-sm"><?= strtoupper(substr($author, 0, 1)) ?></div>
				<div>
					<div class="fw-semibold"><?= htmlspecialchars($author) ?></div>
					<div class="small text-secondary"><?= htmlspecialchars((string) $createdAt) ?></div>
				</div>
			</div>
			<button class="btn btn-sm btn-light rounded-pill"><i class="bi bi-three-dots"></i></button>
		</div>

		<p class="mb-3"><?= nl2br(htmlspecialchars($content)) ?></p>

		<div class="d-flex align-items-center gap-3 text-secondary small">
			<span><i class="bi bi-heart-fill text-danger me-1"></i><?= $likes ?></span>
			<span><i class="bi bi-chat me-1"></i><?= $comments ?></span>
			<span><i class="bi bi-bookmark"></i></span>
		</div>
	</div>
</article>

