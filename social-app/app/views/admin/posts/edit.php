<?php
$postRow = $post ?? [];
$pid = (int) ($postRow['id'] ?? 0);
$authorUser = is_array($authorUser ?? null) ? $authorUser : [];
$editUsername = (string) ($authorUser['username'] ?? ($authorUsername ?? 'Người dùng'));
$editAvatarRaw = (string) ($authorUser['avatar_url'] ?? '');
$editAvatarSrc = $editAvatarRaw !== '' && function_exists('media_public_src')
	? media_public_src($editAvatarRaw)
	: '';
$editAvatarColor = class_exists('Avatar') ? Avatar::colors($editUsername) : ['bg' => '#8adfd7', 'fg' => '#0a3d3a'];
$mediaList = is_array($media ?? null) ? $media : [];
$contentForEditor = (string) ($editContent ?? $postRow['content'] ?? '');
?>
<div class="mb-3">
	<a href="<?= BASE_URL ?>/admin/posts" class="btn btn-sm btn-light rounded-pill text-black fs-6 fw-bold px-3">
		<i class="bi bi-arrow-left me-1"></i> Danh sách bài viết
	</a>
</div>

<article class="card border-0 shadow-sm rounded-4" style="max-width: 720px;">
	<div class="card-body p-3 p-md-4">
		<div class="d-flex align-items-start justify-content-between gap-2 mb-3">
			<div>
				<h1 class="h5 fw-bold mb-1">Sửa bài viết #<?= $pid ?> <span class="badge bg-light text-secondary border">Admin</span></h1>
				<p class="text-secondary small mb-0">Tác giả: <strong><?= htmlspecialchars($editUsername, ENT_QUOTES, 'UTF-8') ?></strong></p>
			</div>
		</div>

		<form method="POST" action="<?= BASE_URL ?>/admin/posts/update/<?= $pid ?>" enctype="multipart/form-data">
			<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
			<div id="adminEditSaveMsg" class="alert py-2 mb-3 d-none" role="alert"></div>
			<?php if (($_GET['error'] ?? '') === 'empty'): ?>
				<div class="alert alert-warning py-2 mb-3" role="alert">Bài viết phải có nội dung hoặc ít nhất 1 media.</div>
			<?php endif; ?>
			<?php if (($_GET['saved'] ?? '') === '1'): ?>
				<div class="alert alert-success py-2 mb-3" role="alert">Đã lưu chỉnh sửa bài viết.</div>
			<?php endif; ?>

			<div class="d-flex border-bottom pb-3 mb-3">
				<?php if ($editAvatarSrc !== ''): ?>
					<img src="<?= htmlspecialchars($editAvatarSrc, ENT_QUOTES, 'UTF-8') ?>"
						 class="rounded-circle me-2"
						 width="40" height="40"
						 style="object-fit: cover; flex-shrink: 0;"
						 alt=""
						 loading="lazy"
						 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
					<div class="avatar-sm me-2" style="background: <?= htmlspecialchars($editAvatarColor['bg'], ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars($editAvatarColor['fg'], ENT_QUOTES, 'UTF-8') ?>; display:none;">
						<?= htmlspecialchars(Avatar::initials($editUsername), ENT_QUOTES, 'UTF-8') ?>
					</div>
				<?php else: ?>
					<div class="avatar-sm me-2" style="background: <?= htmlspecialchars($editAvatarColor['bg'], ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars($editAvatarColor['fg'], ENT_QUOTES, 'UTF-8') ?>;">
						<?= htmlspecialchars(Avatar::initials($editUsername), ENT_QUOTES, 'UTF-8') ?>
					</div>
				<?php endif; ?>

				<div class="flex-grow-1">
					<div class="fw-semibold"><?= htmlspecialchars($editUsername, ENT_QUOTES, 'UTF-8') ?></div>
					<textarea
						name="content"
						class="form-control bg-light rounded-4 composer-textarea mt-2"
						rows="3"
						placeholder="Nội dung (có thể dùng #hashtag)..."
						oninput="adminEditAutoResize(this)"
					><?= htmlspecialchars($contentForEditor, ENT_QUOTES, 'UTF-8') ?></textarea>
				</div>
			</div>

			<?php if (!empty($mediaList)): ?>
				<div class="mb-3">
					<div class="row g-2" id="adminExistingMediaGrid">
						<?php foreach ($mediaList as $m): ?>
							<?php
							$mediaId = (int) ($m['id'] ?? 0);
							$src = media_public_src((string) ($m['media_url'] ?? ''));
							if ($src === '') {
								continue;
							}
							$isVideo = (($m['media_type'] ?? '') === 'video');
							?>
							<div class="col-6 col-md-4" id="admin-media-item-<?= $mediaId ?>">
								<div class="preview-wrapper position-relative">
									<?php if ($isVideo): ?>
										<video src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" controls class="w-100 rounded-4" playsinline></video>
									<?php else: ?>
										<img src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" class="img-fluid rounded-4 w-100 post-media-tile" alt="">
									<?php endif; ?>

									<button type="button" class="bi bi-x text-white preview-remove" onclick="adminRemoveMedia(<?= $mediaId ?>)"></button>
									<input type="checkbox" hidden name="remove_media_ids[]" value="<?= $mediaId ?>" id="admin-remove-media-<?= $mediaId ?>">
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="mb-3 d-none" id="adminNewPreviewContainer">
				<div class="row g-2" id="adminNewPreviewGrid"></div>
			</div>

			<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
				<div class="dropdown">
					<button class="btn btn-light btn-sm rounded-pill dropdown-toggle d-flex align-items-center gap-1 mt-2"
							type="button"
							data-bs-toggle="dropdown">
						<i id="adminEditPrivacyIcon" class="bi bi-globe2"></i>
						<span id="adminEditPrivacyLabel">Công khai</span>
					</button>

					<ul class="dropdown-menu">
						<li>
							<a class="dropdown-item" href="#"
							   onclick="adminSetPrivacy('public', 'bi-globe2', 'Công khai'); return false;">
								Công khai
							</a>
						</li>
						<li>
							<a class="dropdown-item" href="#"
							   onclick="adminSetPrivacy('followers', 'bi-people', 'Người theo dõi'); return false;">
								Người theo dõi
							</a>
						</li>
						<li>
							<a class="dropdown-item" href="#"
							   onclick="adminSetPrivacy('private', 'bi-lock', 'Chỉ mình tôi'); return false;">
								Chỉ mình tôi
							</a>
						</li>
					</ul>
				</div>

				<input type="hidden" name="privacy" id="adminEditPrivacyInput" value="<?= htmlspecialchars((string) ($postRow['visible'] ?? 'public'), ENT_QUOTES, 'UTF-8') ?>">

				<div class="d-flex align-items-center gap-2">
					<label class="btn btn-light btn-sm rounded-pill mb-0">
						<i class="bi bi-image"></i>
						<input type="file" name="media[]" id="adminEditFileInput" hidden multiple accept="image/*,video/*">
					</label>
					<button type="submit" class="btn btn-primary rounded-pill px-4">Lưu thay đổi</button>
				</div>
			</div>
		</form>
	</div>
</article>

<script>
function adminEditAutoResize(el) {
	el.style.height = "auto";
	el.style.height = el.scrollHeight + "px";
}

function adminSetPrivacy(value, icon, label) {
	document.getElementById("adminEditPrivacyInput").value = value;
	document.getElementById("adminEditPrivacyIcon").className = "bi " + icon;
	document.getElementById("adminEditPrivacyLabel").innerText = label;
}

function adminInitPrivacyFromValue() {
	const value = document.getElementById("adminEditPrivacyInput").value;
	if (value === "followers") {
		adminSetPrivacy("followers", "bi-people", "Người theo dõi");
	} else if (value === "private") {
		adminSetPrivacy("private", "bi-lock", "Chỉ mình tôi");
	} else {
		adminSetPrivacy("public", "bi-globe2", "Công khai");
	}
}

function adminRemoveMedia(mediaId) {
	const mediaItem = document.getElementById("admin-media-item-" + mediaId);
	if (!mediaItem) return;
	const form = mediaItem.closest('form');
	if (form) {
		const hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = 'remove_media_ids[]';
		hidden.value = String(mediaId);
		form.appendChild(hidden);
	}
	mediaItem.remove();
}

function adminRemoveNewMediaAt(index) {
	const fileInput = document.getElementById("adminEditFileInput");
	if (!fileInput) return;
	if (!Array.isArray(window.__adminEditSelectedFiles)) {
		window.__adminEditSelectedFiles = [];
	}
	window.__adminEditSelectedFiles = window.__adminEditSelectedFiles.filter(function (_, i) {
		return i !== index;
	});
	adminSyncEditFileInputFromBuffer();
	adminRenderNewMediaPreview();
}

function adminSyncEditFileInputFromBuffer() {
	const fileInput = document.getElementById("adminEditFileInput");
	if (!fileInput) return;
	try {
		const dt = new DataTransfer();
		(window.__adminEditSelectedFiles || []).forEach(function (file) {
			dt.items.add(file);
		});
		fileInput.files = dt.files;
	} catch (e) {}
}

function adminRenderNewMediaPreview() {
	const fileInput = document.getElementById("adminEditFileInput");
	const previewContainer = document.getElementById("adminNewPreviewContainer");
	const previewGrid = document.getElementById("adminNewPreviewGrid");
	if (!previewContainer || !previewGrid) return;

	previewGrid.innerHTML = "";
	const files = Array.isArray(window.__adminEditSelectedFiles) ? window.__adminEditSelectedFiles : [];
	if (!files.length) {
		previewContainer.classList.add("d-none");
		return;
	}

	files.forEach(function (file, index) {
		const col = document.createElement("div");
		col.className = "col-6 col-md-4";

		const wrapper = document.createElement("div");
		wrapper.className = "preview-wrapper position-relative";

		if (file.type.startsWith("video/")) {
			const video = document.createElement("video");
			video.className = "w-100 rounded-4";
			video.controls = true;
			video.playsInline = true;
			video.src = URL.createObjectURL(file);
			wrapper.appendChild(video);
		} else {
			const img = document.createElement("img");
			img.className = "img-fluid rounded-4 w-100 post-media-tile";
			img.alt = "";
			img.src = URL.createObjectURL(file);
			wrapper.appendChild(img);
		}

		const removeBtn = document.createElement("button");
		removeBtn.type = "button";
		removeBtn.className = "bi bi-x text-white preview-remove";
		removeBtn.setAttribute("aria-label", "Xóa preview");
		removeBtn.addEventListener("click", function () {
			adminRemoveNewMediaAt(index);
		});
		wrapper.appendChild(removeBtn);

		col.appendChild(wrapper);
		previewGrid.appendChild(col);
	});

	previewContainer.classList.remove("d-none");
}

document.addEventListener("DOMContentLoaded", function () {
	adminInitPrivacyFromValue();
	const textarea = document.querySelector('form[action*="/admin/posts/update/"] textarea[name="content"]');
	if (textarea) adminEditAutoResize(textarea);

	const editForm = document.querySelector('form[action*="/admin/posts/update/"]');
	const saveMsg = document.getElementById('adminEditSaveMsg');
	if (editForm) {
		editForm.addEventListener('submit', async function (e) {
			e.preventDefault();
			const submitBtn = editForm.querySelector('button[type="submit"]');
			const oldText = submitBtn ? submitBtn.textContent : '';
			if (submitBtn) {
				submitBtn.disabled = true;
				submitBtn.textContent = 'Đang lưu...';
			}

			if (saveMsg) {
				saveMsg.className = 'alert py-2 mb-3 d-none';
				saveMsg.textContent = '';
			}

			try {
				const res = await fetch(editForm.action, {
					method: 'POST',
					body: new FormData(editForm),
					credentials: 'same-origin',
					headers: {
						'X-Requested-With': 'XMLHttpRequest'
					}
				});

				const text = await res.text();
				let data = null;
				try { data = JSON.parse(text); } catch (_) { data = null; }

				if (!data || !data.ok) {
					const msg = data && data.msg === 'empty'
						? 'Bài viết phải có nội dung hoặc ít nhất 1 media.'
						: 'Không thể lưu chỉnh sửa. Vui lòng thử lại.';
					if (saveMsg) {
						saveMsg.className = 'alert alert-warning py-2 mb-3';
						saveMsg.textContent = msg;
					}
					return;
				}

				if (saveMsg) {
					saveMsg.className = 'alert alert-success py-2 mb-3';
					saveMsg.textContent = 'Đã lưu chỉnh sửa bài viết.';
				}
				if (typeof window.history.replaceState === 'function') {
					const u = new URL(window.location.href);
					u.searchParams.set('saved', '1');
					u.searchParams.delete('error');
					window.history.replaceState({}, '', u.pathname + (u.search ? u.search : ''));
				}
			} catch (err) {
				if (saveMsg) {
					saveMsg.className = 'alert alert-danger py-2 mb-3';
					saveMsg.textContent = 'Lỗi kết nối khi lưu bài viết.';
				}
			} finally {
				if (submitBtn) {
					submitBtn.disabled = false;
					submitBtn.textContent = oldText;
				}
			}
		});
	}

	const fileInput = document.getElementById("adminEditFileInput");
	if (fileInput) {
		window.__adminEditSelectedFiles = [];
		fileInput.addEventListener("change", function () {
			const incoming = Array.from(fileInput.files || []);
			if (!incoming.length) return;
			if (!Array.isArray(window.__adminEditSelectedFiles)) {
				window.__adminEditSelectedFiles = [];
			}
			incoming.forEach(function (f) { window.__adminEditSelectedFiles.push(f); });
			adminSyncEditFileInputFromBuffer();
			adminRenderNewMediaPreview();
		});
	}
});
</script>
