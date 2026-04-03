<?php
require_once __DIR__ . '/../../models/Post.php';
require_once __DIR__ . '/AdminFilterController.php';

class AdminPostController extends BaseController {

    public function index(): void {
        $this->requireAdmin();
        $keyword = trim((string) ($_GET['keyword'] ?? ''));
        $field = (string) ($_GET['field'] ?? 'content');
        $filter = new AdminFilterController();
        $posts = $filter->filterPosts($keyword, $field);
        $this->render('admin/posts/index', [
            'title' => 'Quản lý bài viết',
            'currentUser' => $_SESSION['user'] ?? null,
            'csrfToken' => $this->csrfToken(),
            'activeMenu' => 'home',
            'adminTab' => 'posts',
            'posts' => $posts,
            'keyword' => $keyword,
            'field' => $field,
        ], 'feed');
    }

    public function destroy(?string $id): void {
        $this->requireAdmin();
        (new Post())->delete((int)$id);
        $this->redirect('/admin/posts');
    }
}
