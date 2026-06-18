<?php

namespace Fluxor\Core;

use Throwable;
use Fluxor\Core\Http\Router;
use Fluxor\Core\Http\Cors;
use Fluxor\Core\Http\Request;
use Fluxor\Core\Http\Response;
use Fluxor\Core\App\Config;
use Fluxor\Core\App\Environment;
use Fluxor\Core\App\ExceptionHandler;
use Fluxor\Core\Foundation\ServiceContainer;
use Fluxor\Exceptions\AppException;

class App
{
    private static ?self $instance = null;

    private ServiceContainer $container;
    private Config $config;
    private Router $router;
    private string $basePath;
    private string $baseUrl;
    private bool $booted = false;
    private ?ExceptionHandler $exceptionHandler = null;
    private ?Cors $cors = null;

    public function __construct(?string $basePath = null, bool $forceNew = false)
    {
        if (self::$instance && !$forceNew) {
            throw new AppException('App already initialized. Use App::getInstance()');
        }

        $this->basePath = Environment::detectBasePath($basePath);
        $this->baseUrl  = Environment::detectBaseUrl();

        $this->container = new ServiceContainer();
        Environment::loadEnvironment($this->basePath);
        $this->config = Config::createDefault($this->basePath, $this->baseUrl);
        $this->cors   = new Cors();

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
        return $this->container->get($name);
    }

    public function getContainer(): ServiceContainer
    {
        return $this->container;
    }

    public function setConfig(array $config): self
    {
        if ($this->booted) {
            throw new AppException('Cannot modify configuration after application boot');
        }

        $this->config->setMany($config);

        return $this;
    }

    public function getConfig(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config->all();
        }
        return $this->config->get($key, $default);
    }

    public function lockConfig(string ...$keys): self
    {
        if (empty($keys)) {
            $this->config->freeze();
        } else {
            $this->config->lock(...$keys);
        }
        return $this;
    }

    public function unlockConfig(string ...$keys): self
    {
        $this->config->unlock(...$keys);
        return $this;
    }

    public function isConfigLocked(string $key): bool
    {
        return $this->config->isLocked($key);
    }

    public function getLockedConfigKeys(): array
    {
        return $this->config->getLockedKeys();
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

        $errors = $this->config->validate();
        if (!empty($errors)) {
            throw new AppException('Configuration errors: ' . \implode(', ', $errors));
        }

        Environment::configureRuntime(
            $this->config->get('environment'),
            $this->config->get('debug')
        );

        \date_default_timezone_set($this->config->get('timezone'));

        $this->router = new Router($this->basePath, $this->baseUrl);
        $this->router->setPaths(
            $this->config->get('router_path'),
            $this->config->get('views_path')
        );

        $this->container->initializeCoreServices($this->config, $this->router);
        $this->container->set('config', $this->config);
        $this->container->set('router', $this->router);

        $this->booted = true;

        return $this;
    }

    public function getRouter(): Router
    {
        $this->ensureBooted();
        return $this->router;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getStoragePath(): string
    {
        return $this->basePath . '/storage';
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getEnvironment(): string
    {
        $this->ensureBooted();
        return $this->config->get('environment');
    }

    public function isDevelopment(): bool
    {
        return $this->getEnvironment() === 'development';
    }

    public function isDebug(): bool
    {
        $this->ensureBooted();
        return (bool) $this->config->get('debug', false);
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
            $this->router->setCors($this->cors);
            $this->router->dispatch($request);
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

        $baseUrl = $this->config->get('base_url', $this->baseUrl);
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
