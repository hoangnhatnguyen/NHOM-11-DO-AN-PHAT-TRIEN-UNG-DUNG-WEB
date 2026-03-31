<?php
require_once __DIR__ . '/../core/BaseModel.php';

class Hashtag extends BaseModel {
    protected string $table = 'hashtags';

    public function findOrCreate(string $tag): int {
        $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE tag = :tag");
        $stmt->execute(['tag' => $tag]);
        $result = $stmt->fetch();

        if ($result) return (int)$result['id'];

        $stmt = $this->db->prepare("INSERT INTO {$this->table} (tag) VALUES (:tag)");
        $stmt->execute(['tag' => $tag]);
        return (int)$this->db->lastInsertId();
    }
}