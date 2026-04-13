<?php
require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../models/PostHashtag.php';
require_once __DIR__ . '/../models/PostMedia.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Follow.php';
require_once __DIR__ . '/../models/Block.php';
require_once __DIR__ . '/../helpers/notification_helper.php';
require_once __DIR__ . '/../helpers/hashtag_helper.php';
require_once __DIR__ . '/../helpers/post_media_upload.php';

class PostController extends BaseController {

    public function create() {
        $this->requireAuth();
        $this->render('post/create', [
            'title' => 'Tạo bài viết'
        ]);
    }

    public function store() {
        $this->requireAuth();

        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            die('CSRF invalid');
        }

        $rawInput = trim((string) ($_POST['content'] ?? ''));
        $parsed = parse_post_content_hashtags($rawInput);
        $content = $parsed['plain'];
        $visible = $_POST['privacy'] ?? 'public';
        $hasUpload = has_post_media_upload();
        if ($content === '' && !$hasUpload) {
            $this->redirect('/?composer_error=empty');
            return;
        }

        $postModel = new Post();
        $postId = $postModel->create([
            'user_id' => $_SESSION['user']['id'],
            'content' => $content,
            'visible' => $visible
        ]);

        (new PostHashtag())->replaceForPost($postId, $parsed['tags']);

        process_post_uploaded_media_files($postId, new PostMedia());

        $authorId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($authorId > 0 && $content !== '') {
            notify_for_post_content_mentions(notification_db(), $postId, $authorId, $content);
        }

        $this->redirect('/');
    }

    public function detail($id) {
        $this->requireAuth();

        // Sync avatar_url từ database vào session
        $this->syncUserSession();

        $postId = (int) $id;
        $viewerId = (int) ($_SESSION['user']['id'] ?? 0);
        $postModel = new Post();
        $post = $postModel->findDetailWithStats($postId, $viewerId);
        if ($post === null) {
            http_response_code(404);
            echo 'Không tìm thấy bài viết';
            return;
        }

        $media = (new PostMedia())->getByPost($postId);
        $commentsTree = $postModel->getCommentTreeByPost($postId);
  //KIỂM TRA QUYỀN COMMENT ---
        $canComment = true;
        $commentGuard = $this->guardCommentPermission($post, $viewerId);
        if ($commentGuard !== null) {
            $canComment = false; // Nếu bị chặn hoặc không phải bạn chung -> set false
        }
        $this->render('post/detail', [
            'title' => 'Bài viết — ' . APP_NAME,
            'post' => $post,
            'media' => $media,
            'commentsTree' => $commentsTree,
            'currentUser' => $_SESSION['user'] ?? null,
            'csrfToken' => $this->csrfToken(),
                        'canComment' => $canComment,
        ], 'feed');
    }

    public function like($id) {
        $this->requireAuth();
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'msg' => 'csrf_invalid']);
                exit;
            }
            die('CSRF invalid');
        }

        $postId = (int) $id;
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $postModel = new Post();
        $nowLiked = $postModel->toggleLike($postId, $userId);
        if ($nowLiked) {
            $conn = notification_db();
            $stmt = $conn->prepare('SELECT user_id FROM posts WHERE id = ? LIMIT 1');
            $stmt->execute([$postId]);
            $ownerId = (int) $stmt->fetchColumn();
            if ($ownerId > 0 && $ownerId !== $userId) {
                create_notification($conn, $ownerId, $userId, 'like', $postId, $postId);
            }
        }

        if ($this->isAjaxRequest()) {
            $likeCount = $postModel->countLikes($postId);
            $isLiked = $postModel->isLiked($postId, $userId);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'kind' => 'like',
                'postId' => $postId,
                'like_count' => $likeCount,
                'is_liked' => $isLiked,
            ]);
            exit;
        }

        $this->redirect('/post/' . $postId);
    }

    public function save($id) {
        $this->requireAuth();
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            die('CSRF invalid');
        }

        $postId = (int) $id;
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        (new Post())->toggleSave($postId, $userId);

        if ($this->isAjaxRequest()) {
            $postModel = new Post();
            $saveCount = $postModel->countSavedPosts($postId);
            $isSaved = $postModel->isSaved($postId, $userId);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'kind' => 'save',
                'postId' => $postId,
                'save_count' => $saveCount,
                'is_saved' => $isSaved,
            ]);
            exit;
        }

        $this->redirect('/post/' . $postId);
    }

    public function share($id) {
        $this->requireAuth();
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            die('CSRF invalid');
        }

        $postId = (int) $id;
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $postModel = new Post();
        $newShare = $postModel->addShare($postId, $userId);
        if ($newShare) {
            $conn = notification_db();
            $stmt = $conn->prepare('SELECT user_id FROM posts WHERE id = ? LIMIT 1');
            $stmt->execute([$postId]);
            $ownerId = (int) $stmt->fetchColumn();
            if ($ownerId > 0 && $ownerId !== $userId) {
                create_notification($conn, $ownerId, $userId, 'share', $postId, $postId);
            }
        }

        if ($this->isAjaxRequest()) {
            $shareCount = $postModel->countShares($postId);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'kind' => 'share',
                'postId' => $postId,
                'share_count' => $shareCount,
            ]);
            exit;
        }

        $this->redirect('/post/' . $postId);
    }

    public function comment($id) {
        $this->requireAuth();
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            die('CSRF invalid');
        }

        $postId = (int) $id;
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $content = trim((string) ($_POST['content'] ?? ''));
        $commentId = null;
        $postModel = new Post();

        $post = $postModel->findById($postId);
        if ($post === null) {
            http_response_code(404);
            echo 'Không tìm thấy bài viết';
            return;
        }

        $commentGuard = $this->guardCommentPermission($post, $userId);
        if ($commentGuard !== null) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($commentGuard);
                exit;
            }
            http_response_code(403);
            echo '403 Forbidden';
            return;
        }
        
        if ($content !== '') {
            $commentId = $postModel->addComment($postId, $userId, $content);
            notify_for_new_comment(notification_db(), $postId, $userId, $commentId, $content);
        }
        
        // Check if AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'ok' => true,
                'comment_id' => $commentId,
                'message' => 'Bình luận thành công'
            ]);
            exit;
        }
        
        $this->redirect('/post/' . $postId);
    }

    public function reply($postId, $commentId) {
        $this->requireAuth();
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            die('CSRF invalid');
        }

        $postId = (int) $postId;
        $parentCommentId = (int) $commentId;
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $content = trim((string) ($_POST['content'] ?? ''));

        $postModel = new Post();
        $replyId = null;
        $error = null;

        $post = $postModel->findById($postId);
        if ($post === null) {
            http_response_code(404);
            echo 'Không tìm thấy bài viết';
            return;
        }

        $commentGuard = $this->guardCommentPermission($post, $userId);
        if ($commentGuard !== null) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($commentGuard);
                exit;
            }
            http_response_code(403);
            echo '403 Forbidden';
            return;
        }

        if ($content !== '' && $postModel->isCommentBelongsToPost($parentCommentId, $postId)) {
            $replyId = $postModel->addReplyToComment($postId, $parentCommentId, $userId, $content);
            if ($replyId === null) {
                $error = 'Không thể gửi trả lời.';
            } else {
                notify_for_new_comment(notification_db(), $postId, $userId, $replyId, $content);
            }
        }

        // Check if AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            if ($error) {
                echo json_encode([
                    'status' => 'error',
                    'ok' => false,
                    'error' => $error
                ]);
            } else {
                echo json_encode([
                    'status' => 'success',
                    'ok' => true,
                    'comment_id' => $replyId,
                    'reply_id' => $replyId,
                    'message' => 'Trả lời thành công'
                ]);
            }
            exit;
        }

        $this->redirect('/post/' . $postId . '#comment-' . $parentCommentId);
    }

    public function edit($id) {
        $this->requireAuth();
        $postId = (int) $id;
        $post = (new Post())->findById($postId);
        if ($post === null) {
            http_response_code(404);
            echo 'Không tìm thấy bài viết';
            return;
        }

        $currentUserId = (int) ($_SESSION['user']['id'] ?? 0);
        if ((int) ($post['user_id'] ?? 0) !== $currentUserId) {
            http_response_code(403);
            echo '403 Forbidden';
            return;
        }

        $media = (new PostMedia())->getByPost($postId);
        $hashtags = (new PostHashtag())->getTagNamesByPostId($postId);
        $editContent = compose_post_content_for_editor((string) ($post['content'] ?? ''), $hashtags);

        $this->render('post/edit', [
            'title' => 'Chỉnh sửa bài viết — ' . APP_NAME,
            'post' => $post,
            'media' => $media,
            'editContent' => $editContent,
            'currentUser' => $_SESSION['user'] ?? null,
            'csrfToken' => $this->csrfToken(),
        ], 'feed');
    }

    public function update($id) {
        $this->requireAuth();
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'msg' => 'csrf_invalid']);
                exit;
            }
            die('CSRF invalid');
        }

        $postId = (int) $id;
        $post = (new Post())->findById($postId);
        if ($post === null) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'msg' => 'not_found']);
                exit;
            }
            http_response_code(404);
            echo 'Không tìm thấy bài viết';
            return;
        }

        $currentUserId = (int) ($_SESSION['user']['id'] ?? 0);
        if ((int) ($post['user_id'] ?? 0) !== $currentUserId) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'msg' => 'forbidden']);
                exit;
            }
            http_response_code(403);
            echo '403 Forbidden';
            return;
        }

        $postModel = new Post();
        $visible = (string) ($_POST['privacy'] ?? ($post['visible'] ?? 'public'));
        if (!in_array($visible, ['public', 'followers', 'private'], true)) {
            $visible = 'public';
        }
        $rawUpdate = trim((string) ($_POST['content'] ?? ''));
        $parsedUpdate = parse_post_content_hashtags($rawUpdate);

        $mediaModel = new PostMedia();
        $removeMediaIds = $_POST['remove_media_ids'] ?? [];
        if (!is_array($removeMediaIds)) {
            $removeMediaIds = [];
        }
        $removeMediaIds = array_values(array_unique(array_map('intval', $removeMediaIds)));

        $existingMedia = $mediaModel->getByPost($postId);
        $removeSet = array_flip($removeMediaIds);
        $remainingAfterRemove = [];
        foreach ($existingMedia as $m) {
            $mid = (int) ($m['id'] ?? 0);
            if ($mid > 0 && !isset($removeSet[$mid])) {
                $remainingAfterRemove[] = $m;
            }
        }
        $hasExistingMedia = !empty($remainingAfterRemove);
        $hasNewUpload = has_post_media_upload();
        if ($parsedUpdate['plain'] === '' && !$hasExistingMedia && !$hasNewUpload) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'msg' => 'empty']);
                exit;
            }
            $this->redirect('/post/edit/' . $postId . '?error=empty');
            return;
        }

        $postModel->updatePost($postId, $parsedUpdate['plain'], $visible);
        (new PostHashtag())->replaceForPost($postId, $parsedUpdate['tags']);

        if (!empty($removeMediaIds)) {
            $mediaRows = $mediaModel->getByIdsForPost($postId, $removeMediaIds);
            $mediaModel->deleteByIdsForPost($postId, $removeMediaIds);

            require_once __DIR__ . '/../helpers/media.php';
            foreach ($mediaRows as $mediaRow) {
                delete_stored_media((string) ($mediaRow['media_url'] ?? ''));
            }
        }

        process_post_uploaded_media_files($postId, $mediaModel);

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'msg' => 'updated',
                'postId' => $postId,
            ]);
            exit;
        }

        $this->redirect('/post/edit/' . $postId . '?saved=1');
    }

   public function delete($id) {
        $this->requireAuth();

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            http_response_code(405);
            echo '405 Method Not Allowed';
            return;
        }

        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'msg' => 'csrf_invalid']);
                exit;
            }
            die('CSRF invalid');
        }

        $postId = (int) $id;
        $post = (new Post())->findById($postId);
        if ($post === null) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'msg' => 'not_found']);
                exit;
            }
            http_response_code(404);
            echo 'Không tìm thấy bài viết';
            return;
        }

        $currentUserId = (int) ($_SESSION['user']['id'] ?? 0);
        if ((int) ($post['user_id'] ?? 0) !== $currentUserId) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'msg' => 'forbidden']);
                exit;
            }
            http_response_code(403);
            echo '403 Forbidden';
            return;
        }

        (new Post())->delete($postId);

        // NẾU LÀ AJAX -> TRẢ VỀ JSON THAY VÌ REDIRECT
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'postId' => $postId]);
            exit;
        }

        $this->redirect('/');
    }

    /**
     * Thử S3 trước; nếu SDK/credentials lỗi hoặc upload fail → lưu ảnh dưới public/media/posts/.
     */
    private function processUploadedPostMedia(int $postId, PostMedia $mediaModel): void {
        if (empty($_FILES['media']['name'][0])) {
            return;
        }
        require_once __DIR__ . '/../helpers/media.php';
        $s3Service = new S3Service();
        $s3SkipLogged = false;

        foreach ($_FILES['media']['tmp_name'] as $i => $tmpFile) {
            $err = (int) ($_FILES['media']['error'][$i] ?? UPLOAD_ERR_OK);
            if ($err !== UPLOAD_ERR_OK) {
                error_log("Post media upload PHP error #{$err} for post {$postId} index {$i}");
                continue;
            }
            $name = (string) ($_FILES['media']['name'][$i] ?? '');
            if ($name === '' || !is_uploaded_file($tmpFile)) {
                continue;
            }

            $savedPath = null;
            if ($s3Service->isReady()) {
                $key = $s3Service->generatePostMediaKey($postId, $name);
                if ($s3Service->uploadFile($tmpFile, $key)) {
                    $savedPath = $key;
                } elseif ($s3Service->getLastError() !== '') {
                    error_log("Post {$postId} S3 upload failed: " . $s3Service->getLastError());
                }
            } elseif (!$s3SkipLogged) {
                $s3SkipLogged = true;
                error_log('Post media: bỏ qua S3 — ' . $s3Service->getNotReadyReason());
            }
            if ($savedPath === null) {
                $savedPath = save_uploaded_post_image_local($postId, $tmpFile, $name);
            }
            if ($savedPath !== null) {
                $mediaModel->addMedia($postId, $savedPath);
            }
        }
    }

    /**
     * @param array<int, string> $hashtags
     */
    private function composeContentForEditor(string $plainContent, array $hashtags): string {
        $plainContent = trim($plainContent);
        $tags = [];
        foreach ($hashtags as $tag) {
            $t = trim((string) $tag);
            if ($t === '') {
                continue;
            }
            $tags[] = '#' . ltrim($t, '#');
        }
        if (empty($tags)) {
            return $plainContent;
        }
        return trim($plainContent . "\n" . implode(' ', $tags));
    }

    public function guardCommentPermission(array $post, int $viewerId): ?array {
        $ownerId = (int) ($post['user_id'] ?? 0);
        if ($ownerId <= 0 || $viewerId <= 0 || $ownerId === $viewerId) {
            return null;
        }

        $userModel = new User();
        $followModel = new Follow();
        $blockModel = new Block();

        if ($blockModel->isBlocked($viewerId, $ownerId) || $blockModel->isBlocked($ownerId, $viewerId)) {
            return [
                'status' => 'error',
                'ok' => false,
                'error' => 'blocked_relationship',
                'message' => 'Không thể bình luận do quan hệ chặn.',
            ];
        }

        $owner = $userModel->findById($ownerId);
        $privacyComment = (string) ($owner['privacy_comment'] ?? 'everyone');
        if ($privacyComment === 'mutual' && !$followModel->isMutualFollow($viewerId, $ownerId)) {
            return [
                'status' => 'error',
                'ok' => false,
                'error' => 'comment_privacy_restricted',
                'message' => 'Người dùng này chỉ cho phép bạn chung bình luận.',
            ];
        }

        return null;
    }
}
