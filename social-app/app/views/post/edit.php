<div class="mb-3">
	<a href="<?= BASE_URL ?>/post/<?= (int) ($post['id'] ?? 0) ?>" class="btn btn-sm btn-light rounded-pill text-black mt-3 fs-5 fw-bold px-3">
		<i class="bi bi-arrow-left me-1"></i> Chỉnh sửa
	</a>
</div>

<article class="card border-0 shadow-sm rounded-4">
	<div class="card-body p-3 p-md-4">
		<form method="POST" action="<?= BASE_URL ?>/post/update/<?= (int) ($post['id'] ?? 0) ?>" enctype="multipart/form-data">
			<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">

			<div class="d-flex border-bottom pb-3 mb-3">
				<div class="avatar-sm me-2"><?= strtoupper(substr((string) ($currentUser['username'] ?? 'U'), 0, 1)) ?></div>

				<div class="flex-grow-1">
					<div class="fw-semibold"><?= htmlspecialchars((string) ($currentUser['username'] ?? 'Người dùng')) ?></div>
					<textarea
						name="content"
						class="form-control bg-light rounded-4 composer-textarea mt-2"
						rows="3"
						placeholder="Chỉnh sửa nội dung..."
						oninput="autoResize(this)"
					><?= htmlspecialchars((string) ($post['content'] ?? '')) ?></textarea>
				</div>
			</div>

			<?php if (!empty($media ?? [])): ?>
				<div class="mb-3">
					<div class="row g-2" id="existingMediaGrid">
						<?php foreach ($media as $m): ?>
							<?php
							$mediaId = (int) ($m['id'] ?? 0);
							$src = media_public_src((string) ($m['media_url'] ?? ''));
							if ($src === '') {
								continue;
							}
							$isVideo = (($m['media_type'] ?? '') === 'video');
							?>
							<div class="col-12 col-md-6" id="media-item-<?= $mediaId ?>">
								<div class="preview-wrapper position-relative">
									<?php if ($isVideo): ?>
										<video src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" controls class="w-100 rounded-4" playsinline></video>
									<?php else: ?>
										<img src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" class="img-fluid rounded-4 w-100" alt="">
									<?php endif; ?>

									<button type="button" class="bi bi-x text-white preview-remove" onclick="removeMedia(<?= $mediaId ?>)"></button>
									<input type="checkbox" hidden name="remove_media_ids[]" value="<?= $mediaId ?>" id="remove-media-<?= $mediaId ?>">
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="mb-3 d-none" id="newPreviewContainer">
				<div class="row g-2" id="newPreviewGrid"></div>
			</div>

			<div class="d-flex justify-content-between align-items-center">
        		<div class="dropdown">
            		<button class="btn btn-light btn-sm rounded-pill dropdown-toggle d-flex align-items-center gap-1 mt-2"
                    		type="button"
                    		data-bs-toggle="dropdown">

                	<i id="editprivacyIcon" class="bi bi-globe2"></i>
                	<span id="editprivacyLabel">Công khai</span>
            	</button>

				<ul class="dropdown-menu">
					<li>
						<a class="dropdown-item" href="#"
						onclick="setPrivacy('public', 'bi-globe2', 'Công khai')">
							Công khai
						</a>
					</li>
					<li>
						<a class="dropdown-item" href="#"
						onclick="setPrivacy('followers', 'bi-people', 'Người theo dõi')">
							Người theo dõi
						</a>
					</li>
					<li>
						<a class="dropdown-item" href="#"
						onclick="setPrivacy('private', 'bi-lock', 'Chỉ mình tôi')">
							Chỉ mình tôi
						</a>
					</li>
				</ul>
			</div>

				<input type="hidden" name="privacy" id="editprivacyInput" value="<?= htmlspecialchars((string) ($post['visible'] ?? 'public')) ?>">

				<div>
					<label class="btn btn-light btn-sm rounded-pill">
						<i class="bi bi-image"></i>
						<input type="file" name="media[]" id="editfileInput" hidden multiple>
					</label>
					<button type="submit" class="btn btn-primary rounded-pill px-4">Lưu</button>
				</div>
			</div>
		</form>
	</div>
</article>

<script>
function autoResize(el) {
	el.style.height = "auto";
	el.style.height = el.scrollHeight + "px";
}

function setPrivacy(value, icon, label) {
	document.getElementById("editprivacyInput").value = value;
	document.getElementById("editprivacyIcon").className = "bi " + icon;
	document.getElementById("editprivacyLabel").innerText = label;
}

function initPrivacyFromValue() {
	const value = document.getElementById("editprivacyInput").value;
	if (value === "followers") {
		setPrivacy("followers", "bi-people", "Người theo dõi");
	} else if (value === "private") {
		setPrivacy("private", "bi-lock", "Chỉ mình tôi");
	} else {
		setPrivacy("public", "bi-globe2", "Công khai");
	}
}

function removeMedia(mediaId) {
	const mediaItem = document.getElementById("media-item-" + mediaId);
	if (!mediaItem) return;
	mediaItem.remove();
}

function removeNewMediaAt(index) {
	const fileInput = document.getElementById("editfileInput");
	if (!fileInput || !fileInput.files || !fileInput.files.length) return;

	try {
		const dt = new DataTransfer();
		Array.from(fileInput.files).forEach(function (file, i) {
			if (i !== index) dt.items.add(file);
		});
		fileInput.files = dt.files;
	} catch (e) {
		// Fallback: browser không hỗ trợ DataTransfer set files.
		fileInput.value = "";
	}

	renderNewMediaPreview();
}

function renderNewMediaPreview() {
	const fileInput = document.getElementById("editfileInput");
	const previewContainer = document.getElementById("newPreviewContainer");
	const previewGrid = document.getElementById("newPreviewGrid");
	if (!fileInput || !previewContainer || !previewGrid) return;

	previewGrid.innerHTML = "";
	const files = Array.from(fileInput.files || []);
	if (!files.length) {
		previewContainer.classList.add("d-none");
		return;
	}

	files.forEach(function (file, index) {
		const col = document.createElement("div");
		col.className = "col-12 col-md-6";

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
			img.className = "img-fluid rounded-4 w-100";
			img.alt = "";
			img.src = URL.createObjectURL(file);
			wrapper.appendChild(img);
		}

		const removeBtn = document.createElement("button");
		removeBtn.type = "button";
		removeBtn.className = "bi bi-x text-white preview-remove";
		removeBtn.setAttribute("aria-label", "Xóa preview");
		removeBtn.addEventListener("click", function () {
			removeNewMediaAt(index);
		});
		wrapper.appendChild(removeBtn);

		col.appendChild(wrapper);
		previewGrid.appendChild(col);
	});

	previewContainer.classList.remove("d-none");
}

document.addEventListener("DOMContentLoaded", function () {
	initPrivacyFromValue();
	const textarea = document.querySelector("textarea[name='content']");
	if (textarea) autoResize(textarea);

	const fileInput = document.getElementById("editfileInput");
	if (fileInput) {
		fileInput.addEventListener("change", renderNewMediaPreview);
	}
});
</script>