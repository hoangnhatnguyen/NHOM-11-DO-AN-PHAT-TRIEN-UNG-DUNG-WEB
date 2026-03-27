<?php

class Logger {
    private static string $logFile = '';
    private static string $minLevel = 'debug';

    private const LEVELS = [
        'debug' => 100,
        'info' => 200,
        'warning' => 300,
        'error' => 400,
    ];

    public static function init(): void {
        self::$minLevel = strtolower((string) env('LOG_LEVEL', 'debug'));
        if (!isset(self::LEVELS[self::$minLevel])) {
            self::$minLevel = 'debug';
        }

        $configuredPath = (string) env('LOG_FILE', 'storage/logs/app.log');
        if ($configuredPath === '') {
            $configuredPath = 'storage/logs/app.log';
        }

        if (self::isAbsolutePath($configuredPath)) {
            self::$logFile = $configuredPath;
        } else {
            self::$logFile = APP_ROOT . ltrim($configuredPath, '/');
        }

        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    public static function debug(string $message, array $context = []): void {
        self::write('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::write('error', $message, $context);
    }

    private static function write(string $level, string $message, array $context = []): void {
        if (!isset(self::LEVELS[$level])) {
            return;
        }

        if (!self::shouldLog($level)) {
            return;
        }

        if (self::$logFile === '') {
            self::init();
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextJson = !empty($context)
            ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '{}';

        $line = sprintf("[%s] %s: %s | context=%s\n", $timestamp, strtoupper($level), $message, $contextJson);

        @file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private static function shouldLog(string $level): bool {
        return self::LEVELS[$level] >= self::LEVELS[self::$minLevel];
    }

    private static function isAbsolutePath(string $path): bool {
        if ($path === '') {
            return false;
        }

        return $path[0] === '/' || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
    }
}
