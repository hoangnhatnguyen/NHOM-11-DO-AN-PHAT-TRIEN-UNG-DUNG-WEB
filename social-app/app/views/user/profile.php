<script>
const USER_ID = <?= (int)$user['id'] ?>;
</script>
<div class="container">
    <div class="card p-4">
<div class="position-relative">
    <img id="avatarImg"
         src="<?= $user['avatar_url'] ?? BASE_URL.'/public/default-avatar.png' ?>"
         class="rounded-circle"
         width="100" height="100"
         style="object-fit:cover">

    <?php if($isOwner): ?>
        <div id="avatarOverlay"
             class="position-absolute top-50 start-50 translate-middle text-white fw-bold d-none"
             style="background:rgba(0,0,0,0.5); padding:6px 10px; border-radius:20px; cursor:pointer;">
            Đổi ảnh
        </div>

        <input type="file" id="avatarInput" class="d-none">
    <?php endif; ?>
</div>

            <div>
                <h4><?= htmlspecialchars($user['username']) ?></h4>

                <p id="bioText"><?= htmlspecialchars($user['bio'] ?? '') ?></p>

                <div>
                    <b><?= $stats['posts'] ?></b> bài viết
                    <b class="ms-3" id="followersBtn" style="cursor:pointer">
                        <?= $stats['followers'] ?> followers
                    </b>
                    <b class="ms-3" id="followingBtn" style="cursor:pointer">
                        <?= $stats['following'] ?> following
                    </b>
                </div>
            </div>

            <?php if ($isOwner): ?>
                <button class="btn btn-outline-primary ms-auto" id="editBtn">
                    Chỉnh sửa
                </button>
            <?php endif; ?>
        </div>
<div id="badgeArea" class="mt-3 d-flex gap-2 flex-wrap">
    <?php foreach ($badges as $b): ?>
        <div class="badge bg-primary badge-item position-relative"
             data-id="<?= $b['id'] ?>"
             style="cursor:pointer">
            <?= htmlspecialchars($b['name']) ?>
        </div>
    <?php endforeach; ?>
</div>
        <!-- Tabs -->
        <ul class="nav nav-tabs mt-4">
            <li class="nav-item">
                <a class="nav-link active" data-tab="posts">Bài đăng</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-tab="activity">Tương tác</a>
            </li>
        </ul>

        <div id="tabContent" class="mt-3 text-muted text-center">
            Đang tải...
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="followModal">
    <div class="modal-dialog">
        <div class="modal-content p-3">
            <h5 id="modalTitle"></h5>
            <div id="followList"></div>
        </div>
    </div>
</div>

<?php $pageScripts[] = ['src' => BASE_URL . '/public/js/profile.js']; ?>