<?php
require_once __DIR__ . '/../../models/Post.php';
require_once __DIR__ . '/../../models/PostHashtag.php';
require_once __DIR__ . '/../../models/PostMedia.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../helpers/hashtag_helper.php';
require_once __DIR__ . '/../../helpers/media.php';
require_once __DIR__ . '/../../helpers/post_media_upload.php';

class AdminPostController extends BaseController {

    public function index(): void {
        $this->requireAdmin();
        $keyword = trim((string) ($_GET['keyword'] ?? ''));
        $field = (string) ($_GET['field'] ?? 'content');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(50, max(5, (int) ($_GET['per_page'] ?? 15)));

        $postModel = new Post();
        $filter = ['keyword' => $keyword, 'field' => $field];
        $total = $postModel->countAdminPosts($filter);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $filter['limit'] = $perPage;
        $filter['offset'] = $offset;
        $posts = $postModel->getAdminPosts($filter);

        $this->render('admin/posts/index', [
            'title' => 'Quản lý bài viết',
            'currentUser' => $_SESSION['user'] ?? null,
            'csrfToken' => $this->csrfToken(),
            'activeMenu' => 'home',
            'adminTab' => 'posts',
            'posts' => $posts,
            'keyword' => $keyword,
            'field' => $field,
            'paginationPage' => $page,
            'paginationTotalPages' => $totalPages,
            'paginationPerPage' => $perPage,
            'paginationTotal' => $total,
        ], 'admin');
    }

    public function edit(?string $id): void {
        $this->requireAdmin();
        $postId = (int) $id;
        $post = (new Post())->findById($postId);
        if ($post === null) {
            http_response_code(404);
            echo 'Không tìm thấy bài viết';
            return;
        }
        $author = (new User())->findById((int) ($post['user_id'] ?? 0));
        $media = (new PostMedia())->getByPost($postId);
        $hashtags = (new PostHashtag())->getTagNamesByPostId($postId);
        $editContent = compose_post_content_for_editor((string) ($post['content'] ?? ''), $hashtags);
        $this->render('admin/posts/edit', [
            'title' => 'Sửa bài viết #' . $postId,
            'adminTab' => 'posts',
            'post' => $post,
            'authorUser' => is_array($author) ? $author : [],
            'authorUsername' => (string) (is_array($author) ? ($author['username'] ?? '—') : '—'),
            'media' => $media,
            'editContent' => $editContent,
            'csrfToken' => $this->csrfToken(),
        ], 'admin');
    }

    public function update(?string $id): void {
        $this->requireAdmin();
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            http_response_code(405);
            echo '405 Method Not Allowed';
            return;
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            die('CSRF invalid');
        }

        $postId = (int) $id;
        $post = (new Post())->findById($postId);
        if ($post === null) {
            $this->redirect('/admin/posts');
            return;
        }

        $visible = (string) ($_POST['privacy'] ?? ($post['visible'] ?? 'public'));
        if (!in_array($visible, ['public', 'followers', 'private'], true)) {
            $visible = 'public';
        }
        $raw = trim((string) ($_POST['content'] ?? ''));
        $parsed = parse_post_content_hashtags($raw);

        $postModel = new Post();
        $postModel->updatePost($postId, $parsed['plain'], $visible);
        (new PostHashtag())->replaceForPost($postId, $parsed['tags']);

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
                delete_stored_media((string) ($mediaRow['media_url'] ?? ''));
            }
        }

        $remainingMedia = $mediaModel->getByPost($postId);
        $hasExistingMedia = !empty($remainingMedia);
        $hasNewUpload = has_post_media_upload();
        if ($parsed['plain'] === '' && !$hasExistingMedia && !$hasNewUpload) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'msg' => 'empty']);
                exit;
            }
            $this->redirect('/admin/posts/edit/' . $postId . '?error=empty');
            return;
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

        $this->redirect('/admin/posts/edit/' . $postId . '?saved=1');
    }

    public function destroy(?string $id): void {
        $this->requireAdmin();
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            http_response_code(405);
            echo '405 Method Not Allowed';
            return;
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            die('CSRF invalid');
        }
        $postId = (int) $id;
        if ($postId > 0) {
            (new Post())->delete($postId);
        }
        $this->redirect('/admin/posts');
    }
}
