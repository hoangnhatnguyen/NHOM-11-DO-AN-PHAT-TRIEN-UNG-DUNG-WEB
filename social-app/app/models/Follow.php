<?php

class Follow extends BaseModel {
	protected string $table = 'follows';

	public function follow(int $followerId, int $followingId)
    {
		if ($followerId === $followingId) {
			return false; // Cannot follow yourself
		}

		try {
			$stmt = $this->db->prepare("INSERT INTO {$this->table} (follower_id, following_id) VALUES (:follower_id, :following_id)");
			return $stmt->execute([
				'follower_id' => $followerId,
				'following_id' => $followingId,
			]);
		} catch (\Exception $e) {
			return false;
		}
	}

	public function unfollow(int $followerId, int $followingId): bool {
		try {
			$stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE follower_id = :follower_id AND following_id = :following_id");
			return $stmt->execute([
				'follower_id' => $followerId,
				'following_id' => $followingId,
			]);
		} catch (\Exception $e) {
			return false;
		}
	}

	public function isFollowing(int $followerId, int $followingId): bool {
		try {
			$stmt = $this->db->prepare("SELECT 1 FROM {$this->table} WHERE follower_id = :follower_id AND following_id = :following_id LIMIT 1");
			$stmt->execute([
				'follower_id' => $followerId,
				'following_id' => $followingId,
			]);
			return $stmt->rowCount() > 0;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function getFollowers(int $userId, int $limit = 10, int $offset = 0): array {
		try {
			$limit = max(1, min($limit, 100));
			$offset = max(0, $offset);
			
			$sql = "
				SELECT u.id, u.username, u.email, u.avatar_url, u.bio
				FROM {$this->table} f
				JOIN users u ON f.follower_id = u.id
				WHERE f.following_id = :user_id
				ORDER BY f.created_at DESC
				LIMIT :limit OFFSET :offset
			";
			$stmt = $this->db->prepare($sql);
			$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
			$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
			$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
			$stmt->execute();
			return $stmt->fetchAll();
		} catch (\Exception $e) {
			return [];
		}
	}

	public function getFollowing(int $userId, int $limit = 10, int $offset = 0): array {
		try {
			$limit = max(1, min($limit, 100));
			$offset = max(0, $offset);
			
			$sql = "
				SELECT u.id, u.username, u.email, u.avatar_url, u.bio
				FROM {$this->table} f
				JOIN users u ON f.following_id = u.id
				WHERE f.follower_id = :user_id
				ORDER BY f.created_at DESC
				LIMIT :limit OFFSET :offset
			";
			$stmt = $this->db->prepare($sql);
			$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
			$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
			$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
			$stmt->execute();
			return $stmt->fetchAll();
		} catch (\Exception $e) {
			return [];
		}
	}

	public function countFollowers(int $userId): int {
		try {
			$stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE following_id = :user_id");
			$stmt->execute(['user_id' => $userId]);
			$result = $stmt->fetch();
			return (int) ($result['count'] ?? 0);
		} catch (\Exception $e) {
			return 0;
		}
	}

	public function countFollowing(int $userId): int {
		try {
			$stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE follower_id = :user_id");
			$stmt->execute(['user_id' => $userId]);
			$result = $stmt->fetch();
			return (int) ($result['count'] ?? 0);
		} catch (\Exception $e) {
			return 0;
		}
	}

	/**
	 * Gợi ý user chưa theo dõi (widget cột phải).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function suggestForViewer(int $viewerId, int $limit = 5): array {
		if ($viewerId <= 0) {
			return [];
		}
		$limit = max(1, min($limit, 20));
		try {
			$sql = "
				SELECT u.id, u.username, u.avatar_url
				FROM users u
				WHERE u.id <> :viewer
				AND NOT EXISTS (
					SELECT 1 FROM {$this->table} f
					WHERE f.follower_id = :viewer2 AND f.following_id = u.id
				)
				ORDER BY u.id DESC
				LIMIT :lim
			";
			$stmt = $this->db->prepare($sql);
			$stmt->bindValue(':viewer', $viewerId, PDO::PARAM_INT);
			$stmt->bindValue(':viewer2', $viewerId, PDO::PARAM_INT);
			$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
			$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (\Exception $e) {
			return [];
		}
	}
}
