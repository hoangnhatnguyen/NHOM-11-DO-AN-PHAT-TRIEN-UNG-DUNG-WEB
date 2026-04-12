<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../services/NotificationService.php';

function notification_db(): PDO
{
    return Database::getInstance()->getConnection();
}

function notifications_unread_count(PDO $conn, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $stmt = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function notification_mark_read(PDO $conn, int $userId, int $notificationId): void
{
    if ($userId <= 0 || $notificationId <= 0) {
        return;
    }
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->execute([$notificationId, $userId]);
}

/**
 * Usernames dài trước để khớp @mention có dấu cách (vd. @Cam Chillj) đúng user.
 *
 * @return list<string>
 */
function mention_usernames_longest_first(PDO $conn): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $stmt = $conn->query('SELECT username FROM users ORDER BY CHAR_LENGTH(username) DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $cache = array_values(array_filter(array_map('strval', is_array($rows) ? $rows : [])));
    } catch (Throwable $e) {
        $cache = [];
    }
    return $cache;
}

function mention_after_char_ends_mention(string $after): bool
{
    if ($after === '') {
        return true;
    }
    return (bool) preg_match('/^[\s.,;:!?)\]}]/u', $after);
}

/**
 * @return list<string>
 */
function parse_mentioned_usernames_from_text(PDO $conn, string $text): array
{
    $names = mention_usernames_longest_first($conn);
    if ($names === [] || $text === '') {
        return [];
    }
    $found = [];
    $len = mb_strlen($text, 'UTF-8');
    $i = 0;
    while ($i < $len) {
        $at = mb_strpos($text, '@', $i, 'UTF-8');
        if ($at === false) {
            break;
        }
        $rest = mb_substr($text, $at + 1, null, 'UTF-8');
        $best = '';
        foreach ($names as $uname) {
            if ($uname === '') {
                continue;
            }
            $ulen = mb_strlen($uname, 'UTF-8');
            if (mb_substr($rest, 0, $ulen, 'UTF-8') !== $uname) {
                continue;
            }
            $after = $ulen === mb_strlen($rest, 'UTF-8') ? '' : mb_substr($rest, $ulen, 1, 'UTF-8');
            if (!mention_after_char_ends_mention($after)) {
                continue;
            }
            $best = $uname;
            break;
        }
        if ($best !== '') {
            $found[] = $best;
            $i = $at + 1 + mb_strlen($best, 'UTF-8');
        } else {
            $i = $at + 1;
        }
    }

    return array_values(array_unique($found));
}

function format_notification_snippet(?string $raw): string
{
    if ($raw === null || $raw === '') {
        return '';
    }
    $base = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
    $names = mention_usernames_longest_first(notification_db());
    if ($names === []) {
        return htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
    }

    $out = '';
    $len = mb_strlen($raw, 'UTF-8');
    $i = 0;
    while ($i < $len) {
        $at = mb_strpos($raw, '@', $i, 'UTF-8');
        if ($at === false) {
            $out .= htmlspecialchars(mb_substr($raw, $i, null, 'UTF-8'), ENT_QUOTES, 'UTF-8');
            break;
        }
        $out .= htmlspecialchars(mb_substr($raw, $i, $at - $i, 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $rest = mb_substr($raw, $at + 1, null, 'UTF-8');
        $best = '';
        foreach ($names as $uname) {
            if ($uname === '') {
                continue;
            }
            $ulen = mb_strlen($uname, 'UTF-8');
            if (mb_substr($rest, 0, $ulen, 'UTF-8') !== $uname) {
                continue;
            }
            $after = $ulen === mb_strlen($rest, 'UTF-8') ? '' : mb_substr($rest, $ulen, 1, 'UTF-8');
            if (!mention_after_char_ends_mention($after)) {
                continue;
            }
            $best = $uname;
            break;
        }
        if ($best !== '') {
            $profilePath = rtrim($base, '/') . '/profile?u=' . rawurlencode($best);
            $href = htmlspecialchars($profilePath, ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($best, ENT_QUOTES, 'UTF-8');
            $out .= '<a class="text-primary fw-semibold text-decoration-none mention-profile-link position-relative" style="z-index:3" href="'
                . $href . '">@' . $label . '</a>';
            $i = $at + 1 + mb_strlen($best, 'UTF-8');
        } else {
            $out .= htmlspecialchars('@', ENT_QUOTES, 'UTF-8');
            $i = $at + 1;
        }
    }

    return $out;
}

/** Nội dung bài / bình luận hiển thị: escape + tô @username + xuống dòng. */
function format_post_body_html(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $parts = explode("\n", $text);
    $html = [];
    foreach ($parts as $p) {
        $html[] = format_notification_snippet($p);
    }
    return implode('<br>', $html);
}

/**
 * Hiển thị nội dung bài (plain trong DB) + hashtag lưu riêng ở cuối, link tìm kiếm giống style mention.
 *
 * @param list<string> $hashtagNames tên tag không có ký tự #
 */
function format_post_display_html(string $plainContent, array $hashtagNames): string
{
    $body = format_post_body_html($plainContent);
    $names = array_values(array_filter(array_map('trim', $hashtagNames), static function ($n) {
        return $n !== '';
    }));
    $seenLower = [];
    $namesDeduped = [];
    foreach ($names as $n) {
        $k = mb_strtolower((string) $n, 'UTF-8');
        if (isset($seenLower[$k])) {
            continue;
        }
        $seenLower[$k] = true;
        $namesDeduped[] = $n;
    }
    $names = $namesDeduped;
    if ($names === []) {
        return $body;
    }

    $base = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
    $links = [];
    foreach ($names as $name) {
        $esc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $q = rawurlencode('#' . $name);
        $links[] = '<a class="post-hashtag-link text-primary fw-semibold text-decoration-none position-relative" style="z-index:2" href="'
            . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '/search?q=' . $q . '">#' . $esc . '</a>';
    }
    $inner = implode(' ', $links);
    $suffix = '<span class="post-hashtag-suffix d-inline-block mt-1"> ' . $inner . '</span>';
    return $body === '' ? trim($inner) : $body . $suffix;
}

function notification_time_ago_vi(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }
    $tzName = function_exists('env') ? (string) env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh') : 'Asia/Ho_Chi_Minh';
    try {
        $tz = new DateTimeZone($tzName);
        $t = new DateTimeImmutable($datetime, $tz);
        $now = new DateTimeImmutable('now', $tz);
    } catch (Throwable $e) {
        return '';
    }
    $sec = $now->getTimestamp() - $t->getTimestamp();
    if ($sec < 0) {
        $sec = 0;
    }
    if ($sec < 60) {
        return 'vừa xong';
    }
    if ($sec < 3600) {
        return (string) ((int) floor($sec / 60)) . ' phút trước';
    }
    if ($sec < 86400) {
        return (string) ((int) floor($sec / 3600)) . ' giờ trước';
    }
    if ($sec < 86400 * 7) {
        return (string) ((int) floor($sec / 86400)) . ' ngày trước';
    }
    if ($sec < 86400 * 60) {
        return (string) ((int) floor($sec / (86400 * 7))) . ' tuần trước';
    }
    return $t->format('d/m/Y');
}

/**
 * @param 'like'|'comment'|'follow'|'share'|'mention_post'|'mention_comment'|'mention' $type
 */
function create_notification(
    PDO $conn,
    int $recipientUserId,
    int $actorUserId,
    string $type,
    int $referenceId,
    ?int $postId = null
): void {
    NotificationService::create($conn, $recipientUserId, $actorUserId, $type, $referenceId, $postId);
}

function notify_for_new_comment(
    PDO $conn,
    int $postId,
    int $commentAuthorId,
    int $commentId,
    string $content
): void {
    $stmt = $conn->prepare('SELECT user_id FROM posts WHERE id = ? LIMIT 1');
    $stmt->execute([$postId]);
    $ownerId = (int) $stmt->fetchColumn();

    $mentionedIds = [];
    foreach (parse_mentioned_usernames_from_text($conn, $content) as $username) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1');
        $stmt->execute([$username]);
        $mentionedId = (int) $stmt->fetchColumn();
        if ($mentionedId > 0 && $mentionedId !== $commentAuthorId) {
            $mentionedIds[$mentionedId] = true;
        }
    }

    if ($ownerId > 0 && $ownerId !== $commentAuthorId) {
        if (isset($mentionedIds[$ownerId])) {
            create_notification($conn, $ownerId, $commentAuthorId, 'mention_comment', $commentId, $postId);
            unset($mentionedIds[$ownerId]);
        } else {
            create_notification($conn, $ownerId, $commentAuthorId, 'comment', $commentId, $postId);
        }
    }

    foreach (array_keys($mentionedIds) as $mentionedId) {
        create_notification($conn, $mentionedId, $commentAuthorId, 'mention_comment', $commentId, $postId);
    }
}

function notify_for_post_content_mentions(PDO $conn, int $postId, int $authorId, string $content): void
{
    foreach (parse_mentioned_usernames_from_text($conn, $content) as $username) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1');
        $stmt->execute([$username]);
        $mentionedId = (int) $stmt->fetchColumn();
        if ($mentionedId > 0 && $mentionedId !== $authorId) {
            create_notification($conn, $mentionedId, $authorId, 'mention_post', $postId, $postId);
        }
    }
}

/**
 * Danh sách thông báo cho inbox (trang /notifications và API). Hỗ trợ DB chưa có cột actor_id/post_id.
 *
 * @return list<array<string, mixed>>
 */
function notifications_fetch_inbox_rows(PDO $conn, int $userId, string $tab, int $limit = 80): array
{
    $mentionFilter = '';
    if ($tab === 'mention') {
        $mentionFilter = " AND n.type IN ('mention_post', 'mention_comment', 'mention') ";
    }

    $limit = max(1, min(200, $limit));

    if (NotificationService::usesExtendedSchema($conn)) {
        $sql = "
            SELECT
                n.*,
                COALESCE(ua.username, uf.username) AS actor_name,
                COALESCE(ua.avatar_url, uf.avatar_url) AS actor_avatar,
                c.content AS comment_text,
                COALESCE(c.post_id, n.post_id) AS resolved_post_id,
                p.content AS post_text,
                (
                    SELECT COUNT(*) FROM likes lk WHERE lk.post_id = COALESCE(n.post_id,
                        CASE WHEN n.type IN ('like', 'share', 'mention_post') THEN n.reference_id ELSE NULL END)
                ) AS like_total
            FROM notifications n
            LEFT JOIN users ua ON ua.id = n.actor_id
            LEFT JOIN users uf
                ON n.actor_id IS NULL AND n.type = 'follow' AND uf.id = n.reference_id
            LEFT JOIN comments c
                ON n.type IN ('comment', 'mention_comment', 'mention')
                AND c.id = n.reference_id
            LEFT JOIN posts p
                ON p.id = COALESCE(n.post_id,
                    CASE WHEN n.type IN ('mention_post', 'like', 'share') THEN n.reference_id ELSE NULL END)
            WHERE n.user_id = ?
            {$mentionFilter}
            AND NOT (n.type = 'like' AND n.actor_id IS NOT NULL AND n.actor_id = n.user_id)
            ORDER BY n.created_at DESC
            LIMIT {$limit}
        ";
    } else {
        $sql = "
            SELECT
                n.*,
                CASE
                    WHEN n.type = 'follow' THEN uf.username
                    WHEN n.type = 'like' THEN (
                        SELECT uu.username
                        FROM likes ll
                        INNER JOIN users uu ON uu.id = ll.user_id
                        INNER JOIN posts pp ON pp.id = n.reference_id
                        WHERE ll.post_id = n.reference_id AND ll.user_id <> pp.user_id
                        ORDER BY ll.user_id DESC
                        LIMIT 1
                    )
                    WHEN n.type IN ('comment', 'mention', 'mention_comment') THEN u_comm.username
                    WHEN n.type = 'share' THEN u_share.username
                    WHEN n.type = 'mention_post' THEN u_mp.username
                END AS actor_name,
                CASE
                    WHEN n.type = 'follow' THEN uf.avatar_url
                    WHEN n.type = 'like' THEN (
                        SELECT uu.avatar_url
                        FROM likes ll
                        INNER JOIN users uu ON uu.id = ll.user_id
                        INNER JOIN posts pp ON pp.id = n.reference_id
                        WHERE ll.post_id = n.reference_id AND ll.user_id <> pp.user_id
                        ORDER BY ll.user_id DESC
                        LIMIT 1
                    )
                    WHEN n.type IN ('comment', 'mention', 'mention_comment') THEN u_comm.avatar_url
                    WHEN n.type = 'share' THEN u_share.avatar_url
                    WHEN n.type = 'mention_post' THEN u_mp.avatar_url
                END AS actor_avatar,
                c.content AS comment_text,
                COALESCE(
                    c.post_id,
                    CASE WHEN n.type IN ('like', 'share', 'mention_post') THEN n.reference_id ELSE NULL END
                ) AS resolved_post_id,
                p_snip.content AS post_text,
                (
                    SELECT COUNT(*) FROM likes lk WHERE lk.post_id = COALESCE(
                        c.post_id,
                        CASE WHEN n.type IN ('like', 'share', 'mention_post') THEN n.reference_id ELSE NULL END
                    )
                ) AS like_total
            FROM notifications n
            LEFT JOIN users uf ON n.type = 'follow' AND uf.id = n.reference_id
            LEFT JOIN comments c
                ON n.type IN ('comment', 'mention', 'mention_comment') AND c.id = n.reference_id
            LEFT JOIN users u_comm
                ON n.type IN ('comment', 'mention', 'mention_comment') AND u_comm.id = c.user_id
            LEFT JOIN shares s ON n.type = 'share' AND s.post_id = n.reference_id
            LEFT JOIN users u_share ON n.type = 'share' AND u_share.id = s.user_id
            LEFT JOIN posts p_snip ON n.type = 'mention_post' AND p_snip.id = n.reference_id
            LEFT JOIN users u_mp ON n.type = 'mention_post' AND u_mp.id = p_snip.user_id
            WHERE n.user_id = ?
            {$mentionFilter}
            ORDER BY n.created_at DESC
            LIMIT {$limit}
        ";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}
