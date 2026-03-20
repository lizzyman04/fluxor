<?php

namespace Fluxor\Core\App;

class Environment
{
    private static bool $loaded = false;
    private static array $values = [];

    public static function detectBasePath(?string $customPath = null): string
    {
        if ($customPath !== null) {
            return rtrim($customPath, '/\\');
        }

        $paths = [
            dirname($_SERVER['SCRIPT_FILENAME'] ?? ''),
            getcwd(),
            __DIR__
        ];

        foreach ($paths as $path) {
            if ($path === false || $path === '') {
                continue;
            }

            $path = rtrim($path, '/\\');

            if (file_exists($path . '/composer.json') || is_dir($path . '/vendor')) {
                return $path;
            }

            $current = $path;
            for ($i = 0; $i < 10; $i++) {
                if (file_exists($current . '/composer.json') || is_dir($current . '/vendor')) {
                    return $current;
                }
                $parent = dirname($current);
                if ($parent === $current)
                    break;
                $current = $parent;
            }
        }

        return rtrim(getcwd(), '/\\');
    }

    public static function detectBaseUrl(): string
    {
        if (PHP_SAPI === 'cli') {
            return '';
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https' : 'http';

        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $host = preg_replace('/:\d+$/', '', $host);

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = dirname($scriptName);
        $baseDir = $baseDir === DIRECTORY_SEPARATOR ? '' : $baseDir;

        return rtrim($protocol . '://' . $host . $baseDir, '/') . '/';
    }

    public static function loadEnvironment(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        $basePath = rtrim($basePath, '/\\');
        $files = [$basePath . '/.env'];

        if (file_exists($basePath . '/.env.local')) {
            $files[] = $basePath . '/.env.local';
        }

        $env = self::get('APP_ENV', 'production');
        if (file_exists($basePath . '/.env.' . $env)) {
            $files[] = $basePath . '/.env.' . $env;
        }

        foreach ($files as $file) {
            if (is_file($file) && is_readable($file)) {
                self::parseFile($file);
            }
        }

        self::$loaded = true;
    }

    private static function parseFile(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            if ($key === '' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                continue;
            }

            $value = trim(substr($line, $pos + 1));
            $value = self::parseValue($value, $key);

            self::set($key, $value);
        }
    }

    private static function parseValue(string $value, string $key = ''): mixed
    {
        $len = strlen($value);

        if ($len === 0) {
            return '';
        }

        if (
            ($value[0] === '"' && $value[$len - 1] === '"') ||
            ($value[0] === "'" && $value[$len - 1] === "'")
        ) {
            $value = substr($value, 1, -1);
        }

        $value = preg_replace_callback('/\${([a-zA-Z_][a-zA-Z0-9_]*)}/', function ($matches) {
            return (string) self::get($matches[1], '');
        }, $value);

        $lower = strtolower($value);
        if ($lower === 'true')
            return true;
        if ($lower === 'false')
            return false;
        if ($lower === 'null')
            return null;
        if (is_numeric($value))
            return $value + 0;

        return $value;
    }

    public static function get(string $key, $default = null): mixed
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            $parsed = self::parseValue($value);
            self::$values[$key] = $parsed;
            return $parsed;
        }

        return $default;
    }

    public static function set(string $key, $value): void
    {
        self::$values[$key] = $value;
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;

        if (is_bool($value)) {
            putenv("$key=" . ($value ? 'true' : 'false'));
        } elseif ($value === null) {
            putenv("$key=");
        } else {
            putenv("$key=" . (string) $value);
        }
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$values) ||
            getenv($key) !== false ||
            array_key_exists($key, $_ENV) ||
            array_key_exists($key, $_SERVER);
    }

    public static function required(string $key): mixed
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Required environment variable '{$key}' is not set");
        }
        return $value;
    }

    public static function configureRuntime(string $environment, bool $debug): void
    {
        if ($debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }

        ini_set('log_errors', '1');

        $timezone = self::get('APP_TIMEZONE', 'UTC');
        date_default_timezone_set($timezone);

        if ($environment === 'production') {
            ini_set('expose_php', '0');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', '1');
        }
    }
}