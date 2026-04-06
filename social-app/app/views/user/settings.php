<?php $activeMenu = 'settings'; ?>
<script>
const SETTINGS_CSRF = <?= json_encode((string) ($csrfToken ?? ''), JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="container-fluid feed-layout px-lg-4">
    <div class="row g-3 g-lg-4 feed-layout-row">

        <!-- LEFT SIDEBAR -->
        <div class="col-12 col-md-2 col-lg-3 feed-sidebar-column">
            <?php include __DIR__ . '/../partials/feed/left_sidebar.php'; ?>
        </div>

        <!-- SETTINGS CONTENT -->
        <div class="col-12 col-md-7 col-lg-6 bg-white feed-main-column">
            <div class="card border-0 p-4">

                <h5 class="mb-3">Quyền riêng tư</h5>

                <!-- FOLLOW -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Ai có thể theo dõi bạn</span>

                    <select id="privacy_follow" class="form-select w-auto">
    <option value="everyone" <?= ($currentUser['privacy_follow'] ?? '') == 'everyone' ? 'selected' : '' ?>>
        Mọi người
    </option>
    <option value="mutual" <?= ($currentUser['privacy_follow'] ?? '') == 'mutual' ? 'selected' : '' ?>>
        Bạn chung
    </option>
</select>
                </div>

                <!-- COMMENT -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Ai có thể bình luận bài viết của bạn</span>

                   <select id="privacy_comment" class="form-select w-auto">
    <option value="everyone" <?= ($currentUser['privacy_comment'] ?? '') == 'everyone' ? 'selected' : '' ?>>
        Mọi người
    </option>
    <option value="mutual" <?= ($currentUser['privacy_comment'] ?? '') == 'mutual' ? 'selected' : '' ?>>
        Bạn chung
    </option>
</select>
                </div>

                <hr>

                <h5>Danh sách chặn</h5>

                <ul id="blockedList" class="list-unstyled">
                    <?php if (empty($blocked)): ?>
                        <div class="text-muted text-center">
                            Bạn chưa chặn người dùng nào 😌
                        </div>
                    <?php else: ?>
                        <?php foreach($blocked as $u): ?>
                            <li class="d-flex justify-content-between mb-2">
                                <span><?= $u['username'] ?></span>
                                <button class="btn btn-sm btn-danger unblock" data-id="<?= $u['id'] ?>">
                                    Hủy chặn
                                </button>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

            </div>
        </div>

        <!-- RIGHT WIDGETS -->
        <div class="col-12 col-md-3 col-lg-3 feed-widgets-column">
            <?php include __DIR__ . '/../partials/feed/right_widgets.php'; ?>
        </div>

    </div>
</div>

<script>
const BASE = typeof window.__APP_BASE__ === "string" ? window.__APP_BASE__.replace(/\/$/, "") : "";

// AUTO SAVE 
["privacy_follow", "privacy_comment"].forEach(id => {
    document.getElementById(id)?.addEventListener("change", () => {
        fetch(BASE + "/setting-api/update-privacy", {
            method: "POST",
            credentials: "same-origin",
            body: new URLSearchParams({
                _csrf: SETTINGS_CSRF,
                privacy_follow: document.getElementById("privacy_follow").value,
                privacy_comment: document.getElementById("privacy_comment").value
            })
        })
        .then(r => {
            if (!r.ok) throw new Error("save_failed");
            return r.json();
        })
        .then(data => {
            if (!data.success) throw new Error("save_failed");
        })
        .catch(() => alert("Không thể lưu cài đặt quyền riêng tư."));
    });
});

// UNBLOCK
document.querySelectorAll(".unblock").forEach(btn => {
    btn.onclick = () => {
        fetch(BASE + "/setting-api/unblock", {
            method: "POST",
            credentials: "same-origin",
            body: new URLSearchParams({ id: btn.dataset.id, _csrf: SETTINGS_CSRF })
        })
        .then(r => {
            if (!r.ok) throw new Error("unblock_failed");
            return r.json();
        })
        .then(data => {
            if (!data.success) throw new Error("unblock_failed");
            btn.parentElement.remove();
            const list = document.getElementById("blockedList");
            if (list && !list.querySelector("li")) {
                list.innerHTML = `<div class="text-muted text-center">Bạn chưa chặn người dùng nào 😌</div>`;
            }
        })
        .catch(() => alert("Không thể hủy chặn người dùng."));
    };
});
</script>
