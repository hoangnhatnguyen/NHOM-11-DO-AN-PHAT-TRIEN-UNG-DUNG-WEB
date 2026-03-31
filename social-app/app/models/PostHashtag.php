<?php
require_once __DIR__ . '/../core/BaseModel.php';

class PostHashtag extends BaseModel {
    protected string $table = 'post_hashtags';

    public function attach(int $postId, int $hashtagId): void {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (post_id, hashtag_id)
            VALUES (:post_id, :hashtag_id)
        ");
        $stmt->execute([
            'post_id' => $postId,
            'hashtag_id' => $hashtagId
        ]);
    }
}