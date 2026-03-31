<?php
class Block extends BaseModel {
    protected string $table = 'blocks';

    public function getBlocked(int $userId): array {
        $sql = "SELECT u.id, u.username 
                FROM blocks b 
                JOIN users u ON b.blocked_id = u.id
                WHERE b.blocker_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id'=>$userId]);
        return $stmt->fetchAll();
    }

    public function unblock(int $blocker, int $blocked): bool {
        $stmt = $this->db->prepare("DELETE FROM blocks WHERE blocker_id=:b AND blocked_id=:u");
        return $stmt->execute(['b'=>$blocker,'u'=>$blocked]);
    }
}