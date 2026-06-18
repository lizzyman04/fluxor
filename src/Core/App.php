<?php

namespace Fluxor\Core;

use Throwable;
use Fluxor\Core\Http\Router;
use Fluxor\Core\Http\Cors;
use Fluxor\Core\Http\Request;
use Fluxor\Core\Http\Response;
use Fluxor\Core\App\Application;
use Fluxor\Core\App\Environment;
use Fluxor\Exceptions\AppException;
use Fluxor\Core\App\ExceptionHandler;

class App
{
    private static ?self $instance = null;
    private Application $app;
    private ?ExceptionHandler $exceptionHandler = null;
    private bool $booted = false;
    private ?Cors $cors = null;

    public function __construct(?string $basePath = null, bool $forceNew = false)
    {
        if (self::$instance && !$forceNew) {
            throw new AppException('App already initialized. Use App::getInstance()');
        }

        $basePath = Environment::detectBasePath($basePath);
        $baseUrl  = Environment::detectBaseUrl();

        $this->app  = new Application($basePath, $baseUrl);
        $this->cors = new Cors();

        self::$instance = $this;
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    public static function make(?string $service = null)
    {
        $instance = self::getInstance();

        if (!$instance) {
            throw new AppException('Application instance not initialized');
        }

        return $service ? $instance->getService($service) : $instance;
    }

    public function getService(string $name)
    {
        return $this->app->getContainer()->get($name);
    }

    public function setConfig(array $config): self
    {
        if ($this->booted) {
            throw new AppException('Cannot modify configuration after application boot');
        }

        $this->app->getConfig()->setMany($config);

        return $this;
    }

    public function getConfig(?string $key = null, $default = null)
    {
        $config = $this->app->getConfig();
        if ($key === null) {
            return $config->all();
        }
        return $config->get($key, $default);
    }

    public function lockConfig(string ...$keys): self
    {
        if (empty($keys)) {
            $this->app->getConfig()->freeze();
        } else {
            $this->app->getConfig()->lock(...$keys);
        }
        return $this;
    }

    public function unlockConfig(string ...$keys): self
    {
        $this->app->getConfig()->unlock(...$keys);
        return $this;
    }

    public function isConfigLocked(string $key): bool
    {
        return $this->app->getConfig()->isLocked($key);
    }

    public function getLockedConfigKeys(): array
    {
        return $this->app->getConfig()->getLockedKeys();
    }

    public function cors(?array $config = null): Cors
    {
        if ($config !== null) {
            $this->cors->configure($config);
        }
        return $this->cors;
    }

    public function enableCors(): self
    {
        $this->cors->enable();
        return $this;
    }

    public function disableCors(): self
    {
        $this->cors->disable();
        return $this;
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        $errors = $this->app->getConfig()->validate();
        if (!empty($errors)) {
            throw new AppException('Configuration errors: ' . \implode(', ', $errors));
        }

        $this->app->bootstrap();
        $this->booted = true;

        return $this;
    }

    public function getRouter(): Router
    {
        $this->ensureBooted();
        return $this->app->getRouter();
    }

    public function getBasePath(): string
    {
        return $this->app->getBasePath();
    }

    public function getStoragePath(): string
    {
        return $this->app->getBasePath() . '/storage';
    }

    public function getBaseUrl(): string
    {
        return $this->app->getBaseUrl();
    }

    public function getEnvironment(): string
    {
        $this->ensureBooted();
        return $this->app->getConfig()->get('environment');
    }

    public function isDevelopment(): bool
    {
        return $this->getEnvironment() === 'development';
    }

    public function isDebug(): bool
    {
        $this->ensureBooted();
        return (bool) $this->app->getConfig()->get('debug', false);
    }

    public function isProduction(): bool
    {
        return $this->getEnvironment() === 'production';
    }

    public function run(): void
    {
        if (!$this->booted) {
            $this->boot();
        }

        try {
            $request = $this->createRequest();

            // CORS preflight para OPTIONS — responde imediatamente sem conhecer a rota
            if ($request->method === 'OPTIONS') {
                $corsResponse = $this->cors->apply($request);
                if ($corsResponse instanceof Response) {
                    $corsResponse->send();
                    return;
                }
            }

            // Para outros métodos, passa o CORS para o Router aplicar após resolver a rota
            $this->app->getRouter()->setCors($this->cors);
            $this->app->getRouter()->dispatch($request);
        } catch (Throwable $e) {
            $this->getExceptionHandler()->handle($e);
        }
    }

    private function getExceptionHandler(): ExceptionHandler
    {
        if (!$this->exceptionHandler) {
            $this->exceptionHandler = new ExceptionHandler($this->isDebug());
        }
        return $this->exceptionHandler;
    }

    private function ensureBooted(): void
    {
        if (!$this->booted) {
            throw new AppException('Application not booted. Call boot() or run() first.');
        }
    }

    private function createRequest(): Request
    {
        return new Request([
            'method'    => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'path'      => $this->extractUrl(),
            'query'     => $_GET,
            'body'      => $_POST,
            'json'      => $this->getJsonBody(),
            'headers'   => $this->getAllHeaders(),
            'cookies'   => $_COOKIE,
            'files'     => $_FILES,
            'server'    => $_SERVER,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'secure'    => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
    }

    private function extractUrl(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $url        = \parse_url($requestUri, PHP_URL_PATH) ?? '/';

        $baseUrl = $this->app->getConfig()->get('base_url', $this->app->getBaseUrl());
        if (!empty($baseUrl)) {
            $parsedBase = \parse_url($baseUrl);
            $basePath   = $parsedBase['path'] ?? '';
            if ($basePath && \str_starts_with($url, $basePath)) {
                $url = \substr($url, \strlen($basePath));
            }
        }

        if (\str_starts_with($url, '/public')) {
            $url = \substr($url, 7);
        }

        $url = '/' . \trim(\explode('?', $url)[0], '/');
        return $url === '' ? '/' : $url;
    }

    private function getJsonBody(): array
    {
        $input = \file_get_contents('php://input');
        if ($input) {
            $json = \json_decode($input, true);
            if (\json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        return [];
    }

    private function getAllHeaders(): array
    {
        if (\function_exists('getallheaders')) {
            return \getallheaders();
        }
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (\str_starts_with($name, 'HTTP_')) {
                $headerName    = \str_replace(' ', '-', \ucwords(\strtolower(\str_replace('_', ' ', \substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }
}