<?php

namespace Fluxor\Core\Http;

class Cors
{
    private array $config = [
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'User-Agent'],
        'exposed_headers' => [],
        'max_age' => 86400,
        'supports_credentials' => false,
    ];

    private bool $enabled = true;
    private array $routeConfigs = [];

    public function __construct(array $config = [])
    {
        $this->config = [...$this->config, ...$config];
    }

    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }

    public function configure(array $config): self
    {
        $this->config = [...$this->config, ...$config];
        return $this;
    }

    public function forRoute(string $routePattern, array $config): self
    {
        $this->routeConfigs[$routePattern] = [...$this->config, ...$config];
        return $this;
    }

    public function allowOrigin(string $origin): self
    {
        $this->config['allowed_origins'] = [$origin];
        return $this;
    }

    public function allowOrigins(array $origins): self
    {
        $this->config['allowed_origins'] = $origins;
        return $this;
    }

    public function allowCredentials(bool $allow = true): self
    {
        $this->config['supports_credentials'] = $allow;
        return $this;
    }

    public function apply(Request $request, ?string $routePath = null): ?Response
    {
        if (!$this->enabled) {
            return null;
        }

        $config = $this->getConfigForRoute($routePath);
        $origin = $request->headers['Origin'] ?? $request->headers['origin'] ?? null;

        if (!$origin) {
            return null;
        }

        if (!$this->isOriginAllowed($origin, $config)) {
            return Response::error('CORS origin not allowed', 403);
        }

        if ($request->method === 'OPTIONS') {
            return $this->handlePreflight($request, $config);
        }

        $this->applyHeaders($config, $origin);
        return null;
    }

    private function getConfigForRoute(?string $routePath): array
    {
        if ($routePath === null) {
            return $this->config;
        }

        foreach ($this->routeConfigs as $pattern => $config) {
            if ($this->matchesPattern($pattern, $routePath)) {
                return $config;
            }
        }

        return $this->config;
    }

    private function matchesPattern(string $pattern, string $path): bool
    {
        $pattern = \preg_quote($pattern, '#');
        $pattern = \str_replace('\\*', '.*', $pattern);
        return (bool) \preg_match("#^{$pattern}$#", $path);
    }

    private function isOriginAllowed(string $origin, array $config): bool
    {
        $allowed = $config['allowed_origins'];
        
        if (\in_array('*', $allowed, true)) {
            return true;
        }

        return \in_array($origin, $allowed, true);
    }

    private function handlePreflight(Request $request, array $config): Response
    {
        $requestMethod = $request->headers['Access-Control-Request-Method'] ?? null;
        $requestHeaders = $request->headers['Access-Control-Request-Headers'] ?? null;

        if ($requestMethod && !\in_array($requestMethod, $config['allowed_methods'], true)) {
            return Response::error('Method not allowed', 405);
        }

        $response = Response::text('', 204);
        $response->header('Access-Control-Allow-Origin', $this->getAllowOriginHeader($config));
        $response->header('Access-Control-Allow-Methods', \implode(', ', $config['allowed_methods']));
        $response->header('Access-Control-Max-Age', (string) $config['max_age']);

        if ($requestHeaders) {
            $response->header('Access-Control-Allow-Headers', $requestHeaders);
        } elseif (!empty($config['allowed_headers'])) {
            $response->header('Access-Control-Allow-Headers', \implode(', ', $config['allowed_headers']));
        }

        if ($config['supports_credentials']) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        if (!empty($config['exposed_headers'])) {
            $response->header('Access-Control-Expose-Headers', \implode(', ', $config['exposed_headers']));
        }

        return $response;
    }

    private function applyHeaders(array $config, string $origin): void
    {
        if (!\headers_sent()) {
            \header("Access-Control-Allow-Origin: {$this->getAllowOriginHeader($config)}");
            \header("Access-Control-Allow-Methods: " . \implode(', ', $config['allowed_methods']));
            \header("Access-Control-Allow-Headers: " . \implode(', ', $config['allowed_headers']));
            \header("Access-Control-Max-Age: {$config['max_age']}");

            if ($config['supports_credentials']) {
                \header('Access-Control-Allow-Credentials: true');
            }

            if (!empty($config['exposed_headers'])) {
                \header('Access-Control-Expose-Headers: ' . \implode(', ', $config['exposed_headers']));
            }
        }
    }

    private function getAllowOriginHeader(array $config): string
    {
        $origins = $config['allowed_origins'];
        
        if (\in_array('*', $origins, true)) {
            return '*';
        }

        return \implode(', ', $origins);
    }
}