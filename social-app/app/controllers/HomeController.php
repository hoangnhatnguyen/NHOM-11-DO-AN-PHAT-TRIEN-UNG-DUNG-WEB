<?php

require_once __DIR__ . '/../models/Post.php';

class HomeController extends BaseController {
	public function index(): void {
		$this->requireAuth();

		$posts = [];
		$dbError = null;

		try {
			$viewerId = (int) ($_SESSION['user']['id'] ?? 0);
			$posts = (new Post())->getFeed($viewerId);
		} catch (Throwable $e) {
			$dbError = $e->getMessage();
		}

		$this->render('home/feed', [
			'title' => 'Trang chủ',
			'posts' => $posts,
			'dbError' => $dbError,
			'currentUser' => $_SESSION['user'] ?? null,
			'csrfToken' => $this->csrfToken(),
			'activeMenu' => 'home'
		], 'feed');
	}
}

