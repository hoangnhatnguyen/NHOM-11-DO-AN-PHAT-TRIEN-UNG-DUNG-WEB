<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Follow.php';
require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../services/S3Service.php';
require_once __DIR__ . '/../helpers/notification_helper.php';

class UserController extends BaseController {
    private User $userModel;
    private Follow $followModel;

    public function __construct() {
        $this->userModel = new User();
        $this->followModel = new Follow();
    }

    public function profileFromQuery(): void
    {
        $u = isset($_GET['u']) ? trim((string) $_GET['u']) : '';
        if ($u === '') {
            http_response_code(404);
            echo 'User not found';
            return;
        }
        $this->profile($u);
    }

    public function profile(string $username): void {
        $this->requireAuth();

        $username = trim(rawurldecode($username));

        $user = $this->userModel->findByUsername($username);
        if (!$user) {
            echo "User not found"; return;
        }

        $stats = $this->userModel->getStats($user['id']);

        require_once __DIR__ . '/../models/UserBadge.php';
        $badges = (new UserBadge())->getByUser($user['id']);

        $viewerId = (int) ($_SESSION['user']['id'] ?? 0);
        $targetId = (int) ($user['id'] ?? 0);
        $isOwner = $viewerId === $targetId;
        $isFollowing = !$isOwner && $viewerId > 0 && $targetId > 0
            ? $this->followModel->isFollowing($viewerId, $targetId)
            : false;

        $profilePosts = (new Post())->getPostsByUserForProfile($targetId, $viewerId);

        $this->render('user/profile', [
            'user'=>$user,
            'stats'=>$stats,
            'badges'=>$badges,
            'isOwner'=> $isOwner,
            'isFollowing'=>$isFollowing,
            'profilePosts' => $profilePosts,
            'currentUser'=>$_SESSION['user'],
            'csrfToken' => $this->csrfToken(),
            'activeMenu' => $isOwner ? 'profile' : 'browse',
        ]);
    }

    public function updateProfile(): void {
        $this->requireAuth();

        $this->userModel->updateProfile(
            $_SESSION['user']['id'],
            $_POST['bio'] ?? ''
        );

        echo json_encode(['success'=>true]);
    }

    public function uploadAvatar(): void {
        $this->requireAuth();

        if (!isset($_FILES['avatar'])) {
            echo json_encode(['error'=>'No file'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $file = $_FILES['avatar'];
        
        // Validate upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'Upload error: ' . $file['error']], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Validate file type (only images) — ưu tiên finfo vì client có thể gửi sai MIME
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $detected = '';
        if (is_file($file['tmp_name']) && function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $detected = (string) finfo_file($fi, $file['tmp_name']);
                finfo_close($fi);
            }
        }
        $mime = $detected !== '' ? $detected : (string) ($file['type'] ?? '');
        if (!in_array($mime, $allowedTypes, true)) {
            echo json_encode(['error' => 'Chỉ cho phép ảnh JPEG, PNG, GIF hoặc WebP'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['error' => 'File size too large (max 5MB)'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $userId = $_SESSION['user']['id'];
        $previous = $this->userModel->findById((int) $userId);
        $oldAvatar = (string) ($previous['avatar_url'] ?? '');

        try {
            $s3Service = new S3Service();
            if (!$s3Service->isReady()) {
                echo json_encode([
                    'error' => 'S3 chưa sẵn sàng: ' . $s3Service->getNotReadyReason(),
                    'hint' => 'Trong thư mục social-app chạy composer install (cần guzzlehttp/guzzle kèm aws-sdk-php). Lỗi SSL Windows: cấu hình openssl.cafile trong php.ini.',
                ], JSON_UNESCAPED_UNICODE);

                return;
            }

            $filename = $file['name'];
            $s3Key = $s3Service->generateAvatarKey($userId, $filename);
            $s3Url = $s3Service->uploadFile($file['tmp_name'], $s3Key);

            if ($s3Url) {
                if ($oldAvatar !== '' && $oldAvatar !== $s3Key) {
                    $s3Service->deleteFile($oldAvatar);
                }
                $this->userModel->updateAvatar($userId, $s3Key);
                $_SESSION['user']['avatar_url'] = $s3Key;
                $displayUrl = $s3Service->getPresignedUrl($s3Key, 86400) ?: $s3Url;
                echo json_encode(['url' => $displayUrl, 'success' => true], JSON_UNESCAPED_UNICODE);
            } else {
                $detail = $s3Service->getLastError();
                echo json_encode([
                    'error' => 'Upload S3 thất bại' . ($detail !== '' ? ': ' . $detail : ''),
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiPosts(): void {
        header('Content-Type: application/json');

        $userId = (int) ($_GET['user_id'] ?? 0);
        $viewerId = (int) ($_SESSION['user']['id'] ?? 0);

        $posts = (new Post())->getPostsByUserForProfile($userId, $viewerId);

        echo json_encode([
            'posts' => $posts,
        ]);
    }

    public function apiFollowers(): void {
        header('Content-Type: application/json');

        $userId = (int)($_GET['user_id'] ?? 0);

        $data = $this->followModel->getFollowers($userId);

        echo json_encode(['followers'=>$data]);
    }

    public function apiFollowing(): void {
        header('Content-Type: application/json');

        $userId = (int)($_GET['user_id'] ?? 0);

        $data = $this->followModel->getFollowing($userId);

        echo json_encode(['following'=>$data]);
    }

    /**
     * GET  ?action=following&limit=20 — danh sách đang follow của user đăng nhập (tin nhắn, v.v.).
     * POST ?action=follow|unfollow — body: target_id (widgets, finder, gợi ý).
     */
    public function apiFollow(): void {
        header('Content-Type: application/json; charset=utf-8');

        $action = (string) ($_GET['action'] ?? '');

        if ($action === 'following') {
            if (empty($_SESSION['user'])) {
                echo json_encode(['following' => []], JSON_UNESCAPED_UNICODE);
                return;
            }
            $uid = (int) ($_SESSION['user']['id'] ?? 0);
            $limit = (int) ($_GET['limit'] ?? 20);
            $limit = max(1, min(100, $limit));
            $data = $this->followModel->getFollowing($uid, $limit, 0);
            echo json_encode(['following' => $data], JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->requireAuth();
        $uid = (int) ($_SESSION['user']['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $targetId = (int) ($_POST['target_id'] ?? 0);
        if ($targetId <= 0 || $targetId === $uid) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_target'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($action === 'follow') {
            if ($this->followModel->isFollowing($uid, $targetId)) {
                echo json_encode(['success' => true, 'already' => true], JSON_UNESCAPED_UNICODE);
                return;
            }
            $ok = $this->followModel->follow($uid, $targetId);
            if (!$ok) {
                http_response_code(400);
                echo json_encode(['error' => 'follow_failed'], JSON_UNESCAPED_UNICODE);
                return;
            }
            create_notification(notification_db(), $targetId, $uid, 'follow', $uid, null);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($action === 'unfollow') {
            $this->followModel->unfollow($uid, $targetId);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            return;
        }

        http_response_code(400);
        echo json_encode(['error' => 'invalid_action'], JSON_UNESCAPED_UNICODE);
    }

    public function removeFollower(): void {
        $this->requireAuth();

        $targetId = (int)$_POST['target_id'];

        $this->followModel->removeFollower($_SESSION['user']['id'], $targetId);

        echo json_encode(['success'=>true]);
    }

    public function unfollow(): void {
        $this->requireAuth();

        $targetId = (int)$_POST['target_id'];

        $this->followModel->unfollow($_SESSION['user']['id'], $targetId);

        echo json_encode(['success'=>true]);
    }

    public function apiActivity(): void {
        header('Content-Type: application/json');

        $userId = (int)($_GET['user_id'] ?? 0);

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT 'like' as type, p.content, l.created_at
            FROM likes l
            JOIN posts p ON l.post_id = p.id
            WHERE l.user_id = ?

            UNION ALL

            SELECT 'comment' as type, p.content, c.created_at
            FROM comments c
            JOIN posts p ON c.post_id = p.id
            WHERE c.user_id = ?

            ORDER BY created_at DESC
            LIMIT 20
        ");

        $stmt->execute([$userId, $userId]);

        echo json_encode([
            'activities' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    // ===== SEARCH BADGE =====
    public function searchBadge(): void {
        header('Content-Type: application/json');

        $q = $_GET['q'] ?? '';

        require_once __DIR__ . '/../models/Badge.php';
        $badgeModel = new Badge();

        echo json_encode([
            'data' => $badgeModel->search($q)
        ]);
    }

public function addBadge(): void {
    header('Content-Type: application/json');

    $this->requireAuth();

    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        echo json_encode(['error'=>'empty']);
        return;
    }

    require_once __DIR__ . '/../models/Badge.php';
    require_once __DIR__ . '/../models/UserBadge.php';

    $badgeModel = new Badge();
    $userBadgeModel = new UserBadge();

    $list = $badgeModel->search($name);

    if (count($list) > 0) {
        $badgeId = $list[0]['id'];
    } else {
        $badgeId = $badgeModel->create($name);
    }

    // tránh duplicate
    if (!$userBadgeModel->exists($_SESSION['user']['id'], $badgeId)) {
        $userBadgeModel->add($_SESSION['user']['id'], $badgeId);
    }

    echo json_encode(['success'=>true]);
}

    // ===== REMOVE BADGE =====
    public function removeBadge(): void {
        $this->requireAuth();

        $badgeId = (int)($_POST['badge_id'] ?? 0);
        $userId = $_SESSION['user']['id'];

        require_once __DIR__ . '/../models/UserBadge.php';

        $model = new UserBadge();
        $model->remove($userId, $badgeId);

        echo json_encode(['success' => true]);
    }

}
