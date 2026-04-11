<?php
// Render post cards from JSON posts data — cùng phiên với index.php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/session.php';
header('Content-Type: text/html');

// Check if user is logged in
if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

try {
    // Load config & helpers
    require_once __DIR__ . '/../config/constants.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../app/core/Database.php';
    require_once __DIR__ . '/../app/core/BaseModel.php';
    require_once __DIR__ . '/../app/core/Avatar.php';
    require_once __DIR__ . '/../app/helpers/media.php';
    require_once __DIR__ . '/../app/helpers/notification_helper.php';

    $currentUser = $_SESSION['user'] ?? null;
    $csrfToken = $_SESSION['_csrf_token'] ?? '';

    // Get base URL from request (passed from frontend) - for media/avatar URLs
    $baseUrl = (string) ($_POST['base_url'] ?? $_GET['base_url'] ?? '');
    if (empty($baseUrl)) {
        $baseUrl = BASE_URL;
    }
    
    // For action URLs, we need to use the request's base path
    // Since forms use relative URLs like /post/5/like, we need root path
    // If baseUrl has protocol, extract root path
    $actionBaseUrl = BASE_URL;
    if (strpos($baseUrl, '://') !== false) {
        // baseUrl has protocol (http://localhost:8188/)
        // Extract just the path part or use /
        $actionBaseUrl = '/';
    }

    // Get posts JSON from request
    $postsJson = $_POST['posts_json'] ?? $_GET['posts_json'] ?? '[]';
    $posts = json_decode($postsJson, true);

    if (!is_array($posts) || empty($posts)) {
        http_response_code(400);
        echo 'Invalid posts data';
        exit;
    }

    // Render posts HTML using the template
    $html = '';
    foreach ($posts as $post) {
        ob_start();
        include __DIR__ . '/../app/views/partials/post_card.php';
        $html .= ob_get_clean();
    }

    http_response_code(200);
    echo $html;
} catch (Throwable $e) {
    http_response_code(500);
    error_log("Render posts error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo 'Error rendering posts';
}
