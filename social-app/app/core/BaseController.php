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

	protected function isAjaxRequest(): bool {
		$h = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
		return strtolower($h) === 'xmlhttprequest';
	}

	protected function requireAuth(): void {
		if (empty($_SESSION['user'])) {
			$this->redirect('/login');
		}
	}

	protected function requireGuest(): void {
		if (!empty($_SESSION['user'])) {
			$this->redirect('/');
		}
	}

	protected function requireAdmin(): void {
		if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? 'user') !== 'admin')) {
			http_response_code(403);
			echo '403 Forbidden';
			exit;
		}
	}

	protected function csrfToken(): string {
		if (empty($_SESSION['_csrf_token'])) {
			$_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
		}

		return (string) $_SESSION['_csrf_token'];
	}

	protected function verifyCsrf(?string $token): bool {
		$sessionToken = $_SESSION['_csrf_token'] ?? '';
		if ($sessionToken === '' || $token === null || $token === '') {
			return false;
		}

		return hash_equals((string) $sessionToken, $token);
	}

	protected function flash(string $key, ?string $message = null): ?string {
		if ($message !== null) {
			$_SESSION['_flash'][$key] = $message;
			return null;
		}

		if (empty($_SESSION['_flash'][$key])) {
			return null;
		}

		$value = (string) $_SESSION['_flash'][$key];
		unset($_SESSION['_flash'][$key]);
		return $value;
	}

	protected function setOldInput(array $input): void {
		$_SESSION['_old_input'] = $input;
	}

	protected function old(string $key, string $default = ''): string {
		$value = $_SESSION['_old_input'][$key] ?? $default;
		return is_scalar($value) ? (string) $value : $default;
	}

	protected function clearOldInput(): void {
		unset($_SESSION['_old_input']);
	}

	/**
	 * Sync user data from database to session
	 * Useful for getting updated avatar_url and other user fields
	 */
	protected function syncUserSession(): void {
		$userId = $_SESSION['user']['id'] ?? 0;
		if ($userId <= 0) {
			return;
		}

		try {
			$db = Database::getInstance()->getConnection();
			$stmt = $db->prepare("SELECT id, username, email, role, avatar_url FROM users WHERE id = :id LIMIT 1");
			$stmt->execute(['id' => $userId]);
			$user = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if ($user) {
				$_SESSION['user'] = [
					'id' => (int) $user['id'],
					'username' => (string) $user['username'],
					'email' => (string) $user['email'],
					'role' => (string) ($user['role'] ?? 'user'),
					'avatar_url' => (string) ($user['avatar_url'] ?? ''),
				];
			}
		} catch (Exception $e) {
			// Silently fail - session data should still be available
		}
	}

	/**
	 * Clear media URL cache
	 * Dùng khi user upload file mới
	 */
	protected function clearMediaCache(): void {
		unset($_SESSION['_media_cache']);
	}
}

