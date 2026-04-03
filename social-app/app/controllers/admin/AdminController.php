<?php
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Post.php';

class AdminController extends BaseController {
    public function index(): void {
        $this->requireAdmin();
        $userModel = new User();
        $postModel = new Post();

        $this->render('admin/dashboard', [
            'title' => 'Tổng quan quản trị',
            'currentUser' => $_SESSION['user'] ?? null,
            'csrfToken' => $this->csrfToken(),
            'activeMenu' => 'home',
            'adminTab' => 'dashboard',
            'stats' => [
                'totalUsers' => $userModel->countAllUsers(),
                'newUsersToday' => $userModel->countNewUsersToday(),
                'totalPosts' => $postModel->countAllPosts(),
            ],
        ], 'feed');
    }
}
