<?php

class Router {
	private array $routes = [];

	public function __construct() {
		$routeFile = APP_ROOT . 'config/routes.php';
		if (file_exists($routeFile)) {
			$routes = require $routeFile;
			if (is_array($routes)) {
				$this->routes = $routes;
			}
		}
	}

	public function dispatch(): void {
		$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
		$url = '/' . trim($this->resolveUrl(), '/');
		if ($url === '//') {
			$url = '/';
		}

		$route = $this->matchDefinedRoute($method, $url);
		if ($route === null) {
			$route = $this->resolveConventionRoute($url);
		}

		[$controllerClass, $action, $params] = $route;
		$controllerFile = $this->resolveControllerFile($controllerClass);

		if (!file_exists($controllerFile)) {
			$this->notFound('Controller not found');
			return;
		}

		require_once $controllerFile;

		if (!class_exists($controllerClass)) {
			$this->notFound('Controller class not found');
			return;
		}

		$controller = new $controllerClass();

		if (!method_exists($controller, $action)) {
			$this->notFound('Action not found');
			return;
		}

		if (empty($params)) {
			$controller->$action();
			return;
		}

		$controller->$action(...$params);
	}

	private function resolveUrl(): string {
		if (!empty($_GET['url'])) {
			return (string) $_GET['url'];
		}

		$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
		$path = (string) (parse_url($requestUri, PHP_URL_PATH) ?? '/');

		$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
		$scriptDir = rtrim($scriptDir, '/');

		if ($scriptDir !== '' && $scriptDir !== '.' && strpos($path, $scriptDir) === 0) {
			$path = substr($path, strlen($scriptDir));
		}

		return ltrim($path, '/');
	}

	private function matchDefinedRoute(string $method, string $path): ?array {
		$methodRoutes = [];

		if (isset($this->routes[$method]) && is_array($this->routes[$method])) {
			$methodRoutes = array_merge($methodRoutes, $this->routes[$method]);
		}

		if (isset($this->routes['ANY']) && is_array($this->routes['ANY'])) {
			$methodRoutes = array_merge($methodRoutes, $this->routes['ANY']);
		}

		foreach ($methodRoutes as $routePath => $handler) {
			$routePath = rtrim((string) $routePath, '/');
			if ($routePath === '') {
				$routePath = '/';
			}

			$pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $routePath);
			$pattern = '#^' . rtrim((string) $pattern, '/') . '$#';
			if ($pattern === '#^$#') {
				$pattern = '#^/$#';
			}

			$normalizedPath = rtrim($path, '/');
			if ($normalizedPath === '') {
				$normalizedPath = '/';
			}

			if (!preg_match($pattern, $normalizedPath, $matches)) {
				continue;
			}

			array_shift($matches);
			[$controllerClass, $action] = $this->parseHandler($handler);

			return [$controllerClass, $action, $matches];
		}

		return null;
	}

	private function resolveConventionRoute(string $path): array {
		$trimmed = trim($path, '/');
		$segments = $trimmed === '' ? [] : explode('/', $trimmed);

		$controllerClass = 'HomeController';
		$action = 'index';
		$params = [];

		if (!empty($segments)) {
			if ($segments[0] === 'admin') {
				[$controllerClass, $action, $params] = $this->resolveAdminConventionRoute($segments);
			} else {
				$name = ucfirst($segments[0]) . 'Controller';
				if (file_exists(APP_PATH . 'controllers/' . $name . '.php')) {
					$controllerClass = $name;
					$action = $segments[1] ?? 'index';
					$params = array_slice($segments, 2);
				}
			}
		}

		return [$controllerClass, $action, $params];
	}

	private function resolveControllerFile(string $controllerClass): string {
		$default = APP_PATH . 'controllers/' . $controllerClass . '.php';
		$admin = APP_PATH . 'controllers/admin/' . $controllerClass . '.php';

		if (file_exists($default)) {
			return $default;
		}

		if (file_exists($admin)) {
			return $admin;
		}

		return $default;
	}

	private function parseHandler($handler): array {
		if (is_array($handler) && count($handler) === 2) {
			return [(string) $handler[0], (string) $handler[1]];
		}

		$handler = (string) $handler;
		if (strpos($handler, '@') !== false) {
			[$controllerClass, $action] = explode('@', $handler, 2);
			return [trim($controllerClass), trim($action)];
		}

		return ['HomeController', 'index'];
	}

	private function resolveAdminConventionRoute(array $segments): array {
		$area = $segments[1] ?? '';

		switch ($area) {
			case 'posts':
				return [
					'AdminPostController',
					$segments[2] ?? 'index',
					array_slice($segments, 3),
				];

			case 'users':
				return [
					'AdminUserController',
					$segments[2] ?? 'index',
					array_slice($segments, 3),
				];

			default:
				return [
					'AdminController',
					$segments[1] ?? 'index',
					array_slice($segments, 2),
				];
		}
	}

	private function notFound(string $message = '404 Not Found'): void {
		http_response_code(404);
		echo $message;
	}
}

