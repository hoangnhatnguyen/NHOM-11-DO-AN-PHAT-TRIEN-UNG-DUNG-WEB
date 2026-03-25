<section class="card">
	<h1>Danh sach nguoi dung</h1>
	<?php if (empty($users)): ?>
		<p>Chua co du lieu hoac chua ket noi database.</p>
	<?php else: ?>
		<ul>
			<?php foreach ($users as $user): ?>
				<li>
					#<?= (int)($user['id'] ?? 0) ?> - <?= htmlspecialchars($user['username'] ?? ($user['email'] ?? 'unknown')) ?>
					| <a href="<?= BASE_URL ?>/admin/users/edit/<?= (int)($user['id'] ?? 0) ?>">Sua</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

