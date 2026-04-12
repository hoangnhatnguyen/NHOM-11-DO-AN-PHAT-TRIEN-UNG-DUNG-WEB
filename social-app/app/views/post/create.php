<form id="createPostForm" method="POST" action="<?= BASE_URL ?>/post/store" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
    <?php if (($_GET['composer_error'] ?? '') === 'empty'): ?>
        <div class="alert alert-warning py-2 mb-3" role="alert">Bạn cần nhập nội dung hoặc chọn ít nhất 1 ảnh trước khi đăng.</div>
    <?php endif; ?>


    <div class="d-flex border-bottom pb-3 mb-3">
        <?php
            $composerName = (string) ($currentUser['username'] ?? 'U');
            $composerInitial = strtoupper(substr($composerName, 0, 1));
            $composerAvatarRaw = (string) ($currentUser['avatar_url'] ?? '');
            $composerAvatarSrc = $composerAvatarRaw !== '' ? media_public_src($composerAvatarRaw) : '';
        ?>
        <?php if ($composerAvatarSrc !== ''): ?>
            <img
                src="<?= htmlspecialchars($composerAvatarSrc, ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($composerName, ENT_QUOTES, 'UTF-8') ?>"
                class="avatar-sm me-2 rounded-circle"
                style="object-fit: cover;"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
            >
            <div class="avatar-sm me-2 d-none align-items-center justify-content-center"><?= htmlspecialchars($composerInitial, ENT_QUOTES, 'UTF-8') ?></div>
        <?php else: ?>
            <div class="avatar-sm me-2"><?= htmlspecialchars($composerInitial, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="flex-grow-1">
            <textarea name="content"
                        class="form-control border-0 bg-light rounded-4 composer-textarea"
                        rows="2"
                        placeholder="Hãy viết gì đó..."
                        oninput="autoResize(this)"></textarea>
            <div id="previewContainer" class="mt-3 d-none">
                <div class="row g-2" id="previewGrid"></div>
            </div>
        </div>            
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <div class="dropdown">
            <button class="btn btn-light btn-sm rounded-pill dropdown-toggle d-flex align-items-center gap-1"
                    type="button"
                    data-bs-toggle="dropdown">

                <i id="privacyIcon" class="bi bi-globe2"></i>
                <span id="privacyLabel">Công khai</span>
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

        <input type="hidden" name="privacy" id="privacyInput" value="public">

        <div>
                <label class="btn btn-light btn-sm rounded-pill">
                    <i class="bi bi-image"></i>
                    <input type="file" name="media[]" id="fileInput" hidden accept="image/jpeg,image/png,image/gif,image/webp">
                </label>
            </div>
        <button id="btnSubmit" class="btn btn-primary rounded-pill px-4">Đăng</button>
    </div>
</form>
<script>
function autoResize(el) {
    el.style.height = "auto";
    el.style.height = el.scrollHeight + "px";
}
document.addEventListener("DOMContentLoaded", function () {

    const fileInput = document.getElementById("fileInput");
    const previewContainer = document.getElementById("previewContainer");
    const previewGrid = document.getElementById("previewGrid");
    const textarea = document.querySelector("textarea[name='content']");
    const submitBtn = document.getElementById("btnSubmit");

    if (!fileInput || !previewGrid) {
        console.error("Không tìm thấy fileInput / previewGrid");
        return;
    }

    window.__createPostFiles = window.__createPostFiles || [];

    function syncCreateFilesToInput() {
        try {
            const dt = new DataTransfer();
            (window.__createPostFiles || []).forEach(function (f) {
                dt.items.add(f);
            });
            fileInput.files = dt.files;
        } catch (e) {
            fileInput.value = "";
        }
    }

    function renderCreatePreview() {
        previewGrid.innerHTML = "";
        const files = Array.isArray(window.__createPostFiles) ? window.__createPostFiles : [];
        if (!files.length) {
            previewContainer.classList.add("d-none");
            return;
        }
        files.forEach(function (file, index) {
            if (!file.type.startsWith("image/")) return;
            const col = document.createElement("div");
            col.className = "col-6";
            const wrapper = document.createElement("div");
            wrapper.className = "preview-wrapper position-relative";
            const img = document.createElement("img");
            img.className = "img-fluid rounded-4 w-100 post-media-tile";
            img.alt = "";
            img.src = URL.createObjectURL(file);
            wrapper.appendChild(img);
            const removeBtn = document.createElement("button");
            removeBtn.type = "button";
            removeBtn.className = "bi bi-x text-white preview-remove";
            removeBtn.addEventListener("click", function () {
                window.__createPostFiles = (window.__createPostFiles || []).filter(function (_, i) {
                    return i !== index;
                });
                syncCreateFilesToInput();
                renderCreatePreview();
            });
            wrapper.appendChild(removeBtn);
            col.appendChild(wrapper);
            previewGrid.appendChild(col);
        });
        previewContainer.classList.remove("d-none");
    }

    // Một ảnh mỗi lần chọn file; có thể chọn lặp lại để thêm ảnh (trang sửa vẫn cho chọn nhiều ảnh một lần)
    fileInput.addEventListener("change", function () {
        const file = this.files && this.files[0];
        if (!file) return;
        if (!file.type.startsWith("image/")) {
            alert("Vui lòng chọn ảnh!");
            this.value = "";
            return;
        }
        if (!Array.isArray(window.__createPostFiles)) {
            window.__createPostFiles = [];
        }
        window.__createPostFiles.push(file);
        syncCreateFilesToInput();
        this.value = "";
        renderCreatePreview();
    });

    window.removePreview = function () {
        window.__createPostFiles = [];
        syncCreateFilesToInput();
        renderCreatePreview();
    };

    window.syncCreatePostFilesToInput = syncCreateFilesToInput;

});
function setPrivacy(value, icon, label) {
    document.getElementById("privacyInput").value = value;
    document.getElementById("privacyIcon").className = "bi " + icon;
    document.getElementById("privacyLabel").innerText = label;
}
</script>