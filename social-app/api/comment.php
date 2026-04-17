<?php

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/helpers/notification_helper.php';

header('Content-Type: application/json; charset=utf-8');

$currentUserId = (int) ($_SESSION['user']['id'] ?? 0);
$postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
$content = trim((string) ($_POST['content'] ?? ''));

if (!$currentUserId || !$postId || $content === '') {
    echo json_encode(['ok' => false, 'status' => 'error']);
    exit;
}

$conn = Database::getInstance()->getConnection();

$stmt = $conn->prepare('
    INSERT INTO comments (post_id, user_id, content, created_at)
    VALUES (?, ?, ?, NOW())
');
$stmt->execute([$postId, $currentUserId, $content]);

$commentId = (int) $conn->lastInsertId();
notify_for_new_comment($conn, $postId, $currentUserId, $commentId, $content);

echo json_encode(['ok' => true, 'status' => 'success', 'comment_id' => $commentId]);
