<?php

namespace Fluxor\Core\Http\Router;

class Matcher
{
    private string $routerPath;
    private array $routeCache = [];
    private array $methodCache = [];
    private array $compiledRoutes = [];
    private bool $useCache = true;
    private bool $compiled = false;
    private string $cacheFile;

    public function __construct(string $routerPath)
    {
        $this->routerPath = \rtrim(\str_replace('\\', '/', $routerPath), '/');
        $this->cacheFile = \sys_get_temp_dir() . '/fluxor_routes_' . \md5($this->routerPath) . '.php';
    }

    public function compile(): void
    {
        if ($this->compiled) {
            return;
        }

        if ($this->useCache && \file_exists($this->cacheFile)) {
            $cached = include $this->cacheFile;
            if (\is_array($cached) && isset($cached['routes'], $cached['methods'])) {
                $this->compiledRoutes = $cached['routes'];
                $this->methodCache = $cached['methods'];
                $this->compiled = true;
                return;
            }
        }

        $this->compiledRoutes = [];
        $this->methodCache = [];
        $this->scanDirectory($this->routerPath);
        $this->sortRoutes();

        if ($this->useCache) {
            \file_put_contents(
                $this->cacheFile,
                '<?php return ' . \var_export([
                    'routes' => $this->compiledRoutes,
                    'methods' => $this->methodCache,
                ], true) . ';'
            );
        }

        $this->compiled = true;
    }

    private function scanDirectory(string $currentPath): void
    {
        $items = \scandir($currentPath);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (\is_dir($currentPath . '/' . $item)) {
                $this->scanDirectory($currentPath . '/' . $item);
            }
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $currentPath . '/' . $item;

            if (\is_file($fullPath) && \pathinfo($item, PATHINFO_EXTENSION) === 'php') {
                $relativePath = \substr($fullPath, \strlen($this->routerPath) + 1);
                $pattern = $this->buildPattern($relativePath);
                $methods = $this->extractHttpMethods($fullPath);

                foreach ($methods as $method) {
                    $this->compiledRoutes[$method . '|' . $pattern] = [
                        'file' => $fullPath,
                        'pattern' => $pattern,
                        'method' => $method,
                        'regex' => $this->buildRegex($pattern),
                    ];
                }
            }
        }
    }

    private function buildPattern(string $relativePath): string
    {
        $parts = \explode('/', \str_replace('\\', '/', $relativePath));
        $segments = [];
        $total = \count($parts);

        foreach ($parts as $i => $part) {
            $isLast = ($i === $total - 1);

            if ($isLast) {
                $fileName = \pathinfo($part, PATHINFO_FILENAME);

                if ($fileName === 'index') {
                    continue;
                }

                if (\preg_match('/^\[\.\.\.([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $fileName, $m)) {
                    $segments[] = '{' . $m[1] . ':*}';
                } elseif (\preg_match('/^\[([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $fileName, $m)) {
                    $segments[] = '{' . $m[1] . '}';
                } else {
                    $segments[] = $fileName;
                }
            } else {
                if (\preg_match('/^\([a-zA-Z_][a-zA-Z0-9_]*\)$/', $part)) {
                    continue;
                }

                if (\preg_match('/^\[\.\.\.([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $part, $m)) {
                    $segments[] = '{' . $m[1] . ':*}';
                } elseif (\preg_match('/^\[([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $part, $m)) {
                    $segments[] = '{' . $m[1] . '}';
                } else {
                    $segments[] = $part;
                }
            }
        }

        return empty($segments) ? '/' : '/' . \implode('/', $segments);
    }

    private function buildRegex(string $pattern): string
    {
        if ($pattern === '/') {
            return '#^/$#';
        }

        $parts = \preg_split('/(\{[a-zA-Z_][a-zA-Z0-9_]*(?::\*)?\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';

        foreach ($parts as $part) {
            if (\preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*):\*\}$/', $part, $m)) {
                $regex .= '(?P<' . $m[1] . '>.+)';
            } elseif (\preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $part, $m)) {
                // Aceita qualquer caracter excepto barra — incluindo hífens, pontos, underscores
                $regex .= '(?P<' . $m[1] . '>[^/]+)';
            } else {
                $regex .= \preg_quote($part, '#');
            }
        }

        return '#^' . $regex . '$#';
    }

    private function sortRoutes(): void
    {
        \uasort($this->compiledRoutes, static function (array $a, array $b): int {
            $aCatch = \substr_count($a['pattern'], ':*}');
            $bCatch = \substr_count($b['pattern'], ':*}');

            if ($aCatch !== $bCatch) {
                return $aCatch - $bCatch;
            }

            $aParams = \substr_count($a['pattern'], '{');
            $bParams = \substr_count($b['pattern'], '{');

            if ($aParams !== $bParams) {
                return $aParams - $bParams;
            }

            return \strlen($b['pattern']) - \strlen($a['pattern']);
        });
    }

    private function extractHttpMethods(string $file): array
    {
        if (isset($this->methodCache[$file])) {
            return $this->methodCache[$file];
        }

        $content = \file_get_contents($file);
        if ($content === false) {
            return ['GET'];
        }

        \preg_match_all('/Flow::([A-Z]+)\s*\(/', $content, $matches);

        if (!empty($matches[1]) && \in_array('ANY', $matches[1], true)) {
            $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        } elseif (!empty($matches[1])) {
            $methods = \array_values(\array_unique($matches[1]));
        } else {
            $methods = ['GET'];
        }

        $this->methodCache[$file] = $methods;
        return $methods;
    }

    public function find(string $url, string $method = 'GET'): ?array
    {
        if (!$this->compiled) {
            $this->compile();
        }

        $cacheKey = $method . '|' . $url;

        if ($this->useCache && isset($this->routeCache[$cacheKey])) {
            return $this->routeCache[$cacheKey];
        }

        $path = \trim($url, '/');
        $path = $path === '' ? '/' : '/' . $path;

        $matchedFile = null;
        $matchedPattern = null;

        foreach ($this->compiledRoutes as $key => $route) {
            $params = [];
            if (!$this->matchPattern($route['regex'], $path, $params)) {
                continue;
            }

            [$routeMethod] = \explode('|', $key, 2);

            if ($routeMethod === $method || $routeMethod === 'ANY') {
                $result = [
                    'file' => $route['file'],
                    'params' => $params,
                    'router_path' => \dirname($route['file']),
                    'pattern' => $route['pattern'],
                    'method' => $method,
                ];

                if ($this->useCache) {
                    $this->routeCache[$cacheKey] = $result;
                }

                return $result;
            }

            if ($matchedFile === null) {
                $matchedFile = $route['file'];
                $matchedPattern = $route['pattern'];
            }
        }

        if ($matchedFile !== null) {
            return [
                'file' => null,
                'params' => [],
                'router_path' => \dirname($matchedFile),
                'pattern' => $matchedPattern,
                'method' => $method,
                'method_not_allowed' => true,
                'allowed_methods' => $this->getAllowedMethodsForPattern($matchedPattern),
            ];
        }

        return null;
    }

    private function getAllowedMethodsForPattern(string $pattern): array
    {
        $allowed = [];

        foreach ($this->compiledRoutes as $key => $route) {
            if ($route['pattern'] !== $pattern) {
                continue;
            }
            [$routeMethod] = \explode('|', $key, 2);
            $allowed[] = $routeMethod;
        }

        return \array_unique($allowed);
    }

    private function matchPattern(string $regex, string $path, array &$params): bool
    {
        if (!\preg_match($regex, $path, $matches)) {
            return false;
        }

        $params = \array_filter($matches, static fn($k) => !\is_numeric($k), ARRAY_FILTER_USE_KEY);
        return true;
    }

    public function clearCache(): void
    {
        $this->routeCache = [];
        $this->compiledRoutes = [];
        $this->methodCache = [];
        $this->compiled = false;

        if (\file_exists($this->cacheFile)) {
            \unlink($this->cacheFile);
        }
    }

    public function disableCache(): void
    {
        $this->useCache = false;
    }

    public function enableCache(): void
    {
        $this->useCache = true;
    }
}