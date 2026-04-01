<?php

define('APP_ROOT', rtrim(__DIR__ . '/..', '/') . '/');
define('APP_PATH', APP_ROOT . 'app/');
define('VIEW_PATH', APP_PATH . 'views/');
define('APP_NAME', (string) env('APP_NAME', 'Social App'));

$detectBaseFromScript = function (): string {
	$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
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

