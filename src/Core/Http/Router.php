<?php

namespace Fluxor\Core\Http;

use Fluxor\App;
use Fluxor\Exceptions\AppException;

class Router
{
    private string $basePath;
    private string $baseUrl;
    private array $config = [];
    private array $routeCache = [];
    private array $middlewares = [];
    private const CACHE_TTL = 300;

    public function __construct(string $basePath = '', string $baseUrl = '')
    {
        $this->basePath = $basePath;
        $this->baseUrl = $baseUrl;
    }

    public function setBasePath(string $basePath): self
    {
        $this->basePath = $basePath;
        return $this;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;
        return $this;
    }

    public function addMiddleware(string $name, callable $middleware): self
    {
        $this->middlewares[$name] = $middleware;
        return $this;
    }

    public function removeMiddleware(string $name): self
    {
        unset($this->middlewares[$name]);
        return $this;
    }

    private function runMiddlewares(Request $request): ?Response
    {
        foreach ($this->middlewares as $name => $middleware) {
            $result = $middleware($request);
            
            if ($result instanceof Response) {
                return $result;
            }
            
            if ($result === false) {
                return Response::error('Middleware blocked request', 403);
            }
        }
        
        return null;
    }

    public function dispatch(): void
    {
        $request = $this->createRequest();
        
        $middlewareResponse = $this->runMiddlewares($request);
        if ($middlewareResponse) {
            $this->sendResponse($middlewareResponse);
            return;
        }
        
        $routeInfo = $this->findRoute($request->path);

        if ($routeInfo) {
            $this->executeRoute($routeInfo, $request);
        } else {
            $this->handleNotFound($request);
        }
    }

    private function createRequest(): Request
    {
        return new Request([
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'path' => $this->extractUrl(),
            'query' => $_GET,
            'body' => $_POST,
            'json' => $this->getJsonBody(),
            'headers' => $this->getAllHeaders(),
            'cookies' => $_COOKIE,
            'files' => $_FILES,
            'server' => $_SERVER,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
    }

    private function extractUrl(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $url = parse_url($requestUri, PHP_URL_PATH) ?? '/';

        if (!empty($this->config['base_url'])) {
            $parsedBase = parse_url($this->config['base_url']);
            $basePath = $parsedBase['path'] ?? '';
            
            if ($basePath && strpos($url, $basePath) === 0) {
                $url = substr($url, strlen($basePath));
            }
        } 
        elseif (!empty($this->baseUrl)) {
            $parsedBase = parse_url($this->baseUrl);
            $basePath = $parsedBase['path'] ?? '';
            
            if ($basePath && strpos($url, $basePath) === 0) {
                $url = substr($url, strlen($basePath));
            }
        }
        
        if (strpos($url, '/public') === 0) {
            $url = substr($url, 7);
        }

        $url = explode('?', $url)[0];
        $url = '/' . trim($url, '/');

        return $url === '' ? '/' : $url;
    }

    private function findRoute(string $url): ?array
    {
        $cacheKey = 'route_' . md5($url);

        if (
            isset($this->routeCache[$cacheKey]) &&
            time() - $this->routeCache[$cacheKey]['timestamp'] < self::CACHE_TTL
        ) {
            return $this->routeCache[$cacheKey]['data'];
        }

        $routerPath = $this->config['router_path'] ?? $this->basePath . '/app/router';

        if ($url === '/') {
            $routeFile = $this->findRootRoute($routerPath);
            if ($routeFile) {
                $routeInfo = ['file' => $routeFile, 'params' => [], 'router_path' => $routerPath];
                $this->routeCache[$cacheKey] = ['data' => $routeInfo, 'timestamp' => time()];
                return $routeInfo;
            }
        }

        $segments = explode('/', trim($url, '/'));
        $currentPath = $routerPath;
        $params = [];

        foreach ($segments as $segment) {
            $found = $this->findNextSegment($currentPath, $segment, $params);
            if (!$found) {
                return null;
            }
        }

        $routeFile = $currentPath . '/index.php';
        if (file_exists($routeFile)) {
            $routeInfo = ['file' => $routeFile, 'params' => $params, 'router_path' => $currentPath];
            $this->routeCache[$cacheKey] = ['data' => $routeInfo, 'timestamp' => time()];
            return $routeInfo;
        }

        return null;
    }

    private function findRootRoute(string $routerPath): ?string
    {
        $filesToTry = ['/page.php', '/index.php'];

        foreach ($filesToTry as $file) {
            $fullPath = $routerPath . $file;
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        return null;
    }

    private function findNextSegment(string $currentPath, string $segment, array &$params): bool
    {
        if (!is_dir($currentPath))
            return false;

        $items = scandir($currentPath);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..')
                continue;

            $fullPath = $currentPath . '/' . $item;

            if (is_dir($fullPath) && $item === $segment) {
                return true;
            }

            if (is_dir($fullPath) && preg_match('/^\[([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $item, $matches)) {
                $params[$matches[1]] = $segment;
                return true;
            }

            if (is_dir($fullPath) && preg_match('/^\(([a-zA-Z_][a-zA-Z0-9_]*)\)$/', $item, $matches)) {
                $groupSegmentPath = $fullPath . '/' . $segment;
                if (is_dir($groupSegmentPath)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function executeRoute(array $routeInfo, Request $request): void
    {
        $request->setParams($routeInfo['params']);
        $request->setRouterPath($routeInfo['router_path']);

        try {
            $handler = include $routeInfo['file'];

            if (is_callable($handler)) {
                $response = $handler($request);
                $this->sendResponse($response);
            } else {
                throw new AppException('Route file must return a callable');
            }

        } catch (\Throwable $e) {
            $this->handleError($e, $request, $routeInfo['router_path']);
        }
    }

    private function sendResponse($response): void
    {
        if ($response instanceof Response) {
            $response->send();
        } elseif (is_array($response) || is_object($response)) {
            Response::json($response)->send();
        } elseif (is_string($response)) {
            echo $response;
        } elseif ($response instanceof View) {
            echo $response->render('');
        }
    }

    private function handleNotFound(Request $request): void
    {
        $response = $this->findErrorHandlerRecursive('not-found', $request, [
            'requested_path' => $request->path
        ]);

        if ($response) {
            $this->sendResponse($response);
            return;
        }

        Response::json([
            'error' => 'Not Found',
            'message' => 'The requested resource was not found',
            'path' => $request->path
        ], 404)->send();
    }

    private function handleError(\Throwable $e, Request $request, string $routerPath)
    {
        $statusCode = $e instanceof AppException ? $e->getCode() : 500;
        $statusCode = $statusCode ?: 500;

        $errorType = $this->getErrorTypeFromStatusCode($statusCode);

        $response = $this->findErrorHandlerRecursive($errorType, $request, [
            'exception' => $e,
            'status_code' => $statusCode
        ], $routerPath);

        if ($response) {
            $this->sendResponse($response);
            return;
        }

        // Fallback para erro genérico
        if (App::make()->isDevelopment()) {
            $response = [
                'error' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ];
        } else {
            $response = [
                'error' => 'Internal Server Error',
                'message' => 'Something went wrong',
            ];
        }

        Response::json($response, $statusCode)->send();
    }

    private function findErrorHandlerRecursive(string $type, Request $request, array $context = [], string $startPath = null)
    {
        $routerPath = $this->config['router_path'] ?? $this->basePath . '/app/router';
        $currentPath = $startPath ?: $request->getRouterPath();

        while ($currentPath && strpos($currentPath, $routerPath) === 0) {
            $specificFile = $currentPath . '/' . $type . '.php';
            if (file_exists($specificFile)) {
                $handler = include $specificFile;
                if (is_callable($handler)) {
                    $request->setParams(array_merge($request->params, $context));
                    return $handler($request);
                }
            }

            $statusCode = $this->getStatusCodeFromType($type);
            if ($statusCode) {
                $statusFile = $currentPath . '/' . $statusCode . '.php';
                if (file_exists($statusFile)) {
                    $handler = include $statusFile;
                    if (is_callable($handler)) {
                        $request->setParams(array_merge($request->params, $context));
                        return $handler($request);
                    }
                }
            }

            $parentPath = dirname($currentPath);
            if ($parentPath === $currentPath) {
                break;
            }
            $currentPath = $parentPath;
        }

        return null;
    }

    private function getErrorTypeFromStatusCode(int $statusCode): string
    {
        $map = [
            400 => 'bad-request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not-found',
            405 => 'not-allowed',
            419 => 'csrf-error',
            422 => 'validation-error',
            429 => 'too-many-requests',
            500 => 'server-error',
            503 => 'service-unavailable',
        ];

        return $map[$statusCode] ?? 'server-error';
    }

    private function getStatusCodeFromType(string $type): ?int
    {
        $map = [
            'not-found' => 404,
            'not-allowed' => 405,
            'unauthorized' => 401,
            'forbidden' => 403,
            'server-error' => 500,
            'bad-request' => 400,
            'validation-error' => 422,
            'csrf-error' => 419,
        ];

        return $map[$type] ?? null;
    }

    private function getAllHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    private function getJsonBody(): array
    {
        $input = file_get_contents('php://input');

        if ($input) {
            $json = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        return [];
    }

    public function clearCache(): void
    {
        $this->routeCache = [];
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}