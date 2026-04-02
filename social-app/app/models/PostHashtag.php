<?php
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/Hashtag.php';

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

    /** @return list<string> */
    public function getTagNamesByPostId(int $postId): array {
        if ($postId <= 0) {
            return [];
        }
        $stmt = $this->db->prepare("
            SELECT h.name
            FROM {$this->table} ph
            JOIN hashtags h ON h.id = ph.hashtag_id
            WHERE ph.post_id = ?
            ORDER BY h.name ASC
        ");
        $stmt->execute([$postId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return is_array($rows) ? array_values(array_map('strval', $rows)) : [];
    }

    /**
     * @param list<int> $postIds
     * @return array<int, list<string>>
     */
    public function getTagNamesForPostIds(array $postIds): array {
        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        if ($postIds === []) {
            return [];
        }
        sort($postIds, SORT_NUMERIC);
        $ph = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->db->prepare("
            SELECT ph.post_id, h.name
            FROM {$this->table} ph
            JOIN hashtags h ON h.id = ph.hashtag_id
            WHERE ph.post_id IN ($ph)
            ORDER BY ph.post_id ASC, h.name ASC
        ");
        $stmt->execute($postIds);

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pid = (int) ($row['post_id'] ?? 0);
            $name = (string) ($row['name'] ?? '');
            if ($pid > 0 && $name !== '') {
                if (!isset($map[$pid])) {
                    $map[$pid] = [];
                }
                $map[$pid][] = $name;
            }
        }
        return $map;
    }

    /** Xóa liên kết cũ và gắn lại hashtag theo danh sách tên (đã bóc khỏi posts.content). */
    public function replaceForPost(int $postId, array $tagNames): void {
        if ($postId <= 0) {
            return;
        }
        $tagNames = array_values(array_unique(array_filter(array_map('trim', $tagNames), static function ($n) {
            return $n !== '';
        })));
        $del = $this->db->prepare("DELETE FROM {$this->table} WHERE post_id = ?");
        $del->execute([$postId]);
        $hashtagModel = new Hashtag();
        foreach ($tagNames as $name) {
            $hid = $hashtagModel->findOrCreate((string) $name);
            $this->attach($postId, $hid);
        }
    }
}