<?php

namespace Fluxor\Core\Http;

class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $body;
    public array $json;
    public array $headers;
    public array $cookies;
    public array $files;
    public array $server;
    public string $ip;
    public string $userAgent;
    public bool $secure;
    public array $params = [];
    private string $routerPath = '';
    private ?array $acceptableContentTypes = null;

    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function setRouterPath(string $path): void
    {
        $this->routerPath = $path;
    }

    public function getRouterPath(): string
    {
        return $this->routerPath;
    }

    public function param(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $this->json[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body, $this->json);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return !empty($value) || $value === '0' || $value === 0;
    }

    public function missing(string $key): bool
    {
        return !$this->has($key);
    }

    public function bearerToken(): ?string
    {
        $header = $this->headers['Authorization'] ?? $this->headers['authorization'] ?? '';
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    public function isJson(): bool
    {
        $contentType = $this->headers['Content-Type'] ?? $this->headers['content-type'] ?? '';
        return str_contains($contentType, 'application/json');
    }

    public function wantsJson(): bool
    {
        return $this->isJson() || in_array('application/json', $this->getAcceptableContentTypes());
    }

    public function expectsHtml(): bool
    {
        return in_array('text/html', $this->getAcceptableContentTypes()) ||
            in_array('*/*', $this->getAcceptableContentTypes());
    }

    public function getAcceptableContentTypes(): array
    {
        if ($this->acceptableContentTypes === null) {
            $accept = $this->headers['Accept'] ?? $this->headers['accept'] ?? '*/*';
            $this->acceptableContentTypes = array_map('trim', explode(',', $accept));
        }
        return $this->acceptableContentTypes;
    }

    public function is(string $pattern): bool
    {
        $pattern = str_replace('/', '\/', $pattern);
        $pattern = preg_replace('/\{[^}]+\}/', '[^\/]+', $pattern);
        return (bool) preg_match('#^' . $pattern . '$#', $this->path);
    }

    public function session(string $key = null, $default = null)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if ($key === null) {
            return $_SESSION;
        }

        return $_SESSION[$key] ?? $default;
    }

    public function validateCsrf(): bool
    {
        $token = $this->input('csrf_token') ??
            $this->headers['X-CSRF-TOKEN'] ??
            $this->headers['x-csrf-token'] ??
            $this->headers['X-XSRF-TOKEN'] ??
            $this->headers['x-xsrf-token'] ?? '';

        $sessionToken = $this->session('csrf_token');

        return $sessionToken && hash_equals($sessionToken, $token);
    }

    public function isAuthenticated(): bool
    {
        return !empty($this->session('user'));
    }

    public function user()
    {
        return $this->session('user');
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($method) === $this->method;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function getClientIp(): string
    {
        return $this->ip;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function __get(string $name)
    {
        return $this->input($name);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }
}