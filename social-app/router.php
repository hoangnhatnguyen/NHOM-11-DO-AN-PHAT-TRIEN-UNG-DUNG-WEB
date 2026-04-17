<?php

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = $requestPath === false ? '/' : $requestPath;

$staticFile = realpath(__DIR__ . $requestPath);
$rootPath = realpath(__DIR__);

if (
	$staticFile !== false &&
	$rootPath !== false &&
	str_starts_with($staticFile, $rootPath) &&
	is_file($staticFile)
) {
	return false;
}

require __DIR__ . '/index.php';
