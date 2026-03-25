<div class="row g-3 g-lg-4 feed-layout px-lg-4">
	<div class="col-12 col-md-2 col-lg-3">
		<?php include VIEW_PATH . 'partials/feed/left_sidebar.php'; ?>
	</div>

	<div class="col-12 col-md-7 col-lg-6">
		<div class="card border-0 shadow-sm rounded-4 mb-3">
			<div class="card-body p-3 p-md-4">
				<?php include VIEW_PATH . 'partials/feed/composer.php'; ?>
			</div>
		</div>

		<?php if (!empty($dbError)): ?>
			<div class="alert alert-danger rounded-4">DB error: <?= htmlspecialchars($dbError) ?></div>
		<?php endif; ?>

		<?php if (empty($posts ?? [])): ?>
			<div class="card border-0 shadow-sm rounded-4">
				<div class="card-body">
					<p class="text-secondary mb-0">Chưa có bài viết. Thêm dữ liệu bảng <strong>posts</strong> để kiểm tra hiển thị trang chủ.</p>
				</div>
			</div>
		<?php else: ?>
			<?php foreach ($posts as $post): ?>
				<?php include VIEW_PATH . 'partials/post_card.php'; ?>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<div class="col-12 col-md-3 col-lg-3">
		<?php include VIEW_PATH . 'partials/feed/right_widgets.php'; ?>
	</div>
</div>

