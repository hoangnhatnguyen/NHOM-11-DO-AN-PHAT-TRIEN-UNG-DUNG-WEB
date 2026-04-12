<div class="bg-white rounded-4 shadow-sm p-3 p-md-4">
	<h1 class="h4 fw-bold mb-4">Tổng quan</h1>
	<div class="row g-3">
		<div class="col-12 col-md-4">
			<div class="border rounded-4 p-4 h-100 bg-light">
				<div class="text-secondary small">Tổng số người dùng</div>
				<div class="fs-3 fw-bold mt-1"><?= (int) ($stats['totalUsers'] ?? 0) ?></div>
			</div>
		</div>
		<div class="col-12 col-md-4">
			<div class="border rounded-4 p-4 h-100 bg-light">
				<div class="text-secondary small">User mới hôm nay</div>
				<div class="fs-3 fw-bold mt-1"><?= (int) ($stats['newUsersToday'] ?? 0) ?></div>
			</div>
		</div>
		<div class="col-12 col-md-4">
			<div class="border rounded-4 p-4 h-100 bg-light">
				<div class="text-secondary small">Tổng bài viết</div>
				<div class="fs-3 fw-bold mt-1"><?= (int) ($stats['totalPosts'] ?? 0) ?></div>
			</div>
		</div>
	</div>
</div>
