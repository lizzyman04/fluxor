<?php

namespace Fluxor\Core\View;

use Fluxor\Core\View;

class ViewFactory
{
    public static function make(string $view, array $data = [])
    {
        return View::render($view, $data);
    }

    public static function exists(string $view): bool
    {
        return View::exists($view);
    }

    public static function share(string $key, $value): void
    {
        View::share($key, $value);
    }
}