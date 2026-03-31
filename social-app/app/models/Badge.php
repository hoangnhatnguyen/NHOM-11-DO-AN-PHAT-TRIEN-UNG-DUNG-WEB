<?php
class Badge extends BaseModel {

    protected string $table = 'badges';

    public function getAll(): array {
        return $this->db->query("SELECT * FROM badges WHERE is_public = 1")->fetchAll();
    }
}