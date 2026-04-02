<?php $activeMenu = 'profile'; ?>

<script>
const USER_ID = <?= (int)$user['id'] ?>;
</script>

<div class="container mt-3 ms-3 px-4.5">
    <div class="row">

        <!-- LEFT SIDEBAR -->
        <div class="col-md-3">
            <?php include __DIR__ . '/../partials/feed/left_sidebar.php'; ?>
        </div>

        <!-- PROFILE CONTENT -->
        <div class="col-md-9">
            <div class="card p-4">

                <!-- ===== PROFILE HEADER ===== -->
                <div class="mb-4 text-start">

                    <div class="position-relative d-inline-block"
                         id="profileAvatarContainer"
                         data-avatar-container
                         data-avatar-initial="<?= htmlspecialchars(strtoupper(substr($user['username'], 0, 1))) ?>">
                        
                        <?php if (!empty($user['avatar_url'])): ?>
                            <!-- Mode 1: Image Avatar (khi có url) -->
                            <img id="avatarImg"
                                 src="<?= htmlspecialchars($user['avatar_url']) ?>"
                                 class="rounded-circle mb-3"
                                 width="110" height="110"
                                 style="object-fit:cover"
                                 onerror="document.getElementById('avatarImg').style.display='none'; document.getElementById('textAvatar').style.display='flex';">
                            
                            <!-- Mode 2: Text Avatar (fallback khi 404) -->
                            <div id="textAvatar"
                                 class="d-none position-absolute top-0 start-0 align-items-center justify-content-center rounded-circle"
                                 style="width:110px; height:110px; background:#8adfd7; color:#0a3d3a; font-weight:600; font-size:2rem; display:none;">
                                <?= htmlspecialchars(strtoupper(substr($user['username'], 0, 1))) ?>
                            </div>
                        <?php else: ?>
                            <!-- Mode 2: Text Avatar (khi ko có url) -->
                            <div id="textAvatar"
                                 class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                                 style="width:110px; height:110px; background:#8adfd7; color:#0a3d3a; font-weight:600; font-size:2rem;">
                                <?= htmlspecialchars(strtoupper(substr($user['username'], 0, 1))) ?>
                            </div>
                        <?php endif; ?>

                        <?php if($isOwner): ?>
                            <div id="avatarOverlay"
                                 class="position-absolute top-50 start-50 translate-middle text-white fw-bold d-none"
                                 style="background:rgba(0,0,0,0.5); padding:6px 10px; border-radius:20px; cursor:pointer;">
                                Đổi ảnh
                            </div>

                            <input type="file" id="avatarInput" class="d-none">
                        <?php endif; ?>
                    </div>

                    <h4 class="mb-2"><?= htmlspecialchars($user['username']) ?></h4>

                    <p id="bioText" class="text-muted mb-2">
                        <?= htmlspecialchars($user['bio'] ?? '') ?>
                    </p>

                    <div class="d-flex gap-4 mt-2">
                        <span><b><?= $stats['posts'] ?></b> bài viết</span>

                        <span id="followersBtn" style="cursor:pointer">
                            <b><?= $stats['followers'] ?></b> followers
                        </span>

                        <span id="followingBtn" style="cursor:pointer">
                            <b><?= $stats['following'] ?></b> following
                        </span>
                    </div>

                    <?php if ($isOwner): ?>
                        <button class="btn btn-outline-primary mt-3" id="editBtn">
                            Chỉnh sửa
                        </button>
                    <?php endif; ?>

                </div>

                <!-- ===== BADGE ===== -->
                <div id="badgeArea" class="mt-3 d-flex flex-wrap gap-2">

                    <?php if (!empty($badges)): ?>
                        <?php foreach ($badges as $b): ?>
                            <div class="badge bg-primary badge-item px-3 py-2"
                                 data-id="<?= $b['id'] ?>"
                                 style="cursor:pointer; font-size:13px;">
                                <?= htmlspecialchars($b['name']) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted small">
                            Chưa có badge nào ✨
                        </div>
                    <?php endif; ?>

                    <?php if($isOwner): ?>
                        <button id="addBadgeBtn"
                                class="btn btn-sm btn-outline-primary d-none">
                            + Thêm badge
                        </button>
                    <?php endif; ?>

                </div>

                <!-- ===== POPUP ADD BADGE ===== -->
                <div id="badgePopup"
                     class="d-none position-fixed top-50 start-50 translate-middle bg-white border rounded p-3 shadow"
                     style="width:300px; z-index:9999">

                    <input id="badgeSearch"
                           class="form-control mb-2"
                           placeholder="Tìm badge...">

                    <div id="badgeResult"></div>
                </div>

                <!-- ===== TABS ===== -->
                <ul class="nav nav-tabs mt-4">
                    <li class="nav-item">
                        <a class="nav-link active" data-tab="posts">Bài đăng</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-tab="activity">Tương tác</a>
                    </li>
                </ul>

                <div id="tabContent" class="mt-3 text-muted">
                    Đang tải...
                </div>

            </div>
        </div>

    </div>
</div>

<!-- ===== FOLLOW MODAL ===== -->
<div class="modal fade" id="followModal">
    <div class="modal-dialog">
        <div class="modal-content p-3">
            <h5 id="modalTitle" class="text-center mb-3"></h5>
            <div id="followList"></div>
        </div>
    </div>
</div>

<?php $pageScripts[] = ['src' => BASE_URL . '/public/js/profile.js']; ?>