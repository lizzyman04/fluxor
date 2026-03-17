<?php
/**
 * Global Exception Handler
 */

namespace Fluxor\Core\App;

use Throwable;
use Fluxor\Exceptions\AppException;

class ExceptionHandler
{
    private bool $debug;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    public function handle(Throwable $e): void
    {
        $statusCode = $this->getStatusCode($e);
        $response = $this->buildResponse($e, $statusCode);

        http_response_code($statusCode);
        header('Content-Type: application/json');

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->log($e);
    }

    private function getStatusCode(Throwable $e): int
    {
        if ($e instanceof AppException && $e->getCode() >= 400) {
            return $e->getCode();
        }
        return 500;
    }

    private function buildResponse(Throwable $e, int $statusCode): array
    {
        if ($this->debug) {
            return [
                'error' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ];
        }

        return [
            'error' => $statusCode === 500 ? 'Internal Server Error' : 'Error',
            'message' => $e instanceof AppException ? $e->getMessage() : 'An error occurred',
        ];
    }

    private function log(Throwable $e): void
    {
        if ($this->debug) {
            error_log(sprintf(
                "[%s] %s: %s in %s:%d",
                date('Y-m-d H:i:s'),
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }
    }
}