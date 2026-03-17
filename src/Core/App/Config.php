<?php
/**
 * Configuration Management
 */

namespace Fluxor\Core\App;

class Config
{
    private array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function set(string $key, $value): self
    {
        $this->items[$key] = $value;
        return $this;
    }

    public function get(string $key, $default = null)
    {
        return $this->items[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function merge(array $items): self
    {
        $this->items = array_merge($this->items, $items);
        return $this;
    }

    public function all(): array
    {
        return $this->items;
    }

    public static function createDefault(string $basePath, string $baseUrl): self
    {
        return new self([
            'router_path' => $basePath . '/app/router',
            'views_path' => $basePath . '/src/Views',
            'storage_path' => $basePath . '/storage',
            'environment' => $_ENV['APP_ENV'] ?? 'production',
            'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
            'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
            'base_url' => $baseUrl,
        ]);
    }
}