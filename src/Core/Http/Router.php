<?php

namespace Fluxor\Core\Http;

use Fluxor\Core\App;
use Fluxor\Core\Http\Router\Dispatcher;
use Fluxor\Core\Http\Router\ErrorHandler;
use FileRouter\FileRouter;
use FileRouter\Extractor\FlowMethodExtractor;

class Router
{
    private string $basePath;
    private string $baseUrl;
    private array $middlewares = [];
    private FileRouter $fileRouter;
    private ErrorHandler $errorHandler;
    private ?Cors $cors = null;

    public function __construct(string $basePath = '', string $baseUrl = '')
    {
        $this->basePath = $basePath;
        $this->baseUrl = $baseUrl;
    }

    public function setPaths(?string $routerPath = null, ?string $viewsPath = null, ?string $cacheDir = null): self
    {
        $routerPath ??= $this->basePath . '/app/router';
        $viewsPath ??= $this->basePath . '/src/Views';

        // Route matching is delegated to the standalone lizzyman04/file-router
        // package. The FlowMethodExtractor keeps Fluxor's Flow::METHOD() route
        // files working unchanged; Flow dispatch still happens in Dispatcher.
        $this->fileRouter = new FileRouter($routerPath, [
            'methodExtractor' => new FlowMethodExtractor(),
            'cacheDir' => $cacheDir,
        ]);

        $this->errorHandler = new ErrorHandler($routerPath, $viewsPath);

        return $this;
    }

    /**
     * Resolve a request to a route. Returns null for a 404, an array with
     * 'method_not_allowed' for a 405, or the route info for a match.
     */
    public function resolve(Request $request): ?array
    {
        $match = $this->fileRouter->match($request->method, $request->path);

        if ($match === null) {
            return null;
        }

        if ($match->isMethodNotAllowed()) {
            return [
                'method_not_allowed' => true,
                'allowed_methods' => $match->allowedMethods,
                'pattern' => $match->pattern,
            ];
        }

        return [
            'file' => $match->file,
            'params' => $match->params,
            'router_path' => \dirname($match->file),
            'pattern' => $match->pattern,
            'method' => $match->method,
        ];
    }

    public function setCors(Cors $cors): void
    {
        $this->cors = $cors;
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

    public function dispatch(?Request $request = null): void
    {
        $request = $request ?? $this->createRequest();

        $middlewareResponse = $this->runMiddlewares($request);
        if ($middlewareResponse !== null) {
            $middlewareResponse->send();
            return;
        }

        $routeInfo = $this->resolve($request);

        if ($routeInfo === null) {
            $this->errorHandler->handleNotFound($request)->send();
            return;
        }

        if (!empty($routeInfo['method_not_allowed'])) {
            $this->errorHandler->handleMethodNotAllowed($request, $routeInfo['allowed_methods'])->send();
            return;
        }

        if ($this->cors !== null) {
            $corsResponse = $this->cors->apply($request, $routeInfo['pattern']);
            if ($corsResponse instanceof Response) {
                $corsResponse->send();
                return;
            }
        }

        try {
            (new Dispatcher($request))->dispatch($routeInfo)->send();
        } catch (\Throwable $e) {
            $this->errorHandler->handleError($e, $request, $routeInfo['router_path'])->send();
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
        $url = \parse_url($requestUri, PHP_URL_PATH) ?? '/';

        $baseUrl = $this->baseUrl;

        if (!empty($baseUrl)) {
            $basePath = \parse_url($baseUrl, PHP_URL_PATH) ?? '';
            if ($basePath !== '' && \str_starts_with($url, $basePath)) {
                $url = \substr($url, \strlen($basePath));
            }
        }

        if (\str_starts_with($url, '/public')) {
            $url = \substr($url, 7);
        }

        $url = '/' . \trim(\strtok($url, '?'), '/');
        return $url === '/' ? '/' : $url;
    }

    private function runMiddlewares(Request $request): ?Response
    {
        foreach ($this->middlewares as $middleware) {
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

    private function getAllHeaders(): array
    {
        if (\function_exists('getallheaders')) {
            return \getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (\str_starts_with($name, 'HTTP_')) {
                $key = \str_replace(' ', '-', \ucwords(\strtolower(\str_replace('_', ' ', \substr($name, 5)))));
                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    private function getJsonBody(): array
    {
        $input = \file_get_contents('php://input');

        if ($input === false || $input === '') {
            return [];
        }

        $json = \json_decode($input, true);
        return \json_last_error() === JSON_ERROR_NONE ? $json : [];
    }

    public function clearCache(): void
    {
        if (isset($this->fileRouter)) {
            $this->fileRouter->clearCache();
        }
    }

    /**
     * Remove file-router's compiled route cache files from storage/cache.
     * Used by the `fluxor clear-router-cache` CLI command.
     */
    public static function clearRouterCache(): void
    {
        $app = App::getInstance();
        $storagePath = $app ? $app->getStoragePath() : \getcwd() . '/storage';
        $cacheDir = $storagePath . '/cache';

        foreach (\glob($cacheDir . '/file_router_*.php') ?: [] as $file) {
            @\unlink($file);
        }
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}