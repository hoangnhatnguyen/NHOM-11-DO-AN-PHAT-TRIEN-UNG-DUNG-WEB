<aside class="card border-0 shadow-sm rounded-4 left-sidebar">
	<div class="card-body p-3 p-lg-4 d-flex flex-column left-sidebar-body">
		<ul class="nav flex-column gap-2 sidebar-menu">
			<li>
				<a class="nav-link active" href="<?= BASE_URL ?>/">
					<i class="bi bi-house-door"></i><span class="menu-label">Trang chủ</span>
				</a>
			</li>
			<li><a class="nav-link" href="#"><i class="bi bi-envelope"></i><span class="menu-label">Tin nhắn</span></a></li>
			<li><a class="nav-link" href="#"><i class="bi bi-bell"></i><span class="menu-label">Thông báo</span></a></li>
			<li><a class="nav-link" href="#"><i class="bi bi-search"></i><span class="menu-label">Tìm kiếm</span></a></li>
			<li>
				<a class="nav-link" href="<?= BASE_URL ?>/saved">
					<i class="bi bi-bookmark"></i><span class="menu-label">Lưu</span>
				</a>
			</li>
			<li><a class="nav-link" href="#"><i class="bi bi-person"></i><span class="menu-label">Trang</span></a></li>
			<li><a class="nav-link" href="#"><i class="bi bi-gear"></i><span class="menu-label">Cài đặt</span></a></li>
			<li>
				<a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#createPostModal">
					<i class="bi bi-plus-circle-fill"></i>
					<span class="menu-label">Tạo</span>
				</a>
			</li>
		</ul>

		<div class="sidebar-divider"></div>

		<div class="d-flex align-items-center gap-2 sidebar-profile">
			<div class="avatar-lg">T</div>
			<div class="menu-label">
				<div class="fw-semibold small"><?= htmlspecialchars($currentUser['username'] ?? 'Người dùng') ?></div>
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
