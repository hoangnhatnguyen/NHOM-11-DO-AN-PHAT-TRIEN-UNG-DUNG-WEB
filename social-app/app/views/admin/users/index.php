<div class="container py-3 admin-page">
	<?php
	$adminTitle = 'Quản lý người dùng';
	include VIEW_PATH . 'admin/partials/topbar.php';
	?>

	<form method="GET" action="<?= BASE_URL ?>/admin/users" class="mb-3">
		<div class="input-group">
			<input type="text" name="q" class="form-control rounded-pill me-2" placeholder="Tìm theo tên hoặc email..." value="<?= htmlspecialchars((string) ($keyword ?? '')) ?>">
			<button class="btn btn-primary rounded-pill me-1" type="submit">Tìm kiếm</button>
		</div>
	</form>

	<?php if (empty($users ?? [])): ?>
		<div class="card border-0 shadow-sm rounded-4">
			<div class="card-body text-secondary">Không có người dùng phù hợp.</div>
		</div>
	<?php else: ?>
		<?php foreach ($users as $user): ?>
			<div class="card border-0 shadow-sm rounded-4 mb-3">
				<div class="card-body d-flex justify-content-between align-items-center">
					<div class="d-flex align-items-center gap-3">
						<div class="avatar-sm"><?= strtoupper(substr((string) ($user['username'] ?? 'U'), 0, 1)) ?></div>
						<div>
							<div class="fw-semibold"><?= htmlspecialchars((string) ($user['username'] ?? '')) ?></div>
							<div class="text-secondary small"><?= htmlspecialchars((string) ($user['email'] ?? '')) ?></div>
							<div class="small mt-1">
								<?php if (!empty($user['is_active'])): ?>
									<span class="badge bg-success-subtle text-success">Đang hoạt động</span>
								<?php else: ?>
									<span class="badge bg-danger-subtle text-danger">Đã khóa</span>
								<?php endif; ?>
								<?php if (($user['role'] ?? 'user') === 'admin'): ?>
									<span class="badge bg-primary-subtle text-primary ms-1">Admin</span>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<form method="POST" action="<?= BASE_URL ?>/admin/users/toggle-status" class="m-0">
						<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
						<input type="hidden" name="user_id" value="<?= (int) ($user['id'] ?? 0) ?>">
						<input type="hidden" name="active" value="<?= !empty($user['is_active']) ? 0 : 1 ?>">
						<button class="btn btn-sm rounded-3 fw-bold <?= !empty($user['is_active']) ? 'btn-warning' : 'btn-success' ?>" onclick="return confirm('Xác nhận đổi trạng thái tài khoản?')">
							<?= !empty($user['is_active']) ? 'Khóa tài khoản' : 'Mở khóa' ?>
						</button>
					</form>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

