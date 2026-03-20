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

    public function handleNotFound(Request $request): void
    {
        $response = $this->findErrorHandlerRecursive('not-found', $request, ['requested_path' => $request->path]);
        if ($response) {
            (new Dispatcher($request))->dispatch([
                'file' => $response['file'],
                'params' => $response['params'],
                'router_path' => $response['router_path']
            ]);
            return;
        }

        if ($request->wantsJson()) {
            Response::json([
                'error' => 'Not Found',
                'message' => 'The requested resource was not found',
                'path' => $request->path
            ], HttpStatusCode::NOT_FOUND)->send();
            return;
        }

        $this->renderErrorPage(HttpStatusCode::NOT_FOUND, 'Not Found');
    }

    public function handleError(\Throwable $e, Request $request, string $routerPath): void
    {
        $statusCode = $e instanceof AppException ? ($e->getCode() ?: HttpStatusCode::INTERNAL_SERVER_ERROR) : HttpStatusCode::INTERNAL_SERVER_ERROR;
        $errorType = $this->getErrorTypeFromStatusCode($statusCode);

        $response = $this->findErrorHandlerRecursive($errorType, $request, [
            'exception' => $e,
            'status_code' => $statusCode
        ], $routerPath);

        if ($response) {
            (new Dispatcher($request))->dispatch([
                'file' => $response['file'],
                'params' => $response['params'],
                'router_path' => $response['router_path']
            ]);
            return;
        }

        if (App::make()->isDevelopment()) {
            Response::json([
                'error' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ], $statusCode)->send();
        } else {
            $this->renderErrorPage($statusCode, HttpStatusCode::message($statusCode));
        }
    }

    private function renderErrorPage(int $statusCode, string $message): void
    {
        http_response_code($statusCode);

        $userView = $this->viewsPath . "/errors/{$statusCode}.php";
        if (file_exists($userView)) {
            extract(['statusCode' => $statusCode, 'message' => $message]);
            include $userView;
            exit;
        }

        $userCommonView = $this->viewsPath . "/errors/common.php";
        if (file_exists($userCommonView)) {
            extract(['statusCode' => $statusCode, 'message' => $message]);
            include $userCommonView;
            exit;
        }

        $vendorView = dirname(__DIR__, 2) . '/Resources/views/errors/common.php';
        extract(['statusCode' => $statusCode, 'message' => $message]);
        include $vendorView;
        exit;
    }

    private function findErrorHandlerRecursive(string $type, Request $request, array $context = [], ?string $startPath = null): ?array
    {
        $currentPath = $startPath ?: $request->getRouterPath();

        while ($currentPath && str_starts_with($currentPath, $this->routerPath)) {
            foreach ([$type, $this->getStatusCodeFromType($type)] as $handlerName) {
                if (!$handlerName)
                    continue;

                $handlerFile = $currentPath . '/' . $handlerName . '.php';
                if (file_exists($handlerFile)) {
                    $handler = include $handlerFile;
                    if (is_callable($handler)) {
                        $request->setParams(array_merge($request->params, $context));
                        return [
                            'file' => $handlerFile,
                            'params' => $request->params,
                            'router_path' => $currentPath
                        ];
                    }
                }
            }

            $parentPath = dirname($currentPath);
            if ($parentPath === $currentPath)
                break;
            $currentPath = $parentPath;
        }

        return null;
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
            default => 'server-error'
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
            default => null
        };
    }
}