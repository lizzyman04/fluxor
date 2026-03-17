<?php
/**
 * Application Core Container
 * 
 * Handles bootstrapping and core service registration.
 */

namespace Fluxor\Core\App;

use Fluxor\Core\Http\Router;
use Fluxor\Core\Foundation\ServiceContainer;

class Application
{
    private ServiceContainer $container;
    private Config $config;
    private Router $router;
    private string $basePath;
    private string $baseUrl;
    private bool $booted = false;

    public function __construct(string $basePath, string $baseUrl)
    {
        $this->basePath = $basePath;
        $this->baseUrl = $baseUrl;
        $this->container = new ServiceContainer();
    }

    public function bootstrap(): void
    {
        if ($this->booted) {
            return;
        }

        Environment::loadEnvironment($this->basePath);
        $this->config = Config::createDefault($this->basePath, $this->baseUrl);

        Environment::configureRuntime(
            $this->config->get('environment'),
            $this->config->get('debug')
        );

        date_default_timezone_set($this->config->get('timezone'));

        $this->router = new Router($this->basePath, $this->baseUrl);
        $this->container->initializeCoreServices($this->config, $this->router);

        $this->booted = true;
    }

    public function getContainer(): ServiceContainer
    {
        return $this->container;
    }
    public function getConfig(): Config
    {
        return $this->config;
    }
    public function getRouter(): Router
    {
        return $this->router;
    }
    public function getBasePath(): string
    {
        return $this->basePath;
    }
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}