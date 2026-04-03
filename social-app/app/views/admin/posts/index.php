<div class="container py-3 admin-page">
	<?php
	$adminTitle = 'Quản lý bài viết';
	include VIEW_PATH . 'admin/partials/topbar.php';
	?>

	<form method="GET" action="<?= BASE_URL ?>/admin/posts" class="mb-3">
		<div class="row g-2">
			<div class="col-12 col-md-6">
				<input type="text" name="keyword" class="form-control rounded-pill" placeholder="Nhập từ khóa..." value="<?= htmlspecialchars((string) ($keyword ?? '')) ?>">
			</div>
			<div class="col-12 col-md-4">
				<select name="field" class="form-select rounded-pill">
					<option value="user" <?= (($field ?? '') === 'user') ? 'selected' : '' ?>>Người dùng</option>
					<option value="hashtag" <?= (($field ?? '') === 'hashtag') ? 'selected' : '' ?>>Hashtag</option>
					<option value="content" <?= (($field ?? 'content') === 'content') ? 'selected' : '' ?>>Nội dung</option>
				</select>
			</div>
			<div class="col-12 col-md-2">
				<button class="btn btn-primary w-100 rounded-pill" type="submit">Lọc</button>
			</div>
		</div>
	</form>

	<?php if (empty($posts ?? [])): ?>
		<div class="card border-0 shadow-sm rounded-4">
			<div class="card-body text-secondary">Không có bài viết phù hợp.</div>
		</div>
	<?php else: ?>
		<?php foreach ($posts as $post): ?>
			<a href="<?= BASE_URL ?>/post/<?= (int) ($post['id'] ?? 0) ?>" class="js-open-post-modal text-decoration-none text-dark" data-post-id="<?= (int) ($post['id'] ?? 0) ?>">
				<div class="card border-0 shadow-sm rounded-4 mb-3">
					<div class="card-body">
						<div class="d-flex align-items-center justify-content-between mb-2">
							<div class="fw-semibold"><?= htmlspecialchars((string) ($post['author_name'] ?? 'Người dùng')) ?></div>
							<div class="small text-secondary">#<?= (int) ($post['id'] ?? 0) ?></div>
						</div>
						<div class="text-secondary small mb-1"><?= htmlspecialchars((string) ($post['created_at'] ?? '')) ?></div>
						<div><?= nl2br(htmlspecialchars((string) ($post['content'] ?? ''))) ?></div>
					</div>
				</div>
			</a>
		<?php endforeach; ?>
	<?php endif; ?>
</div>