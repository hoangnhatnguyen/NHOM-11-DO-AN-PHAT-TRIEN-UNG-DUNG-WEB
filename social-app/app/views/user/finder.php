<?php
require_once __DIR__ . '/../../models/Follow.php';

$targetId = $targetId ?? '';
$targetUser = $targetUser ?? null;
$error = $error ?? null;
$currentUser = $currentUser ?? [];
$currentUserId = (int) ($currentUser['id'] ?? 0);
$targetUserId = 0;

// Check follow status if viewing another user
$isFollowing = false;
$canFollow = false;
$followModel = new Follow();
if ($targetUser) {
    $targetUserId = (int) ($targetUser['id'] ?? 0);
    if ($targetUserId !== $currentUserId) {
        $isFollowing = $followModel->isFollowing($currentUserId, $targetUserId);
        $canFollow = true; // Can follow this user
    }
}
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
                        <a href="<?= htmlspecialchars(profile_url($targetName), ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none">
                        <div class="avatar-lg" style="background: <?= htmlspecialchars($targetColor['bg']) ?>; color: <?= htmlspecialchars($targetColor['fg']) ?>;">
                            <?= htmlspecialchars(Avatar::initials($targetName)) ?>
                        </div>
                        </a>
                    <div>
                        <a href="<?= htmlspecialchars(profile_url($targetName), ENT_QUOTES, 'UTF-8') ?>" class="fw-bold fs-5 mb-0 text-decoration-none text-body d-inline-block"><?= htmlspecialchars($targetName) ?></a>
                        <div class="text-secondary">ID: <?= $targetUserId ?></div>
                        <div class="text-secondary"><?= htmlspecialchars($targetEmail) ?></div>
                        <div class="mt-2">
                            <span class="badge bg-info">
                                <?= $followModel->countFollowers($targetUserId) ?> Followers
                            </span>
                            <span class="badge bg-info">
                                <?= $followModel->countFollowing($targetUserId) ?> Following
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <?php if ($canChat): ?>
                        <?php if (!($targetUserId === $currentUserId)): ?>
                            <?php if ($canFollow): ?>
                                <button
                                    class="btn btn-sm <?= $isFollowing ? 'btn-outline-primary' : 'btn-primary' ?>"
                                    id="followBtn"
                                    data-user-id="<?= $targetUserId ?>"
                                    data-following="<?= $isFollowing ? 'true' : 'false' ?>"
                                >
                                    <?= $isFollowing ? 'Đang theo dõi' : 'Theo dõi' ?>
                                </button>
                            <?php else: ?>
                                <span class="badge bg-danger">Không thể theo dõi user này</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <a href="<?= BASE_URL ?>/messages?user=<?= $targetUserId ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-chat-dots me-1"></i> Chat
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary btn-sm" disabled>
                            Không thể chat với chính bạn
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const followBtn = document.getElementById('followBtn');
    if (!followBtn) return;

    followBtn.addEventListener('click', async function () {
        const userId = parseInt(this.dataset.userId);
        const isFollowing = this.dataset.following === 'true';

        try {
            this.disabled = true;
            const action = isFollowing ? 'unfollow' : 'follow';
            const formData = new FormData();
            formData.append('target_id', userId);

            const response = await fetch(
                `<?= BASE_URL ?>/user-api/follow?action=${action}`,
                {
                    method: 'POST',
                    body: formData
                }
            );

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Error');
            }

            // Update button state
            this.dataset.following = isFollowing ? 'false' : 'true';
            this.textContent = isFollowing ? 'Theo dõi' : 'Đang theo dõi';
            this.classList.toggle('btn-primary');
            this.classList.toggle('btn-outline-primary');

            // Show toast notification
            const message = isFollowing ? 'Đã hủy theo dõi' : 'Đã theo dõi user';
            showToast(message, 'success');
        } catch (error) {
            console.error('Follow error:', error);
            showToast(error.message || 'Lỗi khi thực hiện hành động', 'danger');
        } finally {
            this.disabled = false;
        }
    });

    function showToast(message, type = 'info') {
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.innerHTML = toastHtml;
        document.body.appendChild(toastContainer);

        const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
        toast.show();

        setTimeout(() => toastContainer.remove(), 3000);
    }
});
</script>