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
    /**
     * Registered route handlers
     */
    private static array $handlers = [];

    /**
     * Registered middleware
     */
    private static array $middlewares = [];

    /**
     * Current HTTP method being built
     */
    private static ?string $currentMethod = null;

    /**
     * Current route pattern
     */
    private static ?string $currentPattern = null;

    /**
     * Route name
     */
    private static ?string $currentName = null;

    /**
     * Error type to HTTP status code mapping
     */
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
        'csrf-error' => 419, // Custom status code
    ];

    /**
     * Static call handler for HTTP methods (GET, POST, etc.)
     */
    public static function __callStatic($method, $args): self
    {
        $instance = new static();
        return $instance->method(strtoupper($method));
    }

    /**
     * Set the HTTP method for the route
     */
    public function method(string $method): self
    {
        self::$currentMethod = strtoupper($method);
        return $this;
    }

    /**
     * Set the route pattern/name
     */
    public function pattern(string $pattern): self
    {
        self::$currentPattern = $pattern;
        return $this;
    }

    /**
     * Name the route for later reference
     */
    public function name(string $name): self
    {
        self::$currentName = $name;
        return $this;
    }

    /**
     * Define a route handler with a closure
     */
    public function do(callable $handler): void
    {
        $this->validateMethod();

        $key = $this->buildHandlerKey();
        self::$handlers[$key] = [
            'handler' => $handler,
            'method' => self::$currentMethod,
            'pattern' => self::$currentPattern,
            'name' => self::$currentName,
        ];

        $this->resetState();
    }

    /**
     * Define a route handler with a controller
     */
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

            // Inject request if controller expects it
            if (method_exists($controller, 'setRequest')) {
                $controller->setRequest($req);
            }

            return $controller->$method($req);
        });
    }

    /**
     * Add middleware to the pipeline
     */
    public static function use(callable $middleware): void
    {
        self::$middlewares[] = $middleware;
    }

    /**
     * Handle any HTTP method
     */
    public static function any(callable $handler): void
    {
        self::$handlers['ANY'] = [
            'handler' => $handler,
            'method' => 'ANY',
            'pattern' => null,
            'name' => null,
        ];
    }

    /**
     * Execute the routing pipeline
     */
    public static function execute(Request $request)
    {
        // Run global middleware
        foreach (self::$middlewares as $middleware) {
            $result = $middleware($request);
            if ($result !== null) {
                return $result;
            }
        }

        // Find matching handler
        $handler = self::findHandler($request);

        if ($handler) {
            try {
                return $handler($request);
            } catch (NotFoundException $e) {
                return self::handleNotFound($request, $e);
            } catch (HttpException $e) {
                return self::handleHttpException($request, $e);
            } catch (\Throwable $e) {
                return self::handleError($request, $e);
            }
        }

        return self::handleNotFound($request);
    }

    /**
     * Find a handler for the request
     */
    private static function findHandler(Request $request): ?callable
    {
        $method = $request->method;

        // Try exact method match
        $exactKey = self::buildKey($method, $request->path);
        if (isset(self::$handlers[$exactKey])) {
            return self::$handlers[$exactKey]['handler'];
        }

        // Try pattern matching
        foreach (self::$handlers as $key => $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }

            if ($route['pattern'] && self::matchesPattern($route['pattern'], $request->path, $params)) {
                $request->setParams($params);
                return $route['handler'];
            }
        }

        // Try ANY handler
        if (isset(self::$handlers['ANY'])) {
            return self::$handlers['ANY']['handler'];
        }

        return null;
    }

    /**
     * Check if a path matches a pattern and extract parameters
     */
    private static function matchesPattern(string $pattern, string $path, ?array &$params = []): bool
    {
        $pattern = preg_replace('/\//', '\/', $pattern);
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^\/]+)', $pattern);
        $pattern = '/^' . $pattern . '$/';

        if (preg_match($pattern, $path, $matches)) {
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return true;
        }

        return false;
    }

    /**
     * Handle 404 Not Found
     */
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
            return Response::error(
                HttpStatusCode::message(HttpStatusCode::NOT_FOUND),
                HttpStatusCode::NOT_FOUND
            );
        }

        // Try to render 404 view
        try {
            return Response::view('errors/404', [
                'path' => $request->path
            ], HttpStatusCode::NOT_FOUND);
        } catch (\Throwable $viewError) {
            // Fallback HTML
            http_response_code(HttpStatusCode::NOT_FOUND);
            echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body>";
            echo "<h1>404 - Page Not Found</h1>";
            echo "</body></html>";
            exit;
        }
    }

    /**
     * Handle HTTP exceptions
     */
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

        // Try to render error view
        try {
            return Response::view("errors/{$statusCode}", [
                'exception' => $e,
                'message' => $e->getMessage(),
            ], $statusCode);
        } catch (\Throwable $viewError) {
            // Fallback
            http_response_code($statusCode);
            echo "<!DOCTYPE html><html><head><title>{$statusCode} Error</title></head><body>";
            echo "<h1>{$statusCode} - {$e->getMessage()}</h1>";
            echo "</body></html>";
            exit;
        }
    }

    /**
     * Handle generic errors
     */
    private static function handleError(Request $request, \Throwable $e)
    {
        $statusCode = $e instanceof AppException && $e->getCode() >= 400
            ? $e->getCode()
            : HttpStatusCode::INTERNAL_SERVER_ERROR;

        if (App::getInstance()?->isDevelopment()) {
            return Response::json([
                'error' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ], $statusCode);
        }

        return Response::error(
            HttpStatusCode::message($statusCode),
            $statusCode
        );
    }

    /**
     * Find and execute an error handler
     */
    private static function findErrorHandler(string $type, Request $request, array $context = [])
    {
        $routerPath = App::make()->getConfig()['router_path'] ?? '';
        $currentPath = $request->getRouterPath();

        while ($currentPath && $currentPath !== $routerPath) {
            // Try specific error type file
            $errorFile = $currentPath . '/' . $type . '.php';

            if (file_exists($errorFile)) {
                $handler = include $errorFile;
                if (is_callable($handler)) {
                    $request->setParams(array_merge($request->params, $context));
                    return $handler($request);
                }
            }

            // Try status code file
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

        // Try global error handler
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

    /**
     * Get HTTP status code from error type
     */
    private static function getStatusCodeFromType(string $type): ?int
    {
        return self::ERROR_TYPE_MAP[$type] ?? null;
    }

    /**
     * Get error type from HTTP status code
     */
    private static function getErrorTypeFromStatusCode(int $statusCode): string
    {
        $map = array_flip(self::ERROR_TYPE_MAP);
        return $map[$statusCode] ?? 'server-error';
    }

    /**
     * Build a unique key for a route handler
     */
    private static function buildKey(string $method, string $pattern): string
    {
        return $method . ':' . $pattern;
    }

    /**
     * Build handler key from current state
     */
    private function buildHandlerKey(): string
    {
        $pattern = self::$currentPattern ?? $this->detectPatternFromBacktrace();
        return self::buildKey(self::$currentMethod, $pattern);
    }

    /**
     * Detect route pattern from file path (for file-based routing)
     */
    private function detectPatternFromBacktrace(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);

        foreach ($trace as $frame) {
            if (isset($frame['file']) && strpos($frame['file'], '/app/router/') !== false) {
                $routerPath = App::make()->getConfig()['router_path'] ?? '';
                $relativePath = str_replace($routerPath, '', dirname($frame['file']));

                // Convert file path to URL pattern
                $pattern = str_replace([
                    '/index',
                    '.php',
                    '[',
                    ']',
                    '(',
                    ')'
                ], [
                    '',
                    '',
                    '{',
                    '}',
                    '',
                    ''
                ], $relativePath);

                return $pattern ?: '/';
            }
        }

        return '/';
    }

    /**
     * Validate that a method has been set
     */
    private function validateMethod(): void
    {
        if (!self::$currentMethod) {
            throw new AppException('No method specified. Use Flow::GET(), Flow::POST(), etc.');
        }
    }

    /**
     * Reset the builder state
     */
    private function resetState(): void
    {
        self::$currentMethod = null;
        self::$currentPattern = null;
        self::$currentName = null;
    }

    /**
     * Clear all routes and middleware (useful for testing)
     */
    public static function clear(): void
    {
        self::$handlers = [];
        self::$middlewares = [];
        self::$currentMethod = null;
        self::$currentPattern = null;
        self::$currentName = null;
    }

    /**
     * Get all registered routes
     */
    public static function getRoutes(): array
    {
        return self::$handlers;
    }

    /**
     * Get all registered middleware
     */
    public static function getMiddlewares(): array
    {
        return self::$middlewares;
    }
}