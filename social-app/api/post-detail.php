<?php
// API endpoint to fetch post detail for modal
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'msg' => 'Unauthorized']);
    exit;
}

try {
    // Load config & helpers
    require_once __DIR__ . '/../config/env.php';
    require_once __DIR__ . '/../config/constants.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../app/core/Database.php';
    require_once __DIR__ . '/../app/core/BaseModel.php';
    require_once __DIR__ . '/../app/core/Avatar.php';
    require_once __DIR__ . '/../app/helpers/media.php';
    require_once __DIR__ . '/../app/helpers/notification_helper.php';

    $postId = (int) ($_GET['id'] ?? 0);
    $viewerId = (int) ($_SESSION['user']['id'] ?? 0);

    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'msg' => 'Invalid post ID']);
        exit;
    }

    // Load models
    require_once __DIR__ . '/../app/models/Post.php';
    require_once __DIR__ . '/../app/models/PostMedia.php';
    require_once __DIR__ . '/../app/models/PostHashtag.php';

    $postModel = new Post();
    
    // Try to get post detail
    $post = $postModel->findDetailWithStats($postId, $viewerId);

    if (!$post) {
        // Fallback: get basic post info
        $conn = new Database();
        $pdo = $conn->connect();
        $stmt = $pdo->prepare('
            SELECT p.*, u.name as user_name, u.avatar_url
            FROM posts p
            LEFT JOIN users u ON u.id = p.user_id
            WHERE p.id = ? LIMIT 1
        ');
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$post) {
            http_response_code(404);
            echo json_encode(['success' => false, 'msg' => 'Post not found']);
            exit;
        }
        
        // Add stats
        $post['like_count'] = $postModel->countLikes($postId);
        $post['comment_count'] = $postModel->countComments($postId);
        $post['share_count'] = $postModel->countShares($postId);
        $post['is_liked'] = $postModel->isLiked($postId, $viewerId);
        $post['is_saved'] = $postModel->isSaved($postId, $viewerId);
    }

    // Get media
    $postMediaModel = new PostMedia();
    $media = $postMediaModel->getByPost($postId) ?? [];

    // Get hashtags
    $postHashtagModel = new PostHashtag();
    $hashtag_names = $postHashtagModel->getTagNamesByPostId($postId) ?? [];

    // Get comments tree (used by detail.php template)
    $commentsTree = $postModel->getCommentTreeByPost($postId) ?? [];

    $csrfToken = $_SESSION['_csrf_token'] ?? '';
    $currentUser = $_SESSION['user'] ?? null;
    $baseUrl = BASE_URL;
    $profileBaseUrl = preg_replace('#/api$#', '', rtrim((string) BASE_URL, '/')) ?: '';

    // Render post detail template (reuse existing detail view)
    ob_start();
    include __DIR__ . '/../app/views/post/detail.php';
    $html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    error_log("Post detail API error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo json_encode(['success' => false, 'msg' => 'Server error: ' . $e->getMessage()]);
}
