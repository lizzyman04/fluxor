<?php

namespace Fluxor\Core\Http;

use Fluxor\Core\View;

class Response
{
    /** @var mixed|null */
    private $data;
    
    private int $statusCode;
    private array $headers = [];

    public function __construct($data = null, int $statusCode = 200, array $headers = [])
    {
        $this->data = $data;
        $this->statusCode = $statusCode;
        $this->headers = array_merge([
            'Content-Type' => 'application/json'
        ], $headers);
    }

    public static function json($data, int $statusCode = 200, array $headers = []): self
    {
        $response = new static($data, $statusCode, $headers);
        $response->header('Content-Type', 'application/json');
        return $response;
    }

    public static function html(string $content, int $statusCode = 200, array $headers = []): self
    {
        $response = new static($content, $statusCode, $headers);
        $response->header('Content-Type', 'text/html; charset=utf-8');
        return $response;
    }

    public static function text(string $content, int $statusCode = 200, array $headers = []): self
    {
        $response = new static($content, $statusCode, $headers);
        $response->header('Content-Type', 'text/plain; charset=utf-8');
        return $response;
    }

    public static function success($data = null, string $message = 'Success', int $statusCode = 200): self
    {
        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    public static function error(string $message = 'Error', int $statusCode = 400, $details = null): self
    {
        return self::json([
            'success' => false,
            'message' => $message,
            'details' => $details
        ], $statusCode);
    }

    public static function redirect(string $url, int $statusCode = 302): self
    {
        $response = new static(null, $statusCode);
        $response->header('Location', $url);
        return $response;
    }

    public static function view(string $view, array $data = [], int $statusCode = 200): self
    {
        $content = View::render($view, $data);
        return self::html($content, $statusCode);
    }

    public static function download(string $filePath, string $filename = null, array $headers = []): self
    {
        $filename = $filename ?: basename($filePath);
        $fileSize = filesize($filePath);

        $response = new static(null, 200, array_merge([
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => $fileSize,
            'Pragma' => 'public',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ], $headers));

        $response->data = $filePath;
        $response->headers['Content-Type'] = 'application/octet-stream';

        return $response;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withCookie(string $name, string $value, int $expire = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = true): self
    {
        setcookie($name, $value, [
            'expires' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax'
        ]);
        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function status(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->data !== null) {
            if (isset($this->headers['Content-Type']) && $this->headers['Content-Type'] === 'application/octet-stream' && is_string($this->data) && file_exists($this->data)) {
                readfile($this->data);
            }
            elseif (is_array($this->data) || is_object($this->data)) {
                echo json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
            elseif (is_string($this->data)) {
                echo $this->data;
            }
            else {
                echo (string) $this->data;
            }
        }
    }

    public function __toString(): string
    {
        ob_start();
        $this->send();
        return ob_get_clean();
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}