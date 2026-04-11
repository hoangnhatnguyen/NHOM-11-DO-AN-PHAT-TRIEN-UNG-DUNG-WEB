<?php
$composerName = (string) (($currentUser['username'] ?? 'Người dùng'));
$composerColor = Avatar::colors($composerName);
?>

<form id="feedComposerForm" method="POST" action="<?= BASE_URL ?>/post/store" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
    <?php if (($_GET['composer_error'] ?? '') === 'empty'): ?>
        <div class="alert alert-warning py-2 mb-3" role="alert">Bạn cần nhập nội dung hoặc chọn ít nhất 1 ảnh trước khi đăng.</div>
    <?php endif; ?>
    <div class="d-flex border-bottom pb-3 mb-3">
        <?php
        $composerAvatarUrl = $currentUser['avatar_url'] ?? '';
        $displayAvatarUrl = $composerAvatarUrl ? media_public_src($composerAvatarUrl) : '';
        $composerInitials = htmlspecialchars(Avatar::initials($composerName), ENT_QUOTES, 'UTF-8');
        ?>
        <?php if ($displayAvatarUrl): ?>
            <img src="<?= htmlspecialchars($displayAvatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                 class="rounded-circle me-2"
                 width="40" height="40"
                 style="object-fit: cover; flex-shrink: 0;"
                 alt="Avatar"
                 loading="lazy"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <span class="avatar-sm me-2" style="background: <?= htmlspecialchars($composerColor['bg'], ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars($composerColor['fg'], ENT_QUOTES, 'UTF-8') ?>; display: none;"><?= $composerInitials ?></span>
        <?php else: ?>
            <span class="avatar-sm me-2" style="background: <?= htmlspecialchars($composerColor['bg'], ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars($composerColor['fg'], ENT_QUOTES, 'UTF-8') ?>;"><?= $composerInitials ?></span>
        <?php endif; ?>

        <div class="flex-grow-1">
            <textarea name="content"
                        class="form-control border-0 bg-light rounded-4 composer-textarea"
                        rows="2"
                        placeholder="Hãy viết gì đó..."
                        oninput="autoResize(this)"></textarea>
            <div id="feedPreviewContainer" class="mt-3 d-none">
                <div class="row g-2" id="feedPreviewGrid"></div>
            </div>
        </div>            
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <div class="dropdown">
            <button class="btn btn-light btn-sm rounded-pill dropdown-toggle d-flex align-items-center gap-1"
                    type="button"
                    data-bs-toggle="dropdown">

                <i id="feedprivacyIcon" class="bi bi-globe2"></i>
                <span id="feedprivacyLabel">Công khai</span>
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

        <input type="hidden" name="privacy" id="feedprivacyInput" value="public">

        <div>
                <label class="btn btn-light btn-sm rounded-pill">
                    <i class="bi bi-image"></i>
                    <input type="file" name="media[]" id="feedFileInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                </label>
            </div>
        <button id="feedComposerSubmit" class="btn btn-primary rounded-pill px-4">Đăng</button>
    </div>
</form>

<script>
function autoResize(el) {
    el.style.height = "auto";
    el.style.height = el.scrollHeight + "px";
}
document.addEventListener("DOMContentLoaded", function () {

    const feedfileInput = document.getElementById("feedFileInput");
    const feedpreviewContainer = document.getElementById("feedPreviewContainer");
    const feedpreviewGrid = document.getElementById("feedPreviewGrid");

    const feedtextarea = document.querySelector("#feedComposerForm textarea[name='content']");
    const submitBtn = document.getElementById("feedComposerSubmit");

    function checkEnableButton() {
        if (!submitBtn || !feedtextarea) return;
        const hasText = feedtextarea.value.trim().length > 0;
        const hasImage = feedfileInput.files.length > 0;
        submitBtn.disabled = !(hasText || hasImage);
    }

    if (!feedfileInput) {
        console.error("Không tìm thấy fileInput");
        return;
    }

    window.__feedComposerFiles = window.__feedComposerFiles || [];

    function syncFeedComposerFilesToInput() {
        try {
            const dt = new DataTransfer();
            (window.__feedComposerFiles || []).forEach(function (file) {
                dt.items.add(file);
            });
            feedfileInput.files = dt.files;
        } catch (e) {
            feedfileInput.value = "";
        }
    }

    function removeNewMediaAt(index) {
        if (!Array.isArray(window.__feedComposerFiles)) return;
        window.__feedComposerFiles = window.__feedComposerFiles.filter(function (_, i) {
            return i !== index;
        });
        syncFeedComposerFilesToInput();
        renderPreview();
        checkEnableButton();
    }

    function renderPreview() {
        const files = Array.isArray(window.__feedComposerFiles) ? window.__feedComposerFiles : [];
        feedpreviewGrid.innerHTML = "";
        if (!files.length) {
            feedpreviewContainer.classList.add("d-none");
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
            removeBtn.addEventListener("click", function () { removeNewMediaAt(index); });
            wrapper.appendChild(removeBtn);
            col.appendChild(wrapper);
            feedpreviewGrid.appendChild(col);
        });
        feedpreviewContainer.classList.remove("d-none");
    }

    // check khi nhập text
    feedtextarea.addEventListener("input", checkEnableButton);

    // Mỗi lần chỉ chọn 1 ảnh; có thể chọn nhiều lần để thêm dần (khác trang sửa: một lần chọn nhiều file)
    feedfileInput.addEventListener("change", function () {
        const picked = this.files && this.files[0];
        if (!picked) return;
        if (!picked.type.startsWith("image/")) {
            alert("Vui lòng chọn ảnh (JPEG, PNG, GIF, WebP).");
            this.value = "";
            return;
        }
        if (!Array.isArray(window.__feedComposerFiles)) {
            window.__feedComposerFiles = [];
        }
        window.__feedComposerFiles.push(picked);
        syncFeedComposerFilesToInput();
        this.value = "";
        renderPreview();
        checkEnableButton();
    });

    checkEnableButton();
});

function setPrivacy(value, icon, label) {
    document.getElementById("feedprivacyInput").value = value;
    document.getElementById("feedprivacyIcon").className = "bi " + icon;
    document.getElementById("feedprivacyLabel").innerText = label;
}
</script>