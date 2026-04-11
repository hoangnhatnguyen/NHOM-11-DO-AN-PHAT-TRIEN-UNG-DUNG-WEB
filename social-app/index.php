<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
/**
 * Front Controller - Mọi request đều đi qua đây
 * Social App - MVC Architecture
 *
 * Không gọi session_start() ở đây — phải load env rồi config/session.php
 * (session_name, cookie path, SameSite) trước khi mở phiên.
 */
require_once __DIR__ . '/vendor/autoload.php';

require_once 'config/env.php';
require_once 'config/session.php';
require_once 'config/constants.php';
require_once 'config/database.php';
require_once APP_PATH . 'helpers/media.php';

// Load core classes
require_once 'app/core/Database.php';
require_once 'app/core/BaseModel.php';
require_once 'app/core/BaseController.php';
require_once 'app/core/Avatar.php';
require_once 'app/core/Logger.php';
require_once 'app/core/Router.php';

Logger::init();

set_exception_handler(function (Throwable $e): void {
	Logger::error('Unhandled exception', [
		'message' => $e->getMessage(),
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);

	$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
		&& strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

	if ($isAjax) {
		header('Content-Type: application/json; charset=utf-8');
		http_response_code(500);
		echo json_encode([
			'ok' => false,
			'msg' => 'server_error',
			'error' => $e->getMessage(),
		], JSON_UNESCAPED_UNICODE);
		return;
	}

	http_response_code(500);
	echo "<h1>ERROR DEBUG</h1>";
	echo "<pre>";
	echo $e;
	echo "</pre>";
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
	if (!(error_reporting() & $severity)) {
		return false;
	}

	Logger::warning('PHP runtime warning/error', [
		'severity' => $severity,
		'message' => $message,
		'file' => $file,
		'line' => $line,
	]);

	return false;
});

register_shutdown_function(function (): void {
	$error = error_get_last();
	if ($error === null) {
		return;
	}

	$fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
	if (!in_array($error['type'], $fatalTypes, true)) {
		return;
	}

	Logger::error('Fatal shutdown error', [
		'type' => $error['type'],
		'message' => $error['message'],
		'file' => $error['file'],
		'line' => $error['line'],
	]);
});

// Khởi động router
$router = new Router();
$router->dispatch();
