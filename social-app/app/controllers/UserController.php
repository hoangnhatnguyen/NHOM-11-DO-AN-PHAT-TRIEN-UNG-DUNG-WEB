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

	// // ===== PROFILE =====
	// public function profile(string $username): void {
	// 	$this->requireAuth();

	// 	$user = $this->userModel->findByUsername($username);
	// 	if (!$user) {
	// 		echo "User not found"; return;
	// 	}

	// 	$stats = $this->userModel->getStats($user['id']);

	// 	$this->render('user/profile', [
	// 		'user'=>$user,
	// 		'stats'=>$stats,
	// 		'isOwner'=>$_SESSION['user']['id'] == $user['id']
	// 	]);
	// }
	public function profile(string $username): void {
    $this->requireAuth();

    $user = (new User())->findByUsername($username);
    if (!$user) {
        echo "User not found"; return;
    }

    $stats = (new User())->getStats($user['id']);

    require_once __DIR__ . '/../models/UserBadge.php';
    $badges = (new UserBadge())->getByUser($user['id']);

    $this->render('user/profile', [
        'user'=>$user,
        'stats'=>$stats,
        'badges'=>$badges,
        'isOwner'=>$_SESSION['user']['id'] == $user['id']
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

    
    (new User())->updateAvatar($_SESSION['user']['id'], $path);

 
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

	// ===== FOLLOWERS =====
	public function apiFollowers(): void {
		header('Content-Type: application/json');

		$userId = (int)($_GET['user_id'] ?? 0);

		$data = $this->followModel->getFollowers($userId);

		echo json_encode(['followers'=>$data]);
	}

	// ===== FOLLOWING =====
	public function apiFollowing(): void {
		header('Content-Type: application/json');

		$userId = (int)($_GET['user_id'] ?? 0);

		$data = $this->followModel->getFollowing($userId);

		echo json_encode(['following'=>$data]);
	}

	// ===== REMOVE FOLLOWER =====
	public function removeFollower(): void {
		$this->requireAuth();

		$targetId = (int)$_POST['target_id'];

		$this->followModel->removeFollower($_SESSION['user']['id'], $targetId);

		echo json_encode(['success'=>true]);
	}

	// ===== UNFOLLOW =====
	public function unfollow(): void {
		$this->requireAuth();

		$targetId = (int)$_POST['target_id'];

		$this->followModel->unfollow($_SESSION['user']['id'], $targetId);

		echo json_encode(['success'=>true]);
	}

	// ===== ACTIVITY (REAL DATA) =====
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

	public function removeBadge(): void {
    $this->requireAuth();

    $badgeId = (int)($_POST['badge_id'] ?? 0);
    $userId = $_SESSION['user']['id'];

    require_once __DIR__ . '/../models/UserBadge.php';
	$badges = (new UserBadge())->getByUser($user['id']);

    $model = new UserBadge();
    $model->remove($userId, $badgeId);

    echo json_encode(['success' => true]);
}
}