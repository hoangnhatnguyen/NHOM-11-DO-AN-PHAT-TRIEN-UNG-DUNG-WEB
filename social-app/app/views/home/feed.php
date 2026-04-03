<?php $feedTab = $feedTab ?? 'foryou'; ?>
<ul class="nav nav-underline justify-content-center mb-3 feed-tabs">
	<li class="nav-item">
		<a class="nav-link <?= $feedTab === 'foryou' ? 'active' : '' ?>" href="<?= BASE_URL ?>/">Dành cho bạn</a>
	</li>
	<li class="nav-item">
		<a class="nav-link <?= $feedTab === 'following' ? 'active' : '' ?>" href="<?= BASE_URL ?>/?tab=following">Đang theo dõi</a>
	</li>
</ul>

<div class="card border-0 shadow-sm rounded-4 mb-3">
	<div class="card-body p-3 p-md-4">
		<?php include VIEW_PATH . 'partials/feed/composer.php'; ?>
	</div>
</div>

<div class="feed-post" data-feed-tab="<?= htmlspecialchars($feedTab) ?>" data-offset="5" data-has-more="<?= count($posts ?? []) >= 5 ? 'true' : 'false' ?>">
	<?php if (!empty($dbError)): ?>
		<div class="alert alert-danger rounded-4">DB error: <?= htmlspecialchars($dbError) ?></div>
	<?php endif; ?>

	<?php if (empty($posts ?? [])): ?>
		<div class="card border-0 shadow-sm rounded-4">
			<div class="card-body">
				<?php if (($feedTab ?? 'foryou') === 'following'): ?>
					<p class="text-secondary mb-0">Chưa có bài từ người bạn theo dõi. Theo dõi thêm người dùng hoặc quay về <a href="<?= BASE_URL ?>/">Dành cho bạn</a>.</p>
				<?php else: ?>
					<p class="text-secondary mb-0">Chưa có bài viết. Thêm dữ liệu bảng <strong>posts</strong> để kiểm tra hiển thị trang chủ.</p>
				<?php endif; ?>
			</div>
		</div>
	<?php else: ?>
		<?php foreach ($posts as $post): ?>
			<?php $currentUser = $currentUser; ?>
			<?php include VIEW_PATH . 'partials/post_card.php'; ?>
		<?php endforeach; ?>
	<?php endif; ?>

	<!-- Loading indicator -->
	<div class="feed-loading" style="display: none; text-align: center; padding: 20px;">
		<div class="spinner-border text-primary" role="status">
			<span class="visually-hidden">Đang tải...</span>
		</div>
	</div>

	<!-- No more posts indicator -->
	<div class="feed-no-more" style="display: none; text-align: center; padding: 20px; color: #6c757d;">
		<p class="mb-0">Không còn bài viết nào</p>
	</div>
</div>
