<?php
require_once APP_PATH . 'models/Block.php';
require_once APP_PATH . 'models/Follow.php';
class SettingController extends BaseController {

    public function index(): void {
    $this->requireAuth();

    require_once APP_PATH . 'models/User.php';

    $userModel = new User();
    $currentUser = $userModel->findById($_SESSION['user']['id']);

    $blocked = (new Block())->getBlocked($_SESSION['user']['id']);

    $this->render('user/settings', [
        'blocked' => $blocked,
        'currentUser' => $currentUser,
        'csrfToken' => $this->csrfToken(),
    ]);
}

    public function updatePrivacy(): void {
        $this->requireAuth();
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'csrf_invalid']);
            return;
        }

        $privacyFollow = (string) ($_POST['privacy_follow'] ?? '');
        $privacyComment = (string) ($_POST['privacy_comment'] ?? '');
        $allowed = ['everyone', 'mutual'];

        if (!in_array($privacyFollow, $allowed, true) || !in_array($privacyComment, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'invalid_privacy_value']);
            return;
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("UPDATE users SET privacy_follow=:f, privacy_comment=:c WHERE id=:id");
        $stmt->execute([
            'f'=>$privacyFollow,
            'c'=>$privacyComment,
            'id'=>$_SESSION['user']['id']
        ]);

        echo json_encode(['success'=>true]);
    }

    public function unblock(): void {
        $this->requireAuth();
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'csrf_invalid']);
            return;
        }

        (new Block())->unblock(
            $_SESSION['user']['id'],
            (int)$_POST['id']
        );

        echo json_encode(['success'=>true]);
    }

    public function block(): void {
        $this->requireAuth();
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'csrf_invalid']);
            return;
        }

        $targetId = (int) ($_POST['id'] ?? $_POST['target_id'] ?? 0);
        $currentUserId = (int) ($_SESSION['user']['id'] ?? 0);

        if ($targetId <= 0 || $targetId === $currentUserId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'invalid_target']);
            return;
        }

        $ok = (new Block())->block($currentUserId, $targetId);
        if ($ok) {
            $followModel = new Follow();
            // Blocking severs follow relationships in both directions.
            $followModel->unfollow($currentUserId, $targetId);
            $followModel->unfollow($targetId, $currentUserId);
        }

        echo json_encode(['success' => $ok]);
    }
}
