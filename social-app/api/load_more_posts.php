<?php
// Load more posts with AJAX — cùng phiên với index.php
// Bắt buộc: Composer autoload (AWS SDK) — media_public_src() presign S3 cần Aws\S3\S3Client.
// Không có file này, render post_card trong API trả src="" cho ảnh S3 dù SSR trên index.php vẫn đúng.
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/session.php';

// Check if user is logged in
if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Unauthorized', 'success' => false]);
    exit;
}

try {
    // Load config & models in correct order
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

    // Render HTML trên server — tránh gửi JSON qua form-urlencoded (dễ hỏng URL media, vd. ký tự +).
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrfToken = (string) $_SESSION['_csrf_token'];
    $currentUser = $_SESSION['user'] ?? null;

    $html = '';
    foreach ($posts as $post) {
        ob_start();
        include __DIR__ . '/../app/views/partials/post_card.php';
        $html .= ob_get_clean();
    }

    $n = count($posts);
    // Trả fragment HTML trực tiếp (không bọc JSON) — tránh escape/parse lỗi với URL presign S3 dài.
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Load-More-Count: ' . $n);
    header('X-Load-More-Has-More: ' . ($n === $limit ? '1' : '0'));
    echo $html;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $errorMsg = $e->getMessage();
    $errorTrace = $e->getTraceAsString();
    error_log("Load more posts error: " . $errorMsg . "\n" . $errorTrace);

    echo json_encode([
        'error' => $errorMsg,
        'success' => false,
        'trace' => $errorTrace,
    ]);
}
