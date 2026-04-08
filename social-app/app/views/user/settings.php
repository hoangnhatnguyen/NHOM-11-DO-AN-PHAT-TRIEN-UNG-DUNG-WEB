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

                <h5 class="mt-4 mb-3">Danh sách chặn</h5>

                <div id="blockedList" class="d-flex flex-column gap-2">

                    <?php if (empty($blocked)): ?>
                        <div class="text-muted text-center py-4 bg-body-tertiary rounded-4">
                            Bạn chưa chặn người dùng nào 😌
                        </div>
                    <?php else: ?>
                        <?php foreach($blocked as $u): ?>
                           <div class="blocked-item d-flex align-items-center justify-content-between 
                            bg-body-tertiary rounded-4 px-3 py-2">

                                <!-- LEFT -->
                                <div class="d-flex align-items-center gap-3">

                                    <!-- Avatar -->
                                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                                         style="width:40px;height:40px;background:#8adfd7;color:#0a3d3a;font-weight:600;">
                                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                    </div>

                                    <!-- Username -->
                                    <span class="fw-medium">
                                        <?= htmlspecialchars($u['username']) ?>
                                    </span>
                                </div>

                                <!-- RIGHT -->
                                <button class="btn btn-sm btn-outline-danger rounded-pill px-3 unblock"
                                        data-id="<?= $u['id'] ?>">
                                    Hủy chặn
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>

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

            btn.closest(".blocked-item").remove();

            const list = document.getElementById("blockedList");
        
            if (list && !list.querySelector(".blocked-item")) {
                list.innerHTML = `
                    <div class="text-muted text-center py-4 bg-body-tertiary rounded-4">
                        Bạn chưa chặn người dùng nào 😌
                    </div>
                `;
            }
        })
        .catch(() => alert("Không thể hủy chặn người dùng."));
    };
});


document.addEventListener("keydown", function (e) {
    const input = e.target;
    
    if (input.tagName !== "INPUT") return;
    if (!input.closest(".search-box")) return;

    if (e.key === "Enter") {
        e.preventDefault();
        const keyword = input.value.trim();
        if (!keyword) return;

        window.location.href = BASE + "/search?q=" + encodeURIComponent(keyword) + "&tab=top";
    }
});


document.addEventListener("click", function (e) {
   
    const trend = e.target.closest(".trend[data-q]");
    if (!trend) return;
    
    e.preventDefault();
    const q = trend.getAttribute("data-q");
    if (!q) return;

  
    window.location.href = BASE + "/search?q=" + encodeURIComponent(q) + "&tab=top";
});

</script>
