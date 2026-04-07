<?php
/**
 * Fluxor - Global Helper Functions
 */

use Fluxor\Core\App;
use Fluxor\Core\Http\Fetch;
use Fluxor\Core\Http\Response;
use Fluxor\Exceptions\HttpException;
use RuntimeException;
use InvalidArgumentException;
use Fluxor\Core\App\Environment;
use Fluxor\Helpers\HttpStatusCode;

if (!\function_exists('app')) {
    function app(string $service = null)
    {
        $instance = App::getInstance();
        return $service ? $instance?->getService($service) : $instance;
    }
}

if (!\function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $app = App::getInstance();
        if (!$app) {
            return \getcwd() . ($path ? '/' . \ltrim($path, '/') : '');
        }
        return $app->getBasePath() . ($path ? '/' . \ltrim($path, '/') : '');
    }
}

if (!\function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $app = App::getInstance();
        return $app ? $app->getBaseUrl() . \ltrim($path, '/') : '';
    }
}

if (!\function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url('assets/' . \ltrim($path, '/'));
    }
}

if (!\function_exists('config')) {
    function config(string $key, $default = null)
    {
        $app = App::getInstance();
        if (!$app) {
            return $default;
        }
        return $app->getConfig()[$key] ?? $default;
    }
}

if (!\function_exists('fetch')) {
    function fetch(string $method, string $url, $body = null): Fetch
    {
        $method = \strtoupper($method);
        return match ($method) {
            'GET' => Fetch::get($url),
            'POST' => Fetch::post($url, $body),
            'PUT' => Fetch::put($url, $body),
            'PATCH' => Fetch::patch($url, $body),
            'DELETE' => Fetch::delete($url, $body),
            'HEAD' => Fetch::head($url),
            'OPTIONS' => Fetch::options($url),
            default => throw new InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }
}

if (!\function_exists('env')) {
    /**
     * Get an environment variable
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        return Environment::get($key, $default);
    }
}

if (!\function_exists('env_required')) {
    /**
     * Get a required environment variable (throws exception if missing)
     * 
     * @param string $key
     * @return mixed
     * @throws RuntimeException
     */
    function env_required(string $key)
    {
        return Environment::required($key);
    }
}

if (!\function_exists('http_status_message')) {
    function http_status_message(int $code): string
    {
        return HttpStatusCode::message($code);
    }
}

if (!\function_exists('abort')) {
    function abort(int $code, string $message = null)
    {
        $message = $message ?? http_status_message($code);
        throw new HttpException($message, $code);
    }
}

if (!\function_exists('redirect')) {
    function redirect(string $url, int $status = HttpStatusCode::FOUND)
    {
        return Response::redirect($url, $status);
    }
}

if (!\function_exists('dd')) {
    function dd(...$vars): void
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
        die(1);
    }
}

if (!\function_exists('dump')) {
    function dump(...$vars): void
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
    }
}