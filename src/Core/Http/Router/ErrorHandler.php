<?php

namespace Fluxor\Core\Http\Router;

use Fluxor\App;
use Fluxor\Core\Http\Request;
use Fluxor\Core\Http\Response;
use Fluxor\Exceptions\AppException;
use Fluxor\Helpers\HttpStatusCode;

class ErrorHandler
{
    private string $routerPath;
    private string $viewsPath;

    public function __construct(string $routerPath, string $viewsPath)
    {
        $this->routerPath = $routerPath;
        $this->viewsPath = $viewsPath;
    }

    public function handleNotFound(Request $request): Response
    {
        $response = $this->findErrorHandler('not-found', $request, ['requested_path' => $request->path]);

        if ($response !== null) {
            return $response;
        }

        if ($request->wantsJson()) {
            return Response::json([
                'error' => 'Not Found',
                'message' => 'The requested resource was not found',
                'path' => $request->path,
            ], HttpStatusCode::NOT_FOUND);
        }

        return $this->renderErrorPage(HttpStatusCode::NOT_FOUND, 'Not Found');
    }

    public function handleMethodNotAllowed(Request $request, array $allowedMethods): Response
    {
        $response = $this->findErrorHandler('not-allowed', $request, [
            'allowed_methods' => $allowedMethods,
        ]);

        if ($response !== null) {
            return $response;
        }

        if ($request->wantsJson()) {
            return Response::json([
                'error' => 'Method Not Allowed',
                'message' => 'The request method is not allowed for this route',
                'allowed_methods' => $allowedMethods,
            ], HttpStatusCode::METHOD_NOT_ALLOWED)->withHeaders([
                        'Allow' => \implode(', ', $allowedMethods),
                    ]);
        }

        return $this->renderErrorPage(HttpStatusCode::METHOD_NOT_ALLOWED, 'Method Not Allowed')
            ->withHeaders(['Allow' => \implode(', ', $allowedMethods)]);
    }

    public function handleError(\Throwable $e, Request $request, string $routerPath): Response
    {
        $statusCode = $e instanceof AppException
            ? ($e->getCode() ?: HttpStatusCode::INTERNAL_SERVER_ERROR)
            : HttpStatusCode::INTERNAL_SERVER_ERROR;

        $errorType = $this->getErrorTypeFromStatusCode($statusCode);
        $response = $this->findErrorHandler($errorType, $request, [
            'exception' => $e,
            'status_code' => $statusCode,
        ], $routerPath);

        if ($response !== null) {
            return $response;
        }

        if (App::make()->isDevelopment()) {
            return Response::json([
                'error' => \get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ], $statusCode);
        }

        return $this->renderErrorPage($statusCode, HttpStatusCode::message($statusCode));
    }

    private function findErrorHandler(
        string $type,
        Request $request,
        array $context = [],
        ?string $startPath = null
    ): ?Response {
        $currentPath = $startPath ?: $request->getRouterPath();
        $depth = 0;

        while ($depth++ < 20 && $currentPath && \str_starts_with($currentPath, $this->routerPath)) {
            $statusCode = $this->getStatusCodeFromType($type);
            $candidates = \array_filter([$type, $statusCode !== null ? (string) $statusCode : null]);

            foreach ($candidates as $name) {
                $handlerFile = $currentPath . '/' . $name . '.php';

                if (!\file_exists($handlerFile)) {
                    continue;
                }

                $handler = (static function (string $file) {
                    return include $file;
                })($handlerFile);

                if (!\is_callable($handler)) {
                    continue;
                }

                $request->setParams(\array_merge($request->params, $context));
                $result = $handler($request);

                if ($result instanceof Response) {
                    return $result;
                }
            }

            $parentPath = \dirname($currentPath);

            if ($parentPath === $currentPath) {
                break;
            }

            $currentPath = $parentPath;
        }

        return null;
    }

    private function renderErrorPage(int $statusCode, string $message): Response
    {
        $candidates = [
            $this->viewsPath . "/errors/{$statusCode}.php",
            $this->viewsPath . '/errors/common.php',
            \dirname(__DIR__, 2) . '/Resources/views/errors/common.php',
        ];

        foreach ($candidates as $viewFile) {
            if (\file_exists($viewFile)) {
                return $this->renderView($viewFile, $statusCode, $message);
            }
        }

        return Response::html(
            '<html><body><h1>' . $statusCode . '</h1><p>' . \htmlspecialchars($message) . '</p></body></html>',
            $statusCode
        );
    }

    private function renderView(string $viewFile, int $statusCode, string $message): Response
    {
        \ob_start();
        (static function (string $file, int $statusCode, string $message): void{
            include $file;
        })($viewFile, $statusCode, $message);
        $content = \ob_get_clean();

        return Response::html($content ?: '', $statusCode);
    }

    private function getErrorTypeFromStatusCode(int $code): string
    {
        return match ($code) {
            HttpStatusCode::BAD_REQUEST => 'bad-request',
            HttpStatusCode::UNAUTHORIZED => 'unauthorized',
            HttpStatusCode::FORBIDDEN => 'forbidden',
            HttpStatusCode::NOT_FOUND => 'not-found',
            HttpStatusCode::METHOD_NOT_ALLOWED => 'not-allowed',
            419 => 'csrf-error',
            HttpStatusCode::UNPROCESSABLE_ENTITY => 'validation-error',
            HttpStatusCode::TOO_MANY_REQUESTS => 'too-many-requests',
            HttpStatusCode::INTERNAL_SERVER_ERROR => 'server-error',
            HttpStatusCode::SERVICE_UNAVAILABLE => 'service-unavailable',
            default => 'server-error',
        };
    }

    private function getStatusCodeFromType(string $type): ?int
    {
        return match ($type) {
            'not-found' => HttpStatusCode::NOT_FOUND,
            'not-allowed' => HttpStatusCode::METHOD_NOT_ALLOWED,
            'unauthorized' => HttpStatusCode::UNAUTHORIZED,
            'forbidden' => HttpStatusCode::FORBIDDEN,
            'server-error' => HttpStatusCode::INTERNAL_SERVER_ERROR,
            'bad-request' => HttpStatusCode::BAD_REQUEST,
            'validation-error' => HttpStatusCode::UNPROCESSABLE_ENTITY,
            'csrf-error' => 419,
            default => null,
        };
    }
}