<?php

namespace Fluxor\Core\Http;

use Fluxor\Core\Http\Router\Matcher;
use Fluxor\Core\Http\Router\Dispatcher;
use Fluxor\Core\Http\Router\ErrorHandler;

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
        if ($middlewareResponse) {
            $this->sendResponse($middlewareResponse);
            return;
        }

        $routeInfo = $this->matcher->find($request->path);
        
        if ($routeInfo) {
            try {
                (new Dispatcher($request))->dispatch($routeInfo);
            } catch (\Throwable $e) {
                $this->errorHandler->handleError($e, $request, $routeInfo['router_path']);
            }
        } else {
            $this->errorHandler->handleNotFound($request);
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
            if ($basePath && str_starts_with($url, $basePath)) {
                $url = substr($url, strlen($basePath));
            }
        } elseif (!empty($this->baseUrl)) {
            $parsedBase = parse_url($this->baseUrl);
            $basePath = $parsedBase['path'] ?? '';
            if ($basePath && str_starts_with($url, $basePath)) {
                $url = substr($url, strlen($basePath));
            }
        }

        if (str_starts_with($url, '/public')) {
            $url = substr($url, 7);
        }

        $url = '/' . trim(explode('?', $url)[0], '/');
        return $url === '' ? '/' : $url;
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

    private function sendResponse($response): void
    {
        if ($response instanceof Response) {
            $response->send();
        } elseif (is_array($response) || is_object($response)) {
            Response::json($response)->send();
        } elseif (is_string($response)) {
            echo $response;
        }
    }

    private function getAllHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
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
        $this->matcher?->clearCache();
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}