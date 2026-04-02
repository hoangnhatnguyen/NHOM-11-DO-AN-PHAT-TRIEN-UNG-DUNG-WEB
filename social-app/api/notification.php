<?php

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Avatar.php';
require_once __DIR__ . '/../app/helpers/media.php';
require_once __DIR__ . '/../app/helpers/notification_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user']['id'])) {
    echo json_encode(['ok' => false, 'msg' => 'not login']);
    exit;
}

$userId = (int) $_SESSION['user']['id'];
$conn = Database::getInstance()->getConnection();

$stmt = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$userId]);
$unread = (int) $stmt->fetchColumn();

$rows = notifications_fetch_inbox_rows($conn, $userId, 'all', 15);

$list = [];
foreach ($rows as $n) {
    $type = (string) ($n['type'] ?? '');
    $actor = (string) ($n['actor_name'] ?? 'User');
    $actorEsc = htmlspecialchars($actor, ENT_QUOTES, 'UTF-8');
    $postId = (int) ($n['resolved_post_id'] ?? 0);
    if ($postId <= 0) {
        $postId = (int) ($n['post_id'] ?? 0);
    }
    if ($postId <= 0 && in_array($type, ['like', 'share', 'mention_post'], true)) {
        $postId = (int) ($n['reference_id'] ?? 0);
    }

    $snippet = '';
    if ($type === 'comment' || $type === 'mention_comment' || $type === 'mention') {
        $snippet = (string) ($n['comment_text'] ?? '');
    } elseif ($type === 'mention_post') {
        $snippet = (string) ($n['post_text'] ?? '');
    }

    $likeTotal = (int) ($n['like_total'] ?? 1);
    $message = '';

    if ($type === 'like') {
        $message = $likeTotal > 1
            ? $actor . ' và ' . ($likeTotal - 1) . ' người khác đã thích bài viết của bạn'
            : $actor . ' đã thích bài viết của bạn';
    } elseif ($type === 'comment') {
        $message = $actor . ' đã bình luận về bài viết của bạn: ' . $snippet;
    } elseif ($type === 'follow') {
        $message = $actor . ' đang theo dõi bạn';
    } elseif ($type === 'share') {
        $message = $actor . ' vừa chia sẻ một bài viết';
    } elseif ($type === 'mention_post') {
        $message = $actor . ' đã nhắc đến bạn trong một bài viết: ' . mb_substr($snippet, 0, 160);
    } elseif ($type === 'mention_comment' || $type === 'mention') {
        $message = $actor . ' đã nhắc đến bạn trong một bình luận: ' . $snippet;
    } else {
        $message = $actor . ' đã tương tác với bạn';
    }

    $link = '#';
    if ($type === 'follow') {
        $an = trim((string) $actor);
        if ($an !== '') {
            $link = '/profile?u=' . rawurlencode($an);
        }
    } elseif ($postId > 0) {
        $link = '/post/' . $postId;
        if ($type === 'comment' || $type === 'mention_comment' || $type === 'mention') {
            $cid = (int) ($n['reference_id'] ?? 0);
            if ($cid > 0) {
                $link .= '#comment-' . $cid;
            }
        }
    }

    $avatarRaw = (string) ($n['actor_avatar'] ?? '');
    $avatarUrl = '';
    if ($avatarRaw !== '' && preg_match('#^https?://#i', $avatarRaw)) {
        $avatarUrl = $avatarRaw;
    } elseif ($avatarRaw !== '') {
        $avatarUrl = media_public_src($avatarRaw);
    }

    $avColors = Avatar::colors($actor);

    $list[] = [
        'id' => (int) ($n['id'] ?? 0),
        'type' => $type,
        'message' => $message,
        'avatar' => $avatarUrl,
        'avatar_initial' => Avatar::initials($actor),
        'avatar_bg' => $avColors['bg'],
        'avatar_fg' => $avColors['fg'],
        'link' => $link,
        'reference_id' => (int) ($n['reference_id'] ?? 0),
        'created_at' => (string) ($n['created_at'] ?? ''),
    ];
}

echo json_encode([
    'ok' => true,
    'unread' => $unread,
    'notifications' => $list,
]);
