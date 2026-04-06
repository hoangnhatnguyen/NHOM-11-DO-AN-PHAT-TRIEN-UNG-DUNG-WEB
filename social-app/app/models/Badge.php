<?php

class Badge extends BaseModel {
    protected string $table = 'badges';

    public function search(string $q): array {
        $stmt = $this->db->prepare("
            SELECT id, name
            FROM badges
            WHERE name COLLATE utf8mb4_bin LIKE :keyword COLLATE utf8mb4_bin
            ORDER BY
                CASE
                    WHEN name COLLATE utf8mb4_bin = :exact COLLATE utf8mb4_bin THEN 0
                    WHEN name COLLATE utf8mb4_bin LIKE :prefix COLLATE utf8mb4_bin THEN 1
                    ELSE 2
                END,
                name ASC
            LIMIT 10
        ");

        $stmt->execute([
            'keyword' => '%' . $q . '%',
            'exact' => $q,
            'prefix' => $q . '%',
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findExactByName(string $name): ?array {
        $stmt = $this->db->prepare("
            SELECT id, name
            FROM badges
            WHERE name COLLATE utf8mb4_bin = :name COLLATE utf8mb4_bin
            LIMIT 1
        ");
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
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
