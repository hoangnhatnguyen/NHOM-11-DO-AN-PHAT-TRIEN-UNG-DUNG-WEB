<div class="container py-3 admin-page">
	<?php
	$adminTitle = 'Tổng quan';
	include VIEW_PATH . 'admin/partials/topbar.php';
	?>

	<div class="row g-3">
		<div class="col-12 col-md-4">
			<div class="card border-0 shadow-sm rounded-4">
				<div class="card-body">
					<div class="text-secondary small">Tổng số người dùng</div>
					<div class="fs-3 fw-bold"><?= (int) ($stats['totalUsers'] ?? 0) ?></div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-4">
			<div class="card border-0 shadow-sm rounded-4">
				<div class="card-body">
					<div class="text-secondary small">User mới hôm nay</div>
					<div class="fs-3 fw-bold"><?= (int) ($stats['newUsersToday'] ?? 0) ?></div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-4">
			<div class="card border-0 shadow-sm rounded-4">
				<div class="card-body">
					<div class="text-secondary small">Tổng post</div>
					<div class="fs-3 fw-bold"><?= (int) ($stats['totalPosts'] ?? 0) ?></div>
				</div>
			</div>
		</div>
	</div>
</div>

