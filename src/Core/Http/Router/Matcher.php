<?php

namespace Fluxor\Core\Http\Router;

class Matcher
{
    private string $routerPath;
    private array $routeCache = [];
    private const CACHE_TTL = 300;

    public function __construct(string $routerPath)
    {
        $this->routerPath = rtrim(str_replace('\\', '/', $routerPath), '/');
    }

    public function find(string $url): ?array
    {
        $cacheKey = 'route_' . md5($url);
        if (isset($this->routeCache[$cacheKey]) && time() - $this->routeCache[$cacheKey]['timestamp'] < self::CACHE_TTL) {
            return $this->routeCache[$cacheKey]['data'];
        }

        $path = trim($url, '/');
        if ($path === '') {
            return $this->findRootRoute();
        }

        $segments = explode('/', $path);

        $result = $this->search($segments, $this->routerPath, [], false);

        if (!$result) {
            $result = $this->searchInGroups($segments, $this->routerPath, []);
        }

        if ($result) {
            $this->cacheRoute($cacheKey, $result);
        }

        return $result;
    }

    private function search(array $segments, string $currentPath, array $params, bool $inGroup = false): ?array
    {
        if (empty($segments)) {
            return $this->checkDirectoryRoute($currentPath, $params);
        }

        $currentSegment = $segments[0];
        $remaining = array_slice($segments, 1);

        $exactDir = $currentPath . '/' . $currentSegment;
        if (is_dir($exactDir)) {
            $found = $this->search($remaining, $exactDir, $params, $inGroup);
            if ($found) {
                return $found;
            }
        }

        foreach (scandir($currentPath) as $item) {
            if ($item === '.' || $item === '..')
                continue;
            $fullPath = $currentPath . '/' . $item;
            if (!is_dir($fullPath))
                continue;

            if (preg_match('/^\[([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $item, $matches)) {
                $newParams = $params;
                $newParams[$matches[1]] = $currentSegment;
                $found = $this->search($remaining, $fullPath, $newParams, $inGroup);
                if ($found) {
                    return $found;
                }
            }
        }

        if (count($segments) === 1) {
            $route = $this->matchFileInDirectory($currentPath, $currentSegment, $params);
            if ($route) {
                return $route;
            }
        }

        if (!$inGroup) {
            foreach (scandir($currentPath) as $item) {
                if ($item === '.' || $item === '..')
                    continue;
                $fullPath = $currentPath . '/' . $item;
                if (!is_dir($fullPath))
                    continue;

                if (preg_match('/^\(([a-zA-Z_][a-zA-Z0-9_]*)\)$/', $item)) {
                    $found = $this->search($segments, $fullPath, $params, true);
                    if ($found) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    private function searchInGroups(array $segments, string $currentPath, array $params): ?array
    {
        foreach (scandir($currentPath) as $item) {
            if ($item === '.' || $item === '..')
                continue;
            $fullPath = $currentPath . '/' . $item;
            if (!is_dir($fullPath))
                continue;

            if (preg_match('/^\(([a-zA-Z_][a-zA-Z0-9_]*)\)$/', $item)) {
                $found = $this->search($segments, $fullPath, $params, true);
                if ($found) {
                    return $found;
                }

                $found = $this->searchInGroups($segments, $fullPath, $params);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function checkDirectoryRoute(string $currentPath, array $params): ?array
    {
        if (is_file($currentPath . '.php')) {
            return [
                'file' => $currentPath . '.php',
                'params' => $params,
                'router_path' => dirname($currentPath)
            ];
        }
        if (is_dir($currentPath) && is_file($currentPath . '/index.php')) {
            return [
                'file' => $currentPath . '/index.php',
                'params' => $params,
                'router_path' => $currentPath
            ];
        }
        return null;
    }

    private function matchFileInDirectory(string $currentPath, string $segment, array $params): ?array
    {
        $directFile = $currentPath . '/' . $segment . '.php';
        if (is_file($directFile)) {
            return [
                'file' => $directFile,
                'params' => $params,
                'router_path' => $currentPath
            ];
        }

        $dirPath = $currentPath . '/' . $segment;
        $indexFile = $dirPath . '/index.php';
        if (is_dir($dirPath) && is_file($indexFile)) {
            return [
                'file' => $indexFile,
                'params' => $params,
                'router_path' => $dirPath
            ];
        }

        foreach (scandir($currentPath) as $item) {
            if ($item === '.' || $item === '..')
                continue;
            $fullPath = $currentPath . '/' . $item;
            if (!is_file($fullPath))
                continue;

            if (preg_match('/^\[([a-zA-Z_][a-zA-Z0-9_]*)\]\.php$/', $item, $matches)) {
                $newParams = $params;
                $newParams[$matches[1]] = $segment;
                return [
                    'file' => $fullPath,
                    'params' => $newParams,
                    'router_path' => $currentPath
                ];
            }
        }

        foreach (scandir($currentPath) as $item) {
            if ($item === '.' || $item === '..')
                continue;
            $fullPath = $currentPath . '/' . $item;
            if (!is_dir($fullPath))
                continue;

            if (preg_match('/^\[([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $item, $matches)) {
                $indexFile = $fullPath . '/index.php';
                if (is_file($indexFile)) {
                    $newParams = $params;
                    $newParams[$matches[1]] = $segment;
                    return [
                        'file' => $indexFile,
                        'params' => $newParams,
                        'router_path' => $fullPath
                    ];
                }
            }
        }

        return null;
    }

    private function findRootRoute(): ?array
    {
        $rootIndex = $this->routerPath . '/index.php';
        if (is_file($rootIndex)) {
            return [
                'file' => $rootIndex,
                'params' => [],
                'router_path' => $this->routerPath
            ];
        }
        return null;
    }

    private function cacheRoute(string $key, array $routeInfo): void
    {
        $this->routeCache[$key] = [
            'data' => $routeInfo,
            'timestamp' => time()
        ];
    }

    public function clearCache(): void
    {
        $this->routeCache = [];
    }
}