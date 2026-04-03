<?php

class Database {
	private static ?Database $instance = null;
	private PDO $connection;

	private function __construct() {
		$dsn = sprintf(
			'mysql:host=%s;port=%s;dbname=%s;charset=%s',
			DB_HOST,
			DB_PORT,
			DB_NAME,
			DB_CHARSET
		);

		$this->connection = new PDO($dsn, DB_USER, DB_PASS, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		]);

		$this->connection->exec('SET NAMES utf8mb4');

		// Đồng bộ múi giờ với PHP (APP_TIMEZONE) để NOW() và so sánh "vừa xong" không lệch (vd UTC vs +7).
		$tzName = function_exists('env') ? (string) env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh') : 'Asia/Ho_Chi_Minh';
		try {
			$appTz = new DateTimeZone($tzName);
			$offset = (new DateTime('now', $appTz))->format('P');
			$this->connection->exec('SET time_zone = ' . $this->connection->quote($offset));
		} catch (Throwable $e) {
		}
	}

	public static function getInstance(): Database {
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function getConnection(): PDO {
		return $this->connection;
	}
	
}

