<?php
require_once __DIR__ . '/../core/BaseModel.php';

class PostMedia extends BaseModel {
    protected string $table = 'post_media';

    public function addMedia(int $postId, string $path): void {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (post_id, media_url)
            VALUES (:post_id, :media_url)
        ");
        $stmt->execute([
            'post_id' => $postId,
            'media_url' => $path
        ]);
    }

    public function getByPost(int $postId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} WHERE post_id = :post_id
        ");
        $stmt->execute(['post_id' => $postId]);
        return $stmt->fetchAll();
    }

    public function getByIdsForPost(int $postId, array $mediaIds): array {
        $mediaIds = array_values(array_unique(array_map('intval', $mediaIds)));
        if (empty($mediaIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($mediaIds), '?'));
        $params = array_merge([$postId], $mediaIds);

        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE post_id = ? AND id IN ($placeholders)
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function deleteByIdsForPost(int $postId, array $mediaIds): void {
        $mediaIds = array_values(array_unique(array_map('intval', $mediaIds)));
        if (empty($mediaIds)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($mediaIds), '?'));
        $params = array_merge([$postId], $mediaIds);

        $stmt = $this->db->prepare("
            DELETE FROM {$this->table}
            WHERE post_id = ? AND id IN ($placeholders)
        ");
        $stmt->execute($params);
    }

    public function deleteAllForPost(int $postId): void {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE post_id = :post_id");
        $stmt->execute(['post_id' => $postId]);
    }
}