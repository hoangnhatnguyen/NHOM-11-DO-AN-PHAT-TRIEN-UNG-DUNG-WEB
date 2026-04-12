<div class="bg-white rounded-4 shadow-sm p-3 p-md-4">
	<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
		<h1 class="h4 fw-bold mb-0">Quản lý người dùng</h1>
		<span class="text-secondary small">Tổng: <?= (int) ($paginationTotal ?? 0) ?></span>
	</div>

	<form method="GET" action="<?= BASE_URL ?>/admin/users" class="mb-4">
		<input type="hidden" name="per_page" value="<?= (int) ($paginationPerPage ?? 15) ?>">
		<div class="input-group">
			<input type="text" name="q" class="form-control rounded-start-pill" placeholder="Tìm theo tên hoặc email..." value="<?= htmlspecialchars((string) ($keyword ?? '')) ?>">
			<button class="btn btn-primary rounded-end-pill px-4" type="submit">Tìm kiếm</button>
		</div>
	</form>

	<?php if (empty($users ?? [])): ?>
		<p class="text-secondary mb-0">Không có người dùng phù hợp.</p>
	<?php else: ?>
		<div class="table-responsive">
			<table class="table table-hover align-middle mb-0">
				<thead class="table-light">
					<tr>
						<th scope="col">ID</th>
						<th scope="col">Tên</th>
						<th scope="col">Email</th>
						<th scope="col">Vai trò</th>
						<th scope="col">Trạng thái</th>
						<th scope="col">Ngày tạo</th>
						<th scope="col" class="text-end">Thao tác</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($users as $user): ?>
						<tr>
							<td><?= (int) ($user['id'] ?? 0) ?></td>
							<td class="fw-semibold"><?= htmlspecialchars((string) ($user['username'] ?? '')) ?></td>
							<td class="small"><?= htmlspecialchars((string) ($user['email'] ?? '')) ?></td>
							<td>
								<?php if (($user['role'] ?? 'user') === 'admin'): ?>
									<span class="badge bg-primary-subtle text-primary">Admin</span>
								<?php else: ?>
									<span class="badge bg-secondary-subtle text-secondary">User</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if (!empty($user['is_active'])): ?>
									<span class="badge bg-success-subtle text-success">Hoạt động</span>
								<?php else: ?>
									<span class="badge bg-danger-subtle text-danger">Đã khóa</span>
								<?php endif; ?>
							</td>
							<td class="small text-secondary"><?= htmlspecialchars((string) ($user['created_at'] ?? '')) ?></td>
							<td class="text-end">
								<form method="POST" action="<?= BASE_URL ?>/admin/users/toggle-status" class="d-inline js-app-confirm-form" data-confirm-title="Đổi trạng thái" data-confirm-message="Xác nhận đổi trạng thái tài khoản này?" data-confirm-ok="Xác nhận">
									<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
									<input type="hidden" name="user_id" value="<?= (int) ($user['id'] ?? 0) ?>">
									<input type="hidden" name="active" value="<?= !empty($user['is_active']) ? 0 : 1 ?>">
									<button type="button" class="btn btn-sm rounded-pill <?= !empty($user['is_active']) ? 'btn-outline-warning' : 'btn-outline-success' ?> js-app-confirm-trigger">
										<?= !empty($user['is_active']) ? 'Khóa' : 'Mở khóa' ?>
									</button>
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
		$paginationBaseUrl = '/admin/users';
		$paginationQuery = [
			'q' => ($keyword ?? '') !== '' ? (string) $keyword : null,
			'per_page' => (int) ($paginationPerPage ?? 15),
		];
		include VIEW_PATH . 'admin/partials/pagination.php';
		?>
	<?php endif; ?>
</div>
