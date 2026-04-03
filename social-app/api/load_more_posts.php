<?php
// Load more posts with AJAX
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'success' => false]);
    exit;
}

try {
    // Load config & models in correct order
    require_once __DIR__ . '/../config/env.php';
    require_once __DIR__ . '/../config/constants.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../app/core/Database.php';
    require_once __DIR__ . '/../app/core/BaseModel.php';
    require_once __DIR__ . '/../app/models/PostMedia.php';
    require_once __DIR__ . '/../app/models/PostHashtag.php';
    require_once __DIR__ . '/../app/models/Post.php';
    require_once __DIR__ . '/../app/core/Avatar.php';
    require_once __DIR__ . '/../app/helpers/media.php';
    require_once __DIR__ . '/../app/helpers/notification_helper.php';

    $viewerId = (int) $_SESSION['user']['id'];
    $offset = (int) ($_POST['offset'] ?? $_GET['offset'] ?? 0);
    $tab = (string) ($_POST['tab'] ?? $_GET['tab'] ?? 'foryou');
    $limit = 5;

    $postModel = new Post();

    $posts = $tab === 'following'
        ? $postModel->getFeedFollowingPaginated($viewerId, $limit, $offset)
        : $postModel->getFeedPaginated($viewerId, $limit, $offset);

    // Return posts as JSON data, not HTML
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'count' => count($posts),
        'hasMore' => count($posts) === $limit
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    $errorMsg = $e->getMessage();
    $errorTrace = $e->getTraceAsString();
    error_log("Load more posts error: " . $errorMsg . "\n" . $errorTrace);
    
    echo json_encode([
        'error' => $errorMsg,
        'success' => false,
        'trace' => $errorTrace
    ]);
}
