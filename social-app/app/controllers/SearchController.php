<?php

require_once __DIR__ . '/../models/Post.php';

class SearchController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        $currentUser = $_SESSION['user'];
        $currentUserId = (int) ($currentUser['id'] ?? 0);

        $conn = Database::getInstance()->getConnection();

        $qRaw = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $q = $qRaw !== '' ? $qRaw : null;
        $tab = (string) ($_GET['tab'] ?? 'top');
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $source = (string) ($_GET['filter_source'] ?? 'all');
        $filterUser = (string) ($_GET['filter_user'] ?? 'all');

        $posts = [];
        $users = [];

        if ($q !== null && $q !== '') {
            $qTrim = $q;
            $isHashtag = isset($qTrim[0]) && $qTrim[0] === '#';

            $params = [];

            if ($isHashtag) {
                $tag = ltrim($qTrim, '#');

                $sql = "
                    SELECT p.*, u.username
                    FROM hashtags h
                    JOIN post_hashtags ph ON h.id = ph.hashtag_id
                    JOIN posts p ON p.id = ph.post_id
                    JOIN users u ON p.user_id = u.id
                    WHERE LOWER(h.name) = LOWER(:tag)
                ";

                $params['tag'] = $tag;
            } else {
                $sql = "
                    SELECT p.*, u.username
                    FROM posts p
                    JOIN users u ON p.user_id = u.id
                    WHERE p.content LIKE :q
                ";

                $params['q'] = '%' . $qTrim . '%';
            }

            if ($source === 'following') {
                if ($currentUserId <= 0) {
                    $sql .= ' AND 1=0';
                } else {
                    $sql .= ' AND p.user_id IN (
                        SELECT following_id
                        FROM follows
                        WHERE follower_id = :currentUserId
                    )';
                    $params['currentUserId'] = $currentUserId;
                }
            }

            if ($from) {
                $sql .= ' AND DATE(p.created_at) >= :from';
                $params['from'] = $from;
            }

            if ($to) {
                $sql .= ' AND DATE(p.created_at) <= :to';
                $params['to'] = $to;
            }

            $sql .= ' ORDER BY p.created_at DESC';

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($posts as &$p) {
                $p['user_name'] = $p['username'];
                $p['author_name'] = $p['username'];
            }
            unset($p);

            if ($posts !== []) {
                $posts = (new Post())->hydrateListWithInteractionStats($posts, $currentUserId);
            }

            if ($tab === 'top') {
                $sql = "
                    SELECT u.id, u.username, COUNT(f.following_id) as followers
                    FROM users u
                    LEFT JOIN follows f ON f.following_id = u.id
                    WHERE u.username LIKE :q
                ";

                $paramsUsers = ['q' => '%' . $qTrim . '%'];

                if ($filterUser === 'following') {
                    if ($currentUserId <= 0) {
                        $sql .= ' AND 1=0';
                    } else {
                        $sql .= ' AND u.id IN (
                            SELECT following_id
                            FROM follows
                            WHERE follower_id = :currentUserId
                        )';
                        $paramsUsers['currentUserId'] = $currentUserId;
                    }
                }

                if ($filterUser === 'friends') {
                    if ($currentUserId <= 0) {
                        $sql .= ' AND 1=0';
                    } else {
                        $sql .= ' AND u.id IN (
                            SELECT f2.following_id
                            FROM follows f1
                            JOIN follows f2 ON f1.following_id = f2.follower_id
                            WHERE f1.follower_id = :uid
                        )';
                        $paramsUsers['uid'] = $currentUserId;
                    }
                }

                $sql .= ' GROUP BY u.id ORDER BY followers DESC LIMIT 3';
            } else {
                $sql = "
                    SELECT u.id, u.username
                    FROM users u
                    WHERE u.username LIKE :q
                ";

                $paramsUsers = ['q' => '%' . $qTrim . '%'];

                if ($filterUser === 'following') {
                    $sql .= ' AND u.id IN (
                        SELECT following_id
                        FROM follows
                        WHERE follower_id = :uid
                    )';
                    $paramsUsers['uid'] = $currentUserId;
                }

                if ($filterUser === 'friends') {
                    $sql .= ' AND u.id IN (
                        SELECT f2.following_id
                        FROM follows f1
                        JOIN follows f2 ON f1.following_id = f2.follower_id
                        WHERE f1.follower_id = :uid
                    )';
                    $paramsUsers['uid'] = $currentUserId;
                }

                $sql .= ' LIMIT 20';
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute($paramsUsers);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->render('search/results', [
            'title' => 'Tìm kiếm — ' . APP_NAME,
            'currentUser' => $currentUser,
            'csrfToken' => $this->csrfToken(),
            'activeMenu' => 'search',
            'posts' => $posts,
            'users' => $users,
            'pageScripts' => [
                ['src' => BASE_URL . '/public/js/search.js'],
                ['src' => BASE_URL . '/public/js/right_widgets.js'],
            ],
        ], 'main');
    }
}
