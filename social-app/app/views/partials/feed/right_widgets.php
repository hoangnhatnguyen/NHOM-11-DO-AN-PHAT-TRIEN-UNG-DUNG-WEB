<?php
if (!isset($trendingHashtags) || !is_array($trendingHashtags)) {
	require_once dirname(__DIR__, 3) . '/models/Hashtag.php';
	$trendingHashtags = (new Hashtag())->getTrending(5);
}
?>
<aside class="d-flex flex-column gap-3 right-sticky">

	<!-- 🔍 SEARCH -->
	<div class="position-relative search-box">
		<i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-secondary"></i>

		<input
			id="search-input"
			class="form-control rounded-pill ps-5 border-0 shadow-sm feed-search-input"
			placeholder="Tìm kiếm..."
		>

		<!-- 🔥 POPUP GẦN ĐÂY -->
		<div id="recent-popup" class="recent-popup hidden">
			<div class="d-flex justify-content-between px-3 py-2 border-bottom">
				<span class="fw-bold">Gần đây</span>
				<button type="button" id="clear-recent" class="btn btn-sm text-primary">Xóa tất cả</button>
			</div>
			<div id="recent-list"></div>
		</div>
	</div>

	<!-- 🔥 TRENDING -->
	<section class="card border-primary-subtle rounded-4 shadow-sm">
		<div class="card-body p-3">
			<h6 class="fw-bold text-primary mb-3">Đang phổ biến</h6>

			<div id="right-trending" class="d-flex flex-column gap-2">
				<?php if (empty($trendingHashtags)): ?>
					<p class="text-muted small mb-0">Chưa có hashtag nào trong bài viết active.</p>
				<?php else: ?>
					<?php foreach (array_values($trendingHashtags) as $ti => $row): ?>
						<?php
						$hname = (string) ($row['name'] ?? '');
						if ($hname === '') {
							continue;
						}
						$qVal = '#' . $hname;
						?>
						<div class="trend" data-q="<?= htmlspecialchars($qVal, ENT_QUOTES, 'UTF-8') ?>" role="button" tabindex="0">
							<small class="text-secondary">#<?= (int) $ti + 1 ?> Trending</small><br>
							<b>#<?= htmlspecialchars($hname, ENT_QUOTES, 'UTF-8') ?></b>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<?php include __DIR__ . '/suggest_follow_widget.php'; ?>

</aside>

<style>
.recent-popup {
	position: absolute;
	top: 45px;
	width: 100%;
	background: white;
	border-radius: 12px;
	box-shadow: 0 4px 12px rgba(0,0,0,0.1);
	z-index: 999;
}

.hidden {
	display: none !important;
}

.recent-item {
	padding: 10px 15px;
	cursor: pointer;
}

.recent-item:hover {
	background: #f5f5f5;
}

.trend {
	padding: 10px 0;
	cursor: pointer;
	border-bottom: 1px solid #eee;
}

.trend:hover {
	background: #f5f5f5;
}
</style>
