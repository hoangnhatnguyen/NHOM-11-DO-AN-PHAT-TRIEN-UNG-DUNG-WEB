<?php
// Chỉ coi là tab "Trang cá nhân" khi đang xem hồ sơ của chính mình
$activeMenu = $isOwner ? 'profile' : 'browse';
$profilePosts = $profilePosts ?? [];
?>

<script>
const USER_ID = <?= (int) ($user['id'] ?? 0) ?>;
</script>

<?php if (!empty($isOwner)): ?>
<style>
#profileAvatarContainer[data-change-avatar="1"] { cursor: pointer; }
#profileAvatarContainer[data-change-avatar="0"] { cursor: default; }
#profileAvatarContainer[data-change-avatar="1"]:hover #avatarOverlay,
#profileAvatarContainer[data-change-avatar="1"]:focus-within #avatarOverlay { opacity: 1; }
#avatarClickTarget {
	position: absolute;
	inset: 0;
	z-index: 3;
	cursor: pointer;
}
#avatarOverlay {
	opacity: 0;
	transition: opacity 0.2s ease;
	border: 0;
	z-index: 4;
}
</style>
<?php endif; ?>

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
                         data-change-avatar="<?= !empty($isOwner) ? '1' : '0' ?>"
                         data-avatar-initial="<?= htmlspecialchars(strtoupper(substr($user['username'], 0, 1))) ?>">
                        
                        <?php if (!empty($user['avatar_url'])): ?>
                            <!-- Mode 1: Image Avatar (khi có url) -->
                            <?php
                            $avatarUrl = $user['avatar_url'] ?? '';
                            $displayUrl = $avatarUrl ? media_public_src($avatarUrl) : '';
                            ?>
                            <?php if ($displayUrl): ?>
                            <img id="avatarImg"
                                 src="<?= htmlspecialchars($displayUrl) ?>"
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
                                <!-- Mode 2: Text Avatar (fallback nếu không generate được URL) -->
                                <div id="textAvatar"
                                     class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                                     style="width:110px; height:110px; background:#8adfd7; color:#0a3d3a; font-weight:600; font-size:2rem;">
                                    <?= htmlspecialchars(strtoupper(substr($user['username'], 0, 1))) ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Mode 2: Text Avatar (khi ko có url) -->
                            <div id="textAvatar"
                                 class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                                 style="width:110px; height:110px; background:#8adfd7; color:#0a3d3a; font-weight:600; font-size:2rem;">
                                <?= htmlspecialchars(strtoupper(substr($user['username'], 0, 1))) ?>
                            </div>
                        <?php endif; ?>

                        <?php if($isOwner): ?>
                            <label
                                 id="avatarClickTarget"
                                 for="avatarInput"
                                 aria-label="Chọn ảnh đại diện mới">
                            </label>

                            <label
                                 for="avatarInput"
                                 id="avatarOverlay"
                                 class="position-absolute top-50 start-50 translate-middle text-white fw-bold rounded-pill"
                                 style="background:rgba(0,0,0,0.55); padding:6px 12px; font-size:0.85rem;"
                                 aria-label="Đổi ảnh đại diện">
                                Đổi ảnh
                            </label>

                            <input type="file" id="avatarInput" class="d-none" accept="image/jpeg,image/png,image/gif,image/webp">
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

                    <?php if (!$isOwner): ?>
                        <div class="d-flex flex-wrap gap-2 mt-3 align-items-center">
                            <a href="<?= BASE_URL ?>/messages?user=<?= (int) ($user['id'] ?? 0) ?>"
                               class="btn btn-brand-follow rounded-pill">
                                <i class="bi bi-chat-dots me-1"></i>Nhắn tin
                            </a>
                            <button type="button"
                                    id="profileFollowBtn"
                                    class="btn rounded-pill <?= !empty($isFollowing) ? 'btn-brand-follow-outline' : 'btn-brand-follow' ?>"
                                    data-user-id="<?= (int) ($user['id'] ?? 0) ?>"
                                    data-username="<?= htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-following="<?= !empty($isFollowing) ? 'true' : 'false' ?>">
                                <?= !empty($isFollowing) ? 'Đã theo dõi' : 'Theo dõi' ?>
                            </button>
                        </div>
                    <?php endif; ?>

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

                <div id="tabContentPosts" class="mt-3">
                    <?php if (empty($profilePosts)): ?>
                        <p class="text-muted mb-0">Chưa có bài viết nào</p>
                    <?php else: ?>
                        <div class="profile-post-grid" aria-label="Danh sách bài đăng">
                            <?php foreach ($profilePosts as $post): ?>
                                <?php
                                $postId = (int) ($post['id'] ?? 0);
                                $content = trim((string) ($post['content'] ?? ''));
                                $mediaItems = array_values(array_filter((array) ($post['media'] ?? []), static function ($item) {
                                    return trim((string) ($item['media_url'] ?? '')) !== '';
                                }));
                                $coverMedia = $mediaItems[0] ?? null;
                                $coverUrl = $coverMedia ? media_public_src((string) ($coverMedia['media_url'] ?? '')) : '';
                                $coverType = (string) ($coverMedia['media_type'] ?? 'image');
                                $isVideoCover = $coverUrl !== '' && $coverType === 'video';
                                $extraMediaCount = max(0, count($mediaItems) - 1);
                                $previewText = $content !== '' ? $content : 'Bài viết không có nội dung';
                                ?>
                                <a
                                    href="<?= BASE_URL ?>/post/<?= $postId ?>"
                                    class="profile-post-tile js-open-post-modal"
                                    data-post-id="<?= $postId ?>"
                                    aria-label="Mở bài viết #<?= $postId ?>"
                                >
                                    <?php if ($coverUrl !== ''): ?>
                                        <?php if ($isVideoCover): ?>
                                            <video
                                                class="profile-post-cover"
                                                src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                muted
                                                playsinline
                                                preload="metadata"
                                            ></video>
                                        <?php else: ?>
                                            <img
                                                src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                class="profile-post-cover"
                                                alt="<?= htmlspecialchars($previewText, ENT_QUOTES, 'UTF-8') ?>"
                                                loading="lazy"
                                            >
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="profile-post-cover profile-post-cover-text">
                                            <p><?= htmlspecialchars($previewText, ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <div class="profile-post-meta">
                                        <?php if ($isVideoCover): ?>
                                            <span class="profile-post-badge">
                                                <i class="bi bi-play-circle-fill"></i>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($extraMediaCount > 0): ?>
                                            <span class="profile-post-badge">
                                                <i class="bi bi-images"></i>
                                                <span><?= count($mediaItems) ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="profile-post-overlay">
                                        <div class="profile-post-stats">
                                            <span><i class="bi bi-heart-fill"></i> <?= (int) ($post['like_count'] ?? 0) ?></span>
                                            <span><i class="bi bi-chat-fill"></i> <?= (int) ($post['comment_count'] ?? 0) ?></span>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="tabContentActivity" class="mt-3 d-none text-muted"></div>

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

<div class="modal fade" id="unfollowConfirmModal" tabindex="-1" aria-labelledby="unfollowConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-semibold" id="unfollowConfirmLabel">Hủy theo dõi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-0 text-secondary" id="unfollowConfirmText"></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Không</button>
                <button type="button" class="btn btn-brand-follow rounded-pill" id="unfollowConfirmBtn">Hủy theo dõi</button>
            </div>
        </div>
    </div>
</div>

<?php $pageScripts[] = ['src' => BASE_URL . '/public/js/profile.js']; ?>
