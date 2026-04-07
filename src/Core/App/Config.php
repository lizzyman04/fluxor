<?php

namespace Fluxor\Core\App;

class Config
{
    private array $items = [];
    private array $locked = [];
    private array $defaults = [];

    public function __construct(array $items = [])
    {
        $this->defaults = [
            'router_path' => null,
            'views_path' => null,
            'storage_path' => null,
            'environment' => 'production',
            'debug' => false,
            'timezone' => 'UTC',
            'base_url' => null,
            'app_name' => 'Fluxor App',
            'app_version' => '1.0.0',
            'session_lifetime' => 120,
            'session_secure' => false,
            'csrf_token_name' => 'csrf_token',
            'method_field_name' => '_method',
        ];

        $this->items = [...$this->defaults, ...$items];
    }

    public function set(string $key, $value): self
    {
        if ($this->isLocked($key)) {
            throw new \RuntimeException("Cannot modify locked configuration key: {$key}");
        }
        $this->items[$key] = $value;
        return $this;
    }

    public function setMany(array $items): self
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    public function get(string $key, $default = null)
    {
        return $this->items[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->items);
    }

    public function merge(array $items): self
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function lock(string ...$keys): self
    {
        foreach ($keys as $key) {
            $this->locked[$key] = true;
        }
        return $this;
    }

    public function unlock(string ...$keys): self
    {
        foreach ($keys as $key) {
            unset($this->locked[$key]);
        }
        return $this;
    }

    public function isLocked(string $key): bool
    {
        return isset($this->locked[$key]);
    }

    public function getLockedKeys(): array
    {
        return array_keys($this->locked);
    }

    public function reset(string $key): self
    {
        if ($this->isLocked($key)) {
            throw new \RuntimeException("Cannot reset locked configuration key: {$key}");
        }
        $this->items[$key] = $this->defaults[$key] ?? null;
        return $this;
    }

    public function resetAll(): self
    {
        foreach ($this->items as $key => $value) {
            if (!$this->isLocked($key)) {
                $this->items[$key] = $this->defaults[$key] ?? null;
            }
        }
        return $this;
    }

    public function validate(): array
    {
        $errors = [];
        $required = ['router_path', 'views_path'];

        foreach ($required as $key) {
            if (empty($this->items[$key])) {
                $errors[] = "Configuration key '{$key}' is required";
            }
        }

        return $errors;
    }

    public static function createDefault(string $basePath, string $baseUrl): self
    {
        return new self([
            'router_path' => "{$basePath}/app/router",
            'views_path' => "{$basePath}/src/Views",
            'storage_path' => "{$basePath}/storage",
            'environment' => Environment::get('APP_ENV', 'production'),
            'debug' => Environment::get('APP_DEBUG', false),
            'timezone' => Environment::get('APP_TIMEZONE', 'UTC'),
            'base_url' => $baseUrl,
            'app_name' => Environment::get('APP_NAME', 'Fluxor App'),
            'app_version' => Environment::get('APP_VERSION', '1.0.0'),
            'session_lifetime' => (int) Environment::get('SESSION_LIFETIME', 120),
            'session_secure' => (bool) Environment::get('SESSION_SECURE', false),
            'csrf_token_name' => Environment::get('CSRF_TOKEN_NAME', 'csrf_token'),
            'method_field_name' => Environment::get('METHOD_FIELD_NAME', '_method'),
        ]);
    }

    public function freeze(): self
    {
        $this->lock(...array_keys($this->items));
        return $this;
    }
}