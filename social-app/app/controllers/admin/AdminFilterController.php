<?php
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Post.php';

class AdminFilterController extends BaseController {
    public function filterUsers(string $keyword = ''): array {
        return (new User())->searchForAdmin($keyword);
    }

    public function filterPosts(string $keyword = '', string $field = 'content'): array {
        return (new Post())->getAdminPosts([
            'keyword' => $keyword,
            'field' => $field,
        ]);
    }
}
