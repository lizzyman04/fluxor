<?php

namespace Fluxor\Core\Http;

use Fluxor\Core\Http\Router\Dispatcher;
use Fluxor\Core\Http\Router\ErrorHandler;
use Fluxor\Core\Http\Router\Matcher;

class Router
{
    private string $basePath;
    private string $baseUrl;
    private array $config = [];
    private array $middlewares = [];
    private Matcher $matcher;
    private ErrorHandler $errorHandler;

    public function __construct(string $basePath = '', string $baseUrl = '')
    {
        $this->basePath = $basePath;
        $this->baseUrl = $baseUrl;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;

        $routerPath = $this->config['router_path'] ?? $this->basePath . '/app/router';
        $viewsPath = $this->config['views_path'] ?? $this->basePath . '/src/Views';

        $this->matcher = new Matcher($routerPath);
        $this->errorHandler = new ErrorHandler($routerPath, $viewsPath);

        $this->matcher->compile();

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

    public function dispatch(): void
    {
        $request = $this->createRequest();

        $middlewareResponse = $this->runMiddlewares($request);
        if ($middlewareResponse !== null) {
            $middlewareResponse->send();
            return;
        }

        $routeInfo = $this->matcher->find($request->path, $request->method);

        if ($routeInfo === null) {
            $this->errorHandler->handleNotFound($request)->send();
            return;
        }

        if (!empty($routeInfo['method_not_allowed'])) {
            $this->errorHandler->handleMethodNotAllowed($request, $routeInfo['allowed_methods'])->send();
            return;
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

        $baseUrl = $this->config['base_url'] ?? $this->baseUrl;

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
        $this->matcher?->clearCache();
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}