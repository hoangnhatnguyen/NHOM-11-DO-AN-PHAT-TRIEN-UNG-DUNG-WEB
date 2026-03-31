<?php
require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../models/Hashtag.php';
require_once __DIR__ . '/../models/PostHashtag.php';
require_once __DIR__ . '/../models/PostMedia.php';

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

        $content = trim($_POST['content'] ?? '');
        $visible = $_POST['privacy'] ?? 'public';

        $postModel = new Post();
        $postId = $postModel->create([
            'user_id' => $_SESSION['user']['id'],
            'content' => $content,
            'visible' => $visible
        ]);

        // xử lý hashtag
        preg_match_all('/#(\w+)/', $content, $matches);
        $hashtagModel = new Hashtag();
        $postHashtag = new PostHashtag();

        foreach ($matches[1] as $tag) {
            $tagId = $hashtagModel->findOrCreate($tag);
            $postHashtag->attach($postId, $tagId);
        }

        // upload ảnh → public/media (khớp URL trong view: /public/media/<tên file>)
        if (!empty($_FILES['media']['name'][0])) {
            $mediaModel = new PostMedia();
            $uploadDir = APP_ROOT . 'public/media/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($_FILES['media']['tmp_name'] as $key => $tmp) {
                $name = time() . '_' . $_FILES['media']['name'][$key];
                move_uploaded_file($tmp, $uploadDir . $name);
                $mediaModel->addMedia($postId, $name);
            }
        }

        $this->redirect('/');
    }

    public function detail($id) {
        $this->requireAuth();

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

        $this->render('post/detail', [
            'title' => 'Bài viết — ' . APP_NAME,
            'post' => $post,
            'media' => $media,
            'commentsTree' => $commentsTree,
            'currentUser' => $_SESSION['user'] ?? null,
            'csrfToken' => $this->csrfToken(),
        ], 'feed');
    }

    public function like($id) {
        $this->requireAuth();
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            die('CSRF invalid');
        }

        $postId = (int) $id;
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        (new Post())->toggleLike($postId, $userId);
        
        $isAjax = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        if ($isAjax) {
            $postModel = new Post();
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
        
        $isAjax = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        if ($isAjax) {
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
        (new Post())->addShare($postId, $userId);
        
        $isAjax = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        if ($isAjax) {
            $postModel = new Post();
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
        $postModel = new Post();
        if ($content !== '') {
            $postModel->addComment($postId, $userId, $content);
        }

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'kind' => 'comment',
                'postId' => $postId,
                'comment_count' => $postModel->countComments($postId),
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
        if ($content === '' || !$postModel->isCommentBelongsToPost($parentCommentId, $postId)) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => false,
                    'message' => 'Nội dung phản hồi không hợp lệ.',
                ]);
                exit;
            }
            $this->redirect('/post/' . $postId . '#comment-' . $parentCommentId);
            return;
        }

        // Store nested reply directly into `comments` with parent_id/level.
        $postModel->addReplyToComment($postId, $parentCommentId, $userId, $content);
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'kind' => 'reply',
                'postId' => $postId,
                'parent_comment_id' => $parentCommentId,
                'comment_count' => $postModel->countComments($postId),
            ]);
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

        $this->render('post/edit', [
            'title' => 'Chỉnh sửa bài viết — ' . APP_NAME,
            'post' => $post,
            'media' => $media,
            'currentUser' => $_SESSION['user'] ?? null,
            'csrfToken' => $this->csrfToken(),
        ], 'feed');
    }

    public function update($id) {
        $this->requireAuth();
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            die('CSRF invalid');
        }

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

        $postModel = new Post();
        $visible = (string) ($_POST['privacy'] ?? ($post['visible'] ?? 'public'));
        if (!in_array($visible, ['public', 'followers', 'private'], true)) {
            $visible = 'public';
        }
        $postModel->updatePost($postId, (string) ($_POST['content'] ?? ''), $visible);

        $mediaModel = new PostMedia();
        $removeMediaIds = $_POST['remove_media_ids'] ?? [];
        if (!is_array($removeMediaIds)) {
            $removeMediaIds = [];
        }
        $removeMediaIds = array_values(array_unique(array_map('intval', $removeMediaIds)));

        if (!empty($removeMediaIds)) {
            $mediaRows = $mediaModel->getByIdsForPost($postId, $removeMediaIds);
            $mediaModel->deleteByIdsForPost($postId, $removeMediaIds);
            foreach ($mediaRows as $mediaRow) {
                $this->removeMediaFileFromDisk((string) ($mediaRow['media_url'] ?? ''));
            }
        }

        if (!empty($_FILES['media']['name'][0])) {
            $uploadDir = APP_ROOT . 'public/media/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($_FILES['media']['tmp_name'] as $key => $tmp) {
                if (!is_uploaded_file($tmp)) {
                    continue;
                }
                $name = time() . '_' . $_FILES['media']['name'][$key];
                if (move_uploaded_file($tmp, $uploadDir . $name)) {
                    $mediaModel->addMedia($postId, $name);
                }
            }
        }

        $this->redirect('/post/' . $postId);
    }

    public function delete($id) {
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

        (new Post())->delete($postId);
        $this->redirect('/');
    }

    private function removeMediaFileFromDisk(string $mediaUrl): void {
        $mediaUrl = trim(str_replace('\\', '/', $mediaUrl));
        if ($mediaUrl === '') {
            return;
        }

        $relative = ltrim($mediaUrl, '/');
        $fullPath = (strpos($relative, 'media/') === 0)
            ? APP_ROOT . 'public/' . $relative
            : APP_ROOT . 'public/media/' . $relative;

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}