<?php
require_once APP_PATH . 'models/Block.php';
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

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("UPDATE users SET privacy_follow=:f, privacy_comment=:c WHERE id=:id");
        $stmt->execute([
            'f'=>$_POST['privacy_follow'],
            'c'=>$_POST['privacy_comment'],
            'id'=>$_SESSION['user']['id']
        ]);

        echo json_encode(['success'=>true]);
    }

    public function unblock(): void {
        $this->requireAuth();

        (new Block())->unblock(
            $_SESSION['user']['id'],
            (int)$_POST['id']
        );

        echo json_encode(['success'=>true]);
    }
}