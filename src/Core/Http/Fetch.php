<?php

namespace Fluxor\Core\Http;

use Fluxor\Exceptions\HttpException;
use Fluxor\Helpers\HttpStatusCode;
use InvalidArgumentException;

class Fetch
{
    private string $method = 'GET';
    private string $url = '';
    private array $headers = [];
    private array $query = [];
    private $body = null;
    private int $timeout = 30;
    private int $maxRedirects = 5;
    private bool $verifySsl = true;
    private $onError = null;
    private array $curlOptions = [];
    private $lastResponse = null;
    private array $lastInfo = [];
    private ?string $lastError = null;
    private ?int $lastErrorNo = null;

    private function __construct(string $method, string $url)
    {
        $this->method = \strtoupper($method);
        $this->url = $url;
    }

    public static function get(string $url): self
    {
        return new self('GET', $url);
    }

    public static function post(string $url, $body = null): self
    {
        $instance = new self('POST', $url);
        if ($body !== null) {
            $instance->body($body);
        }
        return $instance;
    }

    public static function put(string $url, $body = null): self
    {
        $instance = new self('PUT', $url);
        if ($body !== null) {
            $instance->body($body);
        }
        return $instance;
    }

    public static function patch(string $url, $body = null): self
    {
        $instance = new self('PATCH', $url);
        if ($body !== null) {
            $instance->body($body);
        }
        return $instance;
    }

    public static function delete(string $url, $body = null): self
    {
        $instance = new self('DELETE', $url);
        if ($body !== null) {
            $instance->body($body);
        }
        return $instance;
    }

    public static function head(string $url): self
    {
        return new self('HEAD', $url);
    }

    public static function options(string $url): self
    {
        return new self('OPTIONS', $url);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function headers(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    public function query(array $params): self
    {
        $this->query = [...$this->query, ...$params];
        return $this;
    }

    public function body($body): self
    {
        $this->body = $body;
        return $this;
    }

    public function json($data): self
    {
        $this->body = $data;
        $this->header('Content-Type', 'application/json');
        return $this;
    }

    public function form(array $data): self
    {
        $this->body = \http_build_query($data);
        $this->header('Content-Type', 'application/x-www-form-urlencoded');
        return $this;
    }

    public function multipart(array $fields, array $files = []): self
    {
        $boundary = '----FluxorBoundary' . \md5(\random_bytes(16));
        $this->header('Content-Type', "multipart/form-data; boundary={$boundary}");

        $body = '';

        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= (string) $value . "\r\n";
        }

        foreach ($files as $name => $fileInfo) {
            $filePath = $fileInfo['path'] ?? $fileInfo[0] ?? '';
            $filename = $fileInfo['filename'] ?? $fileInfo[1] ?? \basename($filePath);
            $mime = $fileInfo['mime'] ?? $fileInfo[2] ?? (mime_content_type($filePath) ?: 'application/octet-stream');

            if (!\is_file($filePath)) {
                throw new InvalidArgumentException("File not found: {$filePath}");
            }

            $content = \file_get_contents($filePath);
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"\r\n";
            $body .= "Content-Type: {$mime}\r\n\r\n";
            $body .= $content . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";
        $this->body = $body;
        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    public function maxRedirects(int $max): self
    {
        $this->maxRedirects = $max;
        return $this;
    }

    public function withoutVerifyingSsl(): self
    {
        $this->verifySsl = false;
        return $this;
    }

    public function withCurlOption(int $option, $value): self
    {
        $this->curlOptions[$option] = $value;
        return $this;
    }

    public function onError(callable $callback): self
    {
        $this->onError = $callback;
        return $this;
    }

    public function send(): Response
    {
        $ch = \curl_init();

        $finalUrl = $this->url;
        if ($this->query !== []) {
            $separator = \str_contains($finalUrl, '?') ? '&' : '?';
            $finalUrl .= $separator . \http_build_query($this->query);
        }

        \curl_setopt($ch, \CURLOPT_URL, $finalUrl);
        \curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, $this->method);
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_HEADER, true);
        \curl_setopt($ch, \CURLOPT_TIMEOUT, $this->timeout);
        \curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, true);
        \curl_setopt($ch, \CURLOPT_MAXREDIRS, $this->maxRedirects);
        \curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        \curl_setopt($ch, \CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);

        $headerStrings = [];
        foreach ($this->headers as $name => $value) {
            $headerStrings[] = "{$name}: {$value}";
        }

        if ($headerStrings !== []) {
            \curl_setopt($ch, \CURLOPT_HTTPHEADER, $headerStrings);
        }

        if ($this->body !== null) {
            if (\is_array($this->body) || \is_object($this->body)) {
                $encodedBody = \json_encode($this->body);
                if (\json_last_error() === \JSON_ERROR_NONE) {
                    $this->body = $encodedBody;
                    if (!isset($this->headers['Content-Type'])) {
                        $this->header('Content-Type', 'application/json');
                    }
                }
            }
            \curl_setopt($ch, \CURLOPT_POSTFIELDS, $this->body);
        }

        foreach ($this->curlOptions as $opt => $val) {
            \curl_setopt($ch, $opt, $val);
        }

        $response = \curl_exec($ch);
        $this->lastInfo = \curl_getinfo($ch);
        $this->lastError = \curl_error($ch);
        $this->lastErrorNo = \curl_errno($ch);

        if ($response === false || $this->lastErrorNo !== \CURLE_OK) {
            \curl_close($ch);
            if ($this->onError !== null) {
                $errorResponse = ($this->onError)($this, $this->lastError, $this->lastErrorNo);
                if ($errorResponse instanceof Response) {
                    return $errorResponse;
                }
            }
            throw new HttpException("cURL error: {$this->lastError}", HttpStatusCode::BAD_GATEWAY);
        }

        $headerSize = $this->lastInfo['header_size'];
        $rawHeaders = \substr($response, 0, $headerSize);
        $bodyContent = \substr($response, $headerSize);
        $statusCode = $this->lastInfo['http_code'];

        \curl_close($ch);

        $headers = $this->parseHeaders($rawHeaders);
        $responseObj = new Response($bodyContent, $statusCode, $headers);

        $this->lastResponse = $responseObj;
        return $responseObj;
    }

    public function getJson(bool $associative = true)
    {
        $response = $this->send();
        $decoded = \json_decode($response->getBodyContent(), $associative);
        if (\json_last_error() !== \JSON_ERROR_NONE) {
            throw new HttpException('Invalid JSON response: ' . \json_last_error_msg(), HttpStatusCode::BAD_GATEWAY);
        }
        return $decoded;
    }

    public function getText(): string
    {
        return $this->send()->getBodyContent();
    }

    public function getStatusCode(): ?int
    {
        return $this->lastResponse?->getStatusCode();
    }

    public function getResponseHeaders(): array
    {
        return $this->lastResponse?->getHeaders() ?? [];
    }

    public function getInfo(): array
    {
        return $this->lastInfo;
    }

    public function isOk(): bool
    {
        $code = $this->getStatusCode();
        return $code !== null && $code >= 200 && $code < 300;
    }

    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        $lines = \explode("\r\n", $rawHeaders);

        foreach ($lines as $line) {
            $colonPos = \strpos($line, ':');
            if ($colonPos === false) {
                continue;
            }

            $name = \trim(\substr($line, 0, $colonPos));
            $value = \trim(\substr($line, $colonPos + 1));
            $headers[$name] = $value;
        }

        return $headers;
    }
}
