<?php

namespace MVCCore\Core;

use MVCCore\Exceptions\AppException;

class View
{
    private static string $viewsPath = '';
    private static array $shared = [];
    private static array $sections = [];
    private static array $stacks = [];
    private static string $currentSection = '';
    private static bool $sectionStarted = false;
    private static array $extendedLayouts = [];

    public static function setViewsPath(string $path): void
    {
        if (!is_dir($path)) {
            throw new AppException("Views path does not exist: {$path}");
        }

        self::$viewsPath = rtrim($path, '/\\');
    }

    public static function getViewsPath(): string
    {
        return self::$viewsPath;
    }

    public static function share(string $key, $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function exists(string $view): bool
    {
        try {
            $viewFile = self::resolveViewPath($view);
            return file_exists($viewFile);
        } catch (AppException) {
            return false;
        }
    }

    public static function render(string $view, array $data = []): string
    {
        $viewFile = self::resolveViewPath($view);

        if (!file_exists($viewFile)) {
            throw AppException::viewNotFound($view);
        }

        $allData = array_merge(self::$shared, $data);

        extract($allData, EXTR_SKIP);

        ob_start();

        try {
            include $viewFile;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new AppException("View rendering failed: " . $e->getMessage(), 0, $e);
        }

        $content = ob_get_clean();

        if (!empty(self::$extendedLayouts)) {
            $layout = array_pop(self::$extendedLayouts);
            $content = self::renderWithLayout($layout, $content, $allData);
        }

        return $content;
    }

    private static function renderWithLayout(string $layout, string $content, array $data): string
    {
        $originalSections = self::$sections;
        self::$sections['content'] = $content;

        $layoutContent = self::render($layout, $data);

        self::$sections = $originalSections;

        return $layoutContent;
    }

    public static function renderEcho(string $view, array $data = []): void
    {
        echo self::render($view, $data);
    }

    public static function section(string $name, string $content = null): void
    {
        if ($content !== null) {
            self::$sections[$name] = $content;
            return;
        }

        if (self::$sectionStarted) {
            throw new AppException("Cannot nest sections. Section '{$name}' started without ending previous section.");
        }

        self::$currentSection = $name;
        self::$sectionStarted = true;
        ob_start();
    }

    public static function endSection(): void
    {
        if (!self::$sectionStarted) {
            throw new AppException("No section started to end.");
        }

        self::$sections[self::$currentSection] = ob_get_clean();
        self::$sectionStarted = false;
        self::$currentSection = '';
    }

    public static function yield(string $sectionName, string $default = ''): string
    {
        return self::$sections[$sectionName] ?? $default;
    }

    public static function push(string $stack, string $content): void
    {
        if (!isset(self::$stacks[$stack])) {
            self::$stacks[$stack] = [];
        }

        self::$stacks[$stack][] = $content;
    }

    public static function stack(string $stack, string $glue = ''): string
    {
        if (!isset(self::$stacks[$stack])) {
            return '';
        }

        return implode($glue, array_reverse(self::$stacks[$stack]));
    }

    public static function extend(string $layout): void
    {
        self::$extendedLayouts[] = $layout;
    }

    public static function include(string $view, array $data = []): void
    {
        echo self::render($view, $data);
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function e(string $value): string
    {
        return self::escape($value);
    }

    public static function safe(string $value): string
    {
        return $value;
    }

    public static function csrfField(): string
    {
        $token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;

        return '<input type="hidden" name="csrf_token" value="' . self::escape($token) . '">';
    }

    public static function method(string $method): string
    {
        $validMethods = ['PUT', 'PATCH', 'DELETE'];
        $method = strtoupper($method);

        if (in_array($method, $validMethods)) {
            return '<input type="hidden" name="_method" value="' . self::escape($method) . '">';
        }

        return '';
    }

    public static function asset(string $path): string
    {
        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        return $baseUrl . '/assets/' . ltrim($path, '/');
    }

    public static function url(string $path = ''): string
    {
        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        return $baseUrl . '/' . ltrim($path, '/');
    }

    public static function route(string $name, array $params = []): string
    {
        $path = $name;
        foreach ($params as $key => $value) {
            $path = str_replace("{{$key}}", $value, $path);
        }
        return self::url($path);
    }

    private static function resolveViewPath(string $view): string
    {
        $view = preg_replace('/\.php$/', '', $view);

        $possiblePaths = [
            self::$viewsPath . '/' . $view . '.php',
            self::$viewsPath . '/' . $view,
            self::$viewsPath . '/' . $view . '/index.php',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw AppException::viewNotFound($view);
    }

    public static function clear(): void
    {
        self::$sections = [];
        self::$stacks = [];
        self::$extendedLayouts = [];
        self::$currentSection = '';
        self::$sectionStarted = false;
    }

    public static function getSharedData(): array
    {
        return self::$shared;
    }

    public static function getSections(): array
    {
        return self::$sections;
    }

    public static function getStacks(): array
    {
        return self::$stacks;
    }
}