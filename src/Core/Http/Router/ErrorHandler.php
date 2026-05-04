<?php

namespace Fluxor\Core\Http\Router;

use Fluxor\App;
use Fluxor\Core\View;
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

        if ($statusCode < 400 || $statusCode > 599) {
            $statusCode = HttpStatusCode::INTERNAL_SERVER_ERROR;
        }

        $errorType = $this->getErrorTypeFromStatusCode($statusCode);
        $response  = $this->findErrorHandler($errorType, $request, [
            'exception'   => $e,
            'status_code' => $statusCode,
        ], $routerPath);

        if ($response !== null) {
            return $response;
        }

        if ($this->isDevelopment() && $this->isDebug()) {
            if ($request->wantsJson()) {
                return Response::json([
                    'error'   => \get_class($e),
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTrace(),
                ], $statusCode);
            }

            return $this->renderDevErrorPage($e, $statusCode);
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
        $data = ['statusCode' => $statusCode, 'message' => $message];

        if (View::exists("errors/{$statusCode}")) {
            return Response::html(View::render("errors/{$statusCode}", $data), $statusCode);
        }

        if (View::exists('errors/common')) {
            return Response::html(View::render('errors/common', $data), $statusCode);
        }

        $candidates = [
            $this->viewsPath . "/errors/{$statusCode}.php",
            $this->viewsPath . '/errors/common.php',
            \dirname(__DIR__, 2) . '/Resources/views/errors/common.php',
        ];

        foreach ($candidates as $viewFile) {
            if (\file_exists($viewFile)) {
                return $this->renderPhpFile($viewFile, $data, $statusCode);
            }
        }

        return Response::html(
            '<html><body><h1>' . $statusCode . '</h1><p>' . \htmlspecialchars($message) . '</p></body></html>',
            $statusCode
        );
    }

    private function renderPhpFile(string $file, array $data, int $statusCode): Response
    {
        \ob_start();
        (static function (string $file, array $data): void{
            \extract($data, EXTR_SKIP);
            include $file;
        })($file, $data);
        $content = \ob_get_clean();

        return Response::html($content ?: '', $statusCode);
    }

    private function isDevelopment(): bool
    {
        try {
            return App::make()->isDevelopment();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isDebug(): bool
    {
        try {
            return App::make()->isDebug();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function renderDevErrorPage(\Throwable $e, int $statusCode): Response
    {
        $class   = \htmlspecialchars(\get_class($e), ENT_QUOTES, 'UTF-8');
        $message = \htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $file    = \htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
        $line    = $e->getLine();
        $trace   = \htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$statusCode} — {$class}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0f0f0f;color:#e2e2e2;padding:2rem}
h1{font-size:1.1rem;font-weight:600;color:#f87171;word-break:break-word;margin:.5rem 0}
.badge{display:inline-block;background:#3f1313;color:#f87171;border:1px solid #7f2020;font-size:.75rem;padding:.15rem .5rem;border-radius:4px;font-family:monospace}
.msg{font-size:1rem;margin:.75rem 0 1.25rem;color:#cbd5e1;line-height:1.5}
.loc{font-family:monospace;font-size:.8rem;color:#64748b;margin-bottom:1.5rem}
.loc span{color:#94a3b8}
h2{font-size:.7rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;margin-bottom:.5rem}
pre{background:#18181b;border:1px solid #27272a;border-radius:6px;padding:1rem;font-size:.78rem;line-height:1.6;overflow-x:auto;color:#a1a1aa;white-space:pre}
</style>
</head>
<body>
<span class="badge">{$statusCode}</span>
<h1>{$class}</h1>
<p class="msg">{$message}</p>
<p class="loc"><span>{$file}</span> line {$line}</p>
<h2>Stack Trace</h2>
<pre>{$trace}</pre>
</body>
</html>
HTML;

        return Response::html($html, $statusCode);
    }

    private function getErrorTypeFromStatusCode(int $code): string
    {
        return match ($code) {
            HttpStatusCode::BAD_REQUEST => 'bad-request',
            HttpStatusCode::UNAUTHORIZED => 'unauthorized',
            HttpStatusCode::FORBIDDEN => 'forbidden',
            HttpStatusCode::NOT_FOUND => 'not-found',
            HttpStatusCode::METHOD_NOT_ALLOWED => 'not-allowed',
            HttpStatusCode::PAGE_EXPIRED => 'csrf-error',
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
            'csrf-error' => HttpStatusCode::PAGE_EXPIRED,
            default => null,
        };
    }
}