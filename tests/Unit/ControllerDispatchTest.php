<?php

namespace Fluxor\Tests\Unit;

use Fluxor\Core\Controller;
use Fluxor\Core\Http\Request;
use Fluxor\Core\Http\Response;
use Fluxor\Core\Routing\Flow;
use Fluxor\Tests\Fixtures\GreetController;
use PHPUnit\Framework\TestCase;

class ControllerDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        Flow::clear();
    }

    protected function tearDown(): void
    {
        Flow::clear();
    }

    public function testRequestIsPassedIntoControllerMethod(): void
    {
        Flow::GET()->to(GreetController::class, 'show');

        $request = new Request(['method' => 'GET', 'path' => '/']);
        $request->setParams(['id' => '7']);

        $response = Flow::execute($request);

        $this->assertInstanceOf(Response::class, $response);
        $body = \json_decode($response->getBodyContent(), true);
        $this->assertTrue($body['received_request']);
        $this->assertSame('7', $body['id']);
    }

    public function testControllerIsNotARequestHolder(): void
    {
        $controller = new GreetController();

        $this->assertFalse(\method_exists($controller, 'setRequest'));
        $this->assertFalse(\method_exists($controller, 'getRequest'));
        $this->assertInstanceOf(Controller::class, $controller);
    }
}
