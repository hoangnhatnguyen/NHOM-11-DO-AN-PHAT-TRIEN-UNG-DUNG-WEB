<?php
class Block extends BaseModel {
    protected string $table = 'blocks';

    public function isBlocked(int $blockerId, int $blockedId): bool {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM blocks
            WHERE blocker_id = :blocker AND blocked_id = :blocked
            LIMIT 1
        ");
        $stmt->execute([
            'blocker' => $blockerId,
            'blocked' => $blockedId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function block(int $blockerId, int $blockedId): bool {
        if ($blockerId <= 0 || $blockedId <= 0 || $blockerId === $blockedId) {
            return false;
        }

        if ($this->isBlocked($blockerId, $blockedId)) {
            return true;
        }

        $stmt = $this->db->prepare("
            INSERT INTO blocks (blocker_id, blocked_id)
            VALUES (:blocker, :blocked)
        ");

        return $stmt->execute([
            'blocker' => $blockerId,
            'blocked' => $blockedId,
        ]);
    }

    public function getBlocked(int $userId): array {
        $sql = "SELECT u.id, u.username 
                FROM blocks b 
                JOIN users u ON b.blocked_id = u.id
                WHERE b.blocker_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id'=>$userId]);
        return $stmt->fetchAll();
    }

    public function getBlockedIds(int $userId): array {
        $stmt = $this->db->prepare("SELECT blocked_id FROM blocks WHERE blocker_id = :id");
        $stmt->execute(['id' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function getBlockedByIds(int $userId): array {
        $stmt = $this->db->prepare("SELECT blocker_id FROM blocks WHERE blocked_id = :id");
        $stmt->execute(['id' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function unblock(int $blocker, int $blocked): bool {
        $stmt = $this->db->prepare("DELETE FROM blocks WHERE blocker_id=:b AND blocked_id=:u");
        return $stmt->execute(['b'=>$blocker,'u'=>$blocked]);
    }
}
