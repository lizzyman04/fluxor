<?php

namespace Fluxor\Exceptions;

class ValidationException extends HttpException
{
    protected array $errors;

    public function __construct(array $errors = [], string $message = "Validation failed")
    {
        $this->errors = $errors;
        parent::__construct($message, 422);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}