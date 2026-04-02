<?php $activeMenu = 'settings'; ?>

<div class="container mt-3 ms-3 px-4">
    <div class="row">

        <!-- LEFT SIDEBAR -->
        <div class="col-md-3">
            <?php include __DIR__ . '/../partials/feed/left_sidebar.php'; ?>
        </div>

        <!-- SETTINGS CONTENT -->
        <div class="col-md-6">
            <div class="card p-4">

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

        <!-- RIGHT WIDGET -->
        <div class="col-md-3">
            <?php include __DIR__ . '/../partials/feed/right_widgets.php'; ?>
        </div>

    </div>
</div>

<script>
const BASE = window.location.origin;

// AUTO SAVE 
["privacy_follow", "privacy_comment"].forEach(id => {
    document.getElementById(id)?.addEventListener("change", () => {
        fetch(BASE + "/setting-api/update-privacy", {
            method: "POST",
            body: new URLSearchParams({
                privacy_follow: document.getElementById("privacy_follow").value,
                privacy_comment: document.getElementById("privacy_comment").value
            })
        });
    });
});

// UNBLOCK
document.querySelectorAll(".unblock").forEach(btn => {
    btn.onclick = () => {
        fetch(BASE + "/setting-api/unblock", {
            method: "POST",
            body: new URLSearchParams({ id: btn.dataset.id })
        }).then(() => btn.parentElement.remove());
    };
});
</script>