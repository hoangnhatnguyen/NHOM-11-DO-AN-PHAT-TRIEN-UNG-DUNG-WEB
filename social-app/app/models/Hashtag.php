<?php
require_once __DIR__ . '/../core/BaseModel.php';

class Hashtag extends BaseModel {
    protected string $table = 'hashtags';

    public function findOrCreate(string $tag): int {
        $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE LOWER(name) = LOWER(:name) LIMIT 1");
        $stmt->execute(['name' => $tag]);
        $result = $stmt->fetch();

        if ($result) {
            return (int) $result['id'];
        }

        $stmt = $this->db->prepare("INSERT INTO {$this->table} (name) VALUES (:name)");
        $stmt->execute(['name' => $tag]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Hashtag theo số bài active (widget Đang phổ biến + API trending).
     *
     * @return list<array{name: string, total: int|string}>
     */
    public function getTrending(int $limit = 10): array {
        $limit = max(1, min(30, $limit));
        $stmt = $this->db->prepare("
            SELECT h.name, COUNT(DISTINCT ph.post_id) AS total
            FROM {$this->table} h
            INNER JOIN post_hashtags ph ON h.id = ph.hashtag_id
            INNER JOIN posts p ON p.id = ph.post_id AND p.status = 'active'
            GROUP BY h.id, h.name
            ORDER BY total DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }
}