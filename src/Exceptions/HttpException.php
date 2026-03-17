<?php

namespace Fluxor\Exceptions;

class HttpException extends AppException
{
    protected int $statusCode;

    public function __construct(string $message = "", int $statusCode = 500, ?\Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}