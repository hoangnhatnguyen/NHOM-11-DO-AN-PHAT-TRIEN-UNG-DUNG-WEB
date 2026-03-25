<?php

abstract class BaseModel {
	protected PDO $db;
	protected string $table;

	public function __construct() {
		$this->db = Database::getInstance()->getConnection();
	}

	public function findAll(): array {
		$stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY id DESC");
		return $stmt->fetchAll();
	}

	public function findById(int $id): ?array {
		$stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
		$stmt->execute(['id' => $id]);
		$result = $stmt->fetch();

		return $result === false ? null : $result;
	}

	public function delete(int $id): bool {
		$stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
		return $stmt->execute(['id' => $id]);
	}
}

