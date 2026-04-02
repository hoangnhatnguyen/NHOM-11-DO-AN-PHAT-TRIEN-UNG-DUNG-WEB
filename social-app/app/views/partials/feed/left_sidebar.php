<?php $activeMenu = $activeMenu ?? 'home'; ?>
<?php
// Form đăng xuất + modal Đăng bài (post/create.php) cần cùng token với session; nhiều trang (vd. settings) không truyền csrfToken từ controller.
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
?>
<?php
$profileName = (string) ($currentUser['username'] ?? 'Người dùng');
$profileInitial = Avatar::initials($profileName);
$profileColor = Avatar::colors($profileName);
$userId = (int) ($currentUser['id'] ?? 0);

$notifUnread = 0;
if ($userId > 0) {
	require_once dirname(__DIR__, 3) . '/helpers/notification_helper.php';
	try {
		$notifUnread = notifications_unread_count(notification_db(), $userId);
	} catch (Throwable $e) {
		$notifUnread = 0;
	}
}
$notifBadgeLabel = $notifUnread > 99 ? '99+' : (string) max(0, $notifUnread);
?>
<aside class="card border-0 shadow-sm rounded-4 left-sidebar" data-current-user-id="<?= $userId ?>">
	<div class="card-body p-3 p-lg-4 d-flex flex-column left-sidebar-body">
		<ul class="nav flex-column gap-2 sidebar-menu">
			<li>
				<a class="nav-link <?= $activeMenu === 'home' ? 'active' : '' ?>" href="<?= BASE_URL ?>/">
					<i class="bi bi-house-door"></i><span class="menu-label">Trang chủ</span>
				</a>
			</li>
			<li><a class="nav-link <?= $activeMenu === 'messages' ? 'active' : '' ?>" href="<?= BASE_URL ?>/messages"><i class="bi bi-envelope"></i><span class="menu-label">Tin nhắn</span></a></li>
			<li>
				<a class="nav-link sidebar-nav-with-badge <?= $activeMenu === 'notifications' ? 'active' : '' ?>"
				   href="<?= BASE_URL ?>/notifications">
					<span class="d-inline-flex align-items-center gap-2 text-truncate min-w-0">
						<i class="bi bi-bell"></i><span class="menu-label">Thông báo</span>
					</span>
					<span id="sidebar-notif-unread-badge"
						class="sidebar-notif-badge badge rounded-pill flex-shrink-0<?= $notifUnread > 0 ? '' : ' d-none' ?>"
						aria-live="polite"><?= $notifUnread > 0 ? htmlspecialchars($notifBadgeLabel, ENT_QUOTES, 'UTF-8') : '' ?></span>
				</a>
			</li>
			<li><a class="nav-link <?= $activeMenu === 'search' ? 'active' : '' ?>" href="<?= BASE_URL ?>/search"><i class="bi bi-search"></i><span class="menu-label">Tìm kiếm</span></a></li>
			<li><a class="nav-link <?= $activeMenu === 'saved' ? 'active' : '' ?>" href="<?= BASE_URL ?>/saved"><i class="bi bi-bookmark"></i><span class="menu-label">Đã lưu</span></a></li>
			<li>
                <a class="nav-link <?= $activeMenu === 'profile' ? 'active' : '' ?>"
                   href="<?= htmlspecialchars(profile_url((string) ($currentUser['username'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi bi-person"></i>
                    <span class="menu-label">Trang cá nhân</span>
                </a>
            </li>
            <li>
                <a class="nav-link <?= $activeMenu === 'settings' ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>/settings">
                    <i class="bi bi-gear"></i>
                    <span class="menu-label">Cài đặt</span>
                </a>
            </li>			<li>
				<a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#createPostModal">
					<i class="bi bi-plus-circle-fill"></i>
					<span class="menu-label">Tạo</span>
				</a>
			</li>
		</ul>

		<div class="sidebar-divider"></div>

	<div class="d-flex align-items-center gap-2 sidebar-profile">
    <a href="<?= htmlspecialchars(profile_url((string) ($currentUser['username'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" 
       id="sidebarAvatarContainer"
       data-avatar-container
       data-avatar-initial="<?= htmlspecialchars(strtoupper(substr($currentUser['username'], 0, 1))) ?>"
       class="d-flex align-items-center justify-content-center flex-shrink-0"
       style="width:50px; height:50px;">
        
        <?php if (!empty($currentUser['avatar_url'])): ?>
            <!-- Mode 1: Image Avatar (khi có url) -->
            <img id="sidebarAvatarImg"
                 src="<?= htmlspecialchars(media_public_src($currentUser['avatar_url'])) ?>"
                 class="rounded-circle"
                 width="50" height="50"
                 style="object-fit:cover"
                 onerror="document.getElementById('sidebarAvatarImg').style.display='none'; document.getElementById('sidebarTextAvatar').style.display='flex';">
            
            <!-- Mode 2: Text Avatar (fallback khi 404) -->
            <div id="sidebarTextAvatar"
                 class="d-none align-items-center justify-content-center rounded-circle"
                 style="width:50px; height:50px; background:#8adfd7; color:#0a3d3a; font-weight:600; display:none;">
                <?= htmlspecialchars(strtoupper(substr($currentUser['username'], 0, 1))) ?>
            </div>
        <?php else: ?>
            <!-- Mode 2: Text Avatar (khi ko có url) -->
            <div id="sidebarTextAvatar"
                 class="d-flex align-items-center justify-content-center rounded-circle"
                 style="width:50px; height:50px; background:#8adfd7; color:#0a3d3a; font-weight:600;">
                <?= htmlspecialchars(strtoupper(substr($currentUser['username'], 0, 1))) ?>
            </div>
        <?php endif; ?>
    </a>

    <div class="menu-label">
        <a href="<?= htmlspecialchars(profile_url((string) ($currentUser['username'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" class="fw-semibold small text-decoration-none text-body d-inline-block"><?= htmlspecialchars($profileName) ?></a>
        <div class="text-secondary small"><?= htmlspecialchars($currentUser['email'] ?? '') ?></div>
    </div>
</div>

		<form method="post" action="<?= BASE_URL ?>/logout" class="mt-2 menu-label">
			<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
			<button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Đăng xuất</button>
		</form>
	</div>
</aside>

<?php
$modalId = "createPostModal";
$modalTitle = "Đăng bài";

ob_start();
include VIEW_PATH . 'post/create.php';
$modalBody = ob_get_clean();

include VIEW_PATH . 'partials/modal.php';
?>
