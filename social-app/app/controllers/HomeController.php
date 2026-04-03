<?php

require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../models/User.php';

class HomeController extends BaseController {
	public function index(): void {
		$this->requireAuth();

		// Sync avatar_url từ database vào session
		$this->syncUserSession();

		$posts = [];
		$dbError = null;
		$feedTab = 'foryou';

		try {
			$viewerId = (int) ($_SESSION['user']['id'] ?? 0);
			$tab = (string) ($_GET['tab'] ?? '');
			$feedTab = ($tab === 'following') ? 'following' : 'foryou';
			$postModel = new Post();
			// Load only 5 posts initially
			$posts = $feedTab === 'following'
				? $postModel->getFeedFollowingPaginated($viewerId, 5, 0)
				: $postModel->getFeedPaginated($viewerId, 5, 0);
		} catch (Throwable $e) {
			$dbError = $e->getMessage();
		}

		$this->render('home/feed', [
			'title' => 'Trang chủ',
			'posts' => $posts,
			'dbError' => $dbError,
			'feedTab' => $feedTab,
			'currentUser' => $_SESSION['user'] ?? null,
			'csrfToken' => $this->csrfToken(),
			'activeMenu' => 'home'
		], 'feed');
	}
}

