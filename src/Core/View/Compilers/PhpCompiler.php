<?php

namespace Fluxor\Core\View\Compilers;

class PhpCompiler
{
    public function compile(string $content): string
    {
        return $content;
    }

    public function compileEscapedEchos(string $content): string
    {
        return preg_replace('/\{\{\s*(.+?)\s*\}\}/', '<?= htmlspecialchars($1, ENT_QUOTES, \'UTF-8\') ?>', $content);
    }
}