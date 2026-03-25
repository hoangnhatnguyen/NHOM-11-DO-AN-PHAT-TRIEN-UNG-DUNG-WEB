<section class="card">
	<h1>Sua nguoi dung</h1>
	<?php if (empty($user)): ?>
		<p>Khong tim thay user.</p>
	<?php else: ?>
		<p>ID: <?= (int)($user['id'] ?? 0) ?></p>
		<p>Username: <?= htmlspecialchars($user['username'] ?? 'N/A') ?></p>
		<p>Email: <?= htmlspecialchars($user['email'] ?? 'N/A') ?></p>
	<?php endif; ?>
</section>

