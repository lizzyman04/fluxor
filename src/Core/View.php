<?php

namespace Fluxor\Core;

use Fluxor\Exceptions\AppException;
use Fluxor\Core\App;

class View
{
    private static string $viewsPath = '';
    private static array $shared = [];
    private static array $sections = [];
    private static array $stacks = [];
    private static string $currentSection = '';
    private static bool $sectionStarted = false;
    private static array $extendedLayouts = [];
    private static ?App $app = null;
    private static array $currentData = [];

    public static function init(?App $app = null): void
    {
        self::$app = $app ?? App::getInstance();
    }

    public static function setViewsPath(string $path): void
    {
        if (!is_dir($path)) {
            throw new AppException("Views path does not exist: {$path}");
        }
        self::$viewsPath = rtrim(realpath($path), '/\\');
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
            return file_exists(self::resolveViewPath($view));
        } catch (AppException) {
            return false;
        }
    }

    public static function render(string $view, array $data = []): string
    {
        try {
            $viewFile = self::resolveViewPath($view);
            $allData = array_merge(self::$shared, $data, self::$currentData);

            $previousData = self::$currentData;
            self::$currentData = $allData;

            extract($allData, EXTR_SKIP);

            ob_start();
            try {
                include $viewFile;
            } catch (\Throwable $e) {
                ob_end_clean();
                self::$currentData = $previousData;
                throw new AppException("View rendering failed: {$e->getMessage()} in {$view}", 0, $e);
            }
            $content = ob_get_clean();

            while (!empty(self::$extendedLayouts)) {
                $layout = array_pop(self::$extendedLayouts);
                $content = self::render($layout, $allData);
                self::$sections['content'] = $content;
            }

            self::$currentData = $previousData;
            return $content;

        } catch (\Throwable $e) {
            if (self::isDebugMode()) {
                throw $e;
            }
            error_log("[Fluxor View Error] {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
            return '<h1>View Error</h1><p>Something went wrong rendering the view.</p>';
        }
    }

    public static function section(string $name, ?string $content = null): void
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
        self::$stacks[$stack][] = $content;
    }

    public static function stack(string $stack, string $glue = ''): string
    {
        if (empty(self::$stacks[$stack])) {
            return '';
        }
        return implode($glue, self::$stacks[$stack]);
    }

    public static function extend(string $layout): void
    {
        self::$extendedLayouts[] = $layout;
    }

    public static function include(string $view, array $data = []): void
    {
        echo self::render($view, array_merge(self::$currentData, $data));
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function e(string $value): string
    {
        return self::escape($value);
    }

    public static function raw(string $value): string
    {
        return $value;
    }

    public static function csrfField(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return '<input type="hidden" name="_token" value="' . self::escape($token) . '">';
    }

    public static function method(string $method): string
    {
        $method = strtoupper($method);
        if (!in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
            return '';
        }
        return '<input type="hidden" name="_method" value="' . self::escape($method) . '">';
    }

    public static function asset(string $path): string
    {
        return self::url('assets/' . ltrim($path, '/'));
    }

    public static function url(string $path = ''): string
    {
        $app = self::$app ?? App::getInstance();
        if (!$app) {
            return '/' . ltrim($path, '/');
        }
        return rtrim($app->getBaseUrl(), '/') . '/' . ltrim($path, '/');
    }

    private static function resolveViewPath(string $view): string
    {
        if (empty(self::$viewsPath)) {
            throw new AppException("Views path not set.");
        }

        $view = strip_tags($view);
        $view = str_replace(['\\', '.'], '/', $view);
        $view = preg_replace('/\.php$|\.html$/', '', $view);

        $paths = [
            self::$viewsPath . '/' . $view,
            self::$viewsPath . '/' . $view . '.php',
            self::$viewsPath . '/' . $view . '.html',
            self::$viewsPath . '/' . $view . '/index.php',
            self::$viewsPath . '/' . $view . '/index.html',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw AppException::viewNotFound($view);
    }

    private static function isDebugMode(): bool
    {
        $app = self::$app ?? App::getInstance();
        return $app ? $app->isDevelopment() : true;
    }

    public static function clear(): void
    {
        self::$sections = [];
        self::$stacks = [];
        self::$extendedLayouts = [];
        self::$currentSection = '';
        self::$sectionStarted = false;
        self::$currentData = [];
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

    protected static function getData(string $key, $default = null)
    {
        return self::$currentData[$key] ?? $default;
    }
}