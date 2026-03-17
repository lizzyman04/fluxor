<?php
/**
 * Fluxor - Base Service Provider
 * 
 * All service providers should extend this class.
 */

namespace Fluxor\Core\Foundation;

abstract class ServiceProvider
{
    /**
     * The application instance
     */
    protected ServiceContainer $container;

    /**
     * Create a new service provider instance
     */
    public function __construct(ServiceContainer $container)
    {
        $this->container = $container;
    }

    /**
     * Register services in the container
     */
    abstract public function register(): void;

    /**
     * Bootstrap services after all providers are registered
     */
    public function boot(): void
    {
        // Optional: override in child classes
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * Check if the provider is deferred
     */
    public function isDeferred(): bool
    {
        return false;
    }
}