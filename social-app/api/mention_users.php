<?php
/**
 * Gợi ý user khi @mention — cùng bootstrap session với index.php / post-detail.php.
 * Dùng file trong /api/ để luôn truy cập được (không phụ thuộc rewrite tới user-api).
 */
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['id'])) {
	http_response_code(401);
	echo json_encode(['items' => [], 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
	exit;
}

try {
	require_once __DIR__ . '/../config/constants.php';
	require_once __DIR__ . '/../config/database.php';
	require_once __DIR__ . '/../app/core/Database.php';
	require_once __DIR__ . '/../app/core/BaseModel.php';
	require_once __DIR__ . '/../app/models/User.php';
	require_once __DIR__ . '/../app/helpers/media.php';

	$q = trim((string) ($_GET['q'] ?? ''));
	$limit = (int) ($_GET['limit'] ?? 15);
	if ($limit <= 0) {
		$limit = 15;
	}
	if ($limit > 50) {
		$limit = 50;
	}

	$viewerId = (int) ($_SESSION['user']['id'] ?? 0);
	if ($viewerId <= 0) {
		echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$userModel = new User();
	$rows = $userModel->searchForMention($viewerId, $q, $limit);

	$items = [];
	foreach ($rows as $row) {
		$uname = (string) ($row['username'] ?? '');
		$rawAvatar = (string) ($row['avatar_url'] ?? '');
		$items[] = [
			'id' => (int) ($row['id'] ?? 0),
			'username' => $uname,
			'displayName' => $uname,
			'avatarSrc' => $rawAvatar !== '' ? media_public_src($rawAvatar) : '',
		];
	}

	echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['items' => [], 'error' => 'mention_search_failed'], JSON_UNESCAPED_UNICODE);
}
