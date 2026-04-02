<?php
class UserBadge extends BaseModel {
    protected string $table = 'user_badges';

    public function getByUser(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT b.id, b.name
            FROM user_badges ub
            JOIN badges b ON ub.badge_id = b.id
            WHERE ub.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function add(int $userId, int $badgeId): void {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO user_badges (user_id, badge_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$userId, $badgeId]);
    }

    public function exists($userId, $badgeId): bool {
        $stmt = $this->db->prepare("
            SELECT 1 FROM user_badges
            WHERE user_id = ? AND badge_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $badgeId]);

        return $stmt->fetch() ? true : false;
    }

    public function remove(int $userId, int $badgeId): void {
        $stmt = $this->db->prepare("
            DELETE FROM user_badges
            WHERE user_id = ? AND badge_id = ?
        ");
        $stmt->execute([$userId, $badgeId]);
    }
}