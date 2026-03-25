<?php

date_default_timezone_set((string) env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'));

if (session_status() === PHP_SESSION_NONE) {
	$sessionName = (string) env('SESSION_NAME', 'social_app_session');
	$cookieSecure = (bool) env('SESSION_SECURE_COOKIE', false);
	$cookieLifetime = (int) env('SESSION_LIFETIME', 7200);

	if ($sessionName !== '') {
		session_name($sessionName);
	}

	session_set_cookie_params([
		'lifetime' => $cookieLifetime,
		'path' => '/',
		'domain' => '',
		'secure' => $cookieSecure,
		'httponly' => true,
		'samesite' => 'Lax',
	]);

	session_start();
}

