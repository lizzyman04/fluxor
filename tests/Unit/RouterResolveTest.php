<?php

namespace Fluxor\Tests\Unit;

use Fluxor\Core\Http\Request;
use Fluxor\Core\Http\Router;
use PHPUnit\Framework\TestCase;

/**
 * Verifies Fluxor's Router resolves routes correctly through the external
 * lizzyman04/file-router engine (dogfooding the extracted package).
 */
class RouterResolveTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        // cacheDir null => no on-disk cache during the suite.
        $this->router->setPaths(__DIR__ . '/../fixtures/router', __DIR__ . '/../fixtures', null);
    }

    private function resolve(string $method, string $path): ?array
    {
        return $this->router->resolve(new Request(['method' => $method, 'path' => $path]));
    }

    public function testResolvesStaticRoute(): void
    {
        $info = $this->resolve('GET', '/about');

        $this->assertNotNull($info);
        $this->assertStringEndsWith('about.php', $info['file']);
        $this->assertSame([], $info['params']);
        $this->assertStringEndsWith('router', $info['router_path']);
    }

    public function testResolvesDynamicParam(): void
    {
        $info = $this->resolve('GET', '/users/123');

        $this->assertNotNull($info);
        $this->assertStringEndsWith('[id].php', $info['file']);
        $this->assertSame('123', $info['params']['id']);
    }

    public function testResolvesCatchAll(): void
    {
        $info = $this->resolve('GET', '/files/2024/reports/q1.pdf');

        $this->assertNotNull($info);
        $this->assertStringEndsWith('[...slug].php', $info['file']);
        $this->assertSame('2024/reports/q1.pdf', $info['params']['slug']);
    }

    public function testStaticWinsOverDynamic(): void
    {
        $info = $this->resolve('GET', '/users/me');

        $this->assertNotNull($info);
        $this->assertStringEndsWith('me.php', $info['file']);
    }

    public function testUnknownRouteResolvesToNull(): void
    {
        $this->assertNull($this->resolve('GET', '/does-not-exist'));
    }

    public function testMethodNotAllowed(): void
    {
        $info = $this->resolve('DELETE', '/users/123');

        $this->assertNotNull($info);
        $this->assertTrue($info['method_not_allowed']);
        $this->assertContains('GET', $info['allowed_methods']);
        $this->assertContains('PUT', $info['allowed_methods']);
        $this->assertNotContains('DELETE', $info['allowed_methods']);
    }

    public function testSecondaryMethodResolves(): void
    {
        $info = $this->resolve('PUT', '/users/123');

        $this->assertNotNull($info);
        $this->assertArrayNotHasKey('method_not_allowed', $info);
        $this->assertSame('PUT', $info['method']);
    }

    public function testWritesRouteCacheWhenCacheDirProvided(): void
    {
        $cacheDir = \sys_get_temp_dir() . '/fluxor_router_cache_' . \uniqid();

        $router = new Router();
        $router->setPaths(__DIR__ . '/../fixtures/router', __DIR__ . '/../fixtures', $cacheDir);
        $router->resolve(new Request(['method' => 'GET', 'path' => '/about']));

        $this->assertNotEmpty(\glob($cacheDir . '/file_router_*.php'));

        foreach (\glob($cacheDir . '/*') ?: [] as $f) {
            \unlink($f);
        }
        @\rmdir($cacheDir);
    }

    public function testWritesNoCacheWhenCacheDirIsNull(): void
    {
        $cacheDir = \sys_get_temp_dir() . '/fluxor_router_nocache_' . \uniqid();

        $router = new Router();
        // null cache dir => DISABLE_FLUXOR_CACHE behavior.
        $router->setPaths(__DIR__ . '/../fixtures/router', __DIR__ . '/../fixtures', null);
        $router->resolve(new Request(['method' => 'GET', 'path' => '/about']));

        $this->assertFalse(\is_dir($cacheDir));
    }
}
