<?php
require_once __DIR__ . '/../../models/Post.php';

/**
 * AdminPostController - Quản lý bài viết
 */
class AdminPostController extends BaseController {

    public function index(): void {
        $this->requireAdmin();
        $posts = (new Post())->findAll();
        $this->render('admin/posts/index', compact('posts'), 'admin');
    }

    public function destroy(?string $id): void {
        $this->requireAdmin();
        (new Post())->delete((int)$id);
        $this->redirect('/admin/posts');
    }
}
