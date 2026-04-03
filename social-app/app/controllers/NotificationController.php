<?php

require_once __DIR__ . '/../core/BaseController.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../helpers/media.php';
require_once __DIR__ . '/../helpers/notification_helper.php';

class NotificationController extends BaseController
{
    /**
     * POST id — đánh dấu 1 thông báo đã đọc (JSON). Đi qua front controller, tránh lỗi đường dẫn tới api/*.php.
     */
    public function markReadApi(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['user']['id'])) {
            echo json_encode(['ok' => false, 'msg' => 'not login'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $userId = (int) $_SESSION['user']['id'];
        $notificationId = (int) ($_POST['id'] ?? 0);

        if ($notificationId <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'bad id'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $conn = Database::getInstance()->getConnection();
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => 'db'], JSON_UNESCAPED_UNICODE);
            return;
        }

        notification_mark_read($conn, $userId, $notificationId);
        $unread = notifications_unread_count($conn, $userId);

        echo json_encode(['ok' => true, 'unread' => $unread], JSON_UNESCAPED_UNICODE);
    }

    public function index(): void
    {
        $this->requireAuth();

        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $tab = (string) ($_GET['tab'] ?? 'all');
        if ($tab !== 'mention') {
            $tab = 'all';
        }

        $conn = Database::getInstance()->getConnection();
        $rows = notifications_fetch_inbox_rows($conn, $userId, $tab, 80);

        $notifications = [];
        foreach ($rows as $n) {
            $notifications[] = $this->formatNotificationRow($n);
        }

        $this->render('notification/index', [
            'notifications' => $notifications,
            'tab' => $tab,
            'currentUser' => $_SESSION['user'],
            'activeMenu' => 'notifications',
            'csrfToken' => $this->csrfToken(),
        ], 'feed');
    }

    /**
     * @param array<string, mixed> $n
     * @return array<string, mixed>
     */
    private function formatNotificationRow(array $n): array
    {
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
            $snippet = trim(strip_tags((string) ($n['comment_text'] ?? '')));
        } elseif ($type === 'mention_post') {
            $snippet = trim(strip_tags((string) ($n['post_text'] ?? '')));
        }

        $messageHtml = '';
        $likeTotal = (int) ($n['like_total'] ?? 1);

        if ($type === 'like') {
            if ($likeTotal > 1) {
                $messageHtml = '<b>' . $actorEsc . '</b> và ' . ($likeTotal - 1) . ' người khác đã thích bài viết của bạn';
            } else {
                $messageHtml = '<b>' . $actorEsc . '</b> đã thích bài viết của bạn';
            }
        } elseif ($type === 'comment') {
            $msg = format_notification_snippet($snippet);
            $messageHtml = '<b>' . $actorEsc . '</b> đã bình luận về bài viết của bạn: ' . $msg;
        } elseif ($type === 'follow') {
            $messageHtml = '<b>' . $actorEsc . '</b> đang theo dõi bạn';
        } elseif ($type === 'share') {
            $messageHtml = '<b>' . $actorEsc . '</b> vừa chia sẻ một bài viết';
        } elseif ($type === 'mention_post') {
            $msg = format_notification_snippet(mb_substr($snippet, 0, 200));
            $messageHtml = '<b>' . $actorEsc . '</b> đã nhắc đến bạn trong một bài viết: ' . $msg;
        } elseif ($type === 'mention_comment' || $type === 'mention') {
            $msg = format_notification_snippet($snippet);
            $messageHtml = '<b>' . $actorEsc . '</b> đã nhắc đến bạn trong một bình luận: ' . $msg;
        } else {
            $messageHtml = '<b>' . $actorEsc . '</b> đã tương tác với bạn';
        }

        $link = '#';
        if ($type === 'follow') {
            $uname = trim((string) $actor);
            if ($uname !== '') {
                $link = profile_url($uname);
            }
        } elseif ($postId > 0) {
            $frag = '';
            if ($type === 'comment' || $type === 'mention_comment' || $type === 'mention') {
                $cid = (int) ($n['reference_id'] ?? 0);
                if ($cid > 0) {
                    $frag = '#comment-' . $cid;
                }
            }
            $link = BASE_URL . '/post/' . $postId . $frag;
        }

        $avatarRaw = (string) ($n['actor_avatar'] ?? '');
        $avatarUrl = '';
        if ($avatarRaw !== '' && preg_match('#^https?://#i', $avatarRaw)) {
            $avatarUrl = $avatarRaw;
        } elseif ($avatarRaw !== '') {
            $avatarUrl = media_public_src($avatarRaw);
        }

        $actorColor = Avatar::colors($actor);

        return [
            'id' => (int) ($n['id'] ?? 0),
            'type' => $type,
            'actor_name' => $actor,
            'actor_avatar' => $avatarUrl,
            'actor_initial' => Avatar::initials($actor),
            'actor_color_bg' => $actorColor['bg'],
            'actor_color_fg' => $actorColor['fg'],
            'message_html' => $messageHtml,
            'link' => $link,
            'created_at' => (string) ($n['created_at'] ?? ''),
            'is_read' => (int) ($n['is_read'] ?? 0),
            'time_label' => notification_time_ago_vi((string) ($n['created_at'] ?? '')),
        ];
    }
}
