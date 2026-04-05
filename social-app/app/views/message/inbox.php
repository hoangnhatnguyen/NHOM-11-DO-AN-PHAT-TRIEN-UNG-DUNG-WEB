<?php
$me = $currentUser ?? [];
$meId = (int) ($me['id'] ?? 0);
$meName = (string) ($me['username'] ?? 'Bạn');
$meInitial = Avatar::initials($meName);
$meColor = Avatar::colors($meName);
$meAvatarUrl = (string) ($me['avatar_url'] ?? '');
?>

<section
	class="chat-shell"
	id="chatApp"
	data-base-url="<?= htmlspecialchars(BASE_URL) ?>"
	data-csrf-token="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>"
	data-current-user-id="<?= $meId ?>"
	data-current-user-name="<?= htmlspecialchars($meName) ?>"
	data-current-user-initial="<?= htmlspecialchars($meInitial) ?>"
>
	<div class="chat-frame">
		<aside class="chat-rail">
			<a class="chat-rail-btn" href="<?= BASE_URL ?>/" data-label="Trang chủ" title="Trang chủ" aria-label="Trang chủ"><i class="bi bi-house"></i></a>
			<a class="chat-rail-btn active" href="<?= BASE_URL ?>/messages" data-label="Tin nhắn" title="Tin nhắn" aria-label="Tin nhắn"><i class="bi bi-envelope"></i></a>
			<a class="chat-rail-btn" href="<?= BASE_URL ?>/notifications" data-label="Thông báo" title="Thông báo" aria-label="Thông báo"><i class="bi bi-bell"></i></a>
			<a class="chat-rail-btn" href="<?= BASE_URL ?>/search" data-label="Tìm kiếm" title="Tìm kiếm" aria-label="Tìm kiếm"><i class="bi bi-search"></i></a>
			<a class="chat-rail-btn" href="<?= BASE_URL ?>/saved" data-label="Đã lưu" title="Đã lưu" aria-label="Đã lưu"><i class="bi bi-bookmark"></i></a>
			<a class="chat-rail-btn" href="<?= htmlspecialchars(profile_url($meName), ENT_QUOTES, 'UTF-8') ?>" data-label="Trang cá nhân" title="Trang cá nhân" aria-label="Trang cá nhân"><i class="bi bi-person"></i></a>
			<a class="chat-rail-btn" href="<?= BASE_URL ?>/settings" data-label="Cài đặt" title="Cài đặt" aria-label="Cài đặt"><i class="bi bi-gear"></i></a>
			
			<div class="chat-rail-avatar-container">
				<div style="position:relative; width:40px; height:40px;">
					<?php if (!empty($meAvatarUrl)): ?>
						<!-- Mode 1: Image Avatar -->
						<img id="chatRailAvatarImg"
							 src="<?= htmlspecialchars(media_public_src($meAvatarUrl)) ?>"
							 class="rounded-circle"
							 style="width:100%; height:100%; object-fit:cover; position:absolute; top:0; left:0;">
						
						<!-- Invisible overlay button để catch clicks -->
						<button id="chatAvatarBtn"
								class="chat-rail-avatar"
								style="position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer;"
								type="button"
								title="Thông tin tài khoản"
								aria-label="Thông tin tài khoản"></button>
					<?php else: ?>
						<!-- Mode 2: Text Avatar (khi ko có url) -->
						<button class="chat-rail-avatar" 
								id="chatAvatarBtn"
								style="--avatar-bg: <?= htmlspecialchars($meColor['bg']) ?>; --avatar-fg: <?= htmlspecialchars($meColor['fg']) ?>" 
								type="button"
								title="Thông tin tài khoản" 
								aria-label="Thông tin tài khoản"><?= htmlspecialchars($meInitial) ?></button>
					<?php endif; ?>
				</div>
				
				<div class="chat-user-menu" id="chatUserMenu">
					<div class="chat-user-menu-header">
						<?php if (!empty($meAvatarUrl)): ?>
							<!-- Mode 1: Image Avatar -->
							<img id="chatMenuAvatarImg"
								 src="<?= htmlspecialchars(media_public_src($meAvatarUrl)) ?>"
								 class="rounded-circle flex-shrink-0"
								 width="44" height="44"
								 style="object-fit:cover"
								 onerror="document.getElementById('chatMenuAvatarImg').style.display='none'; document.getElementById('chatMenuTextAvatar').style.display='flex';">
							
							<!-- Mode 2: Text Avatar (fallback khi 404) -->
							<div id="chatMenuTextAvatar"
								 class="d-none align-items-center justify-content-center rounded-circle"
								 style="width:44px; height:44px; background:<?= htmlspecialchars($meColor['bg']) ?>; color:<?= htmlspecialchars($meColor['fg']) ?>; font-weight:600; display:none;">
								<?= htmlspecialchars($meInitial) ?>
							</div>
						<?php else: ?>
							<!-- Mode 2: Text Avatar (khi ko có url) -->
							<div id="chatMenuTextAvatar"
								 class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
								 style="width:44px; height:44px; background:<?= htmlspecialchars($meColor['bg']) ?>; color:<?= htmlspecialchars($meColor['fg']) ?>; font-weight:600;">
								<?= htmlspecialchars($meInitial) ?>
							</div>
						<?php endif; ?>
						<div>
							<div class="chat-user-menu-name"><?= htmlspecialchars($meName) ?></div>
							<div class="chat-user-menu-email"><?= htmlspecialchars($me['email'] ?? '') ?></div>
						</div>
					</div>
					<div class="chat-user-menu-divider"></div>
					<button type="button" class="chat-user-menu-item" id="logoutBtn" data-csrf="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>" data-logout-url="<?= BASE_URL ?>/logout">
						<i class="bi bi-box-arrow-right"></i>
						<span>Đăng xuất</span>
					</button>
				</div>
			</div>
		</aside>

		<section class="chat-conversations-panel">
			<div class="chat-card-title">Tin nhắn</div>
			<div class="chat-section-title">Cuộc trò chuyện</div>
			<div class="chat-search-wrap">
				<i class="bi bi-search"></i>
				<input type="text" id="chatSearchInput" class="chat-search-input" placeholder="Tìm kiếm">
			</div>
			<div id="chatSearchSuggestions" class="chat-search-suggestions d-none"></div>
			<div id="chatConversationList" class="chat-conversation-list"></div>
		</section>

		<section class="chat-thread-panel" id="chatThreadPanel">
			<div class="chat-thread-header" id="chatThreadHeader">
				<div class="chat-user-info">
					<div class="chat-user-avatar" id="chatHeaderAvatar">?</div>
					<div>
						<div class="chat-user-name" id="chatHeaderName">Chọn cuộc trò chuyện</div>
						<div class="chat-user-handle" id="chatHeaderStatus">Ngoại tuyến</div>
					</div>
				</div>
				<button type="button" class="chat-icon-btn" id="chatOpenDetailBtn" aria-label="Chi tiết">
					<i class="bi bi-info-circle-fill"></i>
				</button>
			</div>

			<div class="chat-message-scroll" id="chatMessageList"></div>

			<div class="chat-empty-overlay" id="chatEmptyState">
				<i class="bi bi-chat-dots"></i>
				<h3>Tin nhắn của bạn</h3>
				<p>Gửi tin nhắn cho bạn bè của bạn</p>
				<button type="button" id="chatEmptyStateBtn" class="chat-empty-btn">Gửi tin nhắn</button>
			</div>

			<form class="chat-composer" id="chatComposerForm">
				<input id="chatTextInput" type="text" class="chat-text-input" placeholder="Gửi tin nhắn" autocomplete="off">
				<input id="chatFileInput" type="file" class="d-none" multiple>
				<button type="button" class="chat-send-btn" id="chatFileBtn" aria-label="Đính kèm tệp">
					<i class="bi bi-paperclip"></i>
				</button>
				<button type="submit" class="chat-send-btn" id="chatSendBtn" aria-label="Gửi tin nhắn">
					<i class="bi bi-send-fill"></i>
				</button>
			</form>
		</section>

		<aside class="chat-detail-panel" id="chatDetailPanel">
			<div class="chat-detail-title">Chi tiết</div>
			<div class="chat-detail-user" id="chatDetailUser">
				<div id="chatDetailAvatar"
					 class="chat-user-avatar d-flex align-items-center justify-content-center rounded-circle"
					 style="width:48px; height:48px; background:#8adfd7; color:#0a3d3a; font-weight:600; font-size:1.2rem;">?</div>
				<div>
					<div class="chat-user-name" id="chatDetailName">Chưa chọn</div>
					<div class="chat-user-handle" id="chatDetailHandle"></div>
					<div class="chat-user-handle" id="chatDetailStatus">Ngoại tuyến</div>
				</div>
			</div>

			<div class="chat-detail-actions">
				<button type="button" id="chatBlockBtn" class="chat-detail-action">Chặn người dùng</button>
				<button type="button" id="chatDeleteBtn" class="chat-detail-action danger">Xóa tin nhắn</button>
			</div>

			<div class="chat-attachment-head">
				<div class="fw-semibold">Tệp đính kèm đã gửi</div>
				<span id="chatAttachmentCount" class="chat-attachment-count">0</span>
			</div>
			<div id="chatAttachmentList" class="chat-attachment-list"></div>
		</aside>
	</div>
</section>

<div class="modal fade" id="chatNewConversationModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Gửi tin nhắn</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<input type="text" id="chatNewConvSearch" class="form-control mb-3" placeholder="Tìm kiếm người dùng...">
				<div id="chatNewConvSuggestions" style="max-height: 300px; overflow-y: auto;"></div>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="chatDeleteModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content chat-confirm-modal">
			<div class="modal-body text-center p-4">
				<h5 class="mb-4">Xóa cuộc trò chuyện khỏi hộp thư đến?</h5>
				<div class="d-flex justify-content-center gap-3">
					<button type="button" id="chatConfirmDeleteBtn" class="btn chat-primary-btn px-4">Xác nhận</button>
					<button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Hủy</button>
				</div>
			</div>
		</div>
	</div>
</div>

<div id="chatImageViewer" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; align-items: center; justify-content: center;">
	<button type="button" id="chatImageViewerClose" style="position: absolute; top: 16px; right: 16px; background: white; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10000;">✕</button>
	<img id="chatImageViewerImg" src="" style="max-width: 100%; max-height: 100%; object-fit: contain;">
</div>

