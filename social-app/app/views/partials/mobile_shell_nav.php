<?php
$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
$mobileActiveMenu = $activeMenu ?? 'home';

if (strpos($requestPath, '/messages') === 0) {
	$mobileActiveMenu = 'messages';
} elseif (strpos($requestPath, '/notifications') === 0) {
	$mobileActiveMenu = 'notifications';
} elseif (strpos($requestPath, '/search') === 0) {
	$mobileActiveMenu = 'search';
} elseif (strpos($requestPath, '/saved') === 0) {
	$mobileActiveMenu = 'saved';
} elseif (strpos($requestPath, '/settings') === 0) {
	$mobileActiveMenu = 'settings';
} elseif (strpos($requestPath, '/u/') === 0 || strpos($requestPath, '/profile') === 0) {
	$mobileActiveMenu = 'profile';
} elseif (strpos($requestPath, '/admin') === 0) {
	$mobileActiveMenu = 'admin';
}
?>
<?php $mobileBackOnly = $mobileActiveMenu === 'notifications'; ?>
<?php $mobileHideShellNav = $mobileActiveMenu === 'messages'; ?>

<?php if ($mobileHideShellNav): ?>
	<?php /* Messages page uses in-screen mobile headers (Figma style). */ ?>
<?php elseif ($mobileBackOnly): ?>
	<header class="mobile-backbar mobile-backbar--<?= htmlspecialchars($mobileActiveMenu, ENT_QUOTES, 'UTF-8') ?>" aria-label="Mobile backbar">
		<a class="mobile-backbar-btn" href="<?= BASE_URL ?>/" aria-label="Quay lại">
			<i class="bi bi-arrow-left"></i>
		</a>
		<span class="mobile-backbar-title"><?= $mobileActiveMenu === 'messages' ? 'Tin nhắn' : 'Thông báo' ?></span>
	</header>
<?php else: ?>
	<header class="mobile-topbar" aria-label="Mobile topbar">
		<a class="mobile-topbar-brand" href="<?= BASE_URL ?>/">
			<img src="<?= BASE_URL ?>/public/images/mobile-logo.png" alt="<?= htmlspecialchars(APP_NAME) ?>" class="mobile-topbar-logo">
		</a>
		<div class="mobile-topbar-actions">
			<a class="mobile-topbar-icon <?= $mobileActiveMenu === 'notifications' ? 'active' : '' ?>" href="<?= BASE_URL ?>/notifications" aria-label="Thông báo">
				<i class="bi bi-bell"></i>
			</a>
			<a class="mobile-topbar-icon <?= $mobileActiveMenu === 'messages' ? 'active' : '' ?>" href="<?= BASE_URL ?>/messages" aria-label="Tin nhắn">
				<i class="bi bi-envelope"></i>
			</a>
		</div>
	</header>

	<nav class="mobile-bottom-nav" aria-label="Mobile bottom navigation">
		<a class="mobile-bottom-nav-item <?= $mobileActiveMenu === 'home' ? 'active' : '' ?>" href="<?= BASE_URL ?>/" aria-label="Trang chủ">
			<i class="bi bi-house-door"></i>
		</a>
		<a class="mobile-bottom-nav-item <?= $mobileActiveMenu === 'search' ? 'active' : '' ?>" href="<?= BASE_URL ?>/search" aria-label="Tìm kiếm">
			<i class="bi bi-search"></i>
		</a>
		<a class="mobile-bottom-nav-item <?= $mobileActiveMenu === 'saved' ? 'active' : '' ?>" href="<?= BASE_URL ?>/saved" aria-label="Đã lưu">
			<i class="bi bi-bookmark"></i>
		</a>
		<a class="mobile-bottom-nav-item <?= $mobileActiveMenu === 'settings' ? 'active' : '' ?>" href="<?= BASE_URL ?>/settings" aria-label="Cài đặt">
			<i class="bi bi-gear"></i>
		</a>
		<a class="mobile-bottom-nav-item mobile-bottom-nav-avatar <?= $mobileActiveMenu === 'profile' ? 'active' : '' ?>" href="<?= htmlspecialchars(profile_url((string) ($currentUser['username'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" aria-label="Trang cá nhân">
			<?php if (!empty($currentUser['avatar_url'])): ?>
				<img src="<?= htmlspecialchars(media_public_src($currentUser['avatar_url']), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($currentUser['username'] ?? 'avatar', ENT_QUOTES, 'UTF-8') ?>">
			<?php else: ?>
				<span><?= htmlspecialchars(strtoupper(substr((string) ($currentUser['username'] ?? 'U'), 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
			<?php endif; ?>
		</a>
	</nav>
<?php endif; ?>
