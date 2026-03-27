<?php

require_once __DIR__ . '/../models/User.php';

class UserController extends BaseController {
	private User $userModel;

	public function __construct() {
		$this->userModel = new User();
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
}

