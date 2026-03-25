<section class="card">
	<h1>Social App MVC</h1>
	<p class="muted">Bo khung toi thieu da chay: Router + BaseController + Database + View Layout.</p>
	<ul>
		<li>Route mac dinh: /</li>
		<li>Route user newfeed: /newfeed</li>
		<li>Route admin dashboard: /admin</li>
		<li>Route admin posts: /admin/posts</li>
		<li>Route admin users: /admin/users</li>
	</ul>

	<?php if (isset($dbError) && $dbError !== null): ?>
		<p style="color:#b91c1c;"><strong>DB error:</strong> <?= htmlspecialchars($dbError) ?></p>
	<?php endif; ?>

	<?php if (isset($posts)): ?>
		<hr>
		<h2>Danh sach bai viet (test DB)</h2>
		<?php if (empty($posts)): ?>
			<p class="muted">Khong co du lieu posts (hoac bang `posts` dang trong).</p>
		<?php else: ?>
			<ul>
				<?php foreach ($posts as $post): ?>
					<li>#<?= (int)($post['id'] ?? 0) ?> - <?= htmlspecialchars($post['content'] ?? '[khong co content]') ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>
</section>

