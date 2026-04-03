<?php
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/AdminFilterController.php';

class AdminUserController extends BaseController {

    public function index(): void {
        $this->requireAdmin();
        $keyword = trim((string) ($_GET['q'] ?? ''));
        $filter = new AdminFilterController();
        $users = $filter->filterUsers($keyword);
        $this->render('admin/users/index', [
            'title' => 'Quản lý người dùng',
            'currentUser' => $_SESSION['user'] ?? null,
            'csrfToken' => $this->csrfToken(),
            'activeMenu' => 'home',
            'adminTab' => 'users',
            'users' => $users,
            'keyword' => $keyword,
        ], 'feed');
    }

    public function edit(?string $id): void {
        $this->requireAdmin();
        $user = (new User())->findById((int)$id);
        $this->render('admin/users/edit', compact('user'), 'feed');
    }

    public function toggleStatus(): void {
        $this->requireAdmin();
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            die('CSRF invalid');
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $active = (int) ($_POST['active'] ?? 0);
        if ($userId > 0) {
            (new User())->setActiveStatus($userId, $active);
        }
        $this->redirect('/admin/users');
    }

    public function destroy(?string $id): void {
        $this->requireAdmin();
        (new User())->delete((int)$id);
        $this->redirect('/admin/users');
    }
}
