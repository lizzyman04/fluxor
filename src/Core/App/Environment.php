<?php
/**
 * Environment Detection
 */

namespace Fluxor\Core\App;

class Environment
{
    public static function detectBasePath(?string $customPath = null): string
    {
        if ($customPath !== null) {
            return rtrim($customPath, '/\\');
        }

        if (strpos(__DIR__, '/vendor/') !== false) {
            return dirname(__DIR__, 5);
        }
        
        return rtrim(getcwd(), '/\\');
    }

    public static function detectBaseUrl(): string
    {
        if (php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg') {
            return '';
        }

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = dirname($scriptName);

        $baseUrl = $protocol . '://' . $host . ($baseDir !== '/' ? $baseDir : '');
        return rtrim($baseUrl, '/') . '/';
    }

    public static function loadEnvironment(string $basePath): void
    {
        $envFile = $basePath . '/.env';

        if (file_exists($envFile)) {
            try {
                $dotenv = \Dotenv\Dotenv::createImmutable($basePath);
                $dotenv->load();
                $dotenv->required(['APP_ENV'])->allowedValues(['development', 'production', 'testing']);
            } catch (\Exception $e) {
                throw new \Fluxor\Exceptions\AppException('Environment error: ' . $e->getMessage());
            }
        }
    }

    public static function configureRuntime(string $environment, bool $debug): void
    {
        if ($environment === 'production') {
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
        } else {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        }
    }
}