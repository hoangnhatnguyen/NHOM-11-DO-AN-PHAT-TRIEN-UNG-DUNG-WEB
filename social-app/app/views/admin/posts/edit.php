<?php
$postRow = $post ?? [];
$pid = (int) ($postRow['id'] ?? 0);
$vis = (string) ($postRow['visible'] ?? 'public');
?>
<div class="bg-white rounded-4 shadow-sm p-3 p-md-4" style="max-width: 720px;">
	<div class="d-flex align-items-center justify-content-between gap-2 mb-3">
		<h1 class="h4 fw-bold mb-0">Sửa bài viết #<?= $pid ?></h1>
		<a href="<?= BASE_URL ?>/admin/posts" class="btn btn-sm btn-light rounded-pill">← Danh sách</a>
	</div>
	<p class="text-secondary small mb-4">Tác giả: <strong><?= htmlspecialchars((string) ($authorUsername ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></p>

	<form method="POST" action="<?= BASE_URL ?>/admin/posts/update/<?= $pid ?>">
		<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">

		<div class="mb-3">
			<label class="form-label fw-semibold">Nội dung</label>
			<textarea name="content" class="form-control rounded-3" rows="8" required><?= htmlspecialchars((string) ($postRow['content'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
		</div>

		<div class="mb-4">
			<label class="form-label fw-semibold">Ai có thể xem</label>
			<select name="privacy" class="form-select rounded-pill">
				<option value="public" <?= $vis === 'public' ? 'selected' : '' ?>>Công khai</option>
				<option value="followers" <?= $vis === 'followers' ? 'selected' : '' ?>>Người theo dõi</option>
				<option value="private" <?= $vis === 'private' ? 'selected' : '' ?>>Chỉ mình tôi</option>
			</select>
		</div>

		<button type="submit" class="btn btn-primary rounded-pill px-4">Lưu thay đổi</button>
	</form>
</div>
