<div class="container py-3">

    <!-- TITLE -->
    <div class="mb-3">
        <span class="badge rounded-pill bg-light text-dark px-3 py-2 fs-6 shadow-sm">
            Danh sách lưu trữ
        </span>
    </div>

    <?php foreach ($savedPosts as $post): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">

                <!-- LEFT -->
                <div class="d-flex align-items-center gap-3">

                    <!-- IMAGE -->
                    <img 
                        src="<?= htmlspecialchars(media_public_src($post['media_url'] ?? '') ?: 'public/images/default.jpg') ?>" 
                        class="rounded-3"
                        style="width:70px;height:70px;object-fit:cover;"
                    >

                    <!-- CONTENT -->
                    <div>
                        <div class="fw-semibold mb-1" style="max-width:500px;">
                            <?= mb_strimwidth(htmlspecialchars($post['content']), 0, 100, "...") ?>
                        </div>

                        <div class="text-secondary small d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" 
                                  style="width:10px;height:10px;background:#dc3545;"></span>
                            <span>
                                Đã lưu từ bài viết của 
                                <strong><?= htmlspecialchars($post['username']) ?></strong>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- RIGHT -->
                <form method="post" action="<?= BASE_URL ?>/unsave">
                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                    <button class="btn btn-light rounded-3 px-3">
                        <i class="bi bi-trash me-1"></i> Bỏ lưu
                    </button>
                </form>

            </div>
        </div>
    <?php endforeach; ?>

</div>