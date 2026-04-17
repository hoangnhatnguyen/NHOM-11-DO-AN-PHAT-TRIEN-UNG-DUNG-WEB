<?php
require_once __DIR__ . '/../models/Post.php';

class SavedPostController extends BaseController {
    public function index(): void {
        $this->requireAuth();

        $currentUser = $_SESSION['user'] ?? null;
        $userId = (int) ($currentUser['id'] ?? 0);
        $savedPosts = (new Post())->getSavedPostsByUser($userId);

        $this->render('user/saved', [
            'title' => 'Bài viết đã lưu',
            'currentUser' => $currentUser,
            'savedPosts' => $savedPosts,
            'csrfToken' => $this->csrfToken(),
            'activeMenu' => 'saved',
        ]);
    }

    public function unsave(): void {
        $this->requireAuth();
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            die('CSRF invalid');
        }

        $postId = (int) ($_POST['post_id'] ?? 0);
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($postId > 0 && $userId > 0) {
            (new Post())->removeSavedPost($postId, $userId);
        }

        $this->redirect('/saved');
    }
}