<?php
require_once __DIR__ . '/../../models/User.php';

/**
 * AdminUserController - Quản lý user
 */
class AdminUserController extends BaseController {

    public function index(): void {
        $this->requireAdmin();
        $users = (new User())->findAll();
        $this->render('admin/users/index', compact('users'), 'admin');
    }

    public function edit(?string $id): void {
        $this->requireAdmin();
        $user = (new User())->findById((int)$id);
        $this->render('admin/users/edit', compact('user'), 'admin');
    }

    public function destroy(?string $id): void {
        $this->requireAdmin();
        (new User())->delete((int)$id);
        $this->redirect('/admin/users');
    }
}
