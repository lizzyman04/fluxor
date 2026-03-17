<?php
/**
 * Fluxor App Facade
 * 
 * This is the main entry point for the framework.
 * All complex logic is delegated to sub-components.
 */

namespace Fluxor\Core;

use Throwable;
use Fluxor\Core\Http\Router;
use Fluxor\Core\App\Application;
use Fluxor\Core\App\Environment;
use Fluxor\Exceptions\AppException;
use Fluxor\Core\App\ExceptionHandler;

class App
{
    private static ?self $instance = null;
    private Application $app;
    private ?ExceptionHandler $exceptionHandler = null;

    public function __construct(?string $basePath = null)
    {
        $basePath = Environment::detectBasePath($basePath);
        $baseUrl = Environment::detectBaseUrl();
        
        $this->app = new Application($basePath, $baseUrl);
        $this->app->bootstrap();
        
        self::$instance = $this;
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
        
        return $service ? $instance->getService($service) : $instance;
    }

    public function getService(string $name)
    {
        return $this->app->getContainer()->get($name);
    }

    public function setConfig(array $config): self
    {
        foreach ($config as $key => $value) {
            $this->app->getConfig()->set($key, $value);
        }
        
        if ($this->app->getRouter()) {
            $this->app->getRouter()->setConfig($this->app->getConfig()->all());
        }
        
        return $this;
    }

    public function getRouter(): Router
    {
        return $this->app->getRouter();
    }

    public function getConfig(): array
    {
        return $this->app->getConfig()->all();
    }

    public function getBasePath(): string
    {
        return $this->app->getBasePath();
    }

    public function getBaseUrl(): string
    {
        return $this->app->getBaseUrl();
    }

    public function getEnvironment(): string
    {
        return $this->app->getConfig()->get('environment');
    }

    public function isDevelopment(): bool
    {
        return $this->getEnvironment() === 'development';
    }

    public function run(): void
    {
        try {
            $this->app->getRouter()->dispatch();
        } catch (Throwable $e) {
            $this->getExceptionHandler()->handle($e);
        }
    }

    private function getExceptionHandler(): ExceptionHandler
    {
        if (!$this->exceptionHandler) {
            $this->exceptionHandler = new ExceptionHandler($this->isDevelopment());
        }
        return $this->exceptionHandler;
    }
}