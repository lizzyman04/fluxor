<?php
/**
 * Fluxor - Flow Routing Engine
 * 
 * Elegant, file-based routing with chainable syntax.
 */

namespace Fluxor\Core\Routing;

use Fluxor\Core\App;
use Fluxor\Core\Http\Request;
use Fluxor\Core\Http\Response;
use Fluxor\Helpers\HttpStatusCode;
use Fluxor\Exceptions\AppException;
use Fluxor\Exceptions\HttpException;
use Fluxor\Exceptions\NotFoundException;

class Flow
{
    private static array $handlers = [];
    private static array $middlewares = [];
    private static ?string $currentMethod = null;
    private static ?string $currentPattern = null;
    private static ?string $currentName = null;
    private static bool $executed = false;
    private static array $patternCache = [];
    private static ?string $viewsPath = null;

    private const ERROR_TYPE_MAP = [
        'bad-request' => HttpStatusCode::BAD_REQUEST,
        'unauthorized' => HttpStatusCode::UNAUTHORIZED,
        'payment-required' => HttpStatusCode::PAYMENT_REQUIRED,
        'forbidden' => HttpStatusCode::FORBIDDEN,
        'not-found' => HttpStatusCode::NOT_FOUND,
        'not-allowed' => HttpStatusCode::METHOD_NOT_ALLOWED,
        'not-acceptable' => HttpStatusCode::NOT_ACCEPTABLE,
        'conflict' => HttpStatusCode::CONFLICT,
        'gone' => HttpStatusCode::GONE,
        'too-many-requests' => HttpStatusCode::TOO_MANY_REQUESTS,
        'server-error' => HttpStatusCode::INTERNAL_SERVER_ERROR,
        'service-unavailable' => HttpStatusCode::SERVICE_UNAVAILABLE,
        'validation-error' => HttpStatusCode::UNPROCESSABLE_ENTITY,
        'csrf-error' => 419,
    ];

    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = rtrim($path, '/\\');
    }

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

    public function pattern(string $pattern): self
    {
        self::$currentPattern = $pattern;
        return $this;
    }

    public function name(string $name): self
    {
        self::$currentName = $name;
        return $this;
    }

    public function do(callable $handler): void
    {
        $this->validateMethod();

        $pattern = self::$currentPattern ?? $this->detectPatternFromFile();
        $key = self::buildKey(self::$currentMethod, $pattern);

        self::$handlers[$key] = [
            'handler' => $handler,
            'method' => self::$currentMethod,
            'pattern' => $pattern,
            'name' => self::$currentName,
        ];

        $this->resetState();
    }

    public function to(string $controllerClass, string $method = null): void
    {
        $this->validateMethod();
        $method = $method ?: strtolower(self::$currentMethod);

        $this->do(function ($req) use ($controllerClass, $method) {
            if (!class_exists($controllerClass)) {
                throw new AppException("Controller not found: {$controllerClass}");
            }

            $controller = new $controllerClass();

            if (!method_exists($controller, $method)) {
                throw new AppException("Method {$method} not found in {$controllerClass}");
            }

            if (method_exists($controller, 'setRequest')) {
                $controller->setRequest($req);
            }

            return $controller->$method($req);
        });
    }

    public static function use(callable $middleware): void
    {
        self::$middlewares[] = $middleware;
    }

    public static function any(callable $handler): void
    {
        self::$handlers['ANY'] = [
            'handler' => $handler,
            'method' => 'ANY',
            'pattern' => null,
            'name' => null,
        ];
    }

    public static function execute(Request $request)
    {
        if (self::$executed) {
            self::resetForNewRequest();
        }

        foreach (self::$middlewares as $middleware) {
            $result = $middleware($request);

            if ($result instanceof Response) {
                return $result;
            }

            if ($result === false) {
                return Response::error('Middleware blocked request', 403);
            }
        }

        $handler = self::findHandler($request);

        if ($handler) {
            try {
                $response = $handler($request);
                self::$executed = true;
                return $response;
            } catch (NotFoundException $e) {
                return self::handleNotFound($request, $e);
            } catch (HttpException $e) {
                return self::handleHttpException($request, $e);
            } catch (\Throwable $e) {
                return self::handleError($request, $e);
            }
        }

        self::$executed = true;
        return self::handleNotFound($request);
    }

    private static function resetForNewRequest(): void
    {
        if (php_sapi_name() === 'cli' || defined('PHPUNIT_RUNNING')) {
            return;
        }

        self::$executed = false;

        if (count(self::$patternCache) > 100) {
            self::$patternCache = [];
        }
    }

    private static function findHandler(Request $request): ?callable
    {
        $method = $request->method;
        $path = $request->path;

        $exactKey = self::buildKey($method, $path);
        if (isset(self::$handlers[$exactKey])) {
            return self::$handlers[$exactKey]['handler'];
        }

        foreach (self::$handlers as $key => $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }

            if (!isset($route['pattern']) || !$route['pattern']) {
                continue;
            }

            if (self::matchesPattern($route['pattern'], $path, $params)) {
                $request->setParams($params);
                return $route['handler'];
            }
        }

        if (isset(self::$handlers['ANY'])) {
            return self::$handlers['ANY']['handler'];
        }

        return null;
    }

    private static function matchesPattern(string $pattern, string $path, ?array &$params = []): bool
    {
        $regex = preg_quote($pattern, '#');

        $regex = preg_replace_callback('/\\\\\[([a-zA-Z_][a-zA-Z0-9_]*)\\\\\]/', function ($matches) {
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $regex);

        $regex = preg_replace_callback('/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}/', function ($matches) {
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $regex);

        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return true;
        }

        return false;
    }

    private function detectPatternFromFile(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        try {
            $app = App::make();
            $routerPath = $app->getConfig()['router_path'] ?? $app->getBasePath() . '/app/router';
            if (self::$viewsPath === null) {
                self::$viewsPath = $app->getConfig()['views_path'] ?? $app->getBasePath() . '/src/Views';
            }
        } catch (\Throwable $e) {
            $routerPath = getcwd() . '/app/router';
        }

        $routerPath = rtrim(str_replace('\\', '/', $routerPath), '/');

        foreach ($trace as $frame) {
            if (!isset($frame['file']))
                continue;

            $file = str_replace('\\', '/', $frame['file']);
            if (strpos($file, $routerPath) !== 0)
                continue;

            $relativePath = substr($file, strlen($routerPath) + 1);
            $pathParts = explode('/', $relativePath);
            $fileName = array_pop($pathParts);
            $fileName = basename($fileName, '.php');

            $filteredParts = array_filter($pathParts, function ($part) {
                return !preg_match('/^\([^)]+\)$/', $part);
            });

            if (empty($filteredParts)) {
                return ($fileName === 'index') ? '/' : '/' . $fileName;
            }

            $pattern = '/' . implode('/', array_map(function ($part) {
                return preg_replace_callback('/\[([a-zA-Z_][a-zA-Z0-9_]*)\]/', function ($matches) {
                    return '{' . $matches[1] . '}';
                }, $part);
            }, $filteredParts));

            if ($fileName !== 'index') {
                $pattern .= '/' . $fileName;
            }

            return $pattern;
        }

        return '/';
    }

    private static function handleNotFound(Request $request, ?NotFoundException $e = null)
    {
        $response = self::findErrorHandler('not-found', $request, [
            'requested_path' => $request->path,
            'exception' => $e,
        ]);

        if ($response) {
            return $response;
        }

        if ($request->wantsJson()) {
            return Response::error('Not Found', 404);
        }

        return self::renderErrorPage(404, 'Not Found');
    }

    private static function handleHttpException(Request $request, HttpException $e)
    {
        $statusCode = $e->getStatusCode();
        $errorType = self::getErrorTypeFromStatusCode($statusCode);

        $response = self::findErrorHandler($errorType, $request, [
            'exception' => $e,
            'status_code' => $statusCode,
        ]);

        if ($response) {
            return $response;
        }

        if ($request->wantsJson()) {
            return Response::error($e->getMessage(), $statusCode);
        }

        return self::renderErrorPage($statusCode, $e->getMessage());
    }

    private static function handleError(Request $request, \Throwable $e)
    {
        $statusCode = $e instanceof AppException && $e->getCode() >= 400
            ? $e->getCode()
            : HttpStatusCode::INTERNAL_SERVER_ERROR;

        $app = null;
        try {
            $app = App::getInstance();
        } catch (\Throwable $ignored) {
        }

        if ($app && $app->isDevelopment()) {
            return Response::json([
                'error' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ], $statusCode);
        }

        return self::renderErrorPage($statusCode, HttpStatusCode::message($statusCode));
    }

    private static function renderErrorPage(int $statusCode, string $message): Response
    {
        if (self::$viewsPath === null) {
            try {
                $app = App::make();
                self::$viewsPath = $app->getConfig()['views_path'] ?? $app->getBasePath() . '/src/Views';
            } catch (\Throwable $e) {
                self::$viewsPath = getcwd() . '/src/Views';
            }
        }

        $userView = self::$viewsPath . "/errors/{$statusCode}.php";
        if (file_exists($userView)) {
            ob_start();
            extract(['statusCode' => $statusCode, 'message' => $message]);
            include $userView;
            $content = ob_get_clean();
            return Response::html($content, $statusCode);
        }

        $userCommonView = self::$viewsPath . "/errors/common.php";
        if (file_exists($userCommonView)) {
            ob_start();
            extract(['statusCode' => $statusCode, 'message' => $message]);
            include $userCommonView;
            $content = ob_get_clean();
            return Response::html($content, $statusCode);
        }

        $vendorView = dirname(__DIR__, 2) . '/Resources/views/errors/common.php';
        if (file_exists($vendorView)) {
            ob_start();
            extract(['statusCode' => $statusCode, 'message' => $message]);
            include $vendorView;
            $content = ob_get_clean();
            return Response::html($content, $statusCode);
        }

        return Response::text("{$statusCode}\n" . htmlspecialchars($message), $statusCode);
    }

    private static function findErrorHandler(string $type, Request $request, array $context = [])
    {
        try {
            $app = App::make();
            $config = $app->getConfig();
            $routerPath = $config['router_path'] ?? '';
        } catch (\Throwable $e) {
            $routerPath = '';
        }

        $currentPath = $request->getRouterPath();

        while ($currentPath && $currentPath !== $routerPath && $currentPath !== dirname($currentPath)) {
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

        if ($routerPath) {
            $rootErrorFile = $routerPath . '/' . $type . '.php';
            if (file_exists($rootErrorFile)) {
                $handler = include $rootErrorFile;
                if (is_callable($handler)) {
                    $request->setParams(array_merge($request->params, $context));
                    return $handler($request);
                }
            }
        }

        return null;
    }

    private static function getStatusCodeFromType(string $type): ?int
    {
        return self::ERROR_TYPE_MAP[$type] ?? null;
    }

    private static function getErrorTypeFromStatusCode(int $statusCode): string
    {
        $map = array_flip(self::ERROR_TYPE_MAP);
        return $map[$statusCode] ?? 'server-error';
    }

    private static function buildKey(string $method, string $pattern): string
    {
        return $method . ':' . $pattern;
    }

    public static function route(string $name, array $params = []): string
    {
        foreach (self::$handlers as $handler) {
            if (($handler['name'] ?? null) === $name) {
                $pattern = $handler['pattern'] ?? '';

                foreach ($params as $key => $value) {
                    $pattern = str_replace('{' . $key . '}', $value, $pattern);
                    $pattern = str_replace('{' . $key . '?}', $value, $pattern);
                }

                $pattern = preg_replace('/\{[^}]+\?\}/', '', $pattern);

                return App::getInstance()->getBaseUrl() . ltrim($pattern, '/');
            }
        }

        throw new AppException("Route '{$name}' not found");
    }

    public static function hasRoute(string $name): bool
    {
        foreach (self::$handlers as $handler) {
            if (($handler['name'] ?? null) === $name) {
                return true;
            }
        }
        return false;
    }

    public static function hasHandlers(): bool
    {
        return !empty(self::$handlers);
    }

    private function validateMethod(): void
    {
        if (!self::$currentMethod) {
            throw new AppException('No method specified.');
        }
    }

    private function resetState(): void
    {
        self::$currentMethod = null;
        self::$currentPattern = null;
        self::$currentName = null;
    }

    public static function clear(): void
    {
        self::$handlers = [];
        self::$middlewares = [];
        self::$patternCache = [];
        self::$currentMethod = null;
        self::$currentPattern = null;
        self::$currentName = null;
        self::$executed = false;
    }

    public static function getRoutes(): array
    {
        return self::$handlers;
    }

    public static function getMiddlewares(): array
    {
        return self::$middlewares;
    }

    public static function getRoute(string $name): ?array
    {
        foreach (self::$handlers as $handler) {
            if (($handler['name'] ?? null) === $name) {
                return $handler;
            }
        }
        return null;
    }

    public static function group(string $prefix, array $middleware, callable $callback): void
    {
        $oldHandlers = self::$handlers;
        $oldMiddleware = self::$middlewares;

        foreach ($middleware as $mw) {
            self::$middlewares[] = $mw;
        }

        $callback();

        $newHandlers = array_diff_key(self::$handlers, $oldHandlers);
        foreach ($newHandlers as $key => $handler) {
            if ($handler['pattern']) {
                $handler['pattern'] = $prefix . $handler['pattern'];
            }
            self::$handlers[$key] = $handler;
        }

        self::$middlewares = $oldMiddleware;
    }
}