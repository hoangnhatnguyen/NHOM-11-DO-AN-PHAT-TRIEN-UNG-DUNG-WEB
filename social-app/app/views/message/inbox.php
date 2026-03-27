<?php
$me = $currentUser ?? [];
$meId = (int) ($me['id'] ?? 0);
$meName = (string) ($me['username'] ?? 'Bạn');
$meInitial = Avatar::initials($meName);
$meColor = Avatar::colors($meName);
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
			<button class="chat-rail-btn" type="button" data-label="Thông báo" title="Thông báo" aria-label="Thông báo"><i class="bi bi-bell"></i></button>
			<button class="chat-rail-btn" type="button" data-label="Tìm kiếm" title="Tìm kiếm" aria-label="Tìm kiếm"><i class="bi bi-search"></i></button>
			<button class="chat-rail-btn" type="button" data-label="Đã lưu" title="Đã lưu" aria-label="Đã lưu"><i class="bi bi-bookmark"></i></button>
			<button class="chat-rail-btn" type="button" data-label="Tài khoản" title="Tài khoản" aria-label="Tài khoản"><i class="bi bi-person"></i></button>
			<button class="chat-rail-btn" type="button" data-label="Cài đặt" title="Cài đặt" aria-label="Cài đặt"><i class="bi bi-gear"></i></button>
			<button class="chat-rail-btn" id="chatNewConversationBtn" type="button" data-label="Tạo chat" title="Tạo chat" aria-label="Tạo cuộc trò chuyện"><i class="bi bi-plus-lg"></i></button>
			<div class="chat-rail-avatar" style="--avatar-bg: <?= htmlspecialchars($meColor['bg']) ?>; --avatar-fg: <?= htmlspecialchars($meColor['fg']) ?>;"><?= htmlspecialchars($meInitial) ?></div>
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
				<div class="chat-user-avatar">?</div>
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

