<?php

namespace Fluxor\Core;

use Throwable;
use Exception;
use Dotenv\Dotenv;
use Fluxor\Exceptions\AppException;

class App
{
    private static ?self $instance = null;
    private Router $router;
    private string $basePath;
    private string $baseUrl;
    private array $config = [];
    private array $services = [];
    private bool $booted = false;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? $this->detectBasePath();
        
        $this->baseUrl = $this->detectBaseUrl();
        
        $this->router = new Router($this->basePath, $this->baseUrl);
        
        self::$instance = $this;
        $this->loadEnvironment();
    }

    private function detectBasePath(): string
    {
        if (strpos(__DIR__, '/vendor/') !== false) {
            return dirname(__DIR__, 4);
        }
        
        return rtrim(getcwd(), '/\\');
    }

    private function detectBaseUrl(): string
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

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    public static function make(string $service = null)
    {
        $instance = self::getInstance();
        
        if (!$instance) {
            throw new AppException('Application instance not initialized');
        }
        
        if ($service) {
            return $instance->getService($service);
        }
        
        return $instance;
    }

    private function loadEnvironment(): void
    {
        $envFile = $this->basePath . '/.env';
        
        if (file_exists($envFile)) {
            try {
                $dotenv = Dotenv::createImmutable($this->basePath);
                $dotenv->load();
                
                $dotenv->required(['APP_ENV'])->allowedValues(['development', 'production', 'testing']);
                
            } catch (Exception $e) {
                throw new AppException('Environment configuration error: ' . $e->getMessage());
            }
        }
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        $defaultConfig = [
            'router_path' => $this->basePath . '/app/router',
            'views_path' => $this->basePath . '/src/Views',
            'storage_path' => $this->basePath . '/storage',
            'environment' => $_ENV['APP_ENV'] ?? 'production',
            'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
            'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
            'base_url' => $this->baseUrl,
        ];

        $this->config = array_merge($defaultConfig, $this->config);

        date_default_timezone_set($this->config['timezone']);

        if ($this->config['environment'] === 'production') {
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
        } else {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        }

        $this->initializeServices();

        $this->booted = true;
        
        return $this;
    }

    private function initializeServices(): void
    {
        $this->services['view'] = new View();
        $this->services['view']->setViewsPath($this->config['views_path']);

        $this->services['router'] = $this->router;
        $this->services['router']->setConfig($this->config);

        $this->services['config'] = new class($this->config) {
            private array $config;
            
            public function __construct(array $config) {
                $this->config = $config;
            }
            
            public function get(string $key, $default = null) {
                return $this->config[$key] ?? $default;
            }
            
            public function set(string $key, $value): void {
                $this->config[$key] = $value;
            }
            
            public function has(string $key): bool {
                return array_key_exists($key, $this->config);
            }
        };
    }

    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    public function registerService(string $name, $service): self
    {
        $this->services[$name] = $service;
        return $this;
    }

    public function getService(string $name)
    {
        if (!isset($this->services[$name])) {
            throw new AppException("Service '{$name}' not registered");
        }
        
        return $this->services[$name];
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getEnvironment(): string
    {
        return $this->config['environment'];
    }

    public function isProduction(): bool
    {
        return $this->getEnvironment() === 'production';
    }

    public function isDevelopment(): bool
    {
        return $this->getEnvironment() === 'development';
    }

    public function run(): void
    {
        if (!$this->booted) {
            $this->boot();
        }

        try {
            $this->router->dispatch();
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    private function handleException(Throwable $e): void
    {
        $statusCode = 500;
        $message = 'Internal Server Error';
        
        if ($e instanceof AppException) {
            $statusCode = $e->getCode() ?: 500;
            $message = $e->getMessage();
        }

        if ($this->isDevelopment()) {
            $response = [
                'error' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ];
        } else {
            $response = [
                'error' => $statusCode === 500 ? 'Internal Server Error' : 'Error',
                'message' => $message,
            ];
        }

        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if ($this->isDevelopment()) {
            error_log("[" . date('Y-m-d H:i:s') . "] " . get_class($e) . ": " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        }
    }

    public function terminate(): void
    {
        $this->booted = false;
    }
}