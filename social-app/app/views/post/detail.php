<?php
require_once dirname(__DIR__, 2) . '/helpers/notification_helper.php';

if (!function_exists('format_comment_time_vi')) {
	function format_comment_time_vi(?string $rawDateTime): string {
		return notification_time_ago_vi($rawDateTime);
	}
}

$profileBase = rtrim((string) (($profileBaseUrl ?? BASE_URL) ?: ''), '/');
?>

<style>
	.post-detail-media {
		width: 100%;
		max-height: 48vh;
		object-fit: contain;
		background: #f8f9fa;
	}

	.modal .post-detail-media {
		max-height: 42vh;
	}
</style>

<article class="card border-0 shadow-sm rounded-4 mb-4 mt-3">
	<div class="card-body p-3 p-md-4">

        <?php
        $detailAuthor = (string) ($post['author_name'] ?? '');
        $detailAuthorColor = Avatar::colors($detailAuthor);
        $detailAvRaw = (string) ($post['author_avatar_url'] ?? '');
        $detailAvSrc = $detailAvRaw !== '' ? media_public_src($detailAvRaw) : '';

		$detailVisible = (string) ($post['visible'] ?? 'public');
		$detailVisibleIcon = 'bi-globe2';
		$detailVisibleLabel = 'Công khai';

		if ($detailVisible === 'followers') {
			$detailVisibleIcon = 'bi-people';
			$detailVisibleLabel = 'Người theo dõi';
		} elseif ($detailVisible === 'private') {
			$detailVisibleIcon = 'bi-lock';
			$detailVisibleLabel = 'Chỉ mình tôi';
		}
        ?>

        <div class="d-flex align-items-start justify-content-between gap-2">

			<a href="<?= htmlspecialchars($profileBase . '/profile?u=' . rawurlencode($detailAuthor), ENT_QUOTES, 'UTF-8') ?>"
			   class="d-flex align-items-center gap-2 text-decoration-none text-body min-w-0">

                <?php if ($detailAvSrc !== ''): ?>
                    <img src="<?= htmlspecialchars($detailAvSrc) ?>" width="44" height="44"
                         class="rounded-circle flex-shrink-0" style="object-fit:cover">

                <?php else: ?>
                    <div class="avatar-sm flex-shrink-0"
                         style="background: <?= htmlspecialchars($detailAuthorColor['bg']) ?>;
                                color: <?= htmlspecialchars($detailAuthorColor['fg']) ?>;">
                        <?= htmlspecialchars(strtoupper(substr($detailAuthor, 0, 1))) ?>
                    </div>
                <?php endif; ?>

                <div class="min-w-0">
                    <h5 class="fw-semibold mb-0 text-truncate"><?= htmlspecialchars($detailAuthor) ?></h5>
                    <div class="small text-secondary d-flex align-items-center gap-1">
                        <span><?= htmlspecialchars((string) $post['created_at']) ?></span>
                        <i class="bi <?= $detailVisibleIcon ?>" title="<?= $detailVisibleLabel ?>"></i>
                    </div>
                </div>
            </a>

            <?php if (isset($currentUser['id']) && (int)$currentUser['id'] === (int)($post['user_id'] ?? 0)): ?>

            <!-- MENU FIX -->
            <div class="dropdown js-post-card-menu position-relative" style="z-index: 50;">
                <button class="btn btn-sm btn-light rounded-pill"
                        type="button"
                        data-bs-toggle="dropdown"
                        data-bs-display="static"
                        data-bs-auto-close="outside">
                    <i class="bi bi-three-dots"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end post-action-menu">

                    <li>
                        <button type="button"
                                class="dropdown-item js-open-post-edit"
                                data-post-id="<?= (int) $post['id'] ?>">
                            Chỉnh sửa
                        </button>
                    </li>

                    <li>
                        <form method="POST"
                              action="/post/<?= (int) $post['id'] ?>/delete"
                              class="m-0"
                              onsubmit="showCustomDeleteConfirm(event, this, <?= (int) $post['id'] ?>)">

                            <input type="hidden" name="_csrf"
                                   value="<?= htmlspecialchars($csrfToken ?? '') ?>">

                            <button type="submit"
                                    class="dropdown-item text-danger border-0 bg-transparent w-100 text-start">
                                Xóa bài viết
                            </button>

                        </form>
                    </li>

                </ul>
            </div>

            <?php endif; ?>
        </div>

        <!-- CONTENT -->
        <div class="mt-3 mb-3 ms-1 position-relative">
            <?= format_post_display_html((string)($post['content'] ?? ''), $post['hashtag_names'] ?? []) ?>
        </div>

        <!-- MEDIA -->
        <?php foreach ($media as $m): ?>
            <?php
            $src = media_public_src((string) ($m['media_url'] ?? ''));
            if ($src === '') continue;
            $isVideo = ($m['media_type'] ?? '') === 'video';
            ?>

            <?php if ($isVideo): ?>
                <video src="<?= htmlspecialchars($src) ?>" controls class="w-100 rounded-4 mb-2 post-detail-media"></video>
            <?php else: ?>
                <img src="<?= htmlspecialchars($src) ?>" class="w-100 rounded-4 mb-2 post-detail-media">
            <?php endif; ?>
        <?php endforeach; ?>

        <hr class="my-3">

        <!-- ACTIONS -->
        <div class="d-flex gap-3 small">

            <form method="POST" action="/post/<?= (int)$post['id'] ?>/like" class="ajax-post-like m-0">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <button class="btn btn-link p-0 border-0">
                    <i class="bi <?= !empty($post['is_liked']) ? 'bi-heart-fill text-danger' : 'bi-heart' ?>"></i>
                </button>
                <span><?= (int) ($post['like_count'] ?? 0) ?></span>
            </form>

            <a href="#comment-box" class="text-secondary text-decoration-none">
                <i class="bi bi-chat"></i>
                <?= (int) ($post['comment_count'] ?? 0) ?>
            </a>

            <form method="POST" action="/post/<?= (int)$post['id'] ?>/share" class="ajax-post-share m-0">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <button class="btn btn-link p-0 border-0">
                    <i class="bi bi-share"></i>
                </button>
            </form>

            <form method="POST" action="/post/<?= (int)$post['id'] ?>/save" class="ajax-post-save m-0">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <button class="btn btn-link p-0 border-0">
                    <i class="bi <?= !empty($post['is_saved']) ? 'bi-bookmark-fill text-warning' : 'bi-bookmark' ?>"></i>
                </button>
            </form>

        </div>

        <hr class="my-3">

        <!-- COMMENT -->
        <?php if (!empty($canComment)): ?>
            <form method="POST" action="/post/<?= (int)$post['id'] ?>/comment" id="comment-box">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <input class="form-control" name="content" placeholder="Viết bình luận...">
            </form>
        <?php else: ?>
            <div class="alert alert-secondary text-center small">
                Bị giới hạn bình luận
            </div>
        <?php endif; ?>

        <div class="mt-3">
            <?php if (!empty($commentsTree)): ?>
                <?php foreach ($commentsTree as $node): ?>
                    <?php $depth = 0; include VIEW_PATH . 'partials/comment_reply_branch.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

	</div>
</article>

<script src="/public/js/comment.js"></script>

<script>
function showCustomDeleteConfirm(event, formElement, postId) {
    event.preventDefault();

    const ok = confirm("Bạn có chắc muốn xóa bài viết?");
    if (!ok) return;

    fetch(formElement.action, {
        method: "POST",
        body: new FormData(formElement),
        headers: { "X-Requested-With": "XMLHttpRequest" }
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            location.href = "/";
        } else {
            alert("Xóa thất bại");
        }
    });
}
</script>