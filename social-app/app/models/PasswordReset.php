<?php

class PasswordReset extends BaseModel {
	protected string $table = 'password_resets';

	public function createToken(int $userId, int $ttlMinutes = 30): string {
		$this->deleteByUserId($userId);

		$rawToken = bin2hex(random_bytes(32));
		$hashedToken = hash('sha256', $rawToken);
		$expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));

		$stmt = $this->db->prepare(
			"INSERT INTO {$this->table} (user_id, reset_token, expires_at, created_at)\n\t\t\t VALUES (:user_id, :reset_token, :expires_at, NOW())"
		);

		$stmt->execute([
			'user_id' => $userId,
			'reset_token' => $hashedToken,
			'expires_at' => $expiresAt,
		]);

		return $rawToken;
	}

	public function findValidByRawToken(string $rawToken): ?array {
		$hashedToken = hash('sha256', $rawToken);

		$stmt = $this->db->prepare(
			"SELECT pr.*, u.email, u.username, u.is_active\n\t\t\t FROM {$this->table} pr\n\t\t\t JOIN users u ON u.id = pr.user_id\n\t\t\t WHERE pr.reset_token = :reset_token\n\t\t\t   AND pr.expires_at > NOW()\n\t\t\t LIMIT 1"
		);

		$stmt->execute(['reset_token' => $hashedToken]);
		$result = $stmt->fetch();

		return $result === false ? null : $result;
	}

	public function deleteByRawToken(string $rawToken): bool {
		$hashedToken = hash('sha256', $rawToken);
		$stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE reset_token = :reset_token");
		return $stmt->execute(['reset_token' => $hashedToken]);
	}

	public function deleteByUserId(int $userId): bool {
		$stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE user_id = :user_id");
		return $stmt->execute(['user_id' => $userId]);
	}

	public function deleteExpired(): bool {
		$stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE expires_at <= NOW()");
		return $stmt->execute();
	}
}

