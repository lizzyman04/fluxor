<?php
/**
 * Service Container
 */

namespace Fluxor\Core\Foundation;

use Fluxor\Core\Http\Router;
use Fluxor\Exceptions\AppException;
use Fluxor\Core\View;
use Fluxor\Core\App\Config;

class ServiceContainer
{
    private array $services = [];
    private array $bindings = [];

    public function set(string $name, $service): self
    {
        $this->services[$name] = $service;
        return $this;
    }

    public function get(string $name)
    {
        if (isset($this->services[$name])) {
            return $this->services[$name];
        }

        if (isset($this->bindings[$name])) {
            $service = $this->bindings[$name]($this);
            $this->set($name, $service);
            return $service;
        }

        throw new AppException("Service '{$name}' not registered");
    }

    public function bind(string $name, callable $resolver): self
    {
        $this->bindings[$name] = $resolver;
        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->services[$name]) || isset($this->bindings[$name]);
    }

    public function initializeCoreServices(Config $config, Router $router): void
    {
        $view = new View();
        $view->setViewsPath($config->get('views_path'));
        $this->set('view', $view);

        $router->setConfig($config->all());
        $this->set('router', $router);
        $this->set('config', $config);
    }
}