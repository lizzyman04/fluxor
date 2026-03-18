<?php

namespace Fluxor\Core\App;

class Environment
{
    public static function detectBasePath(?string $customPath = null): string
    {
        if ($customPath) {
            return rtrim($customPath, '/\\');
        }

        if (strpos(__DIR__, '/vendor/') === false) {
            return rtrim(getcwd(), '/\\');
        }

        $path = dirname(__DIR__, 6);

        while (!file_exists($path . '/composer.json') && !is_dir($path . '/vendor')) {
            $parent = dirname($path);
            if ($parent === $path)
                break;
            $path = $parent;
        }

        return $path;
    }

    public static function detectBaseUrl(): string
    {
        if (PHP_SAPI === 'cli') {
            return '';
        }

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');

        return rtrim("$protocol://$host" . ($baseDir !== '/' ? $baseDir : ''), '/') . '/';
    }

    public static function loadEnvironment(string $basePath): void
    {
        if (!file_exists($basePath . '/.env')) {
            return;
        }

        $dotenv = \Dotenv\Dotenv::createImmutable($basePath);
        $dotenv->load();
        $dotenv->required('APP_ENV')->allowedValues(['development', 'production', 'testing']);
    }

    public static function configureRuntime(string $environment, bool $debug): void
    {
        if ($environment === 'production') {
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
            return;
        }

        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    }
}