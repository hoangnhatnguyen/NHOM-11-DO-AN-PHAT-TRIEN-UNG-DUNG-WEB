<?php

class User extends BaseModel {
	protected string $table = 'users';

	public function countAllUsers(): int {
		$stmt = $this->db->query("SELECT COUNT(*) FROM {$this->table}");
		return (int) $stmt->fetchColumn();
	}

	public function countNewUsersToday(): int {
		$stmt = $this->db->query("SELECT COUNT(*) FROM {$this->table} WHERE DATE(created_at) = CURRENT_DATE()");
		return (int) $stmt->fetchColumn();
	}

	public function hasAnyAdmin(): bool {
		$stmt = $this->db->query("SELECT 1 FROM {$this->table} WHERE role = 'admin' LIMIT 1");
		return $stmt->fetchColumn() !== false;
	}

	public function searchForAdmin(string $keyword = ''): array {
		$keyword = trim($keyword);
		if ($keyword === '') {
			$stmt = $this->db->query("
				SELECT id, username, email, role, is_active, created_at
				FROM {$this->table}
				ORDER BY id DESC
			");
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

		$stmt = $this->db->prepare("
			SELECT id, username, email, role, is_active, created_at
			FROM {$this->table}
			WHERE username LIKE :kw OR email LIKE :kw
			ORDER BY id DESC
		");
		$stmt->execute(['kw' => '%' . $keyword . '%']);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function setActiveStatus(int $userId, int $active): bool {
		$stmt = $this->db->prepare("UPDATE {$this->table} SET is_active = :active WHERE id = :id");
		return $stmt->execute([
			'active' => $active ? 1 : 0,
			'id' => $userId,
		]);
	}

	public function searchForChat(int $excludeUserId, string $query = '', int $limit = 20): array {
		$limit = max(1, min($limit, 50));
		$params = [
			'exclude_id' => $excludeUserId,
		];

		$where = 'id <> :exclude_id';
		if ($query !== '') {
			$where .= ' AND (username LIKE :query OR email LIKE :query)';
			$params['query'] = '%' . $query . '%';
		}

		$sql = "SELECT id, username, email, avatar_url FROM {$this->table} WHERE {$where} ORDER BY username ASC LIMIT :limit";
		$stmt = $this->db->prepare($sql);

		foreach ($params as $key => $value) {
			$stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
		}

		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll();
	}

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

	public function updateProfile(int $id, string $bio): bool {
    $stmt = $this->db->prepare("UPDATE users SET bio = :bio WHERE id = :id");
    return $stmt->execute(['bio'=>$bio,'id'=>$id]);
}

public function updateAvatar(int $id, string $url): bool {
    $stmt = $this->db->prepare("UPDATE users SET avatar_url = :url WHERE id = :id");
    return $stmt->execute(['url'=>$url,'id'=>$id]);
}

public function getStats(int $userId): array {
    return [
        'posts' => $this->db->query("SELECT COUNT(*) FROM posts WHERE user_id = $userId")->fetchColumn(),
        'followers' => $this->db->query("SELECT COUNT(*) FROM follows WHERE following_id = $userId")->fetchColumn(),
        'following' => $this->db->query("SELECT COUNT(*) FROM follows WHERE follower_id = $userId")->fetchColumn(),
    ];
}
public function getBadges($userId) {
    $stmt = $this->db->prepare("SELECT * FROM user_badges WHERE user_id=?");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

public function addBadge($userId, $name) {
    $stmt = $this->db->prepare("INSERT INTO user_badges(user_id,name) VALUES(?,?)");
    return $stmt->execute([$userId,$name]);
}

}

