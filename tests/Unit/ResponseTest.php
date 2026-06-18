<?php

namespace Fluxor\Tests\Unit;

use Fluxor\Core\Http\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testJsonResponse(): void
    {
        $response = Response::json(['ok' => true], 201);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaders()['Content-Type']);
        $this->assertSame('{"ok":true}', $response->getBodyContent());
    }

    public function testHtmlResponse(): void
    {
        $response = Response::html('<h1>Hi</h1>');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaders()['Content-Type']);
        $this->assertSame('<h1>Hi</h1>', $response->getBodyContent());
    }

    public function testTextResponse(): void
    {
        $response = Response::text('plain');

        $this->assertSame('text/plain; charset=utf-8', $response->getHeaders()['Content-Type']);
        $this->assertSame('plain', $response->getBodyContent());
    }

    public function testSuccessEnvelope(): void
    {
        $response = Response::success(['id' => 1], 'Created', 201);

        $this->assertSame(201, $response->getStatusCode());
        $body = \json_decode($response->getBodyContent(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('Created', $body['message']);
        $this->assertSame(['id' => 1], $body['data']);
    }

    public function testErrorEnvelope(): void
    {
        $response = Response::error('Bad', 422, ['field' => 'name']);

        $this->assertSame(422, $response->getStatusCode());
        $body = \json_decode($response->getBodyContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('Bad', $body['message']);
        $this->assertSame(['field' => 'name'], $body['details']);
    }

    public function testRedirectSetsLocationHeader(): void
    {
        $response = Response::redirect('/login', 301);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaders()['Location']);
    }

    public function testStatusAndHeaderAreChainable(): void
    {
        $response = Response::json(['a' => 1])
            ->status(418)
            ->header('X-Test', 'yes');

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame('yes', $response->getHeaders()['X-Test']);
    }
}
