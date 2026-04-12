<?php

define('APP_ROOT', rtrim(__DIR__ . '/..', '/') . '/');
define('APP_PATH', APP_ROOT . 'app/');
define('VIEW_PATH', APP_PATH . 'views/');
define('APP_NAME', (string) env('APP_NAME', 'Social App'));

$detectBaseFromScript = function (): string {
	$scriptName = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
	// Request từ api/*.php: dirname kết thúc bằng /api — BASE_URL phải là thư mục cha (cùng cấp index.php), không phải .../public (dễ gây link /public/profile → 404).
	while (preg_match('#/api$#', $scriptName)) {
		$scriptName = str_replace('\\', '/', dirname($scriptName));
	}
	return rtrim($scriptName, '/');
};

$appUrl = (string) env('APP_URL', '');
if ($appUrl !== '') {
	$parsedPath = parse_url($appUrl, PHP_URL_PATH);
	$parsedPath = ($parsedPath !== null && $parsedPath !== false) ? rtrim((string) $parsedPath, '/') : '';
	if ($parsedPath !== '') {
		define('BASE_URL', $parsedPath);
	} else {
		define('BASE_URL', $detectBaseFromScript());
	}
} else {
	define('BASE_URL', $detectBaseFromScript());
}

if (!function_exists('profile_url')) {
	/**
	 * URL trang cá nhân (query) — tránh lỗi Apache với username có dấu chấm (vd. lee.d_113).
	 */
	function profile_url(string $username): string
	{
		$base = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');

		return $base . '/profile?u=' . rawurlencode($username);
	}
}

