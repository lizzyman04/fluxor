<?php

namespace Fluxor\Tests\Unit;

use Fluxor\Core\Http\Router\Matcher;
use PHPUnit\Framework\TestCase;

class RouterMatcherTest extends TestCase
{
    private Matcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new Matcher(__DIR__ . '/../fixtures/router');
        $this->matcher->disableCache();
        $this->matcher->compile();
    }

    public function testMatchesRootRoute(): void
    {
        $route = $this->matcher->find('/', 'GET');

        $this->assertNotNull($route);
        $this->assertStringEndsWith('index.php', $route['file']);
        $this->assertSame('/', $route['pattern']);
    }

    public function testMatchesStaticRoute(): void
    {
        $route = $this->matcher->find('/about', 'GET');

        $this->assertNotNull($route);
        $this->assertStringEndsWith('about.php', $route['file']);
        $this->assertSame([], $route['params']);
    }

    public function testMatchesDynamicParam(): void
    {
        $route = $this->matcher->find('/users/123', 'GET');

        $this->assertNotNull($route);
        $this->assertStringEndsWith('[id].php', $route['file']);
        $this->assertSame('123', $route['params']['id']);
    }

    public function testMatchesNestedDynamicParam(): void
    {
        $route = $this->matcher->find('/posts/42/comments', 'GET');

        $this->assertNotNull($route);
        $this->assertStringEndsWith('comments.php', $route['file']);
        $this->assertSame('42', $route['params']['id']);
    }

    public function testMatchesCatchAll(): void
    {
        $route = $this->matcher->find('/files/2024/reports/q1.pdf', 'GET');

        $this->assertNotNull($route);
        $this->assertStringEndsWith('[...slug].php', $route['file']);
        $this->assertSame('2024/reports/q1.pdf', $route['params']['slug']);
    }

    public function testStaticRouteWinsOverDynamic(): void
    {
        $route = $this->matcher->find('/users/me', 'GET');

        $this->assertNotNull($route);
        $this->assertStringEndsWith('me.php', $route['file']);
        $this->assertSame([], $route['params']);
    }

    public function testStaticRouteWinsOverCatchAll(): void
    {
        $route = $this->matcher->find('/files/list', 'GET');

        $this->assertNotNull($route);
        $this->assertStringEndsWith('list.php', $route['file']);
    }

    public function testUnknownRouteReturnsNull(): void
    {
        $this->assertNull($this->matcher->find('/does-not-exist', 'GET'));
    }

    public function testMethodNotAllowed(): void
    {
        $route = $this->matcher->find('/users/123', 'DELETE');

        $this->assertNotNull($route);
        $this->assertTrue($route['method_not_allowed']);
        $this->assertContains('GET', $route['allowed_methods']);
        $this->assertContains('PUT', $route['allowed_methods']);
        $this->assertNotContains('DELETE', $route['allowed_methods']);
    }

    public function testDeclaredSecondaryMethodMatches(): void
    {
        $route = $this->matcher->find('/users/123', 'PUT');

        $this->assertNotNull($route);
        $this->assertArrayNotHasKey('method_not_allowed', $route);
        $this->assertSame('PUT', $route['method']);
    }
}
