<?php
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/PostMedia.php';
require_once __DIR__ . '/PostHashtag.php';

class Post extends BaseModel {
    protected string $table = 'posts';

    /**
     * Exclude authors who are in a block relationship with the viewer.
     *
     * @param array<string, mixed> $params
     */
    private function appendBlockVisibilityClause(string $authorColumn, int $viewerId, array &$params, string $prefix): string {
        if ($viewerId <= 0) {
            return '';
        }

        $params[$prefix . '_viewer_as_blocker'] = $viewerId;
        $params[$prefix . '_viewer_as_blocked'] = $viewerId;

        return "
              AND NOT EXISTS (
                    SELECT 1
                    FROM blocks b
                    WHERE (
                        b.blocker_id = :" . $prefix . "_viewer_as_blocker
                        AND b.blocked_id = {$authorColumn}
                    ) OR (
                        b.blocker_id = {$authorColumn}
                        AND b.blocked_id = :" . $prefix . "_viewer_as_blocked
                    )
              )
        ";
    }

    public function countAllPosts(): int {
        $stmt = $this->db->query("SELECT COUNT(*) FROM {$this->table}");
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array{keyword?: string, field?: string, limit?: int, offset?: int} $filter
     * @return array<int, array<string, mixed>>
     */
    public function getAdminPosts(array $filter = []): array {
        $keyword = trim((string) ($filter['keyword'] ?? ''));
        $field = (string) ($filter['field'] ?? 'content');
        if (!in_array($field, ['content', 'user', 'hashtag'], true)) {
            $field = 'content';
        }

        $limit = isset($filter['limit']) ? (int) $filter['limit'] : null;
        $offset = isset($filter['offset']) ? max(0, (int) $filter['offset']) : 0;

        $sql = "
            SELECT
                p.id,
                p.content,
                p.visible,
                p.created_at,
                u.username AS author_name
            FROM posts p
            JOIN users u ON u.id = p.user_id
        ";
        $params = [];

        if ($field === 'hashtag') {
            $sql .= "
                LEFT JOIN post_hashtags ph ON ph.post_id = p.id
                LEFT JOIN hashtags h ON h.id = ph.hashtag_id
            ";
        }

        if ($keyword !== '') {
            if ($field === 'user') {
                $sql .= ' WHERE u.username LIKE :kw ';
            } elseif ($field === 'hashtag') {
                $sql .= ' WHERE h.name LIKE :kw ';
            } else {
                $sql .= ' WHERE p.content LIKE :kw ';
            }
            $params['kw'] = '%' . $keyword . '%';
        }

        $sql .= ' GROUP BY p.id, p.content, p.visible, p.created_at, u.username ORDER BY p.id DESC ';

        if ($limit !== null) {
            $sql .= ' LIMIT :admin_limit OFFSET :admin_offset ';
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':admin_limit', max(1, min($limit, 100)), PDO::PARAM_INT);
            $stmt->bindValue(':admin_offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array{keyword?: string, field?: string} $filter
     */
    public function countAdminPosts(array $filter = []): int {
        $keyword = trim((string) ($filter['keyword'] ?? ''));
        $field = (string) ($filter['field'] ?? 'content');
        if (!in_array($field, ['content', 'user', 'hashtag'], true)) {
            $field = 'content';
        }

        $sql = 'SELECT COUNT(DISTINCT p.id) FROM posts p JOIN users u ON u.id = p.user_id ';
        $params = [];

        if ($field === 'hashtag') {
            $sql .= '
                LEFT JOIN post_hashtags ph ON ph.post_id = p.id
                LEFT JOIN hashtags h ON h.id = ph.hashtag_id
            ';
        }

        if ($keyword !== '') {
            if ($field === 'user') {
                $sql .= ' WHERE u.username LIKE :kw ';
            } elseif ($field === 'hashtag') {
                $sql .= ' WHERE h.name LIKE :kw ';
            } else {
                $sql .= ' WHERE p.content LIKE :kw ';
            }
            $params['kw'] = '%' . $keyword . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    //CREATE POST
    public function create(array $data): int {
        $sql = "INSERT INTO {$this->table} 
                (user_id, content, visible, status, created_at)
                VALUES (:user_id, :content, :visible, 'active', NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $data['user_id'],
            'content' => $data['content'],
            'visible' => $data['visible'] ?? 'public'
        ]);

        return (int)$this->db->lastInsertId();
    }

    //UPDATE POST
    public function updatePost(int $id, string $content, ?string $visible = null): bool {
        if ($visible === null) {
            $stmt = $this->db->prepare("
                UPDATE {$this->table}
                SET content = :content
                WHERE id = :id
            ");
            return $stmt->execute([
                'id' => $id,
                'content' => $content
            ]);
        }

        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET content = :content, visible = :visible
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'content' => $content,
            'visible' => $visible,
        ]);
    }

    //GET 1 POST + USER
    public function findWithUser(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT p.*, u.username AS author_name
            FROM posts p
            JOIN users u ON u.id = p.user_id
            WHERE p.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    //GET 1 POST + USER + STATS + USER INTERACTION
    public function findDetailWithStats(int $id, int $viewerId): ?array {
        $params = [
            'id' => $id,
            'viewer_like' => $viewerId,
            'viewer_save' => $viewerId,
        ];
        $blockClause = $this->appendBlockVisibilityClause('p.user_id', $viewerId, $params, 'detail');

        $stmt = $this->db->prepare("
            SELECT
                p.*,
                u.username AS author_name,
                u.avatar_url AS author_avatar_url,
                (
                    SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id
                ) AS like_count,
                (
                    SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id
                ) AS comment_count,
                (
                    SELECT COUNT(*) FROM shares s WHERE s.post_id = p.id
                ) AS share_count,
                (
                    SELECT COUNT(*) FROM saved_posts sp WHERE sp.post_id = p.id
                ) AS save_count,
                EXISTS(
                    SELECT 1 FROM likes l2 WHERE l2.post_id = p.id AND l2.user_id = :viewer_like
                ) AS is_liked,
                EXISTS(
                    SELECT 1 FROM saved_posts sp2 WHERE sp2.post_id = p.id AND sp2.user_id = :viewer_save
                ) AS is_saved
            FROM posts p
            JOIN users u ON u.id = p.user_id
            WHERE p.id = :id
            {$blockClause}
            LIMIT 1
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row['hashtag_names'] = (new PostHashtag())->getTagNamesByPostId($id);
        return $row;
    }

    //LOAD FEED (có user + media + stats theo viewer)
    public function getFeed(int $viewerId = 0): array {
        $params = [
            'viewer_like' => $viewerId,
            'viewer_save' => $viewerId,
        ];
        $blockClause = $this->appendBlockVisibilityClause('p.user_id', $viewerId, $params, 'feed');

        $stmt = $this->db->prepare("
            SELECT 
                p.*,
                u.username AS author_name,
                u.avatar_url AS author_avatar_url,
                (
                    SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id
                ) AS like_count,
                (
                    SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id
                ) AS comment_count,
                (
                    SELECT COUNT(*) FROM shares s WHERE s.post_id = p.id
                ) AS share_count,
                (
                    SELECT COUNT(*) FROM saved_posts sp WHERE sp.post_id = p.id
                ) AS save_count,
                EXISTS(
                    SELECT 1 FROM likes l2 WHERE l2.post_id = p.id AND l2.user_id = :viewer_like
                ) AS is_liked,
                EXISTS(
                    SELECT 1 FROM saved_posts sp2 WHERE sp2.post_id = p.id AND sp2.user_id = :viewer_save
                ) AS is_saved
            FROM posts p
            JOIN users u ON u.id = p.user_id
            WHERE p.status = 'active'
              AND p.visible = 'public'
              {$blockClause}
            ORDER BY p.id DESC
        ");
        $stmt->execute($params);

        $posts = $stmt->fetchAll();

        $mediaModel = new PostMedia();
        $postIds = array_map(static function ($p) {
            return (int) ($p['id'] ?? 0);
        }, $posts);
        $tagMap = (new PostHashtag())->getTagNamesForPostIds($postIds);

        foreach ($posts as &$post) {
            $pid = (int) ($post['id'] ?? 0);
            $post['media'] = $mediaModel->getByPost($pid);
            $post['hashtag_names'] = $tagMap[$pid] ?? [];
        }
        unset($post);

        return $posts;
    }

    /**
     * Bài viết public của một user (trang cá nhân), đầy đủ stats + is_liked/is_saved theo viewer.
     */
    public function getPostsByUserForProfile(int $profileUserId, int $viewerId): array {
        if ($profileUserId <= 0) {
            return [];
        }

        $params = [
            'profile_uid' => $profileUserId,
            'viewer_like' => $viewerId,
            'viewer_save' => $viewerId,
        ];
        $blockClause = $this->appendBlockVisibilityClause('p.user_id', $viewerId, $params, 'profile');

        $stmt = $this->db->prepare("
            SELECT
                p.*,
                u.username AS author_name,
                u.avatar_url AS author_avatar_url,
                (
                    SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id
                ) AS like_count,
                (
                    SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id
                ) AS comment_count,
                (
                    SELECT COUNT(*) FROM shares s WHERE s.post_id = p.id
                ) AS share_count,
                (
                    SELECT COUNT(*) FROM saved_posts sp WHERE sp.post_id = p.id
                ) AS save_count,
                EXISTS(
                    SELECT 1 FROM likes l2 WHERE l2.post_id = p.id AND l2.user_id = :viewer_like
                ) AS is_liked,
                EXISTS(
                    SELECT 1 FROM saved_posts sp2 WHERE sp2.post_id = p.id AND sp2.user_id = :viewer_save
                ) AS is_saved
            FROM posts p
            JOIN users u ON u.id = p.user_id
            WHERE p.user_id = :profile_uid AND p.status = 'active'
              {$blockClause}
            ORDER BY p.created_at DESC
        ");
        $stmt->execute($params);

        $posts = $stmt->fetchAll();

        $mediaModel = new PostMedia();
        $postIds = array_map(static function ($p) {
            return (int) ($p['id'] ?? 0);
        }, $posts);
        $tagMap = (new PostHashtag())->getTagNamesForPostIds($postIds);

        foreach ($posts as &$post) {
            $pid = (int) ($post['id'] ?? 0);
            $post['media'] = $mediaModel->getByPost($pid);
            $post['hashtag_names'] = $tagMap[$pid] ?? [];
        }
        unset($post);

        return $posts;
    }

    /**
     * Feed chỉ từ tài khoản đang theo dõi (+ bài của chính mình).
     */
    public function getFeedFollowing(int $viewerId = 0): array {
        if ($viewerId <= 0) {
            return [];
        }

        $params = [
            'viewer_like' => $viewerId,
            'viewer_save' => $viewerId,
            'viewer_follow' => $viewerId,
        ];
        $blockClause = $this->appendBlockVisibilityClause('p.user_id', $viewerId, $params, 'following_feed');

        $stmt = $this->db->prepare("
            SELECT 
                p.*,
                u.username AS author_name,
                u.avatar_url AS author_avatar_url,
                (
                    SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id
                ) AS like_count,
                (
                    SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id
                ) AS comment_count,
                (
                    SELECT COUNT(*) FROM shares s WHERE s.post_id = p.id
                ) AS share_count,
                (
                    SELECT COUNT(*) FROM saved_posts sp WHERE sp.post_id = p.id
                ) AS save_count,
                EXISTS(
                    SELECT 1 FROM likes l2 WHERE l2.post_id = p.id AND l2.user_id = :viewer_like
                ) AS is_liked,
                EXISTS(
                    SELECT 1 FROM saved_posts sp2 WHERE sp2.post_id = p.id AND sp2.user_id = :viewer_save
                ) AS is_saved
            FROM posts p
            JOIN users u ON u.id = p.user_id
            WHERE p.status = 'active'
              AND p.user_id IN (
                  SELECT following_id FROM follows WHERE follower_id = :viewer_follow
              )
              AND p.visible IN ('public', 'followers')
              {$blockClause}
            ORDER BY p.id DESC
        ");
        $stmt->execute($params);

        $posts = $stmt->fetchAll();

        $mediaModel = new PostMedia();
        $postIds = array_map(static function ($p) {
            return (int) ($p['id'] ?? 0);
        }, $posts);
        $tagMap = (new PostHashtag())->getTagNamesForPostIds($postIds);

        foreach ($posts as &$post) {
            $pid = (int) ($post['id'] ?? 0);
            $post['media'] = $mediaModel->getByPost($pid);
            $post['hashtag_names'] = $tagMap[$pid] ?? [];
        }
        unset($post);

        return $posts;
    }

    /**
     * Lấy feed với phân trang (limit số bài viết)
     */
    public function getFeedPaginated(int $viewerId = 0, int $limit = 5, int $offset = 0): array {
        $params = [
            'viewer_like' => $viewerId,
            'viewer_save' => $viewerId,
            'limit' => (int) $limit,
            'offset' => (int) $offset,
        ];
        $blockClause = $this->appendBlockVisibilityClause('p.user_id', $viewerId, $params, 'feed_page');

        if ($viewerId <= 0) {
            $visibilitySql = " AND p.visible = 'public' ";
        } else {
            $params['feed_vid_own'] = $viewerId;
            $params['feed_vid_own2'] = $viewerId;
            $params['feed_vid_follower'] = $viewerId;
            $visibilitySql = "
              AND (
                p.visible = 'public'
                OR (p.visible = 'private' AND p.user_id = :feed_vid_own)
                OR (
                  p.visible = 'followers'
                  AND (
                    p.user_id = :feed_vid_own2
                    OR EXISTS (
                      SELECT 1 FROM follows f
                      WHERE f.follower_id = :feed_vid_follower AND f.following_id = p.user_id
                    )
                  )
                )
              )
            ";
        }

        $stmt = $this->db->prepare("
            SELECT
                p.*,
                u.username AS author_name,
                u.avatar_url AS author_avatar_url,
                (
                    SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id
                ) AS like_count,
                (
                    SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id
                ) AS comment_count,
                (
                    SELECT COUNT(*) FROM shares s WHERE s.post_id = p.id
                ) AS share_count,
                (
                    SELECT COUNT(*) FROM saved_posts sp WHERE sp.post_id = p.id
                ) AS save_count,
                EXISTS(
                    SELECT 1 FROM likes l2 WHERE l2.post_id = p.id AND l2.user_id = :viewer_like
                ) AS is_liked,
                EXISTS(
                    SELECT 1 FROM saved_posts sp2 WHERE sp2.post_id = p.id AND sp2.user_id = :viewer_save
                ) AS is_saved
            FROM posts p
            JOIN users u ON u.id = p.user_id
            WHERE p.status = 'active'
              {$visibilitySql}
              {$blockClause}
            ORDER BY p.id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->execute($params);

        $posts = $stmt->fetchAll();

        $mediaModel = new PostMedia();
        $postIds = array_map(static function ($p) {
            return (int) ($p['id'] ?? 0);
        }, $posts);
        $tagMap = (new PostHashtag())->getTagNamesForPostIds($postIds);

        foreach ($posts as &$post) {
            $pid = (int) ($post['id'] ?? 0);
            $post['media'] = $mediaModel->getByPost($pid);
            $post['hashtag_names'] = $tagMap[$pid] ?? [];
        }
        unset($post);

        return $posts;
    }

    /**
     * Feed theo dõi với phân trang
     */
    public function getFeedFollowingPaginated(int $viewerId = 0, int $limit = 5, int $offset = 0): array {
        if ($viewerId <= 0) {
            return [];
        }

        $params = [
            'viewer_like' => $viewerId,
            'viewer_save' => $viewerId,
            'viewer_follow' => $viewerId,
            'limit' => (int) $limit,
            'offset' => (int) $offset,
        ];
        $blockClause = $this->appendBlockVisibilityClause('p.user_id', $viewerId, $params, 'following_page');

        $stmt = $this->db->prepare("
            SELECT 
                p.*,
                u.username AS author_name,
                u.avatar_url AS author_avatar_url,
                (
                    SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id
                ) AS like_count,
                (
                    SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id
                ) AS comment_count,
                (
                    SELECT COUNT(*) FROM shares s WHERE s.post_id = p.id
                ) AS share_count,
                (
                    SELECT COUNT(*) FROM saved_posts sp WHERE sp.post_id = p.id
                ) AS save_count,
                EXISTS(
                    SELECT 1 FROM likes l2 WHERE l2.post_id = p.id AND l2.user_id = :viewer_like
                ) AS is_liked,
                EXISTS(
                    SELECT 1 FROM saved_posts sp2 WHERE sp2.post_id = p.id AND sp2.user_id = :viewer_save
                ) AS is_saved
            FROM posts p
            JOIN users u ON u.id = p.user_id
            WHERE p.status = 'active'
              AND p.user_id IN (
                  SELECT following_id FROM follows WHERE follower_id = :viewer_follow
              )
              AND p.visible IN ('public', 'followers')
              {$blockClause}
            ORDER BY p.id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->execute($params);

        $posts = $stmt->fetchAll();

        $mediaModel = new PostMedia();
        $postIds = array_map(static function ($p) {
            return (int) ($p['id'] ?? 0);
        }, $posts);
        $tagMap = (new PostHashtag())->getTagNamesForPostIds($postIds);

        foreach ($posts as &$post) {
            $pid = (int) ($post['id'] ?? 0);
            $post['media'] = $mediaModel->getByPost($pid);
            $post['hashtag_names'] = $tagMap[$pid] ?? [];
        }
        unset($post);

        return $posts;
    }

    /**
     * Bổ sung like/comment/share/save + trạng thái viewer cho danh sách post đã có sẵn (vd. tìm kiếm).
     *
     * @param array<int, array<string, mixed>> $posts
     * @return array<int, array<string, mixed>>
     */
    public function hydrateListWithInteractionStats(array $posts, int $viewerId): array {
        if ($posts === []) {
            return $posts;
        }

        $ids = [];
        foreach ($posts as $p) {
            $id = (int) ($p['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        $idList = array_keys($ids);
        if ($idList === []) {
            foreach ($posts as &$p) {
                $p['hashtag_names'] = [];
            }
            unset($p);
            return $posts;
        }
        sort($idList, SORT_NUMERIC);

        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $sql = "
            SELECT
                p.id,
                (
                    SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id
                ) AS like_count,
                (
                    SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id
                ) AS comment_count,
                (
                    SELECT COUNT(*) FROM shares s WHERE s.post_id = p.id
                ) AS share_count,
                (
                    SELECT COUNT(*) FROM saved_posts sp WHERE sp.post_id = p.id
                ) AS save_count,
                EXISTS(
                    SELECT 1 FROM likes l2 WHERE l2.post_id = p.id AND l2.user_id = ?
                ) AS is_liked,
                EXISTS(
                    SELECT 1 FROM saved_posts sp2 WHERE sp2.post_id = p.id AND sp2.user_id = ?
                ) AS is_saved
            FROM posts p
            WHERE p.id IN ($placeholders)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$viewerId, $viewerId], $idList));

        $statsById = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pid = (int) ($row['id'] ?? 0);
            if ($pid > 0) {
                $statsById[$pid] = $row;
            }
        }

        foreach ($posts as &$p) {
            $pid = (int) ($p['id'] ?? 0);
            if (!isset($statsById[$pid])) {
                continue;
            }
            $s = $statsById[$pid];
            $p['like_count'] = (int) ($s['like_count'] ?? 0);
            $p['comment_count'] = (int) ($s['comment_count'] ?? 0);
            $p['share_count'] = (int) ($s['share_count'] ?? 0);
            $p['save_count'] = (int) ($s['save_count'] ?? 0);
            $p['is_liked'] = (bool) (int) ($s['is_liked'] ?? 0);
            $p['is_saved'] = (bool) (int) ($s['is_saved'] ?? 0);
        }
        unset($p);

        $tagMap = (new PostHashtag())->getTagNamesForPostIds($idList);
        foreach ($posts as &$p) {
            $pid = (int) ($p['id'] ?? 0);
            $p['hashtag_names'] = $tagMap[$pid] ?? [];
        }
        unset($p);

        return $posts;
    }

    //GET MEDIA
    private function getByPost(int $postId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM post_media
            WHERE post_id = :post_id
        ");
        $stmt->execute(['post_id' => $postId]);
        return $stmt->fetchAll();
    }

    public function getCommentsByPost(int $postId): array {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                u.username AS author_name
            FROM comments c
            JOIN users u ON u.id = c.user_id
            WHERE c.post_id = :post_id
            ORDER BY c.id ASC
        ");
        $stmt->execute(['post_id' => $postId]);
        return $stmt->fetchAll();
    }

    // Ensure comments table supports nested replies without extra tables.
    private function ensureCommentThreadColumns(): void {
        // Add columns if missing (additive only).
        try {
            $cols = $this->db->query("SHOW COLUMNS FROM comments")->fetchAll();
            $colNames = [];
            foreach ($cols as $c) {
                $colNames[(string) ($c['Field'] ?? '')] = true;
            }

            if (empty($colNames['parent_id'])) {
                $this->db->exec("
                    ALTER TABLE comments
                    ADD COLUMN parent_id INT NULL DEFAULT NULL
                ");
            }

            if (empty($colNames['level'])) {
                $this->db->exec("
                    ALTER TABLE comments
                    ADD COLUMN level INT NOT NULL DEFAULT 1
                ");
            }
        } catch (Throwable $e) {
            // If ALTER fails due to permissions, the app will likely fail later on insert.
        }

        // comment_replies was created by previous implementation.
        // Drop it to avoid maintaining a second reply storage.
        try {
            $this->db->exec("DROP TABLE IF EXISTS comment_replies");
        } catch (Throwable $e) {
            // ignore if not permitted
        }
    }

    /**
     * Build a comment -> replies (recursive) tree using comments.parent_id.
     * Top-level comments: parent_id IS NULL.
     *
     * @return array<int, array<string, mixed>> list of nodes; each node has `children` array.
     */
    public function getCommentTreeByPost(int $postId): array {
        $this->ensureCommentThreadColumns();

        $stmt = $this->db->prepare("
            SELECT
                c.*,
                u.username AS author_name,
                u.avatar_url AS author_avatar_url
            FROM comments c
            JOIN users u ON u.id = c.user_id
            WHERE c.post_id = :post_id
            ORDER BY c.id ASC
        ");
        $stmt->execute(['post_id' => $postId]);
        $rows = $stmt->fetchAll();

        $nodesById = [];
        $childrenByParent = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $parent = $row['parent_id'] ?? null;
            $parentId = ($parent === null || $parent === '') ? null : (int) $parent;

            $row['children'] = [];
            $nodesById[$id] = $row;

            if ($parentId === null) {
                if (!isset($childrenByParent[0])) $childrenByParent[0] = [];
                $childrenByParent[0][] = $id;
            } else {
                if (!isset($childrenByParent[$parentId])) $childrenByParent[$parentId] = [];
                $childrenByParent[$parentId][] = $id;
            }
        }

        // Build tree recursively to avoid "copying" child arrays that can break deeper levels.
        $memo = [];
        $buildNode = function (int $id) use (&$buildNode, &$nodesById, &$childrenByParent, &$memo): array {
            if (isset($memo[$id])) {
                return $memo[$id];
            }

            $node = $nodesById[$id];
            $node['children'] = [];

            $childIds = $childrenByParent[$id] ?? [];
            foreach ($childIds as $childId) {
                $childId = (int) $childId;
                if (!isset($nodesById[$childId])) {
                    continue;
                }
                $node['children'][] = $buildNode($childId);
            }

            $memo[$id] = $node;
            return $node;
        };

        $roots = [];
        $rootIds = $childrenByParent[0] ?? [];
        foreach ($rootIds as $rid) {
            $rid = (int) $rid;
            if (!isset($nodesById[$rid])) {
                continue;
            }
            $roots[] = $buildNode($rid);
        }

        return $roots;
    }

    public function addReplyToComment(int $postId, int $parentCommentId, int $userId, string $content): ?int {
        $this->ensureCommentThreadColumns();

        // Get parent comment info
        $parentStmt = $this->db->prepare("
            SELECT id, level, parent_id FROM comments 
            WHERE id = :id AND post_id = :post_id LIMIT 1
        ");
        $parentStmt->execute([
            'id' => $parentCommentId,
            'post_id' => $postId,
        ]);
        $parent = $parentStmt->fetch();
        
        if (!$parent) {
            return null;
        }

        $parentLevel = (int) ($parent['level'] ?? 1);
        
        // Facebook-style 2 levels only:
        // - Level 1: top-level comment (parent_id = NULL)
        // - Level 2: reply to level 1 (parent_id = level 1 comment id)
        // - If replying to level 2, the new comment is still level 2 with same parent_id (the level 1 comment)
        
        if ($parentLevel === 1) {
            // Reply to level 1 → new comment is level 2
            $nextLevel = 2;
            $actualParentId = $parentCommentId;
        } elseif ($parentLevel === 2) {
            // Reply to level 2 → new comment is level 2, with the level 1 comment as parent
            $nextLevel = 2;
            $actualParentId = (int) ($parent['parent_id'] ?? $parentCommentId);
        } else {
            // Fallback (shouldn't happen)
            return null;
        }

        $stmt = $this->db->prepare("
            INSERT INTO comments (post_id, user_id, content, parent_id, level)
            VALUES (:post_id, :user_id, :content, :parent_id, :level)
        ");
        $stmt->execute([
            'post_id' => $postId,
            'user_id' => $userId,
            'content' => $content,
            'parent_id' => $actualParentId,
            'level' => $nextLevel,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Get comment with author info
     */
    public function getCommentWithAuthor(int $commentId): ?array {
        $this->ensureCommentThreadColumns();
        $stmt = $this->db->prepare("
            SELECT c.*, u.username, u.avatar_url 
            FROM comments c
            JOIN users u ON u.id = c.user_id
            WHERE c.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $commentId]);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    /**
     * Get comment level by ID
     */
    public function getCommentLevel(int $commentId): ?int {
        $this->ensureCommentThreadColumns();
        $stmt = $this->db->prepare("
            SELECT COALESCE(level, 1) AS lvl FROM comments WHERE id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $commentId]);
        $result = $stmt->fetchColumn();
        return $result !== false ? (int) $result : null;
    }

    /**
     * All comments can be replied (2-level system)
     */
    public function canReply(int $commentId): bool {
        return true; // In 2-level system, any comment can be replied
    }

    /**
     * Trả về danh sách reply theo từng comment (dạng phẳng),
     * key là `comment_id`, value là mảng reply.
     *
     * UI `post/detail.php` hiện tại đang render theo kiểu này.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function getRepliesByCommentIds(array $commentIds): array {
        if (empty($commentIds)) {
            return [];
        }

        $this->ensureReplyTableExists();

        $commentIds = array_values(array_unique(array_map('intval', $commentIds)));
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));

        $stmt = $this->db->prepare("
            SELECT
                cr.*,
                u.username AS author_name
            FROM comment_replies cr
            JOIN users u ON u.id = cr.user_id
            WHERE cr.comment_id IN ($placeholders)
            ORDER BY cr.id ASC
        ");
        $stmt->execute($commentIds);
        $rows = $stmt->fetchAll();

        $byComment = [];
        foreach ($rows as $row) {
            $commentId = (int) ($row['comment_id'] ?? 0);
            if (!isset($byComment[$commentId])) {
                $byComment[$commentId] = [];
            }
            $byComment[$commentId][] = $row;
        }
        
        return $byComment;
    }

    /**
     * @return bool true nếu sau thao tác user đang thích bài, false nếu đã bỏ thích
     */
    public function toggleLike(int $postId, int $userId): bool {
        $stmt = $this->db->prepare("
            SELECT 1 FROM likes WHERE post_id = :post_id AND user_id = :user_id LIMIT 1
        ");
        $stmt->execute([
            'post_id' => $postId,
            'user_id' => $userId,
        ]);
        $exists = $stmt->fetchColumn() !== false;

        if ($exists) {
            $deleteStmt = $this->db->prepare("
                DELETE FROM likes WHERE post_id = :post_id AND user_id = :user_id
            ");
            $deleteStmt->execute([
                'post_id' => $postId,
                'user_id' => $userId,
            ]);
            return false;
        }

        $insertStmt = $this->db->prepare("
            INSERT INTO likes (user_id, post_id) VALUES (:user_id, :post_id)
        ");
        $insertStmt->execute([
            'user_id' => $userId,
            'post_id' => $postId,
        ]);
        return true;
    }

    public function countLikes(int $postId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM likes WHERE post_id = :post_id");
        $stmt->execute(['post_id' => $postId]);
        return (int) $stmt->fetchColumn();
    }

    public function isLiked(int $postId, int $userId): bool {
        $stmt = $this->db->prepare("
            SELECT 1 FROM likes WHERE post_id = :post_id AND user_id = :user_id LIMIT 1
        ");
        $stmt->execute([
            'post_id' => $postId,
            'user_id' => $userId,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    public function toggleSave(int $postId, int $userId): void {
        $stmt = $this->db->prepare("
            SELECT 1 FROM saved_posts WHERE post_id = :post_id AND user_id = :user_id LIMIT 1
        ");
        $stmt->execute([
            'post_id' => $postId,
            'user_id' => $userId,
        ]);
        $exists = $stmt->fetchColumn() !== false;

        if ($exists) {
            $deleteStmt = $this->db->prepare("
                DELETE FROM saved_posts WHERE post_id = :post_id AND user_id = :user_id
            ");
            $deleteStmt->execute([
                'post_id' => $postId,
                'user_id' => $userId,
            ]);
            return;
        }

        $insertStmt = $this->db->prepare("
            INSERT INTO saved_posts (user_id, post_id) VALUES (:user_id, :post_id)
        ");
        $insertStmt->execute([
            'user_id' => $userId,
            'post_id' => $postId,
        ]);
    }

    public function countSavedPosts(int $postId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM saved_posts WHERE post_id = :post_id");
        $stmt->execute(['post_id' => $postId]);
        return (int) $stmt->fetchColumn();
    }

    public function isSaved(int $postId, int $userId): bool {
        $stmt = $this->db->prepare("
            SELECT 1 FROM saved_posts WHERE post_id = :post_id AND user_id = :user_id LIMIT 1
        ");
        $stmt->execute([
            'post_id' => $postId,
            'user_id' => $userId,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSavedPostsByUser(int $userId): array {
        $params = ['user_id' => $userId];
        $blockClause = $this->appendBlockVisibilityClause('p.user_id', $userId, $params, 'saved');

        $stmt = $this->db->prepare("
            SELECT
                p.id,
                p.content,
                p.created_at,
                u.username,
                pm.media_url
            FROM saved_posts sp
            JOIN posts p ON p.id = sp.post_id
            JOIN users u ON u.id = p.user_id
            LEFT JOIN post_media pm ON pm.id = (
                SELECT pm2.id
                FROM post_media pm2
                WHERE pm2.post_id = p.id
                ORDER BY pm2.id ASC
                LIMIT 1
            )
            WHERE sp.user_id = :user_id
              {$blockClause}
            ORDER BY p.id DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function removeSavedPost(int $postId, int $userId): void {
        $stmt = $this->db->prepare("
            DELETE FROM saved_posts
            WHERE post_id = :post_id AND user_id = :user_id
        ");
        $stmt->execute([
            'post_id' => $postId,
            'user_id' => $userId,
        ]);
    }

    public function addComment(int $postId, int $userId, string $content): int {
        $stmt = $this->db->prepare("
            INSERT INTO comments (post_id, user_id, content)
            VALUES (:post_id, :user_id, :content)
        ");
        $stmt->execute([
            'post_id' => $postId,
            'user_id' => $userId,
            'content' => $content,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function addReply(int $commentId, int $userId, string $content, ?int $parentReplyId = null): void {
        $this->ensureReplyTableExists();

        $stmt = $this->db->prepare("
            INSERT INTO comment_replies (comment_id, user_id, content)
            VALUES (:comment_id, :user_id, :content)
        ");
        $stmt->execute([
            'comment_id' => $commentId,
            'user_id' => $userId,
            'content' => $content,
        ]);
    }

    public function isCommentBelongsToPost(int $commentId, int $postId): bool {
        $stmt = $this->db->prepare("
            SELECT 1 FROM comments WHERE id = :comment_id AND post_id = :post_id LIMIT 1
        ");
        $stmt->execute([
            'comment_id' => $commentId,
            'post_id' => $postId,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    public function getReplyRow(int $replyId): ?array {
        $this->ensureReplyTableExists();

        $stmt = $this->db->prepare("
            SELECT * FROM comment_replies WHERE id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $replyId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function addShare(int $postId, int $userId): bool {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO shares (user_id, post_id)
            VALUES (:user_id, :post_id)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'post_id' => $postId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function countShares(int $postId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM shares WHERE post_id = :post_id");
        $stmt->execute(['post_id' => $postId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Xóa bài: dọn object S3 + post_media trước khi xóa row posts.
     */
    public function delete(int $id): bool {
        require_once __DIR__ . '/../helpers/media.php';
        $mediaModel = new PostMedia();
        $rows = $mediaModel->getByPost($id);
        foreach ($rows as $row) {
            delete_stored_media((string) ($row['media_url'] ?? ''));
        }
        $mediaModel->deleteAllForPost($id);

        return parent::delete($id);
    }

    private function ensureReplyTableExists(): void {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS comment_replies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                comment_id INT NOT NULL,
                user_id INT NOT NULL,
                content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_comment_replies_comment_id (comment_id),
                INDEX idx_comment_replies_user_id (user_id),
                CONSTRAINT fk_comment_replies_comment
                    FOREIGN KEY (comment_id) REFERENCES comments(id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_comment_replies_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");
    }
}
