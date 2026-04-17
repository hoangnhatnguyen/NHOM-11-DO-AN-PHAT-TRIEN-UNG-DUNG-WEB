<?php
$me = $currentUser ?? [];
$meName = (string) ($me['username'] ?? 'Bạn');
$meInitial = Avatar::initials($meName);
$meColor = Avatar::colors($meName);
?>
<section class="chat-shell chat-shell--saved" data-current-user-id="<?= (int) ($me['id'] ?? 0) ?>">
    <div class="chat-frame chat-frame--saved" style="grid-template-columns: 64px minmax(360px, 1fr);">
        <aside class="chat-rail">
            <a class="chat-rail-btn" href="<?= BASE_URL ?>/" data-label="Trang chủ" title="Trang chủ" aria-label="Trang chủ"><i class="bi bi-house"></i></a>
            <a class="chat-rail-btn" href="<?= BASE_URL ?>/messages" data-label="Tin nhắn" title="Tin nhắn" aria-label="Tin nhắn"><i class="bi bi-envelope"></i></a>
            <a class="chat-rail-btn" href="<?= BASE_URL ?>/notifications" data-label="Thông báo" title="Thông báo" aria-label="Thông báo"><i class="bi bi-bell"></i></a>
            <a class="chat-rail-btn" href="<?= BASE_URL ?>/search" data-label="Tìm kiếm" title="Tìm kiếm" aria-label="Tìm kiếm"><i class="bi bi-search"></i></a>
            <a class="chat-rail-btn active" href="<?= BASE_URL ?>/saved" data-label="Đã lưu" title="Đã lưu" aria-label="Đã lưu"><i class="bi bi-bookmark"></i></a>
            <a class="chat-rail-btn" href="<?= htmlspecialchars(profile_url($meName), ENT_QUOTES, 'UTF-8') ?>" data-label="Trang cá nhân" title="Trang cá nhân" aria-label="Trang cá nhân"><i class="bi bi-person"></i></a>
            <a class="chat-rail-btn" href="<?= BASE_URL ?>/settings" data-label="Cài đặt" title="Cài đặt" aria-label="Cài đặt"><i class="bi bi-gear"></i></a>

            <div class="chat-rail-avatar-container">
                <a class="chat-rail-avatar d-flex align-items-center justify-content-center text-decoration-none" href="<?= htmlspecialchars(profile_url($meName), ENT_QUOTES, 'UTF-8') ?>" style="--avatar-bg: <?= htmlspecialchars($meColor['bg']) ?>; --avatar-fg: <?= htmlspecialchars($meColor['fg']) ?>;" title="<?= htmlspecialchars($meName) ?>" aria-label="<?= htmlspecialchars($meName) ?>">
                    <?= htmlspecialchars($meInitial) ?>
                </a>
            </div>
        </aside>

        <section class="chat-thread-panel chat-thread-panel--saved">
            <div class="chat-thread-header">
                <div class="chat-user-info">
                    <div class="chat-user-avatar"><i class="bi bi-bookmark-fill"></i></div>
                    <div>
                        <div class="chat-user-name">Bài viết đã lưu</div>
                        <div class="chat-user-handle">Danh sách bài viết bạn đã lưu</div>
                    </div>
                </div>
            </div>

            <div class="saved-message-scroll p-3">
                <div id="saved-empty-state" class="text-center text-secondary py-5 <?= !empty($savedPosts ?? []) ? 'd-none' : '' ?>">
                    <i class="bi bi-bookmark fs-1 d-block mb-2"></i>
                    Bạn chưa lưu bài viết nào.
                </div>
                <div id="saved-posts-list">
                <?php if (!empty($savedPosts ?? [])): ?>
                    <?php foreach ($savedPosts as $post): ?>
                        <?php
                        $rawMedia = (string) ($post['media_url'] ?? '');
                        $src = $rawMedia !== '' ? media_public_src($rawMedia) : BASE_URL . '/public/images/default.jpg';
                        ?>
                        <div class="card border-0 shadow-sm rounded-4 mb-1 js-saved-post-row" data-post-id="<?= (int) ($post['id'] ?? 0) ?>">
                            <div class="card-body d-flex justify-content-between align-items-center gap-2">
                                <a href="<?= BASE_URL ?>/post/<?= (int) ($post['id'] ?? 0) ?>" class="js-open-post-modal d-flex align-items-center gap-3 text-decoration-none text-dark flex-grow-1" data-post-id="<?= (int) ($post['id'] ?? 0) ?>">
                                    <img
                                        src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>"
                                        class="rounded-3"
                                        style="width:70px;height:70px;object-fit:cover;"
                                        alt=""
                                    >
                                    <div>
                                        <div class="fw-semibold mb-1" style="max-width:600px;">
                                            <?php
                                            $excerpt = (string) ($post['content'] ?? '');
                                            if (function_exists('mb_strimwidth')) {
                                                echo htmlspecialchars(mb_strimwidth($excerpt, 0, 110, '...', 'UTF-8'));
                                            } else {
                                                echo htmlspecialchars(strlen($excerpt) > 110 ? substr($excerpt, 0, 107) . '...' : $excerpt);
                                            }
                                            ?>
                                        </div>
                                        <div class="text-secondary small">
                                            Đã lưu từ bài viết của <strong><?= htmlspecialchars((string) ($post['username'] ?? 'Người dùng')) ?></strong>
                                        </div>
                                    </div>
                                </a>

                                <button type="button" class="btn btn-light rounded-3 px-3 js-saved-unsave-btn" data-post-id="<?= (int) ($post['id'] ?? 0) ?>" data-csrf="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi bi-trash me-1"></i> Bỏ lưu
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</section>
