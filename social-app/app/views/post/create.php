<form method="POST" action="<?= BASE_URL ?>/post/store" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
    <?php if (($_GET['composer_error'] ?? '') === 'empty'): ?>
        <div class="alert alert-warning py-2 mb-3" role="alert">Bạn cần nhập nội dung hoặc chọn ít nhất 1 ảnh trước khi đăng.</div>
    <?php endif; ?>


    <div class="d-flex border-bottom pb-3 mb-3">
        <div class="avatar-sm me-2"><?= strtoupper(substr((string) ($currentUser['username'] ?? 'U'), 0, 1)) ?></div>

        <div class="flex-grow-1">
            <textarea name="content"
                        class="form-control border-0 bg-light rounded-4 composer-textarea"
                        rows="2"
                        placeholder="Hãy viết gì đó..."
                        oninput="autoResize(this)"></textarea>
            <!-- PREVIEW IMAGE -->
            <div id="previewContainer" class="mt-3 d-none">
                <div class="preview-wrapper position-relative">
                    <img id="previewImage" class="img-fluid rounded-4 w-100">

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
                    <input type="file" name="media[]" id="fileInput" hidden multiple accept="image/jpeg,image/png,image/gif,image/webp">
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
    const previewImage = document.getElementById("previewImage");
    const previewContainer = document.getElementById("previewContainer");
    const textarea = document.querySelector("textarea[name='content']");
    const submitBtn = document.getElementById("btnSubmit");

    if (!fileInput) {
        console.error("Không tìm thấy fileInput");
        return;
    }

    fileInput.addEventListener("change", function () {
        const file = this.files[0];
        if (!file) return;

        if (!file.type.startsWith("image/")) {
            alert("Vui lòng chọn ảnh!");
            return;
        }

        const reader = new FileReader();

        reader.onload = function (e) {
            previewImage.src = e.target.result;
            previewContainer.classList.remove("d-none");

            console.log("Preview loaded OK"); // debug
        };

        reader.readAsDataURL(file);
    });

    window.removePreview = function () {
        previewContainer.classList.add("d-none");
        previewImage.src = "";
        fileInput.value = "";
    };

});
function setPrivacy(value, icon, label) {
    document.getElementById("privacyInput").value = value;
    document.getElementById("privacyIcon").className = "bi " + icon;
    document.getElementById("privacyLabel").innerText = label;
}
</script>