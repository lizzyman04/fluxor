<?php

namespace Fluxor\Exceptions;

class NotFoundException extends HttpException
{
    public function __construct(string $message = "Resource not found", ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}