<?php

require_once __DIR__ . '/../models/Post.php';

class HomeController extends BaseController {
	public function index(): void {
		$this->requireAuth();

		$posts = [];
		$dbError = null;
		$feedTab = 'foryou';

		try {
			$viewerId = (int) ($_SESSION['user']['id'] ?? 0);
			$tab = (string) ($_GET['tab'] ?? '');
			$feedTab = ($tab === 'following') ? 'following' : 'foryou';
			$postModel = new Post();
			$posts = $feedTab === 'following'
				? $postModel->getFeedFollowing($viewerId)
				: $postModel->getFeed($viewerId);
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

