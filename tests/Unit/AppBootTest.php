<?php

namespace Fluxor\Tests\Unit;

use Fluxor\Core\App;
use Fluxor\Core\App\Config;
use Fluxor\Core\Http\Router;
use Fluxor\Core\View;
use Fluxor\Exceptions\AppException;
use PHPUnit\Framework\TestCase;

class AppBootTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = \sys_get_temp_dir() . '/fluxor_test_' . \uniqid();
        \mkdir($this->basePath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->basePath);
    }

    private function makeApp(): App
    {
        // forceNew bypasses the singleton guard so each test gets a fresh instance.
        $app = new App($this->basePath, true);
        $app->setConfig([
            'router_path' => __DIR__ . '/../fixtures/router',
            'views_path' => __DIR__ . '/../fixtures',
        ]);

        return $app;
    }

    public function testBootWiresCoreServicesIntoContainer(): void
    {
        $app = $this->makeApp()->boot();

        $this->assertInstanceOf(Router::class, $app->getService('router'));
        $this->assertInstanceOf(Config::class, $app->getService('config'));
        $this->assertInstanceOf(View::class, $app->getService('view'));
    }

    public function testBootBuildsRouterWithConfiguredPaths(): void
    {
        $app = $this->makeApp()->boot();

        $this->assertInstanceOf(Router::class, $app->getRouter());
        $this->assertSame(__DIR__ . '/../fixtures/router', $app->getConfig('router_path'));
    }

    public function testGetRouterBeforeBootThrows(): void
    {
        $app = $this->makeApp();

        $this->expectException(AppException::class);
        $app->getRouter();
    }

    public function testSetConfigAfterBootThrows(): void
    {
        $app = $this->makeApp()->boot();

        $this->expectException(AppException::class);
        $app->setConfig(['app_name' => 'too late']);
    }

    public function testSingletonAccessorsResolveSameInstance(): void
    {
        $app = $this->makeApp()->boot();

        $this->assertSame($app, App::getInstance());
        $this->assertSame($app, App::make());
        $this->assertInstanceOf(Router::class, App::make('router'));
    }

    public function testStoragePathDerivesFromBasePath(): void
    {
        $app = $this->makeApp();

        $this->assertSame($this->basePath . '/storage', $app->getStoragePath());
        $this->assertSame($this->basePath, $app->getBasePath());
    }

    public function testBootIsIdempotent(): void
    {
        $app = $this->makeApp();

        $this->assertSame($app, $app->boot());
        $this->assertSame($app, $app->boot());
    }

    private function removeDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        $items = \array_diff(\scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            \is_dir($path) ? $this->removeDir($path) : \unlink($path);
        }
        \rmdir($dir);
    }
}
