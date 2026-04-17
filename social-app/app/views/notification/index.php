<style>
.tabs {
	display: flex;
	gap: 0;
	border-bottom: 1px solid #eee;
	margin-top: 6px;
}
.tab {
	flex: 1;
	text-align: center;
	padding: 12px 0;
	cursor: pointer;
	color: #666;
	text-decoration: none;
	font-size: 0.95rem;
}
.tab.active {
	color: #1a6291;
	font-weight: 600;
	border-bottom: 2px solid #1a6291;
	margin-bottom: -1px;
}
.noti-row {
	background: #fff;
	padding: 14px 16px;
	border-radius: 12px;
	margin-bottom: 10px;
	border: 1px solid #eee;
	display: flex;
	align-items: flex-start;
	gap: 12px;
	text-decoration: none;
	color: #212529;
	transition: background 0.15s ease;
	cursor: pointer;
}
.noti-row:hover {
	background: #f9fafb;
}
.noti-row a {
	cursor: pointer;
}
.noti-avatar {
	width: 44px;
	height: 44px;
	border-radius: 50%;
	object-fit: cover;
	flex-shrink: 0;
}
.noti-avatar.noti-avatar-initial {
	object-fit: initial;
	font-weight: 600;
	font-size: 1rem;
}
.noti-body {
	flex: 1;
	min-width: 0;
	font-size: 0.95rem;
	line-height: 1.45;
}
.noti-time {
	flex-shrink: 0;
	font-size: 0.8rem;
	color: #6c757d;
	white-space: nowrap;
	margin-left: 8px;
}
.noti-dot {
	color: #1a6291;
	font-size: 10px;
	align-self: center;
}
</style>

<div class="card border-0 shadow-sm rounded-4 mb-3">
	<div class="card-body py-3">
		<div class="fw-bold mb-1">Thông báo</div>
		<div class="tabs">
			<a href="<?= BASE_URL ?>/notifications?tab=all"
			   class="tab <?= ($tab ?? 'all') === 'all' ? 'active' : '' ?>">Tất cả</a>
			<a href="<?= BASE_URL ?>/notifications?tab=mention"
			   class="tab <?= ($tab ?? '') === 'mention' ? 'active' : '' ?>">Nhắc đến</a>
		</div>
	</div>
</div>

<div>
	<?php if (empty($notifications)): ?>
		<p class="text-secondary small px-1 mb-0">Không có thông báo.</p>
	<?php endif; ?>

	<?php foreach ($notifications as $n): ?>
		<?php
		$notiLink = (string) ($n['link'] ?? '#');
		$notiId = (int) ($n['id'] ?? 0);
		?>
		<div class="noti-row"
		     tabindex="0"
		     role="link"
		     data-href="<?= htmlspecialchars($notiLink, ENT_QUOTES, 'UTF-8') ?>"
		     <?php if ($notiId > 0): ?>data-notification-id="<?= $notiId ?>"<?php endif; ?>>

			<?php if (!empty($n['actor_avatar'])): ?>
				<img src="<?= htmlspecialchars((string) $n['actor_avatar'], ENT_QUOTES, 'UTF-8') ?>"
				     alt="" class="noti-avatar" width="44" height="44"
				     loading="lazy"
				     onerror="this.style.display='none'; var f=this.nextElementSibling; if(f&&f.classList.contains('noti-avatar-fallback')){f.classList.remove('d-none');}">
				<div class="noti-avatar noti-avatar-initial noti-avatar-fallback d-none d-inline-flex align-items-center justify-content-center"
				     style="background: <?= htmlspecialchars((string) ($n['actor_color_bg'] ?? '#E6F4FF'), ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars((string) ($n['actor_color_fg'] ?? '#005B96'), ENT_QUOTES, 'UTF-8') ?>;">
					<?= htmlspecialchars((string) ($n['actor_initial'] ?? '?'), ENT_QUOTES, 'UTF-8') ?>
				</div>
			<?php else: ?>
				<div class="noti-avatar noti-avatar-initial d-inline-flex align-items-center justify-content-center"
				     style="background: <?= htmlspecialchars((string) ($n['actor_color_bg'] ?? '#E6F4FF'), ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars((string) ($n['actor_color_fg'] ?? '#005B96'), ENT_QUOTES, 'UTF-8') ?>;">
					<?= htmlspecialchars((string) ($n['actor_initial'] ?? '?'), ENT_QUOTES, 'UTF-8') ?>
				</div>
			<?php endif; ?>

			<div class="noti-body">
				<?= $n['message_html'] ?? '' ?>
			</div>

			<div class="noti-time"><?= htmlspecialchars($n['time_label'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>

			<?php if ((int) ($n['is_read'] ?? 0) === 0): ?>
				<span class="noti-dot" aria-label="Chưa đọc">&#9679;</span>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
