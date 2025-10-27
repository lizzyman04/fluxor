<?php

namespace MVCCore\Core;

use MVCCore\Exceptions\AppException;

class Flow
{
    private static array $handlers = [];
    private static array $middlewares = [];
    private static ?string $currentMethod = null;

    public static function __callStatic($method, $args): self
    {
        $instance = new static();
        return $instance->method(strtoupper($method));
    }

    public function method(string $method): self
    {
        self::$currentMethod = strtoupper($method);
        return $this;
    }

    public function do(callable $handler): void
    {
        if (!self::$currentMethod) {
            throw new AppException('No method specified. Use Flow::GET(), Flow::POST(), etc.');
        }

        self::$handlers[self::$currentMethod] = $handler;
        self::$currentMethod = null;
    }

    public function to(string $controllerClass, string $method = null): void
    {
        if (!self::$currentMethod) {
            throw new AppException('No method specified. Use Flow::GET(), Flow::POST(), etc.');
        }

        $method = $method ?: strtolower(self::$currentMethod);

        $this->do(function ($req) use ($controllerClass, $method) {
            if (!class_exists($controllerClass)) {
                throw new AppException("Controller not found: {$controllerClass}");
            }

            $controller = new $controllerClass();
            
            if (!method_exists($controller, $method)) {
                throw new AppException("Method {$method} not found in {$controllerClass}");
            }

            return $controller->$method($req);
        });

        self::$currentMethod = null;
    }

    public static function use(callable $middleware): void
    {
        self::$middlewares[] = $middleware;
    }

    public static function any(callable $handler): void
    {
        self::$handlers['ANY'] = $handler;
    }

    public static function execute(Request $request)
    {
        foreach (self::$middlewares as $middleware) {
            $result = $middleware($request);
            if ($result !== null) {
                return $result;
            }
        }

        $method = $request->method;
        $handler = self::$handlers[$method] ?? self::$handlers['ANY'] ?? null;

        if ($handler) {
            return $handler($request);
        }

        return self::handleMethodNotAllowed($request);
    }

    private static function handleMethodNotAllowed(Request $request)
    {
        $allowedMethods = array_keys(self::$handlers);
        
        $notAllowedResponse = self::findErrorHandler('not-allowed', $request, [
            'allowed_methods' => $allowedMethods
        ]);

        if ($notAllowedResponse) {
            return $notAllowedResponse;
        }

        return Response::error('Method Not Allowed', 405, [
            'allowed_methods' => $allowedMethods
        ]);
    }

    private static function findErrorHandler(string $type, Request $request, array $context = [])
    {
        $routerPath = App::make()->getConfig()['router_path'] ?? '';
        $currentPath = $request->getRouterPath();
        
        while ($currentPath && $currentPath !== $routerPath) {
            $errorFile = $currentPath . '/' . $type . '.php';
            
            if (file_exists($errorFile)) {
                $handler = include $errorFile;
                if (is_callable($handler)) {
                    $request->setParams(array_merge($request->params, $context));
                    return $handler($request);
                }
            }
            
            $statusCode = self::getStatusCodeFromType($type);
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
            
            $currentPath = dirname($currentPath);
        }

        $rootErrorFile = $routerPath . '/' . $type . '.php';
        if (file_exists($rootErrorFile)) {
            $handler = include $rootErrorFile;
            if (is_callable($handler)) {
                $request->setParams(array_merge($request->params, $context));
                return $handler($request);
            }
        }

        return null;
    }

    private static function getStatusCodeFromType(string $type): ?int
    {
        $statusMap = [
            'not-found' => 404,
            'not-allowed' => 405,
            'unauthorized' => 401,
            'forbidden' => 403,
            'server-error' => 500,
        ];
        
        return $statusMap[$type] ?? null;
    }

    public static function clear(): void
    {
        self::$handlers = [];
        self::$middlewares = [];
        self::$currentMethod = null;
    }
}