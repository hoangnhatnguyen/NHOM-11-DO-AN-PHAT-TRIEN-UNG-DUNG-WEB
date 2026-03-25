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

