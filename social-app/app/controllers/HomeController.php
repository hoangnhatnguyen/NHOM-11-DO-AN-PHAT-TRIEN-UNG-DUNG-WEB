<?php

require_once __DIR__ . '/../models/Post.php';

class HomeController extends BaseController {
	public function index(): void {
		$posts = [];
		$dbError = null;

		try {
			$posts = (new Post())->findAll();
		} catch (Throwable $e) {
			$dbError = $e->getMessage();
		}

		$this->render('home/feed', [
			'title' => 'Trang chủ',
			'posts' => $posts,
			'dbError' => $dbError,
		]);
	}
}

