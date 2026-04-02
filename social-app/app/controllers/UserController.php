<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Follow.php';
require_once __DIR__ . '/../helpers/notification_helper.php';

class UserController extends BaseController {
	private User $userModel;
	private Follow $followModel;

	public function __construct() {
		$this->userModel = new User();
		$this->followModel = new Follow();
	}

	public function finder(): void {
		$this->requireAuth();

		$id = (int) ($_GET['id'] ?? 0);
		$targetUser = null;
		$error = null;

		if ($id > 0) {
			try {
				$targetUser = $this->userModel->findById($id);
			} catch (Throwable $e) {
				Logger::error('User finder error', [
					'message' => $e->getMessage(),
					'id' => $id,
				]);

				$error = 'Không thể tải thông tin user lúc này.';
			}

			if ($targetUser === null && $error === null) {
				$error = 'Không tìm thấy user với ID đã nhập.';
			}
		}

		$this->render('user/finder', [
			'title' => 'Test User Profile',
			'currentUser' => $_SESSION['user'] ?? null,
			'targetId' => $id > 0 ? $id : '',
			'targetUser' => $targetUser,
			'error' => $error,
		]);
	}

	public function apiFollow(): void {
		header('Content-Type: application/json');

		$currentUserId = $_SESSION['user']['id'] ?? null;
		if (!$currentUserId) {
			http_response_code(401);
			echo json_encode(['error' => 'Unauthorized']);
			return;
		}

		$action = $_GET['action'] ?? '';

		try {
			switch ($action) {
				case 'follow':
					$targetId = (int) ($_POST['target_id'] ?? 0);
					
					if ($targetId <= 0 || $targetId === $currentUserId) {
						http_response_code(400);
						echo json_encode(['error' => 'Invalid target user']);
						return;
					}

					if ($this->followModel->follow($currentUserId, $targetId)) {
						create_notification(notification_db(), $targetId, (int) $currentUserId, 'follow', (int) $currentUserId, null);
						echo json_encode(['success' => true, 'message' => 'Following user']);
					} else {
						http_response_code(400);
						echo json_encode(['error' => 'Already following or error occurred']);
					}
					break;

				case 'unfollow':
					$targetId = (int) ($_POST['target_id'] ?? 0);
					
					if ($targetId <= 0) {
						http_response_code(400);
						echo json_encode(['error' => 'Invalid target user']);
						return;
					}

					if ($this->followModel->unfollow($currentUserId, $targetId)) {
						echo json_encode(['success' => true, 'message' => 'Unfollowed user']);
					} else {
						http_response_code(400);
						echo json_encode(['error' => 'Not following this user']);
					}
					break;

				case 'check':
					$targetId = (int) ($_GET['target_id'] ?? 0);
					
					if ($targetId <= 0) {
						http_response_code(400);
						echo json_encode(['error' => 'Invalid target user']);
						return;
					}

					$isFollowing = $this->followModel->isFollowing($currentUserId, $targetId);
					echo json_encode([
						'success' => true,
						'isFollowing' => $isFollowing
					]);
					break;

				case 'followers':
				$userId = (int) ($_GET['user_id'] ?? $currentUserId);
				$limit = min((int) ($_GET['limit'] ?? 10), 100);
				$offset = (int) ($_GET['offset'] ?? 0);

				$followers = $this->followModel->getFollowers($userId, $limit, $offset);
				$count = $this->followModel->countFollowers($userId);

				echo json_encode([
					'success' => true,
					'followers' => $followers,
					'total' => $count
				]);
				break;

			case 'following':
				$userId = (int) ($_GET['user_id'] ?? $currentUserId);
				$limit = min((int) ($_GET['limit'] ?? 10), 100);
				$offset = (int) ($_GET['offset'] ?? 0);
					$following = $this->followModel->getFollowing($userId, $limit, $offset);
					$count = $this->followModel->countFollowing($userId);

					echo json_encode([
						'success' => true,
						'following' => $following,
						'total' => $count
					]);
					break;

				default:
					http_response_code(400);
					echo json_encode(['error' => 'Invalid action']);
					break;
			}
		} catch (Exception $e) {
			http_response_code(500);
			echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
		}
	}
}

