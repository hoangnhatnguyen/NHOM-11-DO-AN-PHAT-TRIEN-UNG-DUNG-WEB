<?php

// FIX env()
if (!function_exists('env')) {
    function env($key, $default = null) {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/core/Database.php';

try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS
    );

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
    exit;
}

header('Content-Type: application/json');

// =====================
// 🔥 PARAMS
// =====================

session_start();
$q = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? $_GET['tab'] ?? 'top';
     
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$scope = $_GET['filter_user'] 
    ?? $_GET['filter_source'] 
    ?? 'all';

$currentUserId = $_SESSION['user_id'] ?? null;
error_log("USER=" . ($currentUserId ?? 'NULL'));
error_log("SCOPE=" . $scope);
try {

    // =====================================================
    // 🔥 TRENDING
    // =====================================================
    if ($type === 'trending') {

        $stmt = $conn->prepare("
            SELECT h.name, COUNT(ph.post_id) as count
            FROM hashtags h
            JOIN post_hashtags ph ON h.id = ph.hashtag_id
            GROUP BY h.id
            ORDER BY count DESC
            LIMIT 10
        ");

        $stmt->execute();

        echo json_encode([
            "status" => "success",
            "data" => [
                "hashtags" => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        ]);
        exit;
    }



// ======================
// 👥 SUGGEST USERS
// ======================
if ($type === 'suggest_users') {

    $stmt = $conn->query("
        SELECT id, username
        FROM users
        ORDER BY RAND()
        LIMIT 5
    ");

    echo json_encode([
        "status" => "success",
        "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
    exit;
}

// ======================
// 🔥 TRENDING
// ======================
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
        "status" => "success",
        "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
    exit;
}


    // =====================
    // 🔥 EMPTY QUERY
    // =====================
    if ($q === '') {
        echo json_encode([
            "status" => "success",
            "data" => [
                "users" => [],
                "posts" => []
            ]
        ]);
        exit;
    }

    $keyword = "%" . strtolower($q) . "%";

    // =====================================================
    // 👤 USERS (TAB: PEOPLE)
    // =====================================================
    if ($type === 'users') {

        $sql = "
            SELECT id, username
            FROM users
            WHERE LOWER(username) LIKE :q
        ";

        $params = [
            ':q' => $keyword
        ];
         // ✅ FRIENDS OF FRIEND
        if ($scope === 'friends') {
            $sql .= " AND id IN (
                SELECT f2.following_id
                FROM follows f1
                JOIN follows f2 ON f1.following_id = f2.follower_id
                WHERE f1.follower_id = :uid
            )";
            $params[':uid'] = $currentUserId;
        }

        // ✅ FOLLOWING
        if ($scope === 'following') {
            $sql .= " AND id IN (
                SELECT following_id 
                FROM follows 
                WHERE follower_id = :uid
            )";
            $params[':uid'] = $currentUserId;
        }

        // ✅ FOLLOWERS (THÊM MỚI)
        if ($scope === 'followers') {
            $sql .= " AND id IN (
                SELECT follower_id 
                FROM follows 
                WHERE following_id = :uid
            )";
            $params[':uid'] = $currentUserId;
        }

        $sql .= " LIMIT 10";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            "status" => "success",
            "data" => [
                "users" => $stmt->fetchAll(PDO::FETCH_ASSOC),
                "posts" => []
            ]
        ]);
        exit;
    }

// =====================
// 📝 POSTS (TOP / LATEST)
// =====================

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

    $params = [];
    $params[':q'] = "%" . strtolower($q) . "%";
}

// FILTER giữ nguyên
if ($scope === 'following') {
    $sql .= " AND p.user_id IN (
        SELECT following_id 
        FROM follows 
        WHERE follower_id = :uid
    )";
    $params[':uid'] = $currentUserId;
}

// ✅ FOLLOWERS (THÊM)
if ($scope === 'followers') {
    $sql .= " AND p.user_id IN (
        SELECT follower_id 
        FROM follows 
        WHERE following_id = :uid
    )";
    $params[':uid'] = $currentUserId;
}


// DATE giữ nguyên
if (!empty($from)) {
    $sql .= " AND DATE(p.created_at) >= :from";
    $params[':from'] = $from;
}

if (!empty($to)) {
    $sql .= " AND DATE(p.created_at) <= :to";
    $params[':to'] = $to;
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);

echo json_encode([
    "status" => "success",
    "data" => [
        "users" => [],
        "posts" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]
]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}