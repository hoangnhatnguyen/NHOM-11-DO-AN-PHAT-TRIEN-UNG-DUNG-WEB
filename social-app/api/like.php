<?php

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/helpers/notification_helper.php';

header('Content-Type: application/json; charset=utf-8');

function like_json_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

try {
    $conn = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    like_json_response(['ok' => false, 'msg' => 'database_error']);
}

$currentUserId = (int) ($_SESSION['user']['id'] ?? 0);
$postId = $_POST['post_id'] ?? null;

if (!$postId) {
    like_json_response(['ok' => false, 'msg' => 'missing post_id']);
}

if (!$currentUserId) {
    like_json_response(['ok' => false, 'msg' => 'not login']);
}

try {
    $stmt = $conn->prepare(
        'SELECT 1 FROM likes WHERE post_id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->execute([$postId, $currentUserId]);
    $alreadyLiked = $stmt->fetchColumn() !== false;

    if ($alreadyLiked) {
        $stmt = $conn->prepare('DELETE FROM likes WHERE post_id = ? AND user_id = ?');
        $stmt->execute([$postId, $currentUserId]);
        $liked = false;
    } else {
        $stmt = $conn->prepare('INSERT INTO likes (user_id, post_id) VALUES (?, ?)');
        $stmt->execute([$currentUserId, $postId]);
        $liked = true;
        $ownStmt = $conn->prepare('SELECT user_id FROM posts WHERE id = ? LIMIT 1');
        $ownStmt->execute([(int) $postId]);
        $ownerId = (int) $ownStmt->fetchColumn();
        if ($ownerId > 0 && $ownerId !== $currentUserId) {
            create_notification($conn, $ownerId, $currentUserId, 'like', (int) $postId, (int) $postId);
        }
    }

    $stmt = $conn->prepare('SELECT COUNT(*) FROM likes WHERE post_id = ?');
    $stmt->execute([$postId]);
    $likeCount = (int) $stmt->fetchColumn();
} catch (Throwable $e) {
    like_json_response(['ok' => false, 'msg' => 'like_failed']);
}

like_json_response([
    'ok' => true,
    'kind' => 'like',
    'postId' => (int) $postId,
    'is_liked' => $liked,
    'like_count' => $likeCount,
]);
