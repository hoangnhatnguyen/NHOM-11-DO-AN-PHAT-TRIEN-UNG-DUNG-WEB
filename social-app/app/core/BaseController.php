<?php

class BaseController {
	protected function render(string $view, array $data = [], string $layout = 'main'): void {
		$viewFile = VIEW_PATH . $view . '.php';
		$layoutFile = VIEW_PATH . 'layouts/' . $layout . '.php';

		if (!file_exists($viewFile)) {
			http_response_code(404);
			echo "View not found: {$view}";
			return;
		}

		extract($data, EXTR_SKIP);

		if (file_exists($layoutFile)) {
			$contentView = $viewFile;
			include $layoutFile;
			return;
		}

		include $viewFile;
	}

	protected function redirect(string $path): void {
		$base = BASE_URL === '' ? '' : BASE_URL;
		$url = $base . '/' . ltrim($path, '/');
		header('Location: ' . $url);
		exit;
	}

	protected function requireAuth(): void {
		if (empty($_SESSION['user'])) {
			$this->redirect('/login');
		}
	}

	protected function requireAdmin(): void {
		if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? 'user') !== 'admin')) {
			http_response_code(403);
			echo '403 Forbidden';
			exit;
		}
	}
}

