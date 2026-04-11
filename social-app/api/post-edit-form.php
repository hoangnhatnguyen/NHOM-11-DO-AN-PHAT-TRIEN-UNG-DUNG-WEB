<?php
/**
 * Phải dùng cùng cấu hình phiên với index.php (SESSION_NAME, cookie…),
 * không gọi session_start() trước khi load config/session.php.
 */
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['id'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'msg' => 'Unauthorized']);
	exit;
}

try {
	require_once __DIR__ . '/../config/constants.php';
	require_once __DIR__ . '/../config/database.php';
	require_once __DIR__ . '/../app/core/Database.php';
	require_once __DIR__ . '/../app/core/BaseModel.php';
	require_once __DIR__ . '/../app/core/Avatar.php';
	require_once __DIR__ . '/../app/helpers/media.php';
	require_once __DIR__ . '/../app/helpers/hashtag_helper.php';
	require_once __DIR__ . '/../app/models/Post.php';
	require_once __DIR__ . '/../app/models/PostMedia.php';
	require_once __DIR__ . '/../app/models/PostHashtag.php';

	$postId = (int) ($_GET['id'] ?? 0);
	$viewerId = (int) ($_SESSION['user']['id'] ?? 0);

	if ($postId <= 0 || $viewerId <= 0) {
		http_response_code(400);
		echo json_encode(['success' => false, 'msg' => 'Invalid post ID']);
		exit;
	}

	$post = (new Post())->findById($postId);
	if ($post === null) {
		http_response_code(404);
		echo json_encode(['success' => false, 'msg' => 'Post not found']);
		exit;
	}

	if ((int) ($post['user_id'] ?? 0) !== $viewerId) {
		http_response_code(403);
		echo json_encode(['success' => false, 'msg' => 'Forbidden']);
		exit;
	}

	$media = (new PostMedia())->getByPost($postId);
	$hashtags = (new PostHashtag())->getTagNamesByPostId($postId);
	$editContent = compose_post_content_for_editor((string) ($post['content'] ?? ''), $hashtags);

	$csrfToken = $_SESSION['_csrf_token'] ?? '';
	if ($csrfToken === '' && function_exists('random_bytes')) {
		$csrfToken = bin2hex(random_bytes(32));
		$_SESSION['_csrf_token'] = $csrfToken;
	}
	$currentUser = $_SESSION['user'] ?? null;

	// Base URL cho action form: ưu tiên path trong APP_URL (giống trang chủ), tránh lệch khi chạy từ /api/*.php
	$formBaseUrl = rtrim((string) BASE_URL, '/');
	$appUrl = (string) env('APP_URL', '');
	if ($appUrl !== '') {
		$pathFromEnv = parse_url($appUrl, PHP_URL_PATH);
		if ($pathFromEnv !== null && $pathFromEnv !== false) {
			$pathFromEnv = rtrim((string) $pathFromEnv, '/');
			if ($pathFromEnv !== '') {
				$formBaseUrl = $pathFromEnv;
			}
		}
	}

	ob_start();
	include __DIR__ . '/../app/views/partials/post_edit_form.php';
	$html = ob_get_clean();

	echo json_encode([
		'success' => true,
		'html' => $html,
	]);
} catch (Throwable $e) {
	http_response_code(500);
	error_log('post-edit-form API: ' . $e->getMessage());
	echo json_encode(['success' => false, 'msg' => 'Server error']);
}
