<?php

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === '\'' && $last === '\'')) {
                    $value = substr($value, 1, -1);
                }
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        $normalized = strtolower((string) $value);

        if (in_array($normalized, ['true', '(true)'], true)) {
            return true;
        }

        if (in_array($normalized, ['false', '(false)'], true)) {
            return false;
        }

        if (in_array($normalized, ['null', '(null)'], true)) {
            return null;
        }

        if (in_array($normalized, ['empty', '(empty)'], true)) {
            return '';
        }

        return $value;
    }
}

loadEnv(__DIR__ . '/../.env');
