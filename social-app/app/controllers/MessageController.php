<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/FirebaseService.php';

class MessageController extends BaseController {
	private const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
	private const ALLOWED_EXTENSIONS = [
		'jpg', 'jpeg', 'png', 'gif', 'webp',
		'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
		'txt', 'zip', 'rar', '7z', 'mp3', 'mp4',
	];

	private User $userModel;
	private FirebaseService $firebaseService;

	public function __construct() {
		$this->userModel = new User();
		$this->firebaseService = new FirebaseService();
	}

	public function index(): void {
		$this->requireAuth();

		$this->render('message/inbox', [
			'title' => 'Tin nhắn',
			'currentUser' => $_SESSION['user'] ?? null,
			'csrfToken' => $this->csrfToken(),
			'activeMenu' => 'messages',
			'pageScripts' => [
				[
					'src' => BASE_URL . '/public/js/message.js',
					'module' => true,
				],
			],
		]);
	}

	public function apiBootstrap(): void {
		$this->requireAuth();

		if (!$this->firebaseService->isConfigured()) {
			$this->json([
				'error' => 'Firebase config is incomplete. Please update .env.',
			], 500);
			return;
		}

		try {
			$sessionUser = $_SESSION['user'] ?? [];
			$userId = (int) ($sessionUser['id'] ?? 0);
			$role = (string) ($sessionUser['role'] ?? 'user');
			$token = $this->firebaseService->issueChatToken($userId, $role);
		} catch (Throwable $e) {
			Logger::error('Chat bootstrap error', [
				'message' => $e->getMessage(),
			]);

			$this->json([
				'error' => 'Cannot initialize chat bootstrap.',
			], 500);
			return;
		}

		$this->json([
			'firebase' => $this->firebaseService->getWebConfig(),
			'customToken' => $token,
			'me' => $this->formatSessionUser(),
		]);
	}

	public function apiUsers(): void {
		$this->requireAuth();

		$q = trim((string) ($_GET['q'] ?? ''));
		$limit = (int) ($_GET['limit'] ?? 20);
		if ($limit <= 0) {
			$limit = 20;
		}
		if ($limit > 50) {
			$limit = 50;
		}

		try {
			$sessionUser = $_SESSION['user'] ?? [];
			$items = $this->userModel->searchForChat((int) ($sessionUser['id'] ?? 0), $q, $limit);
		} catch (Throwable $e) {
			Logger::error('Chat users search error', [
				'message' => $e->getMessage(),
				'q' => $q,
			]);

			$this->json([
				'error' => 'Không thể tải danh sách người dùng.',
			], 500);
			return;
		}

		$this->json([
			'items' => array_map(fn(array $user): array => $this->formatUser($user), $items),
		]);
	}

	public function apiUser(int $userId): void {
		$this->requireAuth();

		try {
			$user = $this->userModel->findById($userId);
		} catch (Throwable $e) {
			Logger::error('Chat user detail error', [
				'message' => $e->getMessage(),
				'userId' => $userId,
			]);

			$this->json([
				'error' => 'Không thể tải người dùng.',
			], 500);
			return;
		}

		if ($user === null) {
			$this->json([
				'error' => 'User not found.',
			], 404);
			return;
		}

		$this->json([
			'item' => $this->formatUser($user),
		]);
	}

	public function apiUpload(): void {
		$this->requireAuth();

		$csrf = (string) ($_POST['_csrf'] ?? '');
		if (!$this->verifyCsrf($csrf)) {
			$this->json([
				'error' => 'CSRF token invalid.',
			], 419);
			return;
		}

		if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
			$this->json([
				'error' => 'Không có tệp tải lên.',
			], 422);
			return;
		}

		$file = $_FILES['file'];
		$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
		if ($error !== UPLOAD_ERR_OK) {
			$this->json([
				'error' => 'Tải tệp lên thất bại.',
			], 422);
			return;
		}

		$tmpName = (string) ($file['tmp_name'] ?? '');
		if ($tmpName === '' || !is_uploaded_file($tmpName)) {
			$this->json([
				'error' => 'Tệp không hợp lệ.',
			], 422);
			return;
		}

		$originalName = (string) ($file['name'] ?? 'file');
		$size = (int) ($file['size'] ?? 0);
		if ($size <= 0 || $size > self::MAX_UPLOAD_BYTES) {
			$this->json([
				'error' => 'Dung lượng tệp vượt quá giới hạn 10MB.',
			], 422);
			return;
		}

		$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		if ($ext === '' || !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
			$this->json([
				'error' => 'Định dạng tệp chưa được hỗ trợ.',
			], 422);
			return;
		}

		$userId = (int) (($_SESSION['user'] ?? [])['id'] ?? 0);
		$targetDir = APP_ROOT . 'public/uploads/chat/' . $userId . '/';
		if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
			$this->json([
				'error' => 'Không thể tạo thư mục tải tệp.',
			], 500);
			return;
		}

		$safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
		$safeBase = trim((string) $safeBase, '_');
		if ($safeBase === '') {
			$safeBase = 'file';
		}

		$unique = bin2hex(random_bytes(8));
		$fileName = date('Ymd_His') . '_' . $unique . '_' . $safeBase . '.' . $ext;
		$targetPath = $targetDir . $fileName;

		if (!move_uploaded_file($tmpName, $targetPath)) {
			$this->json([
				'error' => 'Không thể lưu tệp tải lên.',
			], 500);
			return;
		}

		$publicUrl = BASE_URL . '/public/uploads/chat/' . $userId . '/' . rawurlencode($fileName);

		$this->json([
			'item' => [
				'fileName' => $originalName,
				'size' => $size,
				'contentType' => (string) ($file['type'] ?? ''),
				'url' => $publicUrl,
				'storagePath' => 'local://chat/' . $userId . '/' . $fileName,
			],
		]);
	}

	private function formatSessionUser(): array {
		$sessionUser = $_SESSION['user'] ?? [];

		return [
			'id' => (int) ($sessionUser['id'] ?? 0),
			'username' => (string) ($sessionUser['username'] ?? ''),
			'email' => (string) ($sessionUser['email'] ?? ''),
			'role' => (string) ($sessionUser['role'] ?? 'user'),
			'firebaseUid' => 'app_' . (int) ($sessionUser['id'] ?? 0),
		];
	}

	private function formatUser(array $user): array {
		$name = (string) ($user['username'] ?? 'User');

		return [
			'id' => (int) ($user['id'] ?? 0),
			'username' => $name,
			'email' => (string) ($user['email'] ?? ''),
			'avatarUrl' => (string) ($user['avatar_url'] ?? ''),
			'initials' => strtoupper(substr($name, 0, 1)),
		];
	}

	private function json(array $payload, int $status = 200): void {
		http_response_code($status);
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
}

