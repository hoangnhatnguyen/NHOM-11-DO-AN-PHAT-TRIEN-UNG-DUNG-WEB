<?php
require_once __DIR__ . '/../../models/User.php';

class AdminUserController extends BaseController {

    public function index(): void {
        $this->requireAdmin();
        $keyword = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(50, max(5, (int) ($_GET['per_page'] ?? 15)));
        $userModel = new User();
        $total = $userModel->countSearchForAdmin($keyword);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $users = $userModel->searchForAdminPaginated($keyword, $perPage, $offset);
        $this->render('admin/users/index', [
            'title' => 'Quản lý người dùng',
            'currentUser' => $_SESSION['user'] ?? null,
            'csrfToken' => $this->csrfToken(),
            'activeMenu' => 'home',
            'adminTab' => 'users',
            'users' => $users,
            'keyword' => $keyword,
            'paginationPage' => $page,
            'paginationTotalPages' => $totalPages,
            'paginationPerPage' => $perPage,
            'paginationTotal' => $total,
        ], 'admin');
    }

    public function edit(?string $id): void {
        $this->requireAdmin();
        $user = (new User())->findById((int)$id);
        $this->render('admin/users/edit', compact('user'), 'admin');
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
