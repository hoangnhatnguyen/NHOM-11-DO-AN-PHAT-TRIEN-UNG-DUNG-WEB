<section class="card">
	<h1>Danh sach bai viet</h1>
	<?php if (empty($posts)): ?>
		<p>Chua co du lieu hoac chua ket noi database.</p>
	<?php else: ?>
		<ul>
			<?php foreach ($posts as $post): ?>
				<li>#<?= (int)($post['id'] ?? 0) ?> - <?= htmlspecialchars($post['content'] ?? '(khong co noi dung)') ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

