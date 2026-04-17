<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

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
	require_once __DIR__ . '/../app/helpers/notification_helper.php';
	require_once __DIR__ . '/../app/models/Post.php';
	require_once __DIR__ . '/../app/models/PostMedia.php';

	$postId = (int) ($_GET['id'] ?? 0);
	$viewerId = (int) ($_SESSION['user']['id'] ?? 0);

	if ($postId <= 0) {
		http_response_code(400);
		echo json_encode(['success' => false, 'msg' => 'Invalid post ID']);
		exit;
	}

	$postModel = new Post();
	$post = $postModel->findDetailWithStats($postId, $viewerId);

	if ($post === null) {
		http_response_code(404);
		echo json_encode(['success' => false, 'msg' => 'Post not found']);
		exit;
	}

	$post['media'] = (new PostMedia())->getByPost($postId);

	$currentUser = $_SESSION['user'] ?? null;
	$csrfToken = $_SESSION['_csrf_token'] ?? '';

	ob_start();
	include __DIR__ . '/../app/views/partials/post_card.php';
	$html = ob_get_clean();

	echo json_encode(['success' => true, 'html' => $html], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	http_response_code(500);
	error_log('post-card API: ' . $e->getMessage());
	echo json_encode(['success' => false, 'msg' => 'Server error']);
}
