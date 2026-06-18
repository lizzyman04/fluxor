<?php

namespace Fluxor\Tests\Unit;

use Fluxor\Core\App\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ConfigTest extends TestCase
{
    public function testGetReturnsDefaultWhenMissing(): void
    {
        $config = new Config();

        $this->assertNull($config->get('nonexistent'));
        $this->assertSame('fallback', $config->get('nonexistent', 'fallback'));
    }

    public function testSetAndGet(): void
    {
        $config = new Config();
        $config->set('app_name', 'Fluxor Test');

        $this->assertSame('Fluxor Test', $config->get('app_name'));
    }

    public function testConstructorMergesOverDefaults(): void
    {
        $config = new Config(['environment' => 'development']);

        $this->assertSame('development', $config->get('environment'));
        $this->assertSame('UTC', $config->get('timezone'));
    }

    public function testSetMany(): void
    {
        $config = new Config();
        $config->setMany([
            'app_name' => 'Many',
            'timezone' => 'Europe/Lisbon',
        ]);

        $this->assertSame('Many', $config->get('app_name'));
        $this->assertSame('Europe/Lisbon', $config->get('timezone'));
    }

    public function testHas(): void
    {
        $config = new Config();

        $this->assertTrue($config->has('timezone'));
        $this->assertFalse($config->has('nope'));
    }

    public function testLockPreventsModification(): void
    {
        $config = new Config();
        $config->lock('app_name');

        $this->assertTrue($config->isLocked('app_name'));

        $this->expectException(RuntimeException::class);
        $config->set('app_name', 'blocked');
    }

    public function testUnlockAllowsModificationAgain(): void
    {
        $config = new Config();
        $config->lock('app_name')->unlock('app_name');

        $config->set('app_name', 'ok');
        $this->assertSame('ok', $config->get('app_name'));
    }

    public function testValidateReportsMissingRequiredKeys(): void
    {
        $errors = (new Config())->validate();

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('router_path', \implode(' ', $errors));
        $this->assertStringContainsString('views_path', \implode(' ', $errors));
    }

    public function testValidatePassesWhenRequiredKeysSet(): void
    {
        $config = new Config([
            'router_path' => '/app/router',
            'views_path' => '/app/views',
        ]);

        $this->assertSame([], $config->validate());
    }
}
