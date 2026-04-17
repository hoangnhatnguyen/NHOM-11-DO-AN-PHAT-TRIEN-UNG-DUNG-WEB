<div class="bg-white rounded-4 shadow-sm p-3 p-md-4">
	<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
		<h1 class="h4 fw-bold mb-0">Quản lý bài viết</h1>
		<span class="text-secondary small">Tổng: <?= (int) ($paginationTotal ?? 0) ?></span>
	</div>

	<form method="GET" action="<?= BASE_URL ?>/admin/posts" class="row g-2 mb-4">
		<input type="hidden" name="per_page" value="<?= (int) ($paginationPerPage ?? 15) ?>">
		<div class="col-12 col-md-5">
			<input type="text" name="keyword" class="form-control rounded-pill" placeholder="Từ khóa..." value="<?= htmlspecialchars((string) ($keyword ?? '')) ?>">
		</div>
		<div class="col-12 col-md-4">
			<select name="field" class="form-select rounded-pill">
				<option value="user" <?= (($field ?? '') === 'user') ? 'selected' : '' ?>>Người dùng</option>
				<option value="hashtag" <?= (($field ?? '') === 'hashtag') ? 'selected' : '' ?>>Hashtag</option>
				<option value="content" <?= (($field ?? 'content') === 'content') ? 'selected' : '' ?>>Nội dung</option>
			</select>
		</div>
		<div class="col-12 col-md-3">
			<button class="btn btn-primary w-100 rounded-pill" type="submit">Lọc</button>
		</div>
	</form>

	<?php if (empty($posts ?? [])): ?>
		<p class="text-secondary mb-0">Không có bài viết phù hợp.</p>
	<?php else: ?>
		<div class="table-responsive">
			<table class="table table-hover align-middle mb-0">
				<thead class="table-light">
					<tr>
						<th scope="col">ID</th>
						<th scope="col">Tác giả</th>
						<th scope="col">Nội dung</th>
						<th scope="col">Hiển thị</th>
						<th scope="col">Ngày đăng</th>
						<th scope="col" class="text-end">Thao tác</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($posts as $post): ?>
						<?php
						$pid = (int) ($post['id'] ?? 0);
						$vis = (string) ($post['visible'] ?? 'public');
						$visLabel = $vis === 'followers' ? 'Theo dõi' : ($vis === 'private' ? 'Riêng tư' : 'Công khai');
						$content = (string) ($post['content'] ?? '');
						$snippet = function_exists('mb_strimwidth') ? mb_strimwidth($content, 0, 120, '...', 'UTF-8') : (strlen($content) > 120 ? substr($content, 0, 117) . '...' : $content);
						?>
						<tr>
							<td><?= $pid ?></td>
							<td class="fw-semibold"><?= htmlspecialchars((string) ($post['author_name'] ?? '')) ?></td>
							<td class="small"><?= nl2br(htmlspecialchars($snippet)) ?></td>
							<td><span class="badge bg-light text-dark border"><?= htmlspecialchars($visLabel) ?></span></td>
							<td class="small text-secondary"><?= htmlspecialchars((string) ($post['created_at'] ?? '')) ?></td>
							<td class="text-end text-nowrap">
								<a href="<?= BASE_URL ?>/admin/posts/edit/<?= $pid ?>" class="btn btn-sm btn-outline-primary rounded-pill me-1">Sửa</a>
								<a href="<?= BASE_URL ?>/post/<?= $pid ?>" class="btn btn-sm btn-outline-secondary rounded-pill me-1" target="_blank" rel="noopener">Xem</a>
								<form method="POST" action="<?= BASE_URL ?>/admin/posts/destroy/<?= $pid ?>" class="d-inline js-app-confirm-form" data-confirm-title="Xóa bài viết" data-confirm-message="Xóa bài viết #<?= $pid ?>? Hành động không hoàn tác." data-confirm-danger="1" data-confirm-ok="Xóa">
									<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
									<button type="button" class="btn btn-sm btn-outline-danger rounded-pill js-app-confirm-trigger">Xóa</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php
		$paginationPage = (int) ($paginationPage ?? 1);
		$paginationTotalPages = (int) ($paginationTotalPages ?? 1);
		$paginationBaseUrl = '/admin/posts';
		$paginationQuery = [
			'keyword' => ($keyword ?? '') !== '' ? (string) $keyword : null,
			'field' => (string) ($field ?? 'content'),
			'per_page' => (int) ($paginationPerPage ?? 15),
		];
		include VIEW_PATH . 'admin/partials/pagination.php';
		?>
	<?php endif; ?>
</div>
