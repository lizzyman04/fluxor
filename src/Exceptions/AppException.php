<?php

namespace Fluxor\Exceptions;

use Exception;

class AppException extends Exception
{
    protected array $context = [];

    public function __construct(string $message = "", int $code = 0, \Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public static function routeNotFound(string $path): self
    {
        return new self("Route not found: {$path}", 404, null, ['path' => $path]);
    }

    public static function methodNotAllowed(string $method, array $allowedMethods = []): self
    {
        return new self("Method {$method} not allowed", 405, null, [
            'method' => $method,
            'allowed_methods' => $allowedMethods
        ]);
    }

    public static function viewNotFound(string $view): self
    {
        return new self("View not found: {$view}", 500, null, ['view' => $view]);
    }

    public static function validationFailed(array $errors = []): self
    {
        return new self("Validation failed", 422, null, ['errors' => $errors]);
    }
}