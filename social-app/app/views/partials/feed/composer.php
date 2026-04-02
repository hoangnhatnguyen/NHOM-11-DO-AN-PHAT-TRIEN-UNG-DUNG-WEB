<?php
$composerName = (string) (($currentUser['username'] ?? 'Người dùng'));
$composerColor = Avatar::colors($composerName);
?>




<form method="POST" action="<?= BASE_URL ?>/post/store" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">


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
            <!-- PREVIEW IMAGE -->
            <div id="feedPreviewContainer" class="mt-3 d-none">
                <div class="preview-wrapper position-relative">
                    <img id="feedPreviewImage" class="img-fluid rounded-4 w-100">

                    <button type="button" class="bi bi-x text-white preview-remove"
                            onclick="removePreview()"></button>
                </div>
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
                    <input type="file" name="media[]" id="feedFileInput" hidden>
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

    const feedfileInput = document.getElementById("feedFileInput");
    const feedpreviewImage = document.getElementById("feedPreviewImage");
    const feedpreviewContainer = document.getElementById("feedPreviewContainer");

    const feedtextarea = document.querySelector("textarea[name='content']");
    const feedsubmitBtn = document.getElementById("btnSubmit");

    function checkEnableButton() {
        const hasText = feedtextarea.value.trim().length > 0;
        const hasImage = feedfileInput.files.length > 0;

        if (hasText || hasImage) {
            submitBtn.disabled = false;
        } else {
            submitBtn.disabled = true;
        }
    }

    if (!feedfileInput) {
        console.error("Không tìm thấy fileInput");
        return;
    }

    feedfileInput.addEventListener("change", function () {
        const file = this.files[0];
        if (!file) return;

        if (!file.type.startsWith("image/")) {
            alert("Vui lòng chọn ảnh!");
            return;
        }

        const reader = new FileReader();

        reader.onload = function (e) {
            feedpreviewImage.src = e.target.result;
            feedpreviewContainer.classList.remove("d-none");

            console.log("Preview loaded OK"); // debug
        };

        reader.readAsDataURL(file);
    });

    window.removePreview = function () {
        feedpreviewContainer.classList.add("d-none");
        feedpreviewImage.src = "";
        feedfileInput.value = "";

        checkEnableButton();
    };

    // check khi nhập text
    feedtextarea.addEventListener("input", checkEnableButton);

    // check khi chọn ảnh
    feedfileInput.addEventListener("change", checkEnableButton);
});

function setPrivacy(value, icon, label) {
    document.getElementById("feedprivacyInput").value = value;
    document.getElementById("feedprivacyIcon").className = "bi " + icon;
    document.getElementById("feedprivacyLabel").innerText = label;
}
</script>