<?php

declare(strict_types=1);

/**
 * Tạo bản ghi notifications. Ưu tiên schema có actor_id + post_id (xem migration 002).
 */
final class NotificationService
{
    private static ?bool $extendedSchema = null;

    public static function usesExtendedSchema(PDO $db): bool
    {
        if (self::$extendedSchema !== null) {
            return self::$extendedSchema;
        }
        try {
            $a = $db->query("SHOW COLUMNS FROM notifications LIKE 'actor_id'");
            $p = $db->query("SHOW COLUMNS FROM notifications LIKE 'post_id'");
            self::$extendedSchema = $a && $a->fetch(PDO::FETCH_ASSOC) !== false
                && $p && $p->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (Throwable $e) {
            self::$extendedSchema = false;
        }
        return self::$extendedSchema;
    }

    /**
     * @param 'like'|'comment'|'follow'|'share'|'mention_post'|'mention_comment'|'mention' $type
     */
    public static function create(
        PDO $db,
        int $recipientUserId,
        int $actorUserId,
        string $type,
        int $referenceId,
        ?int $postId = null
    ): void {
        if ($recipientUserId <= 0 || $actorUserId <= 0 || $recipientUserId === $actorUserId) {
            return;
        }

        if (self::usesExtendedSchema($db)) {
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, actor_id, type, reference_id, post_id, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([
                $recipientUserId,
                $actorUserId,
                $type,
                $referenceId,
                $postId,
            ]);
            return;
        }

        // Schema cũ (chưa migration): chỉ có user_id, type, reference_id.
        $ref = $referenceId;
        if ($type === 'follow') {
            $ref = $actorUserId;
        }

        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, type, reference_id, is_read, created_at)
            VALUES (?, ?, ?, 0, NOW())
        ");
        try {
            $stmt->execute([$recipientUserId, $type, $ref]);
        } catch (Throwable $e) {
            if ($type === 'mention_comment') {
                $stmt2 = $db->prepare("
                    INSERT INTO notifications (user_id, type, reference_id, is_read, created_at)
                    VALUES (?, 'mention', ?, 0, NOW())
                ");
                $stmt2->execute([$recipientUserId, $ref]);
                return;
            }
            throw $e;
        }
    }

    /** @return list<string> */
    public static function parseMentionedUsernames(string $text): array
    {
        if (preg_match_all('/@([a-zA-Z0-9_]+)/u', $text, $m)) {
            return array_values(array_unique($m[1]));
        }
        return [];
    }
}
