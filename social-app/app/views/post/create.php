<form method="POST" action="<?= BASE_URL ?>/post/store" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">


    <div class="d-flex border-bottom pb-3 mb-3">
        <div class="avatar-sm me-2"><?= strtoupper(substr((string) ($currentUser['username'] ?? 'U'), 0, 1)) ?></div>

        <div class="flex-grow-1">
            <textarea name="content"
                        class="form-control border-0 bg-light rounded-4 composer-textarea"
                        rows="2"
                        placeholder="Hãy viết gì đó..."
                        oninput="autoResize(this)"></textarea>
            <div id="previewContainer" class="mt-3 d-none">
                <div id="previewGrid" class="post-media-grid"></div>
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
                    <input type="file" name="media[]" id="fileInput" hidden multiple accept="image/*,video/*">
                </label>
            </div>
        <button class="btn btn-primary rounded-pill px-4">Đăng</button>
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
    let selectedFiles = [];

    if (!fileInput) {
        console.error("Không tìm thấy fileInput");
        return;
    }

    function syncInputFiles() {
        try {
            const dt = new DataTransfer();
            selectedFiles.forEach(function (file) { dt.items.add(file); });
            fileInput.files = dt.files;
        } catch (e) {
            // Ignore if DataTransfer is not supported.
        }
    }

    function removeNewMediaAt(index) {
        if (index < 0 || index >= selectedFiles.length) return;
        selectedFiles.splice(index, 1);
        syncInputFiles();
        renderPreview();
    }

    function renderPreview() {
        if (!previewGrid) return;
        previewGrid.innerHTML = "";

        const files = selectedFiles.slice();
        if (!files.length) {
            previewContainer.classList.add("d-none");
            return;
        }

        files.forEach(function (file, index) {
            if (!file.type.startsWith("image/") && !file.type.startsWith("video/")) {
                return;
            }

            const item = document.createElement("div");
            item.className = "post-media-item";

            const wrapper = document.createElement("div");
            wrapper.className = "preview-wrapper position-relative";

            if (file.type.startsWith("video/")) {
                const video = document.createElement("video");
                video.className = "w-100";
                video.controls = true;
                video.playsInline = true;
                video.src = URL.createObjectURL(file);
                wrapper.appendChild(video);
            } else {
                const img = document.createElement("img");
                img.className = "img-fluid w-100";
                img.alt = "";
                img.src = URL.createObjectURL(file);
                wrapper.appendChild(img);
            }

            const removeBtn = document.createElement("button");
            removeBtn.type = "button";
            removeBtn.className = "bi bi-x text-white preview-remove";
            removeBtn.setAttribute("aria-label", "Xóa media");
            removeBtn.addEventListener("click", function () {
                removeNewMediaAt(index);
            });
            wrapper.appendChild(removeBtn);

            item.appendChild(wrapper);
            previewGrid.appendChild(item);
        });

        previewContainer.classList.remove("d-none");
    }

    fileInput.addEventListener("change", function () {
        const incoming = Array.from(fileInput.files || []);
        if (incoming.length) {
            selectedFiles = selectedFiles.concat(incoming);
            syncInputFiles();
        }
        renderPreview();
    });

});
function setPrivacy(value, icon, label) {
    document.getElementById("privacyInput").value = value;
    document.getElementById("privacyIcon").className = "bi " + icon;
    document.getElementById("privacyLabel").innerText = label;
}
</script>