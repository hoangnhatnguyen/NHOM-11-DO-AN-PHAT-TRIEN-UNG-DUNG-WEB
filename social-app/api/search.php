<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/core/Database.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string) ($_GET['q'] ?? ''));
$type = (string) ($_GET['type'] ?? $_GET['tab'] ?? 'top');
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$scope = (string) ($_GET['filter_user'] ?? $_GET['filter_source'] ?? 'all');

$currentUserId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;

try {
    $conn = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'database']);
    exit;
}

try {
    if ($type === 'trending') {
        $stmt = $conn->query("
            SELECT h.name, COUNT(ph.post_id) as count
            FROM hashtags h
            JOIN post_hashtags ph ON h.id = ph.hashtag_id
            GROUP BY h.id
            ORDER BY count DESC
            LIMIT 10
        ");
        echo json_encode([
            'status' => 'success',
            'data' => ['hashtags' => $stmt->fetchAll(PDO::FETCH_ASSOC)],
        ]);
        exit;
    }

    if ($type === 'suggest_users') {
        $stmt = $conn->query("
            SELECT id, username
            FROM users
            ORDER BY RAND()
            LIMIT 5
        ");
        echo json_encode([
            'status' => 'success',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
        exit;
    }

    if ($type === 'trending_full') {
        $stmt = $conn->query("
            SELECT h.name, COUNT(ph.post_id) as total
            FROM hashtags h
            JOIN post_hashtags ph ON h.id = ph.hashtag_id
            GROUP BY h.id
            ORDER BY total DESC
            LIMIT 5
        ");
        echo json_encode([
            'status' => 'success',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
        exit;
    }

    if ($q === '') {
        echo json_encode([
            'status' => 'success',
            'data' => ['users' => [], 'posts' => []],
        ]);
        exit;
    }

    $keyword = '%' . strtolower($q) . '%';

    if ($type === 'users') {
        $sql = "
            SELECT id, username
            FROM users
            WHERE LOWER(username) LIKE :q
        ";
        $params = [':q' => $keyword];

        if ($scope === 'friends' && $currentUserId) {
            $sql .= ' AND id IN (
                SELECT f2.following_id
                FROM follows f1
                JOIN follows f2 ON f1.following_id = f2.follower_id
                WHERE f1.follower_id = :uid
            )';
            $params[':uid'] = $currentUserId;
        } elseif ($scope === 'friends') {
            $sql .= ' AND 1=0';
        }

        if ($scope === 'following' && $currentUserId) {
            $sql .= ' AND id IN (
                SELECT following_id
                FROM follows
                WHERE follower_id = :uid
            )';
            $params[':uid'] = $currentUserId;
        } elseif ($scope === 'following') {
            $sql .= ' AND 1=0';
        }

        if ($scope === 'followers' && $currentUserId) {
            $sql .= ' AND id IN (
                SELECT follower_id
                FROM follows
                WHERE following_id = :uid
            )';
            $params[':uid'] = $currentUserId;
        } elseif ($scope === 'followers') {
            $sql .= ' AND 1=0';
        }

        $sql .= ' LIMIT 10';

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'users' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'posts' => [],
            ],
        ]);
        exit;
    }

    $params = [];
    $isHashtag = isset($q[0]) && $q[0] === '#';

    if ($isHashtag) {
        $tag = ltrim($q, '#');
        $sql = "
            SELECT
                p.id,
                p.content,
                p.created_at,
                u.username,
                u.avatar_url
            FROM hashtags h
            JOIN post_hashtags ph ON h.id = ph.hashtag_id
            JOIN posts p ON p.id = ph.post_id
            JOIN users u ON u.id = p.user_id
            WHERE LOWER(h.name) = :tag
        ";
        $params['tag'] = strtolower($tag);
    } else {
        $sql = "
            SELECT
                p.id,
                p.content,
                p.created_at,
                u.username,
                u.avatar_url
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE LOWER(p.content) LIKE :q
        ";
        $params[':q'] = '%' . strtolower($q) . '%';
    }

    if ($scope === 'following' && $currentUserId) {
        $sql .= ' AND p.user_id IN (
            SELECT following_id
            FROM follows
            WHERE follower_id = :uid
        )';
        $params[':uid'] = $currentUserId;
    } elseif ($scope === 'following') {
        $sql .= ' AND 1=0';
    }

    if ($scope === 'followers' && $currentUserId) {
        $sql .= ' AND p.user_id IN (
            SELECT follower_id
            FROM follows
            WHERE following_id = :uid
        )';
        $params[':uid'] = $currentUserId;
    } elseif ($scope === 'followers') {
        $sql .= ' AND 1=0';
    }

    if (!empty($from)) {
        $sql .= ' AND DATE(p.created_at) >= :from';
        $params[':from'] = $from;
    }

    if (!empty($to)) {
        $sql .= ' AND DATE(p.created_at) <= :to';
        $params[':to'] = $to;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'users' => [],
            'posts' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'search_failed',
    ]);
}
