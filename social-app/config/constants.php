<?php

define('APP_ROOT', rtrim(__DIR__ . '/..', '/') . '/');
define('APP_PATH', APP_ROOT . 'app/');
define('VIEW_PATH', APP_PATH . 'views/');
define('APP_NAME', (string) env('APP_NAME', 'Social App'));

$appUrl = (string) env('APP_URL', '');
if ($appUrl !== '') {
	$parsedPath = parse_url($appUrl, PHP_URL_PATH) ?: '';
	define('BASE_URL', rtrim((string) $parsedPath, '/'));
} else {
	$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
	define('BASE_URL', rtrim($scriptName, '/'));
}

