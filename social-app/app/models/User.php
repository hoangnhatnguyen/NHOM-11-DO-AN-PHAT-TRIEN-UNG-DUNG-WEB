<?php

class User extends BaseModel {
	protected string $table = 'users';

	public function findByEmail(string $email): ?array {
		$stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = :email LIMIT 1");
		$stmt->execute(['email' => $email]);
		$result = $stmt->fetch();

		return $result === false ? null : $result;
	}

	public function findByUsername(string $username): ?array {
		$stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE username = :username LIMIT 1");
		$stmt->execute(['username' => $username]);
		$result = $stmt->fetch();

		return $result === false ? null : $result;
	}

	public function findByLoginIdentifier(string $identifier): ?array {
		$stmt = $this->db->prepare(
			"SELECT * FROM {$this->table} WHERE email = :email OR username = :username LIMIT 1"
		);
		$stmt->execute([
			'email' => $identifier,
			'username' => $identifier,
		]);
		$result = $stmt->fetch();

		return $result === false ? null : $result;
	}

	public function createUser(array $data): int {
		$stmt = $this->db->prepare(
			"INSERT INTO {$this->table} (username, email, password_hash, role, is_active, created_at, updated_at)\n\t\t\t VALUES (:username, :email, :password_hash, :role, :is_active, NOW(), NOW())"
		);

		$stmt->execute([
			'username' => $data['username'],
			'email' => $data['email'],
			'password_hash' => $data['password_hash'],
			'role' => $data['role'] ?? 'user',
			'is_active' => (int) ($data['is_active'] ?? 1),
		]);

		return (int) $this->db->lastInsertId();
	}

	public function updatePassword(int $userId, string $passwordHash): bool {
		$stmt = $this->db->prepare(
			"UPDATE {$this->table} SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id"
		);

		return $stmt->execute([
			'password_hash' => $passwordHash,
			'id' => $userId,
		]);
	}
}

