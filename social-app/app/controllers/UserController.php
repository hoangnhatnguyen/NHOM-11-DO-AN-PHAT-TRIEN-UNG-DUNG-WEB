<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Follow.php';

class UserController extends BaseController {
    private User $userModel;
    private Follow $followModel;

    public function __construct() {
        $this->userModel = new User();
        $this->followModel = new Follow();
    }

    public function profile(string $username): void {
        $this->requireAuth();

        $user = $this->userModel->findByUsername($username);
        if (!$user) {
            echo "User not found"; return;
        }

        $stats = $this->userModel->getStats($user['id']);

        require_once __DIR__ . '/../models/UserBadge.php';
        $badges = (new UserBadge())->getByUser($user['id']);

        $this->render('user/profile', [
            'user'=>$user,
            'stats'=>$stats,
            'badges'=>$badges,
            'isOwner'=>$_SESSION['user']['id'] == $user['id'],
             'currentUser'=>$_SESSION['user']
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
            echo json_encode(['error'=>'No file']);
            return;
        }

        $file = $_FILES['avatar'];

        $filename = time() . '_' . basename($file['name']);
        $path = '/public/uploads/' . $filename;

        move_uploaded_file($file['tmp_name'], APP_ROOT . $path);

        $this->userModel->updateAvatar($_SESSION['user']['id'], $path);

        $_SESSION['user']['avatar_url'] = $path;

        echo json_encode(['url'=>$path]);
    }

    public function apiPosts(): void {
        header('Content-Type: application/json');

        $userId = (int)($_GET['user_id'] ?? 0);

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT p.*, pm.media_url
            FROM posts p
            LEFT JOIN post_media pm ON p.id = pm.post_id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
        ");

        $stmt->execute([$userId]);

        echo json_encode([
            'posts' => $stmt->fetchAll(PDO::FETCH_ASSOC)
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
