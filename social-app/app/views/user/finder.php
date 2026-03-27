<?php
$targetId = $targetId ?? '';
$targetUser = $targetUser ?? null;
$error = $error ?? null;
$currentUser = $currentUser ?? [];
$currentUserId = (int) ($currentUser['id'] ?? 0);
?>

<div class="container" style="max-width: 900px;">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <h1 class="h3 mb-3">Test User Profile</h1>
            <p class="text-secondary mb-4">Nhập ID user để mở profile đơn giản và bấm nút chat test realtime.</p>

            <form method="get" action="<?= BASE_URL ?>/users/finder" class="row g-2 mb-4">
                <div class="col-sm-8 col-md-6">
                    <input
                        type="number"
                        min="1"
                        step="1"
                        name="id"
                        value="<?= htmlspecialchars((string) $targetId) ?>"
                        class="form-control"
                        placeholder="Nhập user id, ví dụ 83"
                        required
                    >
                </div>
                <div class="col-sm-4 col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Tìm user</button>
                </div>
            </form>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 px-3"><?= htmlspecialchars((string) $error) ?></div>
            <?php endif; ?>

            <?php if (!empty($targetUser)): ?>
                <?php
                    $targetUserId = (int) ($targetUser['id'] ?? 0);
                    $targetName = (string) ($targetUser['username'] ?? 'User');
                    $targetEmail = (string) ($targetUser['email'] ?? '');
                    $targetColor = Avatar::colors($targetName);
                    $canChat = $targetUserId > 0 && $targetUserId !== $currentUserId;
                ?>
                <div class="border rounded-4 p-3 p-md-4 bg-light-subtle">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="avatar-lg" style="background: <?= htmlspecialchars($targetColor['bg']) ?>; color: <?= htmlspecialchars($targetColor['fg']) ?>;">
                            <?= htmlspecialchars(Avatar::initials($targetName)) ?>
                        </div>
                        <div>
                            <div class="fw-bold fs-5 mb-0"><?= htmlspecialchars($targetName) ?></div>
                            <div class="text-secondary">ID: <?= $targetUserId ?></div>
                            <div class="text-secondary"><?= htmlspecialchars($targetEmail) ?></div>
                        </div>
                    </div>

                    <?php if ($canChat): ?>
                        <a href="<?= BASE_URL ?>/messages?user=<?= $targetUserId ?>" class="btn btn-primary">
                            <i class="bi bi-chat-dots me-1"></i> Chat với user này
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" disabled>
                            Không thể chat với chính bạn
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
