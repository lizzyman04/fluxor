<?php

namespace MVCCore;

use Composer\Script\Event;
use Composer\IO\IOInterface;

class Installer
{
    private static array $templates = [
        'basic' => 'Simple template with basic routing',
        'mvc' => 'Full MVC with authentication and views',
        'api' => 'RESTful API template with endpoints'
    ];

    public static function postInstall(Event $event): void
    {
        self::showTemplateSelection($event);
    }

    private static function showTemplateSelection(Event $event): void
    {
        $io = $event->getIO();

        $io->write("\n<info>🚀 MVCCore Installation</info>");
        $io->write("======================================");
        $io->write("Select a template to install:");

        foreach (self::$templates as $key => $description) {
            $io->write("  <comment>{$key}</comment> - {$description}");
        }

        $template = $io->askAndValidate(
            "\nChoose template [<comment>basic</comment>]: ",
            function ($value) {
                if (!$value)
                    return 'basic';
                if (!array_key_exists($value, self::$templates)) {
                    throw new \InvalidArgumentException('Invalid template selected');
                }
                return $value;
            },
            3,
            'basic'
        );

        $projectName = basename(getcwd());
        $confirm = $io->askConfirmation(
            "Install <comment>{$template}</comment> template to <comment>{$projectName}</comment>? [<comment>Y/n</comment>] ",
            true
        );

        if ($confirm) {
            self::installTemplate($template, $io);
        } else {
            $io->write("<info>Template installation skipped.</info>");
        }
    }

    private static function installTemplate(string $template, IOInterface $io): void
    {
        $source = __DIR__ . '/../templates/' . $template;
        $destination = getcwd();

        if (!is_dir($source)) {
            $io->writeError("<error>Template '{$template}' not found!</error>");
            return;
        }

        $io->write("\n<info>📦 Installing {$template} template...</info>");

        try {
            $copiedFiles = self::copyDirectory($source, $destination, $io);

            $io->write("\n<info>✅ Template installed successfully!</info>");
            $io->write("<comment>📁 Created {$copiedFiles} files</comment>");

            self::showNextSteps($template, $io);

        } catch (\Exception $e) {
            $io->writeError("<error>Installation failed: " . $e->getMessage() . "</error>");
        }
    }

    private static function copyDirectory(string $source, string $destination, IOInterface $io): int
    {
        $copiedFiles = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . DIRECTORY_SEPARATOR .
                $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                // Skip if file already exists (don't overwrite)
                if (file_exists($targetPath)) {
                    $io->write("  <comment>Skip:</comment> {$targetPath} (already exists)");
                    continue;
                }

                // Create directory if it doesn't exist
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                if (copy($item->getPathname(), $targetPath)) {
                    $io->write("  <info>Create:</info> {$targetPath}");
                    $copiedFiles++;
                }
            }
        }

        return $copiedFiles;
    }

    private static function showNextSteps(string $template, IOInterface $io): void
    {
        $io->write("\n<info>🎯 Next Steps:</info>");

        switch ($template) {
            case 'basic':
                $io->write("  1. Run: <comment>php -S localhost:8000 -t public</comment>");
                $io->write("  2. Visit: <comment>http://localhost:8000</comment>");
                $io->write("  3. Try: <comment>http://localhost:8000/api/hello?name=YourName</comment>");
                break;

            case 'mvc':
                $io->write("  1. Run: <comment>php -S localhost:8000 -t public</comment>");
                $io->write("  2. Visit: <comment>http://localhost:8000</comment>");
                $io->write("  3. Register at: <comment>http://localhost:8000/auth/register</comment>");
                $io->write("  4. Default login: <comment>admin@example.com / password</comment>");
                break;

            case 'api':
                $io->write("  1. Run: <comment>php -S localhost:8000 -t public</comment>");
                $io->write("  2. Test API: <comment>curl http://localhost:8000/api/v1/users</comment>");
                $io->write("  3. Add header: <comment>X-API-Key: test-key</comment>");
                break;
        }

        $io->write("\n<comment>📚 Documentation: https://github.com/lizzyman04/mvccore</comment>");
    }
}