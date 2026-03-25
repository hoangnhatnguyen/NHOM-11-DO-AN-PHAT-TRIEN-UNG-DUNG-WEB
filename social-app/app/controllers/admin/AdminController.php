<?php
/**
 * AdminController - Base cho admin, kiểm tra quyền
 */
class AdminController extends BaseController {
    public function index(): void {
        $this->requireAdmin();
        $this->render('admin/dashboard', [], 'admin');
    }
}
