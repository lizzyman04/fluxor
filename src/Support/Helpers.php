<?php
/**
 * Fluxor - Support Helper Functions
 * 
 * These may use other Fluxor classes.
 */

use Fluxor\Helpers\Str;
use Fluxor\Helpers\HttpStatusCode;

if (!function_exists('str')) {
    function str(string $value = null)
    {
        if ($value === null) {
            return new Str();
        }
        return new Str($value);
    }
}

if (!function_exists('http_status')) {
    function http_status(int $code = null)
    {
        if ($code === null) {
            return new HttpStatusCode();
        }
        return HttpStatusCode::message($code);
    }
}

if (!function_exists('class_basename')) {
    function class_basename($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('value')) {
    function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }
}

if (!function_exists('tap')) {
    function tap($value, callable $callback)
    {
        $callback($value);
        return $value;
    }
}

if (!function_exists('blank')) {
    function blank($value): bool
    {
        if (is_null($value))
            return true;
        if (is_string($value))
            return trim($value) === '';
        if (is_numeric($value) || is_bool($value))
            return false;
        if ($value instanceof Countable)
            return count($value) === 0;
        return empty($value);
    }
}

if (!function_exists('filled')) {
    function filled($value): bool
    {
        return !blank($value);
    }
}