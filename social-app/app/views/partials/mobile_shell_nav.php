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

if (!isset($csrfToken) || $csrfToken === '') {
	if (session_status() === PHP_SESSION_ACTIVE) {
		if (empty($_SESSION['_csrf_token'])) {
			$_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
		}
		$csrfToken = (string) $_SESSION['_csrf_token'];
	} else {
		$csrfToken = '';
	}
}

$mobileNotifUnread = 0;
$mobileUserId = (int) ($currentUser['id'] ?? 0);
if ($mobileUserId > 0) {
	require_once dirname(__DIR__, 2) . '/helpers/notification_helper.php';
	try {
		$mobileNotifUnread = notifications_unread_count(notification_db(), $mobileUserId);
	} catch (Throwable $e) {
		$mobileNotifUnread = 0;
	}
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
				<span id="mobile-notif-dot" class="mobile-notif-dot<?= $mobileNotifUnread > 0 ? '' : ' d-none' ?>" aria-hidden="true"></span>
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
		<button
			type="button"
			class="mobile-bottom-nav-item mobile-bottom-nav-avatar <?= $mobileActiveMenu === 'profile' ? 'active' : '' ?>"
			id="mobileProfileToggle"
			aria-label="Mở menu tài khoản"
			aria-controls="mobileProfileMenu"
			aria-expanded="false"
		>
			<?php $mobileProfileInitial = htmlspecialchars(strtoupper(substr((string) ($currentUser['username'] ?? 'U'), 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
			<?php if (!empty($currentUser['avatar_url'])): ?>
				<img
					id="mobileProfileAvatarImg"
					src="<?= htmlspecialchars(media_public_src($currentUser['avatar_url']), ENT_QUOTES, 'UTF-8') ?>"
					alt="<?= htmlspecialchars($currentUser['username'] ?? 'avatar', ENT_QUOTES, 'UTF-8') ?>"
				>
				<span id="mobileProfileAvatarFallback" style="display:none;"><?= $mobileProfileInitial ?></span>
			<?php else: ?>
				<span><?= $mobileProfileInitial ?></span>
			<?php endif; ?>
		</button>
	</nav>

	<button type="button" class="mobile-profile-backdrop" id="mobileProfileBackdrop" aria-label="Đóng menu tài khoản"></button>
	<div class="mobile-profile-menu" id="mobileProfileMenu" role="menu" aria-hidden="true">
		<a
			class="mobile-profile-menu-item"
			href="<?= htmlspecialchars(profile_url((string) ($currentUser['username'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
			role="menuitem"
		>
			<i class="bi bi-person"></i>
			<span>Trang cá nhân</span>
		</a>
		<form method="post" action="<?= BASE_URL ?>/logout" class="mobile-profile-menu-form">
			<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
			<button type="submit" class="mobile-profile-menu-item mobile-profile-menu-item--danger" role="menuitem">
				<i class="bi bi-box-arrow-right"></i>
				<span>Đăng xuất</span>
			</button>
		</form>
	</div>

	<script>
	(function () {
		const toggle = document.getElementById('mobileProfileToggle');
		const menu = document.getElementById('mobileProfileMenu');
		const backdrop = document.getElementById('mobileProfileBackdrop');
		const avatarImg = document.getElementById('mobileProfileAvatarImg');
		const avatarFallback = document.getElementById('mobileProfileAvatarFallback');
		if (!toggle || !menu || !backdrop) return;

		if (avatarImg && avatarFallback) {
			avatarImg.addEventListener('error', function () {
				avatarImg.style.display = 'none';
				avatarFallback.style.display = 'inline-flex';
			});
		}

		const closeMenu = () => {
			menu.classList.remove('show');
			backdrop.classList.remove('show');
			menu.setAttribute('aria-hidden', 'true');
			toggle.setAttribute('aria-expanded', 'false');
		};

		const openMenu = () => {
			menu.classList.add('show');
			backdrop.classList.add('show');
			menu.setAttribute('aria-hidden', 'false');
			toggle.setAttribute('aria-expanded', 'true');
		};

		toggle.addEventListener('click', function (event) {
			event.preventDefault();
			if (menu.classList.contains('show')) {
				closeMenu();
			} else {
				openMenu();
			}
		});

		backdrop.addEventListener('click', closeMenu);
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeMenu();
			}
		});
		window.addEventListener('resize', function () {
			if (!window.matchMedia('(max-width: 767.98px)').matches) {
				closeMenu();
			}
		});
	})();
	</script>
<?php endif; ?>
