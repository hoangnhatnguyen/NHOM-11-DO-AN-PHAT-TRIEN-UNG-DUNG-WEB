<?php
class UserBadge extends BaseModel {

    protected string $table = 'user_badges';

    public function getByUser(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT b.*
            FROM user_badges ub
            JOIN badges b ON ub.badge_id = b.id
            WHERE ub.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function add(int $userId, int $badgeId): bool {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO user_badges(user_id, badge_id)
            VALUES (?, ?)
        ");
        return $stmt->execute([$userId, $badgeId]);
    }

    public function remove(int $userId, int $badgeId): bool {
    $stmt = $this->db->prepare("
        DELETE FROM user_badges
        WHERE user_id = ? AND badge_id = ?
    ");
    return $stmt->execute([$userId, $badgeId]);
}
}