<?php

class Badge extends BaseModel {
    protected string $table = 'badges';

    public function search($q) {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT id, name
        FROM badges
        WHERE name LIKE ?
        LIMIT 10
    ");

    $stmt->execute(["%$q%"]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public function create(string $name): int {
        $stmt = $this->db->prepare("
            INSERT INTO badges (name)
            VALUES (?)
        ");
        $stmt->execute([$name]);
        return $this->db->lastInsertId();
    }
}